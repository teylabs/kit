<?php

// .agents/setup's Node 24 block: runs it in a throwaway dir with stub `curl` and `sudo` (no network, no installs) and fake
// `node` / `npm` scripts that report a version, so the PATH choice can be checked without a real Node: an image's Node 22
// first on PATH with Node 24 in the system bin dir (NODE_SYSTEM_BIN) puts the system dir first; without that pair, setup fails closed.
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
// Runs the block, then prints the node it ended with; PATH is the stubs, $first, then only the system's own tools.
$run = function (string $first, string $systemBin) use ($tmp): array {
    $path = $tmp.'/stubs:'.$first.':/bin:/usr/bin';
    $script = 'set -euo pipefail; . '.escapeshellarg($tmp.'/block.sh').'; echo "chose $(command -v node) $(node -v)"';
    exec('env -i HOME='.escapeshellarg($tmp).' PATH='.escapeshellarg($path).' NODE_SYSTEM_BIN='.escapeshellarg($systemBin).' bash -c '.escapeshellarg($script).' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};

try {
    $fake($tmp.'/image', '22.21.0');
    $fake($tmp.'/system', '24.22.3');

    [$code, $output] = $run($tmp.'/image', $tmp.'/system');
    $check($code === 0 && str_contains($output, "chose {$tmp}/system/node v24.22.3"), 'an image\'s Node 22 first on PATH: the system Node 24 is put first and used');
    $check(str_contains($output, 'comes before '.$tmp.'/system/node on PATH'), 'and setup says which node was in the way and how to keep Node 24');

    [$code, $output] = $run($tmp.'/image', $tmp.'/missing');
    $check($code !== 0 && str_contains($output, 'setup: node is not Node 24 with npm (found '.$tmp.'/image/node v22.21.0)'), 'no system Node 24 and npm pair: setup fails closed, naming the node it found');

    $fake($tmp.'/nonpm', '24.22.3');
    unlink($tmp.'/nonpm/npm');
    [$code] = $run($tmp.'/image', $tmp.'/nonpm');
    $check($code !== 0, 'a system Node 24 without npm is not used: setup fails closed');

    [$code, $output] = $run($tmp.'/system', $tmp.'/missing');
    $check($code === 0 && str_contains($output, "chose {$tmp}/system/node v24.22.3") && ! str_contains($output, 'comes before'), 'Node 24 already first on PATH: nothing to install or reorder');
} finally {
    exec('rm -rf '.escapeshellarg($tmp));
}

exit($failures === 0 ? 0 : 1);
