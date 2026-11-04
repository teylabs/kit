<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\MediaCollection;
use Tey\Kit\Media\MediaRules;

/**
 * Moves one of a record's items to another of its collections, last in order, with no byte copy: the same object, a new collection, and the target's conversions
 * queued. Refused, fail closed: an item that isn't the owner's (`media.move.not_found`), a stale source `$version` (`media.move.stale`, answer 409), a type the
 * target's rules don't accept (`media.move.type`), or a single-file target that already holds an item (`media.move.full`) unless `$replace`, which removes that
 * item first. Returns the moved item.
 *
 * @api
 */
class MoveMedia
{
    use AsAction;

    /** @param  HasMedia&Model  $owner */
    public function handle(HasMedia $owner, string|int $mediaId, string $to, string $version, ?MediaRules $rules = null, bool $single = false, bool $replace = false): Media
    {
        $media = DB::transaction(function () use ($owner, $mediaId, $to, $version, $rules, $single, $replace): Media {
            /** @var Media|null $media */
            $media = $owner->media()->getQuery()->whereKey($mediaId)->first();
            if ($media === null) {
                throw new RuntimeException('media.move.not_found');
            }
            $from = (string) $media->collection_name;
            if (MediaCollection::version($owner, $from) !== $version) {
                throw new RuntimeException('media.move.stale');
            }
            if ($from === $to) {
                return $media;
            }
            if ($rules !== null && ! $rules->accepts((string) $media->mime_type)) {
                throw new RuntimeException('media.move.type');
            }
            $occupants = MediaCollection::items($owner, $to);
            if ($single && $occupants !== []) {
                if (! $replace) {
                    throw new RuntimeException('media.move.full');
                }
                foreach ($occupants as $occupant) {
                    $occupant->delete();
                }
                $occupants = [];
            }
            $last = $occupants === [] ? 0 : (int) max(array_map(fn (Media $item): int => (int) $item->order_column, $occupants));
            $media->forceFill(['collection_name' => $to, 'order_column' => $last + 1])->save();

            return $media;
        });
        // The target collection's conversions differ; queue them, as AttachUpload does.
        app(FileManipulator::class)->createDerivedFiles($media);

        return $media;
    }
}
