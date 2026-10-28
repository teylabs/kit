<?php

namespace Tey\Kit\Media\Jobs;

use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Conversions for one item: queued when it is created, and regenerated idempotently (only the missing ones) on demand.
 */
final class Conversions
{
    public static function queue(): string
    {
        return (string) config('kit.media.queue', 'media');
    }

    /** Queues the item's conversions that are not yet generated; nothing when all exist. */
    public static function regenerate(Media $media): void
    {
        app(FileManipulator::class)->createDerivedFiles($media, onlyMissing: true, queueAll: true);
    }
}
