<?php

namespace Tey\Kit\Features\Core\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Core\Touch;

/**
 * The touched_at pattern on a model (docs/touched-at.md). A child names the relations it touches, separately from Eloquent's `$touches`:
 *
 *     protected array $touchesParents = ['assignment'];
 *
 * Its create, save or delete touches each of those parents, and theirs. A model with a `touched_at` column stamps it on create and on a save with
 * changes; a parent is touched by a quiet update of `touched_at` alone. `updated_at` is never written. Touch::without(fn) turns it off for a block.
 *
 * @mixin Model
 */
trait Touches
{
    public static function bootTouches(): void
    {
        static::saving(static fn (Model $model) => Touch::stamp($model));
        // created and updated, not saved: a save with no changes writes nothing and touches nothing.
        static::created(static fn (Model $model) => Touch::parents($model));
        static::updated(static fn (Model $model) => Touch::parents($model));
        static::deleted(static fn (Model $model) => Touch::parents($model));
    }

    /** @return list<string> the relations whose records this one touches */
    public function touchesParents(): array
    {
        return property_exists($this, 'touchesParents') ? array_values($this->touchesParents) : [];
    }

    /** Touches this record and its parents without saving it: for an action that changes nothing on it, such as a reorder. */
    public function touchTree(): static
    {
        Touch::tree($this);

        return $this;
    }

    /** Override to keep denormalised copies in step (e.g. a pinned row carrying this record's touched_at); called with the new time when this record is touched. */
    public function alsoTouch(DateTimeInterface $at): void {}
}
