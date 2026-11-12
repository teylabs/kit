<?php

namespace Tey\Kit\Media\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;
use InvalidArgumentException;

/**
 * A read hint for the `kit-imagick` driver: libjpeg may decode a JPEG at a reduced scale (shrink-on-load, `jpeg:size`: 1/2, 1/4 or 1/8)
 * as long as the decoded image still fills this box in one dimension; a 4000 x 3000 JPEG hinted 500 x 500 decodes at 500 x 375. The driver sets it before the bytes are read; it changes nothing on its own, so add a Scale or CropToRatio
 * after it. Other drivers don't know it.
 */
final class DecodeAtLeast implements Transformation
{
    public function __construct(public readonly int $width, public readonly int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('DecodeAtLeast needs a width and height of at least 1.');
        }
    }
}
