<?php

use Illuminate\Support\Facades\Facade;
use Tey\Kit\Brand;

require dirname(__DIR__).'/vendor/autoload.php';

/** The brand's variant tokens, its CSS, the local-only switch's bounds, and the 4.5:1 contrast guard. No client values: synthetic colours only. */
function variantCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$app = new Illuminate\Foundation\Application(dirname(__DIR__));
Illuminate\Container\Container::setInstance($app);
$app->instance('config', new Illuminate\Config\Repository(['kit' => ['brand' => ['accent' => '#52525b', 'presets' => ['fill-one' => ['accent' => '#2563eb', 'accent_foreground' => '#ffffff', 'accent_strong' => '#1d4ed8', 'accent_dark' => '#60a5fa', 'accent_dark_foreground' => '#09090b'], 'mono-one' => ['accent' => '#16a34a', 'accent_strong' => '#15803d', 'accent_dark' => '#4ade80', 'style' => 'mono', 'ink' => '#050505'], 'Bad Name' => ['accent' => '#ff0000'], 'x' => 'not an array']]]]));
Facade::setFacadeApplication($app);

variantCheck(abs(Brand::contrast('#000000', '#ffffff') - 21.0) < 0.01 && abs(Brand::contrast('#ffffff', '#ffffff') - 1.0) < 0.01, 'contrast is the WCAG ratio (21:1 black on white, 1:1 alike)');
variantCheck(array_keys(Brand::presets()) === ['fill-one', 'mono-one'], 'only well-named presets that are arrays are offered');
$neutral = Brand::tokens();
variantCheck($neutral['accent'] === '#52525b' && $neutral['style'] === 'fill' && Brand::css($neutral) === '', 'the neutral default emits no overriding CSS');
variantCheck(Brand::contrastProblems($neutral) === [], 'the neutral default passes 4.5:1');
$fill = Brand::tokens('fill-one');
$css = Brand::css($fill);
variantCheck(str_contains($css, ':root{--primary:#2563eb;--primary-foreground:#ffffff;--ring:#1d4ed8') && str_contains($css, '.dark{--primary:#60a5fa;--primary-foreground:#09090b'), 'fill: the accent fills primary, with the strong accent for focus and the dark accent in dark mode');
$mono = Brand::tokens('mono-one');
$css = Brand::css($mono);
variantCheck($mono['style'] === 'mono' && str_contains($css, '--primary:#050505') && str_contains($css, '[data-shell-bar]{--background:#050505') && str_contains($css, '--brand-marker:#16a34a'), 'mono: ink header and primary, the accent only a highlight');
variantCheck(Brand::contrastProblems($fill) === [] && Brand::contrastProblems($mono) === [], 'the synthetic variants pass 4.5:1');
$weak = Brand::tokens('fill-one');
$weak['accent_strong'] = '#93c5fd';
$weak['accent_foreground'] = '#bfdbfe';
variantCheck(count(Brand::contrastProblems($weak)) === 2, 'a weak accent-strong on white, or accent-foreground on the accent, fails the guard');
$unsafe = Brand::tokens('fill-one');
config(['kit.brand.presets.fill-one.accent' => 'red;}body{display:none']);
variantCheck(Brand::tokens('fill-one')['accent'] === '#52525b', 'a colour that is not #rrggbb is never printed into CSS');
