<?php

namespace Tey\Kit\Media\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/** For the `kit-imagick` driver: centre-crops to `$ratio` (width / height) and scales to at most `$maxWidth` wide, never wider than the image. */
final class CropToRatio implements Transformation
{
    public function __construct(public readonly int $maxWidth, public readonly float $ratio) {}
}
