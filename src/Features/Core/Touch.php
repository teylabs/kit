<?php

namespace Tey\Kit\Features\Core;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The touched_at pattern (docs/touched-at.md): "something under this record changed", kept apart from `updated_at`, which stays the edit and version key.
 * A model using Concerns\Touches stamps its own `touched_at` when it is created or changed, and its save, create or delete touches the parents it names
 * in `$touchesParents`, each of which touches its own parents in turn. A parent is touched by a quiet update of `touched_at` alone (a base query on its
 * key: no model events, no `updated_at`, no version bump), and only when its table has a `touched_at` column (opt-in by schema). Each record is touched
 * once per request or job (keyed by class and key), and a cycle stops at the first record seen twice.
 */
final class Touch
{
    /** @var array<string, true> records already touched in this request or job, keyed by class and key */
    private static array $touched = [];

    /** @var array<string, bool> whether a table has a touched_at column, keyed by connection and table */
    private static array $columns = [];

    private static int $paused = 0;

    /** Runs $run with no touching at all (imports, backfills, migrations): nothing it saves stamps or cascades. */
    public static function without(Closure $run): mixed
    {
        self::$paused++;
        try {
            return $run();
        } finally {
            self::$paused--;
        }
    }

    /** Whether touching runs now: not inside without(), and under the console (commands, queue workers) only while `kit.touch.console` is on (the default). */
    public static function enabled(): bool
    {
        if (self::$paused > 0) {
            return false;
        }

        return ! app()->runningInConsole() || (bool) config('kit.touch.console', true);
    }

    /** Forgets which records were touched: the request, job or test that touched them is over. */
    public static function flush(): void
    {
        self::$touched = [];
        self::$columns = [];
    }

    public static function hasColumn(Model $model): bool
    {
        $key = $model->getConnectionName().'|'.$model->getTable();

        return self::$columns[$key] ??= Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'touched_at');
    }

    /** Stamps a model being saved (a new record, or one with changes) with its own touched_at, as part of that save. */
    public static function stamp(Model $model): void
    {
        if (! self::enabled() || ! self::hasColumn($model) || ($model->exists && ! $model->isDirty())) {
            return;
        }
        $model->setAttribute('touched_at', $at = Carbon::now());
        if (method_exists($model, 'alsoTouch')) {
            $model->alsoTouch($at);
        }
    }

    /** Touches $model's parents, and theirs, after it was saved or deleted. */
    public static function parents(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }
        $seen = [self::key($model) => true];
        // Stamped by its own save (a new record has its key only now): current for the rest of the request.
        if ($model->exists && self::hasColumn($model)) {
            self::$touched[self::key($model)] = true;
        }
        self::up($model, Carbon::now(), $seen);
    }

    /** Touches $model itself (quietly) and its parents: for an action that saves nothing, such as a reorder. */
    public static function tree(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }
        $seen = [];
        self::bump($model, Carbon::now(), $seen);
    }

    /** @param array<string, true> $seen */
    private static function bump(Model $model, DateTimeInterface $at, array &$seen): void
    {
        $key = self::key($model);
        // Seen in this cascade: a cycle. Touched earlier in this request: it and its parents are already current.
        if (isset($seen[$key]) || isset(self::$touched[$key])) {
            return;
        }
        $seen[$key] = true;
        if ($model->exists && self::hasColumn($model)) {
            $model->newQueryWithoutScopes()->toBase()->where($model->getKeyName(), $model->getKey())->update(['touched_at' => $at]);
            $model->setRawAttributes(['touched_at' => $model->fromDateTime($at)] + $model->getAttributes());
            $model->syncOriginalAttribute('touched_at');
            self::$touched[$key] = true;
            if (method_exists($model, 'alsoTouch')) {
                $model->alsoTouch($at);
            }
        }
        self::up($model, $at, $seen);
    }

    /** @param array<string, true> $seen */
    private static function up(Model $model, DateTimeInterface $at, array &$seen): void
    {
        foreach (method_exists($model, 'touchesParents') ? $model->touchesParents() : [] as $relation) {
            $parents = $model->relationLoaded($relation) ? $model->getRelation($relation) : $model->{$relation}()->getResults();
            foreach ($parents instanceof Model ? [$parents] : ($parents ?? []) as $parent) {
                self::bump($parent, $at, $seen);
            }
        }
    }

    private static function key(Model $model): string
    {
        return $model::class.':'.($model->getKey() ?? spl_object_id($model));
    }
}
