<?php

namespace Tey\Kit\Media\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/** For the `kit-imagick` driver: whether this JPEG is encoded progressive, over `kit.images.progressive`. */
final class Progressive implements Transformation
{
    public function __construct(public readonly bool $progressive) {}
}
