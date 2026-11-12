<?php

namespace Tey\Kit\Media\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;
use InvalidArgumentException;

/** For the `kit-imagick` driver: centre-crops to `$ratio` (width / height) and scales to at most `$maxWidth` wide, never wider than the image. */
final class CropToRatio implements Transformation
{
    public function __construct(public readonly int $maxWidth, public readonly float $ratio)
    {
        if ($maxWidth < 1 || ! is_finite($ratio) || $ratio <= 0) {
            throw new InvalidArgumentException('CropToRatio needs a width of at least 1 and a finite ratio above 0.');
        }
    }
}
