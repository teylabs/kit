<?php

namespace Tey\Kit\Features\Workspaces\Flags;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * The `tags` a page shows for its records, for the kit's TagList: each record's flags first (the most severe first, then in the order they were defined),
 * then its people's tags (in their own order). One query loads every record's tags, however many rows the page has.
 */
final class Tags
{
    /**
     * @param  iterable<Model>|Model  $records  models using HasFlags
     * @return array<int|string, list<TagItemData>> record key => its tags; for one model, keyed by its key too
     */
    public static function for(iterable|Model $records): array
    {
        $models = new EloquentCollection($records instanceof Model ? [$records] : (is_array($records) ? $records : iterator_to_array($records)));
        $models->loadMissing('tags');
        $tags = [];
        foreach ($models as $model) {
            $tags[$model->getKey()] = self::of($model);
        }

        return $tags;
    }

    /** @return list<TagItemData> */
    public static function of(Model $record): array
    {
        $flags = [];
        $own = [];
        foreach ($record->getRelationValue('tags') ?? [] as $tag) {
            if ($tag->type === Flags::TYPE) {
                $definition = Flags::definition((string) $tag->slug);
                $meta = $tag->pivot?->meta ?? [];
                $meta = is_string($meta) ? (array) json_decode($meta, true) : (array) $meta;
                $flags[] = [$definition, new TagItemData($definition['key'], $definition['label'], 'flag', $definition['tone'], $meta['reason'] ?? null, $meta['link'] ?? null)];
            } else {
                $own[] = new TagItemData('tag:'.$tag->slug, (string) $tag->name, 'tag');
            }
        }
        usort($flags, fn (array $a, array $b): int => [self::severity($a[0]['tone']), $a[0]['order']] <=> [self::severity($b[0]['tone']), $b[0]['order']]);

        return [...array_column($flags, 1), ...$own];
    }

    private static function severity(string $tone): int
    {
        $at = array_search($tone, Flags::SEVERITY, true);

        return $at === false ? count(Flags::SEVERITY) : $at;
    }
}
