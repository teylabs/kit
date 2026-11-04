<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\MediaCollection;

/**
 * Puts a record's collection in a new order: `$order` must name exactly the owner's items in that collection, each once (`media.order.mismatch` otherwise, so
 * an id from another record or collection is refused, never reordered), and `$version` must match MediaCollection::version (`media.order.stale`, answer 409).
 * Returns the new version. The app authorises the owner first; this checks only that the items are the owner's.
 *
 * @api
 */
class ReorderMedia
{
    use AsAction;

    /**
     * @param  HasMedia&Model  $owner
     * @param  list<string|int>  $order
     */
    public function handle(HasMedia $owner, string $collection, array $order, string $version): string
    {
        return DB::transaction(function () use ($owner, $collection, $order, $version): string {
            $items = MediaCollection::items($owner, $collection);
            if (MediaCollection::version($owner, $collection) !== $version) {
                throw new RuntimeException('media.order.stale');
            }
            $ids = array_map(fn (Media $item): string => (string) $item->getKey(), $items);
            $wanted = array_map('strval', $order);
            $sorted = $wanted;
            sort($sorted);
            sort($ids);
            if ($sorted !== $ids || count(array_unique($wanted)) !== count($wanted)) {
                throw new RuntimeException('media.order.mismatch');
            }
            foreach ($wanted as $position => $id) {
                Media::query()->whereKey($id)->update(['order_column' => $position + 1]);
            }

            return MediaCollection::version($owner, $collection);
        });
    }
}
