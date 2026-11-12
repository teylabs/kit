<?php

// bin/release-lineages.php: the release-notes line naming the lineages whose src/Catalog.php pin changed, checked on made-up catalogs and a made-up repository.
require dirname(__DIR__).'/bin/release-lineages.php';

function releaseLineagesCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

$pin = fn (string $seed): string => 'sha256:'.hash('sha256', $seed);
$catalog = function (array $pins) use ($pin): string {
    $rows = implode('', array_map(fn (string $id, string $seed): string => "        '{$id}' => '".$pin($seed)."',\n", array_keys($pins), $pins));

    return "<?php\n\nfinal readonly class Catalog\n{\n    private const FEATURES = [\n{$rows}    ];\n\n    private const BASE = '".$pin('base')."';\n}\n";
};
$v1 = ['core' => 'core-1', 'shell' => 'shell-1', 'workspace-ui' => 'ui-1', 'notes' => 'notes-1'];
$block = fn (array $base, array $head): string => releaseLineagesBlock(releaseLineagePins($catalog($base)), releaseLineagePins($catalog($head)));
$run = function (string $script, array $arguments): array {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $arguments)).' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
};

$repo = sys_get_temp_dir().'/kit-release-lineages-'.bin2hex(random_bytes(6));
$git = function (string $command) use ($repo): void {
    exec('git -C '.escapeshellarg($repo).' -c user.name=Check -c user.email=check@example.test '.$command.' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException("git {$command}: ".implode("\n", $output));
    }
};

try {
    releaseLineagesCheck(releaseLineagePins($catalog($v1)) === ['core' => $pin('core-1'), 'shell' => $pin('shell-1'), 'workspace-ui' => $pin('ui-1'), 'notes' => $pin('notes-1')], 'the pins are read from FEATURES in order, and BASE is not a lineage');
    releaseLineagesCheck($block($v1, $v1) === 'No lineage pin changed: `composer update` is enough.', 'unchanged pins say composer update is enough');
    releaseLineagesCheck($block($v1, array_replace($v1, ['shell' => 'shell-2'])) === 'Run `php artisan kit:update` for: shell', 'one changed pin names that lineage only');
    // Changed in the reverse of canonical order, so a list that follows the change order (or the alphabet) fails.
    releaseLineagesCheck($block($v1, array_replace($v1, ['notes' => 'notes-2', 'workspace-ui' => 'ui-2', 'core' => 'core-2'])) === 'Run `php artisan kit:update` for: core, workspace-ui, notes', 'several changed pins are named in canonical order, unchanged ones left out');
    releaseLineagesCheck($block($v1, $v1 + ['deliver' => 'deliver-1']) === 'New lineages (add with `php artisan kit:add`): deliver', 'a new feature is listed as new, not as an update');
    releaseLineagesCheck($block($v1, array_replace($v1, ['core' => 'core-2']) + ['deliver' => 'deliver-1']) === "Run `php artisan kit:update` for: core\nNew lineages (add with `php artisan kit:add`): deliver", 'a changed and a new lineage get a line each');
    $refused = false;
    try {
        releaseLineagePins("<?php\n\nfinal class Catalog {}\n");
    } catch (UnexpectedValueException) {
        $refused = true;
    }
    releaseLineagesCheck($refused, 'a src/Catalog.php without FEATURES pins is refused, never read as no change');

    // The command: with no refs it compares HEAD with the newest release before it, also when HEAD carries its own release tag.
    mkdir($repo.'/bin', 0700, true);
    mkdir($repo.'/src');
    copy(dirname(__DIR__).'/bin/release-lineages.php', $repo.'/bin/release-lineages.php');
    $git('init -q -b main');
    $commit = function (array $pins, string $message) use ($repo, $catalog, $git): void {
        file_put_contents($repo.'/src/Catalog.php', $catalog($pins));
        $git('add -A');
        $git('commit -q -m '.escapeshellarg($message));
    };
    $commit($v1, 'one');
    $git('tag v0.1.0');
    $commit(array_replace($v1, ['shell' => 'shell-2']), 'shell fix');
    $git('tag v0.1.1');
    $commit(array_replace($v1, ['core' => 'core-2', 'shell' => 'shell-2', 'notes' => 'notes-2']), 'core and notes fix');
    $script = $repo.'/bin/release-lineages.php';
    releaseLineagesCheck($run($script, []) === [0, 'Run `php artisan kit:update` for: core, notes'], 'with no refs, HEAD is compared with the newest release tag');
    $git('tag v0.1.2');
    releaseLineagesCheck($run($script, []) === [0, 'Run `php artisan kit:update` for: core, notes'], 'a tag on HEAD itself is not its own base');
    releaseLineagesCheck($run($script, ['v0.1.0', 'v0.1.2']) === [0, 'Run `php artisan kit:update` for: core, shell, notes'], 'two named refs span every release between them');
    releaseLineagesCheck($run($script, ['no-such-ref'])[0] === 1, 'an unreadable ref fails');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
} finally {
    exec('rm -rf '.escapeshellarg($repo));
}
