<?php

// kit:brand's renderer choice for a stroked logomark: ImageMagick's own SVG renderer draws no strokes, so a stroked mark goes to rsvg-convert,
// or is refused with how to outline it, never rasterised to a dot. The detection and the choice are pure; the render runs with whatever this
// machine has, and says which paths it could exercise.

require $argv[1] ?? __DIR__.'/../vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\BrandAssets;
use Tey\Kit\Console\BrandCommand;

function check(bool $ok, string $message): void
{
    if (! $ok) {
        fwrite(STDERR, 'FAIL '.$message."\n");
        exit(1);
    }
    echo 'PASS '.$message."\n";
}

$filled = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 4h16v16H4z" fill="currentColor"/></svg>';
$stroked = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/></svg>';

check(! BrandAssets::strokes($filled), 'a filled mark has no strokes');
check(BrandAssets::strokes($stroked), 'a stroke on the root is a stroke (it inherits)');
check(BrandAssets::strokes('<svg><path d="M0 0" style="fill:none; stroke: #000"/></svg>'), 'a stroke in a style attribute is a stroke');
check(BrandAssets::strokes('<svg><style>.a{stroke:currentColor}</style><path class="a" d="M0 0"/></svg>'), 'a stroke in a <style> block is a stroke');
check(! BrandAssets::strokes('<svg><path d="M0 0" stroke="none" style="stroke:none" stroke-width="2"/></svg>'), 'stroke none, or a stroke-width alone, is not a stroke');
check(! BrandAssets::strokes('<svg><!-- stroke="#000" --><path d="M0 0" fill="#000"/></svg>'), 'a stroke in a comment is not a stroke');
check(! BrandAssets::strokes('<svg><style>/* stroke: red */ .a{fill:#000}</style><path class="a" style="fill:#000 /* stroke:red */" d="M0 0"/></svg>'), 'a stroke in a CSS comment is not a stroke');
$styled = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill:none; stroke: #111; stroke-width:2"><circle cx="12" cy="12" r="8"/></svg>';
[$markup] = (fn (string $color) => $this->inner($color))->call(new BrandAssets($styled, '#2563eb', 'Example'), '#2563eb');
check(BrandAssets::strokes($styled) && str_contains($markup, 'stroke:#2563eb') && str_contains($markup, 'fill:none') && str_contains($markup, 'stroke-width:2'), 'a stroke set by the root style survives the rebuilt mark, in the mark colour');

check(BrandAssets::engineFor(false, true, false, false) === 'imagick', 'a filled mark renders with Imagick, librsvg or not');
check(BrandAssets::engineFor(true, true, true, false) === 'imagick', 'a stroked mark renders with an Imagick that has librsvg');
check(BrandAssets::engineFor(true, true, false, true) === 'rsvg-convert', 'a stroked mark goes to rsvg-convert when Imagick has no librsvg');
check(BrandAssets::engineFor(true, true, false, false) === null, 'a stroked mark with only a stroke-less Imagick is refused');
check(BrandAssets::engineFor(false, false, false, true) === 'rsvg-convert', 'without Imagick, rsvg-convert renders');
check(BrandAssets::engineFor(true, false, false, false) === null && BrandAssets::engineFor(false, false, false, false) === null, 'with no renderer, nothing renders');

$renderer = BrandAssets::renderer();
$delegates = class_exists(Imagick::class) ? (string) (Imagick::getConfigureOptions()['DELEGATES'] ?? '') : '';
$rsvg = trim((string) shell_exec('command -v rsvg-convert 2>/dev/null')) !== '';
if ($renderer === null) {
    echo "SKIP render: no SVG renderer on this machine\n";
} else {
    $files = (new BrandAssets($filled, '#2563eb', 'Example'))->render();
    check(array_keys($files) === BrandAssets::FILES, 'a filled mark renders every asset with '.$renderer);
    if ($renderer === 'imagick' && ! str_contains($delegates, 'rsvg') && ! $rsvg) {
        try {
            (new BrandAssets($stroked, '#2563eb', 'Example'))->render();
            check(false, 'a stroked mark on a stroke-less Imagick is refused');
        } catch (RuntimeException $error) {
            check(str_contains($error->getMessage(), 'Outline the strokes') && str_contains($error->getMessage(), 'rsvg-convert'), 'a stroked mark on a stroke-less Imagick is refused, naming how to outline it and what to install');
        }
    } else {
        $png = (new BrandAssets($stroked, '#2563eb', 'Example'))->render()['mail-mark.png'];
        if (! function_exists('imagecreatefromstring')) {
            echo "SKIP stroke pixels: no GD to read the PNG\n";
        } else {
            $image = imagecreatefromstring($png);
            $painted = 0;
            for ($x = 0; $x < imagesx($image); $x += 2) {
                for ($y = 0; $y < imagesy($image); $y += 2) {
                    $painted += (imagecolorat($image, $x, $y) >> 24) < 64 ? 1 : 0;
                }
            }
            check($painted > 100, 'a stroked mark renders its strokes, not a dot ('.$painted.' painted samples)');
        }
    }
}

// The icon source (kit.brand.icon): the favicons, touch icon and mail mark come from it, else from the logomark; a mark that isn't square is padded to a
// centred square, and a square one is left byte for byte.
check(BrandAssets::square('0 0 24 24') === '0 0 24 24' && BrandAssets::square('0,0,64,64') === '0,0,64,64', 'a square viewBox is left exactly as written');
check(BrandAssets::square('0 0 100 25') === '0 -37.5 100 100' && BrandAssets::square('10 0 20 40') === '0 0 40 40', 'a wide or tall viewBox is padded to a square around the same centre');
$wide = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 25"><rect width="100" height="25" fill="currentColor"/></svg>';
$tiny = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="currentColor"/></svg>';
$svgOf = fn (BrandAssets $assets, string $method, mixed ...$args) => (fn () => $this->{$method}(...$args))->call($assets);
$fromIcon = new BrandAssets($wide, '#2563eb', 'Example', null, $tiny);
check(str_contains($svgOf($fromIcon, 'tile', 14), '<circle') && ! str_contains($svgOf($fromIcon, 'tile', 14), '<rect width="100"') && str_contains($svgOf($fromIcon, 'mark', '#000'), 'viewBox="0 0 10 10"'), 'icon set: the favicon tile and the mail mark are drawn from it');
check(str_contains($svgOf(new BrandAssets($tiny, '#2563eb', 'Example'), 'tile', 14), '<circle') && $svgOf(new BrandAssets($tiny, '#2563eb', 'Example'), 'tile', 14) === $svgOf(new BrandAssets($tiny, '#2563eb', 'Example', null, null), 'tile', 14), 'icon unset: they fall back to the logomark');
check($svgOf(new BrandAssets($filled, '#2563eb', 'Example'), 'tile', 14) === '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#2563eb"/><g transform="translate(14 14) scale(1.5) translate(-0 -0)"><g><path d="M4 4h16v16H4z" fill="#ffffff"/></g></g></svg>', 'unset with a square mark: the favicon SVG is what it was, byte for byte');
check(str_contains($svgOf(new BrandAssets($wide, '#2563eb', 'Example'), 'mark', '#000'), 'viewBox="0 -37.5 100 100"'), 'a wide mark is padded to a centred square viewBox');
if ($renderer !== null && function_exists('imagecreatefromstring')) {
    $files = (new BrandAssets($wide, '#2563eb', 'Example'))->render();
    $mail = imagecreatefromstring($files['mail-mark.png']);
    check(imagesx($mail) === 96 && imagesy($mail) === 96, 'a wide mark renders a 96x96 mail mark');
    // Painted bounds: a 4:1 mark stays 4:1 inside the square, centred, not stretched to fill it.
    [$left, $top, $right, $bottom] = [96, 96, -1, -1];
    for ($x = 0; $x < 96; $x++) {
        for ($y = 0; $y < 96; $y++) {
            if ((imagecolorat($mail, $x, $y) >> 24) < 64) {
                [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
            }
        }
    }
    $ratio = ($right - $left + 1) / max(1, $bottom - $top + 1);
    check($ratio > 3.5 && $ratio < 4.5 && abs(($top + $bottom) / 2 - 47.5) <= 1.5, sprintf('a wide mark keeps its aspect (%.2f:1) and is centred in the square', $ratio));
    $touch = imagecreatefromstring($files['apple-touch-icon.png']);
    check(imagesx($touch) === 180 && imagesy($touch) === 180, 'the touch icon stays square for a wide mark');
    $og = (new BrandAssets($wide, '#2563eb', 'Example', null, $tiny))->render()['og-image.png'];
    check($og === (new BrandAssets($wide, '#2563eb', 'Example'))->render()['og-image.png'], 'the social image keeps the logomark whatever the icon is');
} else {
    echo "SKIP wide-mark pixels: no SVG renderer or no GD\n";
}

// kit:brand --check compares against the icon source.
if ($renderer !== null) {
    $public = sys_get_temp_dir().'/kit-brandicon-'.getmypid();
    @mkdir($public);
    file_put_contents($public.'/logomark.svg', $wide);
    file_put_contents($public.'/icon.svg', $tiny);
    $app = new Application(dirname(__DIR__));
    $app->usePublicPath($public);
    Container::setInstance($app);
    $app->instance('config', new Repository(['app' => ['name' => 'Example'], 'kit' => ['brand' => ['accent' => '#2563eb', 'logomark' => '/logomark.svg', 'icon' => '/icon.svg']]]));
    Facade::setFacadeApplication($app);
    $runCheck = function () use ($app): int {
        $command = new BrandCommand;
        $command->setLaravel($app);

        return $command->run(new ArrayInput(['--check' => true]), new BufferedOutput);
    };
    try {
        (new BrandAssets($wide, '#2563eb', 'Example', null, $tiny))->write($public);
        check($runCheck() === 0, '--check passes when public/ holds what the icon renders');
        (new BrandAssets($wide, '#2563eb', 'Example'))->write($public);
        check($runCheck() === 1, '--check fails when public/ was rendered from the logomark instead of the icon');
    } finally {
        array_map('unlink', glob($public.'/*'));
        rmdir($public);
    }
}
echo 'BrandAssets checks: renderer '.($renderer ?? 'none').', Imagick delegates "'.$delegates.'", rsvg-convert '.($rsvg ? 'present' : 'absent').".\n";

// An empty kit.brand.accent: the assets take the accent the theme resolves to (the light --primary in the host's stylesheets), or kit:brand refuses;
// nothing is drawn in an empty colour. A set accent reads as before.
$host = sys_get_temp_dir().'/kit-brandaccent-'.getmypid();
@mkdir($host.'/resources/css', 0777, true);
$app = new Application($host);
Container::setInstance($app);
Facade::setFacadeApplication($app);
$brand = fn (array $values) => $app->instance('config', new Repository(['app' => ['name' => 'Example'], 'kit' => ['brand' => $values]]));
try {
    file_put_contents($host.'/resources/css/app.css', "@import './kit.css';\n:root { --primary: #1d4ed8; }\n.dark { --primary: #93c5fd; }\n");
    file_put_contents($host.'/resources/css/kit.css', ":root { --primary: oklch(0.21 0.006 285.885); }\n");
    $brand(['accent' => '']);
    check(BrandAssets::configuredAccent($host) === '#1d4ed8', 'an empty accent falls back to the theme\'s light --primary, the app\'s over kit.css');
    file_put_contents($host.'/resources/css/app.css', "@import './kit.css';\n:root { --primary: #1d4ed8; }\n:root { --primary: var(--brand); }\n");
    try {
        BrandAssets::configuredAccent($host);
        check(false, 'a later unreadable --primary wins over an earlier hex and over kit.css');
    } catch (RuntimeException) {
        check(true, 'a later unreadable --primary wins over an earlier hex and over kit.css: refused, not guessed');
    }
    file_put_contents($host.'/resources/css/app.css', "@import './kit.css';\n");
    $app->instance('config', new Repository(['app' => ['name' => 'Example'], 'kit' => ['brand' => [], 'brand_sources' => ['accent' => 'config']]]));
    check(preg_match('/\A#[0-9a-f]{6}\z/', BrandAssets::configuredAccent($host)) === 1, 'an accent resolved unset from config/brand.php (left out of kit.brand) also falls back to the theme');
    $brand(['accent' => '  ']);
    check(preg_match('/\A#[0-9a-f]{6}\z/', BrandAssets::configuredAccent($host)) === 1, 'with only kit.css, the accent is its --primary as #rrggbb');
    file_put_contents($host.'/resources/css/app.css', ":root { --primary: var(--brand); }\n.dark { --primary: #93c5fd; }\n");
    unlink($host.'/resources/css/kit.css');
    try {
        BrandAssets::configuredAccent($host);
        check(false, 'an empty accent with no theme accent is refused');
    } catch (RuntimeException $error) {
        check(str_contains($error->getMessage(), "'accent' in config/brand.php") && str_contains($error->getMessage(), '--primary'), 'an empty accent with no theme accent is refused, naming what to set');
    }
    $brand([]);
    check(BrandAssets::configuredAccent($host) === '#52525b', 'no accent key: unchanged, the neutral default');
    $brand(['accent' => null]);
    check(BrandAssets::configuredAccent($host) === '#52525b', 'a null accent: unchanged, the neutral default');
    $brand(['accent' => '#2563EB']);
    check(BrandAssets::configuredAccent($host) === '#2563eb', 'a set accent is used as before, whatever the theme');
    $brand(['accent' => 'not-a-colour']);
    check(BrandAssets::configuredAccent($host) === '#52525b', 'a set but unreadable accent keeps its old fallback');
    try {
        new BrandAssets($filled, '', 'Example');
        check(false, 'an empty colour is refused');
    } catch (RuntimeException $error) {
        check(str_contains($error->getMessage(), 'empty'), 'BrandAssets refuses an empty colour outright');
    }

    // kit.brand.own: the app's hand-made outputs, never overwritten. Default empty.
    $brand(['accent' => '#2563eb']);
    check(BrandAssets::owned() === [], 'own defaults to an empty list');
    $brand(['accent' => '#2563eb', 'own' => ['/favicon.ico', 'public/og-image.png', 'og-image.png', '', 7]]);
    check(BrandAssets::owned() === ['favicon.ico', 'og-image.png'], 'own takes names in public/, with or without a leading / or public/');
    if ($renderer !== null) {
        $public = $host.'/public';
        @mkdir($public);
        file_put_contents($public.'/logomark.svg', $filled);
        $app->usePublicPath($public);
        $run = function (array $input = []) use ($app): array {
            $command = new BrandCommand;
            $command->setLaravel($app);
            $output = new BufferedOutput;

            return [$command->run(new ArrayInput($input), $output), $output->fetch()];
        };
        $brand(['accent' => '#2563eb', 'logomark' => '/logomark.svg']);
        [$exit, $out] = $run();
        check($exit === 0 && substr_count($out, 'wrote public/') === count(BrandAssets::FILES) && ! str_contains($out, 'skipped'), 'default: kit:brand writes every file and skips none');
        file_put_contents($public.'/favicon.ico', 'hand made');
        $brand(['accent' => '#1d4ed8', 'logomark' => '/logomark.svg', 'own' => ['favicon.ico']]);
        [$exit, $out] = $run();
        check($exit === 0 && file_get_contents($public.'/favicon.ico') === 'hand made' && str_contains($out, 'skipped public/favicon.ico') && str_contains($out, 'wrote public/favicon.svg'), 'a file in own is left untouched and reported as skipped; the rest are written');
        [$exit, $out] = $run(['--check' => true]);
        check($exit === 0 && str_contains($out, 'skipped public/favicon.ico'), '--check skips an owned file instead of calling it out of date');
        $brand(['accent' => '', 'logomark' => '/logomark.svg', 'own' => ['favicon.ico']]);
        file_put_contents($host.'/resources/css/app.css', ":root { --primary: #1d4ed8; }\n");
        [$exit] = $run(['--check' => true]);
        check($exit === 0, 'an empty accent renders in the theme accent: the same bytes as that accent set outright (the favicon.svg, mail mark, touch icon, social image)');
        file_put_contents($host.'/resources/css/app.css', "body { color: red; }\n");
        [$exit, $out] = $run();
        check($exit === 1 && str_contains($out, "'accent' in config/brand.php") && file_get_contents($public.'/favicon.ico') === 'hand made', 'kit:brand refuses with no resolvable accent and writes nothing');
    } else {
        echo "SKIP own and theme-accent writes: no SVG renderer on this machine\n";
    }
} finally {
    exec('rm -rf '.escapeshellarg($host));
}
