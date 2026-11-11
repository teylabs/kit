# Images

The kit ships `kit-imagick`, a size-aware Imagick driver for [Laravel's image API](https://laravel.com/framework/docs/images) (`Tey\Kit\Media\Images\SizedImagickDriver`). The kit registers it with `Image::extend()` when the framework has the image API and ext-imagick is loaded.

## The default driver

`kit-imagick` becomes `images.default` while that is still the framework's `gd`. A host that set another driver (`IMAGE_DRIVER=imagick`, say) keeps it. To keep gd, set `kit.images.default` to false (env `KIT_IMAGE_DEFAULT=false`). The driver can always be named: `Image::fromPath($path)->using('kit-imagick')`. Without ext-imagick nothing is registered and the framework default stands.

## Why it matters

The framework's Imagick driver decodes every image at full size before any transformation runs. `kit-imagick` reads the pipeline first, so a `DecodeAtLeast` step sets libjpeg's shrink-on-load hint (`jpeg:size`) before the bytes are read. It coalesces only a multi-frame image (an animated GIF); a single frame is used as decoded.

On a 63 MP JPEG scaled for print:

| | Peak memory |
| --- | --- |
| Always coalesced, no hint | about 3.3 GB |
| Always coalesced, with the hint | about 0.9 GB |
| Hint plus the single-frame skip (this driver) | 421 to 490 MB |

The output bytes are the same with or without the coalesce.

```php
use Illuminate\Support\Facades\Image;
use Tey\Kit\Media\Images\Transformations\DecodeAtLeast;
use Tey\Kit\Media\Images\Transformations\KeepTransparency;

$bytes = Image::fromStorage($path, 's3')
    ->transform(new DecodeAtLeast(1600, 1600)) // a marker: libjpeg may decode at 1/2, 1/4 or 1/8 while still filling this box in one dimension
    ->orient()
    ->scale(800)
    ->transform(new KeepTransparency)          // a transparent image stays PNG
    ->toJpeg()
    ->toBytes();
```

Put `DecodeAtLeast` first and hint at least the size you scale to (twice it keeps resampling quality). It changes nothing on its own.

## What it handles

- **Itself:** `orient()`, `scale()` (never enlarges), JPEG (flattened on white) or PNG output, quality, and the kit's steps: `DecodeAtLeast`, `KeepTransparency`, `Progressive(bool)` and `CropToRatio($maxWidth, $ratio)` (centre-crop to width / height, at most `$maxWidth` wide, never wider than the image). With no output format, a JPEG or PNG keeps its own.
- **Handed on:** any other transformation (`cover()`, `blur()` and so on) or format (WebP, AVIF and so on) goes to the framework's Imagick driver, with the kit's markers removed. That driver needs `intervention/image`; without it `kit-imagick` throws `ImageException`. `CropToRatio` and custom handlers can't be handed on, so a pipeline mixing them with such a step is refused.

## Options

`kit.images` in `config/kit.php`. Every key is read with a default, so the block is optional.

| Key | Default | Meaning |
| --- | --- | --- |
| `default` | `true` | Take `images.default` while it is `gd`. |
| `memory_limit` | 256 MiB | Imagick pixel-cache bytes per image; beyond it Imagick pages to disk. Null leaves Imagick's own. |
| `strip_metadata` | `true` | Drop EXIF, comments and profiles from the output. |
| `progressive` | `null` | JPEG interlace: true progressive, false baseline, null keeps the source's. A `Progressive` step overrides it. |
| `crop_to_ratio` | `true` | Honour `CropToRatio`; false refuses it. |

## Custom transformations

`Image::transformUsing('kit-imagick', MyStep::class, fn (Imagick $image, MyStep $step) => ...)` works, but the handler receives the **raw `Imagick` object**, not an Intervention image, and must return it. Handlers match by `instanceof`, so a subclass of `MyStep` uses the same handler. A handler written for the framework's drivers won't work unchanged.
