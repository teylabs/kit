<?php

namespace Tey\Kit\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Deleting a Media item over a key never deletes the object: only the item's own conversions and responsive images go. The object is shared or
 * externally owned, and only the app's reference-counted pruner removes it. Any other item is removed as usual. Set it as
 * `media-library.file_remover_class`.
 */
class KeptFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        if (! KeyPathGenerator::keyed($media)) {
            parent::removeAllFiles($media);

            return;
        }
        $paths = PathGeneratorFactory::create($media);
        $disk = $this->filesystem->disk($media->conversions_disk ?: $media->disk);
        $disk->deleteDirectory($paths->getPathForConversions($media));
        $disk->deleteDirectory($paths->getPathForResponsiveImages($media));
    }
}
