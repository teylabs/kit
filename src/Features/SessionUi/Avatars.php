<?php

namespace Tey\Kit\Features\SessionUi;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/** Where a user's avatar is: the square thumbnail's URL (and its 2x), or null when they have none (the UI shows their initials). */
final class Avatars
{
    /** @return array{url: string, url_2x: string}|null */
    public static function urls(int $userId): ?array
    {
        $media = Media::query()->where('model_type', (new UserAvatar)->getMorphClass())->where('model_id', $userId)->where('collection_name', 'avatar')->latest('id')->first();
        if ($media === null) {
            return null;
        }

        return ['url' => self::url($media, 'thumb'), 'url_2x' => self::url($media, 'thumb2x')];
    }

    private static function url(Media $media, string $conversion): string
    {
        // A private disk (a bucket) gets a short-lived signed URL; a public one a plain URL. Either changes when the file is replaced (a new media row, a new path).
        $private = config('filesystems.disks.'.$media->conversions_disk.'.visibility', config('filesystems.disks.'.$media->disk.'.visibility')) !== 'public' && config('filesystems.disks.'.$media->disk.'.driver') === 's3';

        return $private ? $media->getTemporaryUrl(now()->addMinutes(30), $conversion) : $media->getUrl($conversion).'?v='.$media->updated_at?->timestamp;
    }
}
