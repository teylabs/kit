<?php

namespace Tey\Kit\Media\Images;

use Illuminate\Contracts\Image\Driver;
use Illuminate\Contracts\Image\Transformation;
use Illuminate\Image\Drivers\ImagickDriver;
use Illuminate\Image\ImageException;
use Illuminate\Image\ImageOutputOptions;
use Illuminate\Image\ImagePipeline;
use Illuminate\Image\Transformations\Orient;
use Illuminate\Image\Transformations\Scale;
use Imagick;
use Intervention\Image\ImageManager as InterventionManager;
use Tey\Kit\Media\Images\Transformations\CropToRatio;
use Tey\Kit\Media\Images\Transformations\DecodeAtLeast;
use Tey\Kit\Media\Images\Transformations\KeepTransparency;
use Tey\Kit\Media\Images\Transformations\Progressive;

/**
 * The `kit-imagick` image driver (docs/images.md): Laravel's image API on raw Imagick, size-aware.
 *
 * The framework's Imagick driver decodes every image at full size before any transformation runs. This one reads the pipeline first, so a
 * DecodeAtLeast step sets libjpeg's shrink-on-load hint (`jpeg:size`) before the read, and each image is held under `kit.images.memory_limit`.
 * Only a multi-frame image (an animated GIF) is coalesced; a single frame is used as decoded. On a 63 MP JPEG that takes the peak from about
 * 3.3 GB (no hint, coalesced) to about 0.9 GB (hinted) and about 0.4 GB (hinted, single frame not coalesced), with the same output bytes.
 *
 * It handles Orient, Scale (never enlarges), CropToRatio and the markers itself and encodes JPEG (on white) or PNG. KeepTransparency writes PNG
 * for an image with transparent pixels. Any other transformation or format goes to the framework's Imagick driver (which needs
 * intervention/image) with the markers removed. A custom handler (transformUsing) receives the raw Imagick object, not an Intervention image,
 * and returns it.
 */
final class SizedImagickDriver implements Driver
{
    public const NAME = 'kit-imagick';

    /** @var array<class-string<Transformation>, callable(Imagick, Transformation): Imagick> */
    private array $handlers = [];

    /**
     * @param  int|null  $memoryLimit  Imagick pixel-cache bytes per image; beyond it Imagick pages to disk. Null leaves Imagick's own limit.
     * @param  bool|null  $progressive  JPEG interlace when no Progressive step is given; null keeps the decoded image's own.
     */
    public function __construct(
        private readonly ?int $memoryLimit = 256 * 1024 * 1024,
        private readonly bool $stripMetadata = true,
        private readonly ?bool $progressive = null,
        private readonly bool $cropToRatio = true,
    ) {}

    /** From `kit.images` (every key optional). */
    public static function fromConfig(): self
    {
        $limit = config('kit.images.memory_limit', 256 * 1024 * 1024);
        $progressive = config('kit.images.progressive');

        return new self(
            $limit === null ? null : (int) $limit,
            (bool) config('kit.images.strip_metadata', true),
            $progressive === null ? null : (bool) $progressive,
            (bool) config('kit.images.crop_to_ratio', true),
        );
    }

    /** Whether the decoded image needs coalescing: only when it has more than one frame. */
    public static function needsCoalesce(Imagick $image): bool
    {
        return $image->getNumberImages() > 1;
    }

    public function process(string $contents, ImagePipeline $pipeline): string
    {
        $format = $pipeline->output->format ?? $this->sourceFormat($contents);
        if (! in_array($format, ['jpg', 'jpeg', 'png'], true) || ! $this->handlesAll($pipeline)) {
            return $this->delegate($contents, $pipeline);
        }
        $image = new Imagick;
        if ($this->memoryLimit !== null) {
            $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $this->memoryLimit);
        }
        $keepTransparency = false;
        $progressive = $this->progressive;
        foreach ($pipeline->transformations as $transformation) {
            if ($transformation instanceof DecodeAtLeast) {
                $image->setOption('jpeg:size', $transformation->width.'x'.$transformation->height);
            } elseif ($transformation instanceof KeepTransparency) {
                $keepTransparency = true;
            } elseif ($transformation instanceof Progressive) {
                $progressive = $transformation->progressive;
            }
        }
        try {
            $image->readImageBlob($contents);
            if (self::needsCoalesce($image)) {
                $coalesced = $image->coalesceImages();
                $image->clear();
                $image = $coalesced;
            }
            $image->setFirstIterator();
            $frames = $image;
            $image = $frames->getImage();
            $frames->clear();
            foreach ($pipeline->transformations as $transformation) {
                $image = $this->apply($image, $transformation);
            }
            if ($this->stripMetadata) {
                $image->stripImage();
            }
            $opaque = ! $image->getImageAlphaChannel() || $image->getImageChannelRange(Imagick::CHANNEL_ALPHA)['minima'] >= Imagick::getQuantum();
            if ($format === 'png' || ($keepTransparency && ! $opaque)) {
                $image->setImageFormat('png');
            } else {
                $image->setImageBackgroundColor('white');
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality($pipeline->output->quality ?? ImageOutputOptions::DEFAULT_QUALITY);
                if ($progressive !== null) {
                    $image->setInterlaceScheme($progressive ? Imagick::INTERLACE_PLANE : Imagick::INTERLACE_NO);
                }
            }

            return $image->getImageBlob();
        } finally {
            $image->clear();
        }
    }

    private function sourceFormat(string $contents): ?string
    {
        $image = new Imagick;
        try {
            $image->pingImageBlob($contents);

            return match (strtolower($image->getImageFormat())) {
                'jpeg', 'jpg' => 'jpeg',
                'png', 'png8', 'png24', 'png32', 'png48', 'png64' => 'png',
                default => null,
            };
        } finally {
            $image->clear();
        }
    }

    private function handlesAll(ImagePipeline $pipeline): bool
    {
        foreach ($pipeline->transformations as $transformation) {
            if (! isset($this->handlers[$transformation::class]) && ! $this->isOwn($transformation)) {
                return false;
            }
        }

        return true;
    }

    private function isOwn(Transformation $transformation): bool
    {
        return $transformation instanceof Orient || $transformation instanceof Scale || $this->isMarker($transformation)
            || ($this->cropToRatio && $transformation instanceof CropToRatio);
    }

    private function isMarker(Transformation $transformation): bool
    {
        return $transformation instanceof DecodeAtLeast || $transformation instanceof KeepTransparency || $transformation instanceof Progressive;
    }

    /** Anything this driver doesn't encode or transform itself goes to the framework's Imagick driver, without the kit's markers. */
    private function delegate(string $contents, ImagePipeline $pipeline): string
    {
        foreach ($pipeline->transformations as $transformation) {
            if ($transformation instanceof CropToRatio || isset($this->handlers[$transformation::class])) {
                throw new ImageException('The kit-imagick driver can apply ['.$transformation::class.'] only for JPEG or PNG output alongside its own transformations.');
            }
        }
        if (! class_exists(InterventionManager::class)) {
            throw new ImageException('The kit-imagick driver hands this pipeline to the framework Imagick driver, which needs intervention/image.');
        }
        $plain = clone $pipeline;
        $plain->transformations = array_values(array_filter($pipeline->transformations, fn (Transformation $t): bool => ! $this->isMarker($t)));

        return (new ImagickDriver)->process($contents, $plain);
    }

    private function apply(Imagick $image, Transformation $transformation): Imagick
    {
        if (isset($this->handlers[$transformation::class])) {
            return ($this->handlers[$transformation::class])($image, $transformation);
        }
        if ($transformation instanceof Orient) {
            $image->autoOrient();
        } elseif ($transformation instanceof Scale) {
            $this->scaleDown($image, $transformation->width, $transformation->height);
        } elseif ($transformation instanceof CropToRatio) {
            $width = min($transformation->maxWidth, $image->getImageWidth());
            $image->cropThumbnailImage($width, (int) round($width / $transformation->ratio));
            $image->setImagePage(0, 0, 0, 0);
        }

        return $image;
    }

    /** Scales to fit within the given width and/or height, never enlarging. */
    private function scaleDown(Imagick $image, ?int $width, ?int $height): void
    {
        if ($width !== null && $height === null) {
            if ($image->getImageWidth() > $width) {
                $image->thumbnailImage($width, 0);
            }

            return;
        }
        if ($width === null && $height !== null) {
            if ($image->getImageHeight() > $height) {
                $image->thumbnailImage(0, $height);
            }

            return;
        }
        if ($width !== null && $height !== null && ($image->getImageWidth() > $width || $image->getImageHeight() > $height)) {
            $image->thumbnailImage($width, $height, true);
        }
    }

    /** @return array{0: int, 1: int} */
    public function dimensions(string $contents): array
    {
        $image = new Imagick;
        try {
            $image->pingImageBlob($contents);

            return [$image->getImageWidth(), $image->getImageHeight()];
        } finally {
            $image->clear();
        }
    }

    public function dominantColor(string $contents): string
    {
        $image = new Imagick;
        try {
            $image->readImageBlob($contents);
            $image->resizeImage(1, 1, Imagick::FILTER_BOX, 1);
            $color = $image->getImagePixelColor(0, 0)->getColor();

            return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
        } finally {
            $image->clear();
        }
    }

    /** Registers a handler for a custom transformation. It receives the raw Imagick object (not an Intervention image) and returns it. */
    public function transformUsing(string $transformation, callable $callback): static
    {
        $this->handlers[$transformation] = $callback;

        return $this;
    }
}
