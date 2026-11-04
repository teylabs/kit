<?php

namespace Tey\Kit\Media;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One record's collection as the page shows it (docs/media.md, "A record's collection"): its items in order, each with the app's own delivery URLs (never a
 * bucket key), and a version token over the order, which ReorderMedia and MoveMedia check so two tabs never silently reorder over each other.
 *
 * @api
 */
final class MediaCollection
{
    /**
     * @param  HasMedia&Model  $owner
     * @param  (Closure(Media, string): string)|null  $url  the app's delivery route for an item and a conversion ('' for the original)
     * @return array{collection: string, version: string, items: list<array{id: string, name: string, mime: string, size: int, src: string, srcset: string, width: ?int, height: ?int}>}
     */
    public static function for(HasMedia $owner, string $collection, ?Closure $url = null): array
    {
        $media = self::items($owner, $collection);

        return [
            'collection' => $collection,
            'version' => self::versionOf($media),
            'items' => array_map(fn (Media $item): array => [
                'id' => (string) $item->getKey(),
                'name' => (string) $item->name,
                'mime' => (string) $item->mime_type,
                'size' => (int) $item->size,
                ...Srcset::for($item, $url),
            ], $media),
        ];
    }

    /** @param  HasMedia&Model  $owner */
    public static function version(HasMedia $owner, string $collection): string
    {
        return self::versionOf(self::items($owner, $collection));
    }

    /**
     * The owner's items in a collection, in order. Read fresh from the database, never from a loaded relation, so a check never trusts a stale copy.
     *
     * @param  HasMedia&Model  $owner
     * @return list<Media>
     */
    public static function items(HasMedia $owner, string $collection): array
    {
        return array_values($owner->media()->getQuery()->where('collection_name', $collection)->orderBy('order_column')->orderBy('id')->get()->all());
    }

    /** @param  list<Media>  $media */
    private static function versionOf(array $media): string
    {
        return substr(hash('sha256', implode(',', array_map(fn (Media $item): string => $item->getKey().':'.$item->order_column, $media))), 0, 16);
    }
}
