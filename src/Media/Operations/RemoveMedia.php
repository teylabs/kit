<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

/**
 * Removes one of a record's items: `media.remove.not_found` for an item that isn't the owner's. The object stays in the bucket (KeptFileRemover); only this item
 * and its own conversions go. The app authorises the owner first.
 *
 * @api
 */
class RemoveMedia
{
    use AsAction;

    /** @param  HasMedia&Model  $owner */
    public function handle(HasMedia $owner, string|int $mediaId): void
    {
        $media = $owner->media()->getQuery()->whereKey($mediaId)->first() ?? throw new RuntimeException('media.remove.not_found');
        $media->delete();
    }
}
