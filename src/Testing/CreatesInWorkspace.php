<?php

namespace Tey\Kit\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * For the factory of a model that uses BelongsToWorkspace. The workspace column defaults to the current workspace, and with none current and none
 * given the factory throws `workspace_required` (fail closed). Each row is saved, and its afterCreating callbacks run, inside that row's own
 * workspace (`Workspaces::run()`), so a row given another workspace is created there and the current tenant is restored after.
 */
trait CreatesInWorkspace
{
    protected function makeInstance(?Model $parent)
    {
        $model = parent::makeInstance($parent);
        $column = $model::workspaceColumn();
        if ($model->getAttribute($column) === null) {
            $model->setAttribute($column, Workspaces::current()?->getKey() ?? throw new RuntimeException('workspace_required'));
        }

        return $model;
    }

    protected function store(Collection $results)
    {
        $results->each(function (Model $model): void {
            Workspaces::run((int) $model->getAttribute($model::workspaceColumn()), function () use ($model): void {
                parent::store(new Collection([$model]));
            });
        });
    }

    protected function callAfterCreating(Collection $instances, ?Model $parent = null)
    {
        $instances->each(function (Model $model) use ($parent): void {
            Workspaces::run((int) $model->getAttribute($model::workspaceColumn()), function () use ($model, $parent): void {
                parent::callAfterCreating(new Collection([$model]), $parent);
            });
        });
    }
}
