<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/**
 * Where a user's avatar is: `/avatars/{token}` (and `/2x`), or null when they have none (the UI shows their initials). The avatar is a Media Library item on
 * the private media disk; the token is a random string kept in its custom properties, so the URL carries no user or media id, cannot be guessed, and a
 * replaced or removed avatar's old URL stops resolving.
 */
final class Avatars
{
    /**
     * Several users' avatars in one query (a list of people): user id => the same as `urls()`.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{url: string, url_2x: string}|null>
     */
    public static function urlsFor(array $userIds): array
    {
        $found = array_fill_keys($userIds, null);
        if ($userIds === []) {
            return $found;
        }
        $latest = [];
        foreach (Media::query()->where('model_type', (new UserAvatar)->getMorphClass())->whereIn('model_id', $userIds)->where('collection_name', 'avatar')->orderByDesc('id')->get() as $media) {
            $latest[(int) $media->model_id] ??= $media;
        }
        foreach ($latest as $id => $media) {
            $found[$id] = self::forMedia($media);
        }

        return $found;
    }

    /** @return array{url: string, url_2x: string}|null */
    public static function urls(int $userId): ?array
    {
        $media = Media::query()->where('model_type', (new UserAvatar)->getMorphClass())->where('model_id', $userId)->where('collection_name', 'avatar')->latest('id')->first();

        return $media === null ? null : self::forMedia($media);
    }

    /** @return array{url: string, url_2x: string} */
    private static function forMedia(Media $media): array
    {
        $token = $media->getCustomProperty('token');
        if (! is_string($token) || $token === '') {
            // An avatar from before tokens: give it one.
            $token = Str::random(40);
            $media->setCustomProperty('token', $token)->save();
        }

        return ['url' => '/avatars/'.$token, 'url_2x' => '/avatars/'.$token.'/2x'];
    }

    /** The avatar a token names, or null (unknown, replaced or removed). */
    public static function find(string $token): ?Media
    {
        return Media::query()->where('model_type', (new UserAvatar)->getMorphClass())->where('collection_name', 'avatar')->where('custom_properties->token', $token)->first();
    }
}
