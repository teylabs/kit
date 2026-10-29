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

The model is read and written only inside its workspace's tenant context (`TenantBoundary::run()`, which every workspace request and MCP call already uses).

- **Reads.** Every query is scoped to the current workspace. Outside any workspace, a query throws `workspace_required`; it never quietly returns every tenant's rows.
- **Creates.** A new row takes the current workspace. If it names another workspace, it throws `workspace_mismatch`.
- **Updates and deletes.** A row from another workspace, or one being moved to another workspace, throws `workspace_mismatch`.
- **Column and relation.** The column is `workspace_id`; set a `WORKSPACE_COLUMN` constant to use another. `workspace()` is the relation.
- **Crossing workspaces.** An operator command opts out of the read scope explicitly with `withoutGlobalScope('workspace')`. It still cannot write outside the current workspace.
- **Moving a row to another workspace.** `Tey\Kit\Features\Workspaces\Operations\MoveToWorkspace::run($row, $target, $principal)` moves one row from the current workspace to another and keeps its id, so rows that reference it stay attached. Use it to re-bind an app to a new workspace instead of deleting and re-creating it. It refuses:
  - a row that isn't the current workspace's (`workspace_mismatch`);
  - the same or a missing target (`workspace_same`, `workspace_not_found`);
  - an actor without the move ability in BOTH workspaces (a 403 with Access's code, such as `permission_denied`). The ability is `kit.workspaces.move_ability`, by default `members.manage` (owners and admins), and is checked through Access in each workspace;
  - a row changed concurrently: the update matches only `where workspace_id = source`, so a concurrent move updates 0 rows, throws `workspace_mismatch` and rolls back.

  It rewrites the column with one guarded query, so there's no bypass of the updating guard: an ordinary `save()` still can't move a row. In the same transaction it audits `workspace.row.moved` (from, to, model, id) in the source workspace.

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

## When a workspace is deleted

Each kit table with a `workspace_id` key chooses what happens to its rows when the workspace row is deleted:

| Table | Feature | On delete | Why |
| --- | --- | --- | --- |
| `entitlements` | access | restrict | Commercial records: deleting a workspace that has them is a deliberate act. |
| `complimentary_grants` | access | restrict | Commercial records, as above. |
| `kit_capability_overrides` | access | cascade | Settings of the workspace; they mean nothing without it. |
| `kit_entitlements` | access | cascade | The workspace's derived capabilities. |
| `audit_events` | workspaces | set null | History outlives the workspace; `workspace_name` still says which one it was. |
| `connections` | mcp | restrict | |
| `kit_notes`, `kit_note_intents` | notes | restrict | |
| `settings_intents` | settings | restrict | |

A restrict key makes the database refuse the delete while rows remain, so code that deletes a workspace removes or archives them first, in order, inside the same transaction.

## Coverage

`composer test:workspace-models` runs on SQLite memory with real Concierge and multitenancy. It checks:

- scoped reads, and the fail-closed read outside a workspace;
- the create, save, move and delete refusals;
- the stale-write refusal, including one made from another workspace;
- the error code.

Database-level constraints (the foreign key and any check constraints) belong to the app's migration. tey/standards' `standards:make model --tenant` generates them, with tests that run on the app's own database.
