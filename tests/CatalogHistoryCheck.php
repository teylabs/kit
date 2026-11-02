<?php

// bin/catalog-history.php records only releases that are ancestors of HEAD: a maintenance branch never lists a newer line's tags, fetched or not.
require dirname(__DIR__).'/bin/catalog-history.php';

function catalogHistoryCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

$repo = sys_get_temp_dir().'/kit-history-tags-'.bin2hex(random_bytes(6));
$git = function (string $command) use ($repo): void {
    exec('git -C '.escapeshellarg($repo).' -c user.name=Check -c user.email=check@example.test '.$command.' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException("git {$command}: ".implode("\n", $output));
    }
};

try {
    mkdir($repo, 0700);
    $git('init -q -b main');
    $git('commit -q --allow-empty -m one');
    $git('tag v0.5.0');
    $git('branch 0.5.x');
    // The newer line: main moves on and tags v0.6.0.
    $git('commit -q --allow-empty -m two');
    $git('tag v0.6.0');
    // The maintenance line: 0.5.x tags its own patch, with v0.6.0 present in the repository (fetched) but not in its history.
    $git('checkout -q 0.5.x');
    $git('commit -q --allow-empty -m patch');
    $git('tag v0.5.1');
    $git('tag not-a-release');

    catalogHistoryCheck(catalogHistoryTags($repo) === ['v0.5.0', 'v0.5.1'], 'a maintenance branch lists only its own releases, not a fetched newer line\'s tag');
    $git('checkout -q main');
    // Main keeps an older line's patch (an app installed from v0.5.1 is still identified), and nothing newer than its own newest release.
    catalogHistoryCheck(catalogHistoryTags($repo) === ['v0.5.0', 'v0.5.1', 'v0.6.0'], 'main lists its own releases and an older line\'s later patch');
    $git('tag v0.7.0 0.5.x');
    catalogHistoryCheck(! in_array('v0.7.0', catalogHistoryTags($repo), true), 'a newer tag that is not in main\'s history is still left out');
} finally {
    exec('rm -rf '.escapeshellarg($repo));
}
