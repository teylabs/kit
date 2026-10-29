<?php

namespace Tey\Kit\Media\Generators;

use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image;

/**
 * Media Library's raster generator, tolerant of a PNG with malformed metadata (a bad iCCP colour profile is the usual one): before GD loads it, the PNG's
 * iCCP, iTXt, zTXt and tEXt chunks are removed from a temporary copy beside the one Media Library made (PngChunks). The original object is never touched, and
 * a PNG that is corrupt beyond its metadata is passed on unchanged, so it still fails loudly (PerformConversions' none_generated). Replace `Image::class` with
 * this in `media-library.image_generators`; `kit.media.tolerant_png` false turns it off.
 */
class TolerantImage extends Image
{
    public function convert(string $path, ?Conversion $conversion = null): string
    {
        if (! config('kit.media.tolerant_png', true) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'png') {
            return $path;
        }
        $png = @file_get_contents($path);
        $clean = $png === false ? null : PngChunks::strip($png);
        if ($clean === null || $clean === $png) {
            return $path;
        }
        $copy = dirname($path).'/'.pathinfo($path, PATHINFO_FILENAME).'-tolerant.png';
        file_put_contents($copy, $clean);

        return $copy;
    }
}
