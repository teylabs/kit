<?php

namespace Tey\Kit\Media\Images\Transformations;

use Illuminate\Contracts\Image\Transformation;

/** For the `kit-imagick` driver: an image with transparent pixels is encoded as PNG instead of the requested JPEG; an opaque one stays JPEG. */
final class KeepTransparency implements Transformation {}
