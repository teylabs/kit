<?php

// Maintainer tool, run by hand when Fonts::SETS changes (never in tests or CI): downloads every face of every set, as fonts.bunny.net lists them, into
// resources/fonts/files, writes resources/fonts/faces.json (one row per @font-face: family, style, weight, subset, unicode-range, file) and each
// family's SIL Open Font License from google/fonts into resources/fonts/licenses. `<x-kit::fonts />` serves these from the app's own origin under
// `kit.fonts_source = self` (docs/fonts.md). Every family in the sets is OFL, which allows redistribution with its license.
require __DIR__.'/../vendor/autoload.php';

use Tey\Kit\Fonts;

$root = dirname(__DIR__).'/resources/fonts';
@mkdir($root.'/files', 0777, true);
@mkdir($root.'/licenses', 0777, true);

$wanted = [];
foreach (Fonts::SETS as $set) {
    foreach (explode('|', $set['families']) as $family) {
        [$slug, $variants] = explode(':', $family);
        foreach (explode(',', $variants) as $variant) {
            $wanted[$slug][$variant] = true;
        }
    }
}
ksort($wanted);
$query = implode('|', array_map(fn (string $slug): string => $slug.':'.implode(',', array_keys($wanted[$slug])), array_keys($wanted)));
$fetch = function (string $url): string {
    $context = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130 Safari/537.36\r\n", 'timeout' => 30]]);
    $body = file_get_contents($url, false, $context);
    if (! is_string($body) || $body === '') {
        fwrite(STDERR, "Could not fetch $url\n");
        exit(1);
    }

    return $body;
};

$css = $fetch('https://fonts.bunny.net/css?family='.$query.'&display=swap');
preg_match_all('#/\* ([a-z-]+) \*/\s*@font-face\s*\{(.*?)\}#s', $css, $blocks, PREG_SET_ORDER);
$faces = [];
foreach ($blocks as [, $subset, $body]) {
    $read = fn (string $property): string => preg_match('/'.$property.':\s*([^;]+);/', $body, $m) === 1 ? trim($m[1]) : '';
    // bunny serves most faces itself and some from fontsource on jsDelivr; the file is named from the face either way.
    if (preg_match('#url\((https://[^)]+\.woff2)\)#', $body, $url) !== 1) {
        fwrite(STDERR, "No woff2 source in the $subset face of ".$read('font-family')."\n");
        exit(1);
    }
    $family = trim($read('font-family'), "'\"");
    $slug = strtolower(str_replace(' ', '-', $family));
    $file = $slug.'-'.$subset.'-'.(int) $read('font-weight').'-'.$read('font-style').'.woff2';
    file_put_contents($root.'/files/'.$file, $fetch($url[1]));
    $faces[] = ['family' => $family, 'slug' => $slug, 'style' => $read('font-style'), 'weight' => (int) $read('font-weight'), 'subset' => $subset, 'unicodeRange' => $read('unicode-range'), 'file' => $file];
}
usort($faces, fn (array $a, array $b): int => [$a['slug'], $a['weight'], $a['style'], $a['subset']] <=> [$b['slug'], $b['weight'], $b['style'], $b['subset']]);
file_put_contents($root.'/faces.json', json_encode(['source' => 'fonts.bunny.net', 'faces' => $faces], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

foreach (array_keys($wanted) as $slug) {
    file_put_contents($root.'/licenses/'.$slug.'.txt', $fetch('https://raw.githubusercontent.com/google/fonts/main/ofl/'.str_replace('-', '', $slug).'/OFL.txt'));
}
echo count($faces).' faces, '.count($wanted)." families\n";
