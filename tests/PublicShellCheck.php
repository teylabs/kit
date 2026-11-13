<?php

// The public pages' server side: PublicMarkdown renders legal markdown and sanitizes it (raw HTML stripped, unsafe links refused), and the shell's manifest
// declares its `mention` host step for the layout callback (a malformed step is refused). No database, no HTTP.
use Tey\Kit\Blueprint;
use Tey\Kit\Features\Shell\PublicMarkdown;

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

    // The shell's host step: the manifest declares where the layout callback must mention PublicShell, and Blueprint refuses a malformed step.
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/resources/features/shell/feature.json'), true, 512, JSON_THROW_ON_ERROR);
    publicCheck(in_array('shell', Blueprint::all()->selection(), true), 'the package catalog, shell included, passes Blueprint\'s manifest validation');
    publicCheck(($manifest['host_steps']['mention'][0]['file'] ?? null) === 'resources/js/app.ts' && $manifest['host_steps']['mention'][0]['contains'] === 'pages/Workspace/Partials/PublicShell', 'shell declares the mention step for the layout callback, on the kit\'s import path');
    foreach ([[['file' => '/etc/passwd', 'contains' => 'x', 'why' => 'y']], [['file' => 'a.ts', 'contains' => '', 'why' => 'y']], [['file' => 'a.ts', 'why' => 'y']]] as $bad) {
        try {
            Blueprint::validateFeatures(['x' => ['format' => 1, 'id' => 'x', 'owns' => [], 'files' => [], 'requires' => ['features' => [], 'packages' => []], 'conflicts' => [], 'host_owned' => [], 'host_steps' => ['mention' => $bad]]], ['x']);
            throw new LogicException('a malformed mention step was accepted');
        } catch (RuntimeException $error) {
            publicCheck(str_contains($error->getMessage(), 'Invalid host step'), 'a malformed mention step is refused ('.json_encode($bad[0]).')');
        }
    }
    echo "Public shell server checks: markdown sanitizing and the host step declaration. The shell's markup is tests/js/public-shell.test.mjs.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
