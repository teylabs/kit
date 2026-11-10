<?php

// bin/form-payload-check.php fails a removed or renamed form field, or a removed fixture, against a base ref; an added field passes with a note,
// and --allow or the CHANGELOG marker lets a minor release through. Runs the script in a synthetic git repository; no network.
require dirname(__DIR__).'/bin/form-payload-check.php';

function formPayloadCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

$script = dirname(__DIR__).'/bin/form-payload-check.php';
$repo = sys_get_temp_dir().'/kit-form-payload-'.bin2hex(random_bytes(6));
$git = function (string $command) use ($repo): void {
    exec('git -C '.escapeshellarg($repo).' -c user.name=Check -c user.email=check@example.test '.$command.' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException("git {$command}: ".implode("\n", $output));
    }
};
$write = function (string $form, array $payload) use ($repo): void {
    file_put_contents("{$repo}/tests/fixtures/form-payloads/{$form}.json", json_encode($payload, JSON_PRETTY_PRINT)."\n");
};
$run = function (string ...$arguments) use ($repo, $script): array {
    $command = 'cd '.escapeshellarg($repo).' && '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $arguments)).' 2>&1';
    exec($command, $output, $code);

    return [$code, implode("\n", $output)];
};
$reset = function () use ($git, $write, $repo): void {
    $git('checkout -q -- .');
    $git('clean -qfd');
    file_put_contents("{$repo}/CHANGELOG.md", "# Changelog\n\n## Unreleased\n\n- Something.\n\n## v0.1.0\n\n- Form payload: breaking\n");
};

try {
    mkdir("{$repo}/tests/fixtures/form-payloads", 0700, true);
    $git('init -q -b main');
    $write('profile', ['name' => 'Robin Ashby', 'email' => 'robin@example.test']);
    $write('consent', [['_token', 'c'], ['workspace', '2'], ['grants[]', 'files']]);
    file_put_contents("{$repo}/CHANGELOG.md", "# Changelog\n\n## Unreleased\n\n- Something.\n\n## v0.1.0\n\n- Form payload: breaking\n");
    $git('add -A');
    $git('commit -q -m base');
    $git('tag v0.1.0');

    formPayloadCheck(formPayloadFields('[["a","1"],["b","2"],["a","3"]]') === ['a', 'b'], 'a FormData entry list yields its unique names');
    formPayloadCheck(formPayloadFields('{"avatar":{"file":"x.png"},"name":"x"}') === ['avatar', 'name'], 'an object yields its top-level keys only');

    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 0 && str_contains($output, 'no field removed or renamed'), 'identical fixtures pass');

    $write('profile', ['name' => 'Different Value', 'email' => 'other@example.test']);
    [$code] = $run('v0.1.0');
    formPayloadCheck($code === 0, 'changed values alone pass');

    $write('profile', ['name' => 'Robin Ashby', 'email' => 'robin@example.test', 'timezone' => 'UTC']);
    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 0 && str_contains($output, 'profile: added timezone (not breaking'), 'an added field passes with a note');
    $reset();

    $write('profile', ['name' => 'Robin Ashby']);
    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 1 && str_contains($output, 'profile: removed email (breaking)') && str_contains($output, 'minor'), 'a removed field fails and explains the rule');
    $reset();

    $write('consent', [['_token', 'c'], ['workspace_id', '2'], ['grants[]', 'files']]);
    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 1 && str_contains($output, 'consent: renamed workspace -> workspace_id (breaking)'), 'a renamed field fails');
    $reset();

    unlink("{$repo}/tests/fixtures/form-payloads/profile.json");
    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 1 && str_contains($output, 'profile: fixture removed (breaking)'), 'a deleted fixture fails');

    [$code, $output] = $run('v0.1.0', '--allow');
    formPayloadCheck($code === 0 && str_contains($output, 'allowed by --allow'), '--allow passes a break');

    formPayloadCheck(! formPayloadMarked((string) file_get_contents("{$repo}/CHANGELOG.md")), 'the marker under a released section does not count');
    file_put_contents("{$repo}/CHANGELOG.md", "# Changelog\n\n## Unreleased\n\n- Form payload: breaking. Renamed x.\n\n## v0.1.0\n\n- Old.\n");
    [$code, $output] = $run('v0.1.0');
    formPayloadCheck($code === 0 && str_contains($output, 'allowed by "Form payload: breaking"'), 'the marker under ## Unreleased passes a break');
    $reset();

    [$code] = $run('no-such-ref');
    formPayloadCheck($code === 2, 'an unknown base ref fails closed');
} finally {
    exec('rm -rf '.escapeshellarg($repo));
}
