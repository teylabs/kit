<?php

// The `kit-imagick` driver for Laravel's image API: the decode-size hint is set before the read, a single frame is never coalesced (an animated
// GIF still is, onto its full canvas), transparent images stay PNG, the kit.images options apply, and the kit makes it images.default only
// while that is still `gd`. Small synthetic images only; no network. Skips (exit 0) without ext-imagick or the framework's image API.
use Illuminate\Config\Repository;
use Illuminate\Contracts\Image\Transformation;
use Illuminate\Foundation\Application;
use Illuminate\Image\Image;
use Illuminate\Image\ImageException;
use Illuminate\Image\ImageManager;
use Illuminate\Image\ImagePipeline;
use Illuminate\Image\ImageServiceProvider;
use Illuminate\Image\Transformations\Blur;
use Illuminate\Image\Transformations\Scale;
use Illuminate\Support\Facades\Facade;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Media\Images\SizedImagickDriver;
use Tey\Kit\Media\Images\Transformations\CropToRatio;
use Tey\Kit\Media\Images\Transformations\DecodeAtLeast;
use Tey\Kit\Media\Images\Transformations\KeepTransparency;
use Tey\Kit\Media\Images\Transformations\Progressive;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

if (! extension_loaded('imagick') || ! class_exists(ImageManager::class)) {
    echo 'SKIP: '.(extension_loaded('imagick') ? 'the framework has no image API' : 'ext-imagick is not loaded').PHP_EOL;
    exit(0);
}

$failures = 0;
$passes = 0;
function check(bool $ok, string $label): void
{
    global $failures, $passes;
    $ok ? $passes++ : $failures++;
    echo ($ok ? 'ok   ' : 'FAIL ').$label.PHP_EOL;
}

$app = new Application(sys_get_temp_dir());
$app->instance('config', new Repository(['images' => ['default' => 'gd'], 'kit' => []]));
Facade::setFacadeApplication($app);
(new ImageServiceProvider($app))->register();

/** A step the check registers a handler for, to see the raw Imagick object right after the read. */
final class Probe implements Transformation {}

function jpeg(int $width, int $height, ?callable $tweak = null): string
{
    $image = new Imagick;
    $image->newPseudoImage($width, $height, 'gradient:#2a6-#c84');
    $image->setImageFormat('jpeg');
    $tweak && $tweak($image);

    return $image->getImageBlob();
}

function pipeline(string $format, Transformation ...$steps): ImagePipeline
{
    $pipeline = new ImagePipeline;
    $pipeline->output->format = $format;
    foreach ($steps as $step) {
        $pipeline->add($step);
    }

    return $pipeline;
}

function read(string $bytes): Imagick
{
    $image = new Imagick;
    $image->readImageBlob($bytes);

    return $image;
}

// The hint: libjpeg decodes a 4000 x 3000 JPEG shrunk (by 1/8 here, to 500 x 375) before any step runs. A custom handler gets the raw Imagick.
$seen = [];
$driver = (new SizedImagickDriver)->transformUsing(Probe::class, function (Imagick $image) use (&$seen): Imagick {
    $seen[] = [$image->getImageWidth(), $image->getImageHeight()];

    return $image;
});
$big = jpeg(4000, 3000);
$out = read($driver->process($big, pipeline('jpeg', new DecodeAtLeast(500, 500), new Probe, new Scale(400, null))));
check($seen[0][0] < 4000 && max($seen[0]) >= 500, 'with DecodeAtLeast the JPEG is decoded shrunk, its long side still at least the hint (got '.implode('x', $seen[0]).')');
check($out->getImageWidth() === 400 && $out->getImageFormat() === 'JPEG', 'Scale then brings it to 400 wide, JPEG');
$driver->process($big, pipeline('jpeg', new Probe));
check($seen[1] === [4000, 3000], 'without the hint it is decoded at full size');

// Single frames are not coalesced; multi-frame images are.
check(! SizedImagickDriver::needsCoalesce(read(jpeg(64, 48))), 'a single-frame JPEG skips coalesce');
$gif = new Imagick;
$canvas = new Imagick;
$canvas->newImage(80, 60, 'red');
$canvas->setImageFormat('gif');
$canvas->setImagePage(80, 60, 0, 0);
$gif->addImage($canvas);
$patch = new Imagick;
$patch->newImage(20, 20, 'blue');
$patch->setImageFormat('gif');
$patch->setImagePage(80, 60, 30, 20);
$gif->addImage($patch);
$gif->setFormat('gif');
$gifBytes = $gif->getImagesBlob();
check(SizedImagickDriver::needsCoalesce(read($gifBytes)), 'an animated GIF is coalesced');
$frames = read($gifBytes)->coalesceImages();
$frames->setIteratorIndex(1);
$last = $frames->getImage();
check([$last->getImageWidth(), $last->getImageHeight()] === [80, 60], 'coalescing the GIF gives full-canvas frames (the second is 80x60, not 20x20)');
$gifOut = read((new SizedImagickDriver)->process($gifBytes, pipeline('png')));
$pixel = $gifOut->getImagePixelColor(5, 5)->getColor();
check([$gifOut->getImageWidth(), $gifOut->getImageHeight()] === [80, 60] && $pixel['r'] > 200 && $pixel['b'] < 60, 'the animated GIF encodes its first coalesced frame (80x60, red)');

// Skipping coalesce for one frame changes no output byte.
$photo = jpeg(900, 600);
$reference = new Imagick;
$reference->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
$reference->setOption('jpeg:size', '600x600');
$reference->readImageBlob($photo);
$reference = $reference->coalesceImages()->getImage();
$reference->thumbnailImage(300, 0);
$reference->stripImage();
$reference->setImageBackgroundColor('white');
$reference = $reference->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
$reference->setImageFormat('jpeg');
$reference->setImageCompressionQuality(82);
$skipped = new ImagePipeline;
$skipped->output->format = 'jpeg';
$skipped->output->quality = 82;
$skipped->add(new DecodeAtLeast(600, 600));
$skipped->add(new Scale(300, null));
check((new SizedImagickDriver)->process($photo, $skipped) === $reference->getImageBlob(), 'a single-frame JPEG without coalesce gives the same bytes as with it');

// Transparency.
$clear = new Imagick;
$clear->newImage(40, 30, 'transparent');
$clear->setImageFormat('png');
$clearPng = $clear->getImageBlob();
check(read((new SizedImagickDriver)->process($clearPng, pipeline('jpeg', new KeepTransparency)))->getImageFormat() === 'PNG', 'KeepTransparency keeps a transparent image as PNG');
check(read((new SizedImagickDriver)->process($clearPng, pipeline('jpeg')))->getImageFormat() === 'JPEG', 'without it the image is flattened to JPEG');
check(read((new SizedImagickDriver)->process(jpeg(40, 30), pipeline('jpeg', new KeepTransparency)))->getImageFormat() === 'JPEG', 'an opaque image stays JPEG under KeepTransparency');

// Options.
$tagged = jpeg(60, 40, fn (Imagick $image) => $image->setImageProperty('comment', 'synthetic note'));
check(read((new SizedImagickDriver)->process($tagged, pipeline('jpeg')))->getImageProperty('comment') === false, 'metadata is stripped by default');
check(read((new SizedImagickDriver(stripMetadata: false))->process($tagged, pipeline('jpeg')))->getImageProperty('comment') === 'synthetic note', 'strip_metadata false keeps it');
// A progressive JPEG has a SOF2 frame marker; a baseline one SOF0.
check(str_contains((new SizedImagickDriver(progressive: true))->process(jpeg(60, 40), pipeline('jpeg')), "\xFF\xC2"), 'progressive true writes a progressive JPEG');
check(! str_contains((new SizedImagickDriver(progressive: true))->process(jpeg(60, 40), pipeline('jpeg', new Progressive(false))), "\xFF\xC2"), 'a Progressive(false) step overrides the option');
$crop = read((new SizedImagickDriver)->process(jpeg(600, 600), pipeline('jpeg', new CropToRatio(300, 1.5))));
check([$crop->getImageWidth(), $crop->getImageHeight()] === [300, 200], 'CropToRatio centre-crops to 3:2 at 300 wide');
$refused = false;
try {
    (new SizedImagickDriver(cropToRatio: false))->process(jpeg(600, 600), pipeline('jpeg', new CropToRatio(300, 1.5)));
} catch (ImageException) {
    $refused = true;
}
check($refused, 'with crop_to_ratio false, CropToRatio is refused');
$app['config']->set('kit.images', ['memory_limit' => null, 'strip_metadata' => false, 'progressive' => true, 'crop_to_ratio' => false]);
$configured = (fn () => [$this->memoryLimit, $this->stripMetadata, $this->progressive, $this->cropToRatio])->call(SizedImagickDriver::fromConfig());
check($configured === [null, false, true, false], 'fromConfig reads every kit.images option');
$app['config']->set('kit.images', []);
$defaults = (fn () => [$this->memoryLimit, $this->stripMetadata, $this->progressive, $this->cropToRatio])->call(SizedImagickDriver::fromConfig());
check($defaults === [256 * 1024 * 1024, true, null, true], 'every option has a default when kit.images is absent');

// Anything else goes to the framework Imagick driver, which needs intervention/image.
if (! class_exists(Intervention\Image\ImageManager::class)) {
    $message = '';
    try {
        (new SizedImagickDriver)->process(jpeg(60, 40), pipeline('jpeg', new Blur(2)));
    } catch (ImageException $e) {
        $message = $e->getMessage();
    }
    check(str_contains($message, 'intervention/image'), 'an unsupported step without intervention/image is refused, naming the package');
} else {
    check(read((new SizedImagickDriver)->process(jpeg(60, 40), pipeline('jpeg', new DecodeAtLeast(10, 10), new Blur(2))))->getImageWidth() === 60, 'an unsupported step goes to the framework driver with the markers removed');
}

// Registration: the kit's driver becomes images.default only while that is gd and kit.images.default is on.
$register = fn (array $config): Repository => (function () use ($config, $app): Repository {
    $app->forgetInstance('image');
    $app['config']->set($config);
    (new ImageServiceProvider($app))->register();
    (fn () => $this->registerImageDriver())->call(new KitServiceProvider($app));

    return $app['config'];
})();
check($register(['images.default' => 'gd', 'kit.images.default' => true])->get('images.default') === 'kit-imagick', 'the kit takes images.default from gd');
check($app->make('image')->driver() instanceof SizedImagickDriver, 'the default driver resolves to the kit driver');
check((new Image(jpeg(200, 100)))->scale(50)->toJpeg()->width() === 50, 'Laravel\'s Image API runs on it end to end');
check($register(['images.default' => 'gd', 'kit.images.default' => false])->get('images.default') === 'gd', 'kit.images.default false keeps gd');
check($register(['images.default' => 'imagick', 'kit.images.default' => true])->get('images.default') === 'imagick', 'a host that chose another driver keeps it');
check($app->make('image')->driver('kit-imagick') instanceof SizedImagickDriver, 'kit-imagick is still registered by name');

echo PHP_EOL.$passes.' passed, '.$failures.' failed'.PHP_EOL;
exit($failures === 0 && $passes > 0 ? 0 : 1);
