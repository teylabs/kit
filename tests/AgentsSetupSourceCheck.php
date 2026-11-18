<?php

// .agents/setup's PHP apt source (add_php_source, remove_php_source): runs the two functions in a throwaway root with stub
// `sudo` (runs the command as is) and `curl` (serves the providers' public keys from tests/fixtures/apt-keys). No network,
// no root, nothing outside the system temp dir. Checks the source per distribution, the signed-by entry, the fingerprint
// pin (a wrong key writes nothing), idempotent reruns and the cleanup.
$source = file_get_contents(dirname(__DIR__).'/.agents/setup');
if (! preg_match('/^add_php_source\(\) \{.*?^remove_php_source\(\) \{[^\n]*\}$/ms', $source, $match)) {
    fwrite(STDERR, "FAIL .agents/setup has no add_php_source / remove_php_source\n");
    exit(1);
}
if (trim(shell_exec('command -v gpg') ?? '') === '') {
    fwrite(STDERR, "FAIL gpg is required\n");
    exit(1);
}
$keys = __DIR__.'/fixtures/apt-keys';
$tmp = sys_get_temp_dir().'/agents-source-'.bin2hex(random_bytes(6));
mkdir($tmp.'/bin', 0777, true);
file_put_contents($tmp.'/functions.sh', $match[0]."\n");
file_put_contents($tmp.'/bin/sudo', "#!/bin/sh\nexec \"\$@\"\n");
// `curl -fsSL <url> -o <file>`: the keyserver URL gets ondrej's armored key, sury's the binary one; KEY_OVERRIDE serves another file.
file_put_contents($tmp.'/bin/curl', <<<SH
#!/bin/sh
url=""; out=""
while [ \$# -gt 0 ]; do case "\$1" in -o) out="\$2"; shift 2 ;; -*) shift ;; *) url="\$1"; shift ;; esac; done
case "\$url" in
  *keyserver.ubuntu.com*) key='{$keys}/ondrej.asc' ;;
  *packages.sury.org*) key='{$keys}/sury.gpg' ;;
  *) exit 22 ;;
esac
[ -n "\${KEY_OVERRIDE:-}" ] && key="\$KEY_OVERRIDE"
cp "\$key" "\$out"
SH);
chmod($tmp.'/bin/sudo', 0755);
chmod($tmp.'/bin/curl', 0755);

$failures = 0;
$check = function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$message."\n";
    $failures += $ok ? 0 : 1;
};
// A fresh root per run: etc/os-release from $release, the keyrings and sources.list.d folders.
$run = function (string $release, string $call, array $env = []) use ($tmp): array {
    $root = $tmp.'/root-'.bin2hex(random_bytes(4));
    mkdir($root.'/usr/share/keyrings', 0777, true);
    mkdir($root.'/etc/apt/sources.list.d', 0777, true);
    file_put_contents($root.'/etc/os-release', $release);
    $vars = ['APT_ROOT' => $root, 'OS_RELEASE' => $root.'/etc/os-release', 'PATH' => $tmp.'/bin:'.getenv('PATH'), 'GNUPGHOME' => $tmp.'/gnupg'] + $env;
    $prefix = implode(' ', array_map(fn ($k, $v) => $k.'='.escapeshellarg($v), array_keys($vars), $vars));
    exec($prefix.' bash -c '.escapeshellarg('set -u; . '.escapeshellarg($tmp.'/functions.sh').'; '.$call).' 2>&1', $output, $code);

    return [$code, implode("\n", $output), $root];
};
@mkdir($tmp.'/gnupg', 0700);
$list = fn (string $root): string => @file_get_contents($root.'/etc/apt/sources.list.d/ondrej-php.list') ?: '';
$keyring = fn (string $root): string => $root.'/usr/share/keyrings/ondrej-php.gpg';
$fingerprint = function (string $file): string {
    exec('gpg --show-keys --with-colons '.escapeshellarg($file).' 2>/dev/null', $lines);
    $primary = false;
    foreach ($lines as $line) {
        if (str_starts_with($line, 'pub:')) {
            $primary = true;
        } elseif ($primary && str_starts_with($line, 'fpr:')) {
            return explode(':', $line)[9];
        }
    }

    return '';
};

try {
    $debian = "ID=debian\nVERSION_CODENAME=trixie\n";
    $ubuntu = "ID=ubuntu\nVERSION_CODENAME=noble\n";

    [$code, , $root] = $run($debian, 'add_php_source');
    $check($code === 0 && $list($root) === "deb [signed-by={$keyring($root)}] https://packages.sury.org/php/ trixie main\n", 'Debian: one packages.sury.org entry for its codename, signed by the keyring');
    $check($fingerprint($keyring($root)) === '15058500A0235D97F5D10063B188E2B695BD4743' && (fileperms($keyring($root)) & 0777) === 0644, 'Debian: the keyring is sury\'s pinned key, world-readable');
    $before = [$list($root), md5_file($keyring($root))];
    [$code2, , $root2] = $run($debian, 'add_php_source; add_php_source');
    $check($code2 === 0 && $list($root2) === str_replace($root, $root2, $before[0]) && md5_file($keyring($root2)) === $before[1], 'Debian: a rerun leaves the same entry and keyring');

    [$code, , $root] = $run($ubuntu, 'add_php_source');
    $check($code === 0 && $list($root) === "deb [signed-by={$keyring($root)}] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main\n", 'Ubuntu: one ondrej PPA entry for its codename, signed by the keyring');
    $check($fingerprint($keyring($root)) === '14AA40EC0831756756D7F66C4F4EA0AAE5267A6C', 'Ubuntu: the armored key is dearmored into the keyring, ondrej\'s pinned key');

    // The wrong provider's key on each distribution: refused, nothing written.
    foreach (['Debian' => [$debian, 'ondrej.asc'], 'Ubuntu' => [$ubuntu, 'sury.gpg']] as $label => [$release, $other]) {
        [$code, $output, $root] = $run($release, 'add_php_source', ['KEY_OVERRIDE' => $keys.'/'.$other]);
        $check($code !== 0 && str_contains($output, 'refusing it') && ! file_exists($keyring($root)) && $list($root) === '', "{$label}: a key with another fingerprint is refused and nothing is written");
    }

    foreach (['another distribution' => "ID=fedora\nVERSION_CODENAME=x\n", 'no codename' => "ID=debian\n"] as $label => $release) {
        [$code, , $root] = $run($release, 'add_php_source');
        $check($code !== 0 && $list($root) === '' && ! file_exists($keyring($root)), "{$label}: no source, a failing return for the fallback");
    }

    [$code, , $root] = $run($debian, 'add_php_source && remove_php_source');
    $check($code === 0 && ! file_exists($keyring($root)) && $list($root) === '', 'remove_php_source deletes the entry and the keyring');
} finally {
    exec('rm -rf '.escapeshellarg($tmp));
}

exit($failures === 0 ? 0 : 1);
