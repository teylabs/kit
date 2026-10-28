<?php

namespace Tey\Kit\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Media Library paths for items over an existing key (an attached upload or an adopted object): the original is the key itself, and each item's own
 * conversions sit beside it under `conversions/<uuid>/`. Any other item takes the default paths. Set it as `media-library.path_generator`.
 */
class KeyPathGenerator implements PathGenerator
{
    /** The custom property that holds the object's key; its presence means the item does not own the object. */
    public const KEY = 'kit_key';

    public const UPLOAD = 'kit_upload';

    public function __construct(private readonly PathGenerator $fallback = new DefaultPathGenerator) {}

    public static function keyed(Media $media): bool
    {
        return is_string($media->getCustomProperty(self::KEY));
    }

    public function getPath(Media $media): string
    {
        return self::keyed($media) ? $this->directory($media).'/' : $this->fallback->getPath($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return self::keyed($media) ? $this->directory($media).'/conversions/'.$media->uuid.'/' : $this->fallback->getPathForConversions($media);
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return self::keyed($media) ? $this->directory($media).'/responsive/'.$media->uuid.'/' : $this->fallback->getPathForResponsiveImages($media);
    }

    private function directory(Media $media): string
    {
        $directory = dirname((string) $media->getCustomProperty(self::KEY));

        return $directory === '.' ? '' : $directory;
    }
}
