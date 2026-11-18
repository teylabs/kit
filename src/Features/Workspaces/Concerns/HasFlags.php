<?php

namespace Tey\Kit\Features\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\HasTags;
use Tey\Kit\Features\Workspaces\Flags\Flags;
use Tey\Kit\Features\Workspaces\Flags\Tag;
use Tey\Kit\Features\Workspaces\Flags\Taggable;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Flags and tags on a record, through spatie/laravel-tags on the kit's own tables (`kit_tags`, `kit_taggables`), whatever the app's `config/tags.php`
 * says. A flag is a tag of type `flag` the system raises: `$case->flag('possible-duplicate', reason: 'Looks like A-1042', link: $url)` where a match is
 * stored, `$case->flag('unpaid', when: $case->paid_at === null)` where payment changes (false removes it), `$case->unflag('stalled')` where a run is
 * retried. A flag someone dismissed ("Not a duplicate") is not raised again unless `force: true`, for a new and different reason. Query with
 * `Model::flagged('unpaid')` and `notFlagged()`; show with Tags::for() and the kit's TagList. Every other spatie HasTags method works for people's own
 * tags, which belong to the workspace they were made in.
 */
trait HasFlags
{
    use HasTags;

    public static function getTagClassName(): string
    {
        return Tag::class;
    }

    public function getTaggableTableName(): string
    {
        return 'kit_taggables';
    }

    public function getPivotModelClassName(): string
    {
        return Taggable::class;
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, $this->getTaggableMorphName(), $this->getTaggableTableName())
            ->using(Taggable::class)->withPivot(['meta', 'workspace_id'])->ordered();
    }

    /** Raises the flag (or, with `when: false`, removes it). Returns whether it is on afterwards: false when it was dismissed and not forced. */
    public function flag(string $key, ?string $reason = null, ?string $link = null, bool $when = true, bool $force = false): bool
    {
        $key = Flags::key($key);
        if (! $when) {
            $this->unflag($key);

            return false;
        }
        $dismissals = DB::table('kit_flag_dismissals')->where(['flaggable_type' => $this->getMorphClass(), 'flaggable_id' => $this->getKey(), 'flag' => $key]);
        if ($force) {
            $dismissals->delete();
        } elseif ($dismissals->exists()) {
            return false;
        }
        $tag = Tag::findOrCreate($key, Flags::TYPE);
        $this->tags()->syncWithoutDetaching([$tag->getKey() => ['meta' => array_filter(['reason' => $reason, 'link' => $link], fn (?string $value): bool => $value !== null), 'workspace_id' => $this->flagWorkspace()]]);
        $this->unsetRelation('tags');

        return true;
    }

    public function unflag(string $key): void
    {
        $tag = Tag::query()->withType(Flags::TYPE)->get()->first(fn (Tag $tag): bool => $tag->slug === Flags::key($key));
        if ($tag !== null) {
            $this->tags()->detach($tag->getKey());
        }
        $this->unsetRelation('tags');
    }

    public function flagged(string $key): bool
    {
        return $this->flags()->contains(fn (Tag $tag): bool => $tag->slug === Flags::key($key));
    }

    /** @return Collection<int, Tag> the record's flags, with their pivot `meta` */
    public function flags(): Collection
    {
        return $this->tags->filter(fn (Tag $tag): bool => $tag->type === Flags::TYPE)->values();
    }

    /** Removes the flag and records who dismissed it, so it is not raised again unless forced. Only a flag defined dismissible may be dismissed. */
    public function dismissFlag(string $key, ?int $by = null): void
    {
        $key = Flags::key($key);
        if (! Flags::definition($key)['dismissible']) {
            throw new \RuntimeException('flag_not_dismissible: '.$key);
        }
        DB::table('kit_flag_dismissals')->updateOrInsert(['flaggable_type' => $this->getMorphClass(), 'flaggable_id' => $this->getKey(), 'flag' => $key],
            ['workspace_id' => $this->flagWorkspace(), 'dismissed_by' => $by, 'dismissed_at' => now()]);
        $this->unflag($key);
    }

    /** @param  Builder<static>  $query */
    public function scopeFlagged(Builder $query, string ...$keys): Builder
    {
        return $query->whereHas('tags', fn (Builder $tags) => $tags->where('type', Flags::TYPE)->whereIn($tags->qualifyColumn('id'), self::flagTagIds($keys)));
    }

    /** @param  Builder<static>  $query */
    public function scopeNotFlagged(Builder $query, string ...$keys): Builder
    {
        return $query->whereDoesntHave('tags', fn (Builder $tags) => $tags->where('type', Flags::TYPE)->whereIn($tags->qualifyColumn('id'), self::flagTagIds($keys)));
    }

    /** @param  list<string>  $keys  @return list<int> */
    private static function flagTagIds(array $keys): array
    {
        $slugs = array_map(Flags::key(...), $keys);

        return Tag::query()->withType(Flags::TYPE)->get()->filter(fn (Tag $tag): bool => in_array($tag->slug, $slugs, true))->modelKeys();
    }

    private function flagWorkspace(): ?int
    {
        $own = $this->getAttribute('workspace_id');

        return $own !== null ? (int) $own : Workspaces::current()?->getKey();
    }
}
