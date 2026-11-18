<?php

// .agents/setup's Node 24 block: runs it in a throwaway home with stub `curl` and `sudo` (no network, no installs) and fake
// `node` / `npm` scripts that report a version, so the PATH choice can be checked without a real Node. An image's Node 22 first on
// PATH with Node 24 in the system bin dir (NODE_SYSTEM_BIN): Node 24 is pinned first (NODE_PIN_DIR), here and in later shells through
// ~/.profile and ~/.bashrc, once. Without a Node 24 and npm pair, setup fails closed.
$source = file_get_contents(dirname(__DIR__).'/.agents/setup');
$start = strpos($source, "\n# Node 24 exactly");
$end = $start === false ? false : strpos($source, "\ncomposer update", $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "FAIL .agents/setup has no Node 24 block\n");
    exit(1);
}
$tmp = sys_get_temp_dir().'/agents-node-'.bin2hex(random_bytes(6));
mkdir($tmp.'/stubs', 0777, true);
file_put_contents($tmp.'/block.sh', substr($source, $start, $end - $start)."\n");
// curl prints nothing (the piped nodesource script is then empty); sudo runs only the bash it is piped to, and apt-get does nothing.
file_put_contents($tmp.'/stubs/curl', "#!/bin/sh\nexit 0\n");
file_put_contents($tmp.'/stubs/sudo', "#!/bin/sh\n[ \"\$1\" = \"-E\" ] && shift\ncase \"\$1\" in bash) exec \"\$@\" ;; *) exit 0 ;; esac\n");
chmod($tmp.'/stubs/curl', 0755);
chmod($tmp.'/stubs/sudo', 0755);
// A fake node answers `-v` and the block's `-e` major-version check; npm only needs to exist.
$fake = function (string $dir, string $version) {
    @mkdir($dir, 0777, true);
    $major = explode('.', $version)[0];
    file_put_contents($dir.'/node', "#!/bin/sh\ncase \"\$1\" in -v) echo v{$version} ;; -e) [ {$major} -eq 24 ] ;; esac\n");
    file_put_contents($dir.'/npm', "#!/bin/sh\necho 11.0.0\n");
    chmod($dir.'/node', 0755);
    chmod($dir.'/npm', 0755);
};

$failures = 0;
$check = function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$message."\n";
    $failures += $ok ? 0 : 1;
};
// PATH is the stubs, $first, then only the system's own tools. Each case gets its own home unless one is passed.
$env = fn (string $home, string $first, string $systemBin): string => 'env -i HOME='.escapeshellarg($home).' PATH='.escapeshellarg($tmp.'/stubs:'.$first.':/bin:/usr/bin')
    .' NODE_SYSTEM_BIN='.escapeshellarg($systemBin).' NODE_PIN_DIR='.escapeshellarg($home.'/pin');
$home = function () use ($tmp): string {
    $dir = $tmp.'/home-'.bin2hex(random_bytes(4));
    mkdir($dir);

    return $dir;
};
$run = function (string $first, string $systemBin, ?string $at = null) use ($tmp, $env, $home): array {
    $at ??= $home();
    $script = 'set -euo pipefail; . '.escapeshellarg($tmp.'/block.sh').'; echo "chose $(node -v)"';
    exec($env($at, $first, $systemBin).' bash -c '.escapeshellarg($script).' 2>&1', $output, $code);

    return [$code, implode("\n", $output), $at];
};
// A later shell: a fresh bash that reads ~/.profile (a login shell) or ~/.bashrc (an interactive one), with the image's node first again.
$later = function (string $at, string $first, string $rc): string {
    exec('env -i HOME='.escapeshellarg($at).' PATH='.escapeshellarg($first.':/bin:/usr/bin').' bash -c '.escapeshellarg('. '.escapeshellarg($at.'/'.$rc).'; node -v').' 2>&1', $output);

    return implode("\n", $output);
};

try {
    $fake($tmp.'/image', '22.21.0');
    $fake($tmp.'/system', '24.22.3');

    [$code, $output, $at] = $run($tmp.'/image', $tmp.'/system');
    $check($code === 0 && str_contains($output, 'chose v24.22.3'), 'an image\'s Node 22 first on PATH: Node 24 is pinned first and used');
    $check(str_contains($output, 'comes before '.$tmp.'/system/node on PATH; pinning Node 24 first'), 'and setup says which node was in the way and where it pinned Node 24');
    $check(is_link($at.'/pin/node') && readlink($at.'/pin/node') === $tmp.'/system/node' && is_link($at.'/pin/npm'), 'the pin dir links node and npm to the system ones');
    $check($later($at, $tmp.'/image', '.profile') === 'v24.22.3' && $later($at, $tmp.'/image', '.bashrc') === 'v24.22.3', 'a later login or interactive shell gets Node 24 ahead of the image\'s node');

    [$code] = $run($tmp.'/image', $tmp.'/system', $at);
    $marks = substr_count((string) file_get_contents($at.'/.profile'), '# kit .agents/setup: Node 24 first') + substr_count((string) file_get_contents($at.'/.bashrc'), '# kit .agents/setup: Node 24 first');
    $check($code === 0 && $marks === 2, 'a rerun keeps one marked line in each of ~/.profile and ~/.bashrc');

    [$code, $output, $at] = $run($tmp.'/image', $tmp.'/missing');
    $check($code !== 0 && str_contains($output, 'setup: node is not Node 24 with npm (found '.$tmp.'/image/node v22.21.0)') && ! file_exists($at.'/.profile'), 'no system Node 24 and npm pair: setup fails closed, names the node it found, and pins nothing');

    $fake($tmp.'/nonpm', '24.22.3');
    unlink($tmp.'/nonpm/npm');
    [$code] = $run($tmp.'/image', $tmp.'/nonpm');
    $check($code !== 0, 'a system Node 24 without npm is not used: setup fails closed');

    [$code, $output, $at] = $run($tmp.'/system', $tmp.'/missing');
    $check($code === 0 && str_contains($output, 'chose v24.22.3') && ! str_contains($output, 'comes before') && ! file_exists($at.'/.profile'), 'Node 24 already first on PATH: nothing to install, reorder or pin');
} finally {
    exec('rm -rf '.escapeshellarg($tmp));
}

exit($failures === 0 ? 0 : 1);
