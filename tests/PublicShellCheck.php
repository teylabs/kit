<?php

// The public pages' server side: PublicMarkdown renders legal markdown and sanitizes it (raw HTML stripped, unsafe links refused), and the shell's `mention` host
// step points out a layout callback that does not use the PublicShell (attention, never blocking). No database, no HTTP.
use Tey\Kit\Features\Shell\PublicMarkdown;
use Tey\Kit\HostRequirements;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function publicCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $html = PublicMarkdown::html("# Terms\n\nRead the [privacy policy](/legal/privacy).\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1))\n\n- one\n- two\n");
    publicCheck(str_contains($html, '<h1>Terms</h1>') && str_contains($html, '<a href="/legal/privacy">privacy policy</a>') && str_contains($html, '<li>one</li>'), 'markdown renders headings, links and lists');
    publicCheck(! str_contains($html, '<script') && ! str_contains($html, 'onerror') && ! str_contains($html, '<img'), 'raw HTML in the source is stripped (no script, no event handler)');
    publicCheck(! str_contains(strtolower($html), 'javascript:'), 'a javascript: link is refused');

    // The shell's host step: a layout callback that does not mention PublicShell is pointed out, never blocking.
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/features/shell/feature.json'), true, 512, JSON_THROW_ON_ERROR);
    HostRequirements::validate('shell', $manifest);
    publicCheck(($manifest['host_steps']['mention'][0]['file'] ?? null) === 'resources/js/app.ts' && $manifest['host_steps']['mention'][0]['contains'] === 'PublicShell', 'shell declares the mention step for the layout callback');
    $root = sys_get_temp_dir().'/kit-public-shell-'.bin2hex(random_bytes(4));
    mkdir($root.'/resources/js', 0777, true);
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    $step = fn () => array_values(array_filter(HostRequirements::of($root, ['shell' => ['host_steps' => ['mention' => $manifest['host_steps']['mention']]]])['steps'], fn (array $step) => $step['kind'] === 'mention'))[0];
    $missing = $step();
    publicCheck($missing['status'] === 'unmet' && $missing['blocking'] === false && str_contains($missing['message'], 'No resources/js/app.ts here'), 'no app.ts: unmet, attention only');
    file_put_contents($root.'/resources/js/app.ts', "layout: name => name.startsWith('Auth/') ? GuestShell : Layout,\n");
    $unmet = $step();
    publicCheck($unmet['status'] === 'unmet' && $unmet['blocking'] === false && str_contains($unmet['message'], 'does not mention PublicShell'), 'a callback without PublicShell: unmet, never blocking');
    file_put_contents($root.'/resources/js/app.ts', "layout: name => name.startsWith('Public/') || name === 'Workspace/Error/Page' ? PublicShell : Layout,\n");
    publicCheck($step()['status'] === 'met', 'a callback that routes to PublicShell: met');
    // The app's entry is whatever its Vite config names (next.ts), and the config may be vite.config.js or .mjs: the steps read those, not only app.ts and .ts.
    $steps = fn (): array => array_column(array_filter(HostRequirements::of($root, ['shell' => ['host_steps' => ['mention' => $manifest['host_steps']['mention']]]])['steps'], fn (array $step) => $step['kind'] === 'mention'), null, 'step');
    unlink($root.'/resources/js/app.ts');
    file_put_contents($root.'/vite.config.js', "export default defineConfig({ plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/next.ts'] })], resolve: { alias: { '@kit/ui': 'x' } } });\n");
    file_put_contents($root.'/resources/js/next.ts', "layout: name => name.startsWith('Public/') ? PublicShell : Layout,\n");
    $found = $steps();
    publicCheck($found['resources/js/app.ts']['status'] === 'met' && $found['resources/js/app.ts']['message'] === 'resources/js/next.ts mentions PublicShell', 'no app.ts: the Vite entry next.ts that routes to PublicShell meets it, and is named');
    publicCheck($found['vite.config.ts']['status'] === 'unmet' && str_contains($found['vite.config.ts']['message'], 'vite.config.js does not mention @kit/shell'), 'the alias check reads vite.config.js when there is no .ts config, and names it');
    file_put_contents($root.'/vite.config.js', "export default defineConfig({ plugins: [laravel({ input: ['resources/js/next.ts'] })], resolve: { alias: { '@kit/shell': 'x' } } });\n");
    publicCheck($steps()['vite.config.ts']['status'] === 'met', 'and is met by @kit/shell there');
    rename($root.'/vite.config.js', $root.'/vite.config.mjs');
    publicCheck($steps()['vite.config.ts']['status'] === 'met' && $steps()['resources/js/app.ts']['status'] === 'met', 'vite.config.mjs too, entries included');
    file_put_contents($root.'/resources/js/next.ts', "createInertiaApp({});\n");
    publicCheck($steps()['resources/js/app.ts']['status'] === 'unmet' && str_contains($steps()['resources/js/app.ts']['message'], 'resources/js/next.ts does not mention PublicShell'), 'an entry without it: unmet, naming the file it read');
    unlink($root.'/vite.config.mjs');
    unlink($root.'/resources/js/next.ts');
    foreach ([[['file' => '/etc/passwd', 'contains' => 'x', 'why' => 'y']], [['file' => 'a.ts', 'contains' => '', 'why' => 'y']], [['file' => 'a.ts', 'why' => 'y']]] as $bad) {
        try {
            HostRequirements::validate('x', ['host_steps' => ['mention' => $bad]]);
            throw new LogicException('a malformed mention step was accepted');
        } catch (RuntimeException $error) {
            publicCheck(str_contains($error->getMessage(), 'Invalid host step'), 'a malformed mention step is refused ('.json_encode($bad[0]).')');
        }
    }
    echo "Public shell server checks: markdown sanitizing and the host step. The shell's markup is tests/js/public-shell.test.mjs.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
