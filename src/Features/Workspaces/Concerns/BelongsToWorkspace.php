<?php

namespace Tey\Kit\Features\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * A model one workspace owns, read and written only while that workspace is the current tenant (a workspace request, an MCP call, a TenantAware job or command, or `Workspaces::run()`). It fails closed:
 * - every query is scoped to the current workspace, and outside any workspace it throws `workspace_required` instead of reading every tenant's rows;
 * - a new row takes the current workspace; one that names another workspace throws `workspace_mismatch`;
 * - saving or deleting a row from another workspace, or moving one to another workspace, throws `workspace_mismatch`.
 * The column is `workspace_id`, or the model's `WORKSPACE_COLUMN` constant. Code that must cross workspaces (an operator command) opts out explicitly
 * with `withoutGlobalScope(BelongsToWorkspace::SCOPE)`, and still cannot write a row outside the current workspace.
 * Route model binding outside any workspace finds nothing (a 404) instead of throwing: there is no workspace to look in, and a URL is not a query the app
 * chose to run. Inside a workspace another workspace's row is a 404 through the scope. Every other query outside a workspace still throws.
 */
trait BelongsToWorkspace
{
    /** The global scope's name: `withoutGlobalScope(BelongsToWorkspace::SCOPE)` is the one opt-out, and the name stays this. */
    public const SCOPE = 'workspace';

    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(self::SCOPE, fn (Builder $query) => $query->where($query->qualifyColumn(static::workspaceColumn()), Workspaces::required()->getKey()));
        static::creating(function ($model): void {
            $current = (int) Workspaces::required()->getKey();
            $named = $model->getAttribute(static::workspaceColumn());
            if ($named !== null && (int) $named !== $current) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $model->setAttribute(static::workspaceColumn(), $current);
        });
        static::updating(fn ($model) => $model->assertOwnedByCurrentWorkspace());
        static::deleting(fn ($model) => $model->assertOwnedByCurrentWorkspace());
    }

    /** No current workspace: nothing to bind (a 404), never workspace_required (a 500). Inside one, the scoped query decides. */
    public function resolveRouteBinding($value, $field = null)
    {
        return Workspaces::current() === null ? null : parent::resolveRouteBinding($value, $field);
    }

    /** The same for a child binding (`/projects/{project}/tickets/{ticket:slug}`), whichever side owns the workspace. */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return Workspaces::current() === null ? null : parent::resolveChildRouteBinding($childType, $value, $field);
    }

    public static function workspaceColumn(): string
    {
        return defined(static::class.'::WORKSPACE_COLUMN') ? constant(static::class.'::WORKSPACE_COLUMN') : 'workspace_id';
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspaces::model(), static::workspaceColumn());
    }

    /** The row as stored and as it would be saved must both belong to the current workspace. */
    protected function assertOwnedByCurrentWorkspace(): void
    {
        $current = (int) Workspaces::required()->getKey();
        $column = static::workspaceColumn();
        if ((int) $this->getOriginal($column) !== $current || (int) $this->getAttribute($column) !== $current) {
            throw new \RuntimeException('workspace_mismatch');
        }
    }
}
