# Workspace-owned models

A model that one workspace owns uses two concerns from `Tey\Kit\Features\Workspaces\Concerns`. Both fail closed, and both keep the rule in one place instead of in each model.

```php
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Concerns\BelongsToWorkspace;
use Tey\Kit\Features\Workspaces\Concerns\GuardsStaleWrites;

final class Invoice extends Model
{
    use BelongsToWorkspace, GuardsStaleWrites;
}
```

## BelongsToWorkspace

The model is read and written only while its workspace is the current tenant. A workspace request (`/@{slug}`) and an MCP call already make it current, and so does a TenantAware job or command; anything else switches explicitly with `Workspaces::query()->findOrFail($id)->execute(function () { ... })`.

- **Reads.** Every query is scoped to the current workspace. Outside any workspace, a query throws `workspace_required`; it never quietly returns every tenant's rows.
- **Creates.** A new row takes the current workspace. If it names another workspace, it throws `workspace_mismatch`.
- **Updates and deletes.** A row from another workspace, or one being moved to another workspace, throws `workspace_mismatch`.
- **Column and relation.** The column is `workspace_id`; set a `WORKSPACE_COLUMN` constant to use another. `workspace()` is the relation.
- **Crossing workspaces.** An operator command opts out of the read scope explicitly with `withoutGlobalScope('workspace')`. It still cannot write outside the current workspace.

In the migration, constrain the column and say what happens when a workspace is deleted. Index it first in the indexes you query by, and add a unique `(workspace_id, id)` so that child tables can hold a composite foreign key to it, which keeps a child in its parent's workspace:

```php
$table->foreignId('workspace_id')->constrained()->restrictOnDelete();
$table->unique(['workspace_id', 'id']);
```

## GuardsStaleWrites

This is optimistic locking on `lock_version` (set a `LOCK_COLUMN` constant to use another column). A new row starts at version 1. An edit form sends back the version it loaded, and the write goes through `updateIfCurrent()`:

```php
$invoice->updateIfCurrent($request->integer('lock_version'), ['name' => $request->string('name')]);
```

It is one conditional `UPDATE … WHERE lock_version = ?` that also bumps the version, so of two saves from the same version only one lands. The other throws `stale_write`, which kit registers as a 409 and as an MCP refusal code, and writes nothing. Model events don't fire for this write; the workspace scope still applies.

## Coverage

`composer test:workspace-models` runs on SQLite memory with real Concierge and multitenancy. It checks:

- scoped reads, and the fail-closed read outside a workspace;
- the create, save, move and delete refusals;
- the stale-write refusal, including one made from another workspace;
- the error code.

Database-level constraints (the foreign key and any check constraints) belong to the app's migration. tey/standards' `standards:make model --tenant` generates them, with tests that run on the app's own database.
