<?php

// kit:brand's renderer choice for a stroked logomark: ImageMagick's own SVG renderer draws no strokes, so a stroked mark goes to rsvg-convert,
// or is refused with how to outline it, never rasterised to a dot. The detection and the choice are pure; the render runs with whatever this
// machine has, and says which paths it could exercise.

require $argv[1] ?? __DIR__.'/../vendor/autoload.php';

use Tey\Kit\BrandAssets;

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
            exit(0);
        }
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
echo 'BrandAssets checks: renderer '.($renderer ?? 'none').', Imagick delegates "'.$delegates.'", rsvg-convert '.($rsvg ? 'present' : 'absent').".\n";
