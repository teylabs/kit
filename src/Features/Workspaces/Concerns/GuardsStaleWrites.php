<?php

namespace Tey\Kit\Features\Workspaces\Concerns;

/**
 * Optimistic locking on `lock_version` (or the model's `LOCK_COLUMN`): an edit carries the version it was loaded at, and `updateIfCurrent()` writes only
 * while the row is still at that version, bumping it in the same statement. Two saves from the same version cannot both land: the second throws
 * `stale_write` and changes nothing. A new row starts at version 1.
 */
trait GuardsStaleWrites
{
    public static function bootGuardsStaleWrites(): void
    {
        static::creating(function ($model): void {
            if ($model->getAttribute(static::lockColumn()) === null) {
                $model->setAttribute(static::lockColumn(), 1);
            }
        });
    }

    public static function lockColumn(): string
    {
        return defined(static::class.'::LOCK_COLUMN') ? constant(static::class.'::LOCK_COLUMN') : 'lock_version';
    }

    /**
     * Writes `$attributes` only if the row is still at `$expected`, and moves it to the next version. Model events do not fire for this write (it is one
     * conditional UPDATE); the model's global scopes (its workspace) still apply. On success the model is refreshed; on a stale version nothing is written.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws \RuntimeException `stale_write`
     */
    public function updateIfCurrent(int $expected, array $attributes): static
    {
        $column = static::lockColumn();
        unset($attributes[$column]);
        if ($this->usesTimestamps() && $this->getUpdatedAtColumn() !== null) {
            $attributes[$this->getUpdatedAtColumn()] = $this->freshTimestampString();
        }
        $changed = $this->newQuery()->whereKey($this->getKey())->where($column, $expected)->update([...$attributes, $column => $expected + 1]);
        if ($changed !== 1) {
            throw new \RuntimeException('stale_write');
        }

        return $this->refresh();
    }
}
