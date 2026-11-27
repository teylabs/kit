<?php

// Refactoring UI rules for the kit's own markup (docs/rui.md): every Kit component (resources/js/components/Kit) and every feature page stub
// (resources/features/*/pages, widgets) is read as text, and the check fails on
//   palette    a Tailwind palette colour in a class (`bg-red-500`, `text-zinc-400`): colours come from the theme's tokens;
//   px         an arbitrary px size in a class (`text-[10px]`, `h-[42px]`): sizes come from the type and spacing scales;
//   destructive `variant="destructive"` on a control that is not a confirm dialog's own confirm or the page's primary action (`data-primary-action`):
//              a row's or a card's trigger is quiet, and the dialog it opens carries the red;
//   empty      a text-only empty state (a muted `<p>` saying "No … yet", "Nothing here", "No results", or a `<p data-…-empty>`): EmptyState is used;
//   destructive-text `text-destructive` as a text colour: on a surface it misses 4.5:1, so text takes `text-destructive-text`.
// A known exception is listed in tests/fixtures/rui-allowlist.json (path, rule, the matched text and a reason); an entry that no longer matches fails
// too. Usage: php tests/RuiCheck.php [root]. Exits nonzero on any finding.

$root = rtrim($argv[1] ?? dirname(__DIR__), '/');
$allowPath = __DIR__.'/fixtures/rui-allowlist.json';
$allow = is_file($allowPath) ? json_decode((string) file_get_contents($allowPath), true, flags: JSON_THROW_ON_ERROR)['allow'] : [];

$palette = 'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone';
$rules = [
    'palette' => '/(?<![\w-])(?:[a-z-]+:)*(?:bg|text|border|ring|fill|stroke|from|via|to|outline|divide|decoration|accent|caret|shadow)-(?:'.$palette.')-\d{2,3}(?:\/\d+)?(?![\w-])/',
    'px' => '/(?<![\w-])(?:[a-z-]+:)*-?[a-z][a-z-]*-\[-?\d+(?:\.\d+)?px\]/',
    'destructive' => '/<[A-Za-z][^<>]*\bvariant="destructive"[^<>]*>/s',
    'empty' => '/<p\b[^<>]*(?:text-muted-foreground[^<>]*>\s*(?:No [^<]{0,40}?\b(?:yet|match|found)|Nothing (?:here|has been|to show)|No results)|data-[a-z-]*-empty\b[^<>]*>)/s',
    'destructive-text' => '/(?<![\w-])(?:[a-z-]+:)*text-destructive(?![\w-])/',
];

// Each rule must catch its own synthetic sample, and pass its good twin, or the check proves nothing.
$samples = [
    'palette' => ['<span class="bg-red-500 dark:text-zinc-400">', '<span class="bg-destructive text-muted-foreground">'],
    'px' => ['<span class="text-[10px] h-[42px]">', '<span class="text-xs h-10">'],
    'destructive' => ['<Button variant="destructive" type="button">Remove</Button>', '<Button variant="destructive" data-confirm-action>Remove</Button>'],
    'empty' => ['<p class="text-sm text-muted-foreground">No passkeys yet.</p>', '<EmptyState title="No passkeys yet" />'],
    'destructive-text' => ['<p class="text-destructive">Failed</p>', '<p class="text-destructive-text">Failed</p>'],
];
foreach ($samples as $rule => [$bad, $good]) {
    $caught = preg_match($rules[$rule], $bad, $hit) === 1 && ! ($rule === 'destructive' && str_contains($hit[0], 'data-confirm-action'));
    $passed = preg_match($rules[$rule], $good, $hit) !== 1 || ($rule === 'destructive' && str_contains($hit[0], 'data-confirm-action'));
    if (! $caught || ! $passed) {
        fwrite(STDERR, "FAIL the {$rule} rule does not tell its sample from its good twin\n");
        exit(1);
    }
}

$files = [];
foreach (['resources/js/components/Kit', 'resources/features'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);
        if (preg_match('/\.(vue|ts)(\.stub)?\z/', $path) && ! str_contains($path, '/tests/') && ! str_ends_with($path, '.d.ts')) {
            $files[] = $path;
        }
    }
}
sort($files);

$findings = [];
$used = [];
foreach ($files as $path) {
    $src = (string) file_get_contents($root.'/'.$path);
    // Comments are prose, not markup.
    $code = preg_replace(['~<!--.*?-->~s', '~/\*.*?\*/~s', '~(?<![:"\'\w])//[^\n]*~'], '', $src);
    foreach ($rules as $rule => $pattern) {
        preg_match_all($pattern, $code, $matches);
        foreach ($matches[0] as $hit) {
            if ($rule === 'destructive' && (str_contains($hit, 'data-primary-action') || str_contains($hit, 'data-confirm-action'))) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $hit));
            $key = null;
            foreach ($allow as $index => $entry) {
                if (($entry['path'] === $path || $entry['path'] === '*') && $entry['rule'] === $rule && str_contains($text, $entry['match'])) {
                    $key = $index;
                    break;
                }
            }
            if ($key !== null) {
                $used[$key] = true;
                continue;
            }
            $findings[] = sprintf('%s [%s] %s', $path, $rule, mb_strimwidth($text, 0, 140, '…'));
        }
    }
}
foreach ($allow as $index => $entry) {
    if (! isset($used[$index]) && is_dir($root.'/resources/js/components/Kit')) {
        $findings[] = sprintf('%s [allowlist] the entry for %s "%s" matches nothing: remove it', $entry['path'], $entry['rule'], $entry['match']);
    }
}

if ($findings !== []) {
    fwrite(STDERR, implode("\n", array_map(fn ($line) => 'FAIL '.$line, array_unique($findings)))."\n");
    fwrite(STDERR, count(array_unique($findings))." Refactoring UI finding(s) in ".count($files)." files.\n");
    exit(1);
}
echo 'PASS '.count($files)." Kit components and feature stubs keep to the Refactoring UI rules (palette, px, destructive, empty, destructive-text).\n";
