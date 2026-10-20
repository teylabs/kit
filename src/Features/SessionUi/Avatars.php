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
    /** @return array{url: string, url_2x: string}|null */
    public static function urls(int $userId): ?array
    {
        $media = Media::query()->where('model_type', (new UserAvatar)->getMorphClass())->where('model_id', $userId)->where('collection_name', 'avatar')->latest('id')->first();
        if ($media === null) {
            return null;
        }
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
