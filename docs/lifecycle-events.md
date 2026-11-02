# Ownership transfer, deletion and lifecycle events

workspace-ui adds three owner and account actions: transferring a workspace's ownership, deleting a workspace, and deleting your own account. Each deletion dispatches a pair of events so a host can clear its own data.

## Deletion is off until you turn it on

Both deletions are **off by default**. While off, their routes return 404, the settings pages don't list them, and `kit:status` reports them as off. Turning one on takes two things:

1. The flag: `kit.lifecycle.workspace_delete` (`KIT_WORKSPACE_DELETE=true`) or `kit.lifecycle.account_delete` (`KIT_ACCOUNT_DELETE=true`).
2. A bound purge handler for your own data: `Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData::purge(Workspace $workspace)` or `Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData::purge(Model $user)`.

```php
// AppServiceProvider::register()
$this->app->bind(PurgesWorkspaceData::class, PurgeWorkspace::class);
$this->app->bind(PurgesAccountData::class, PurgeAccount::class);
```

The handler runs inside the deletion's transaction, before the kit's own rows go, so it can still read them. If it throws, the whole deletion rolls back. If you have nothing of your own to purge, bind a handler that does nothing, explicitly: the kit never assumes it.

With the flag on and no handler bound, the deletion refuses with a configuration error and deletes nothing, and `kit:status` flags the step as unmet.

## Transfer ownership

Settings › People offers **Make owner** on a member's row to the workspace's owner. It opens the Transfer ownership modal (`Workspace/Settings/People/Transfer/Page`, at `GET /@{workspace}/settings/people/members/{membership}/transfer`), which sends `PUT /@{workspace}/settings/ownership` with `membership`, `password` and `confirmation` (the workspace's name, typed). Concierge's `TransferOwnership` makes the transfer: the recipient becomes the owner and the old owner is demoted to `concierge.transfer_demotes_to` (`admin` by default).

- Only the owner may. An admin or a member gets 403, and someone who isn't an active member of the workspace gets 404.
- The recipient must be an active, verified member other than the owner. A scoped guest (Concierge 0.9's guest-only membership) is never offered and is refused on the server too, whenever the installed Concierge has `isScopedGuest()`. On Concierge 0.8 there are no scoped guests.
- The server checks the typed name and the password. A mismatch gets a 422 with the field's error, and nothing changes.

## Delete a workspace

Settings › Delete workspace (`Workspace/Settings/Delete/Page`, at `GET /@{workspace}/settings/delete`, listed in a "Danger zone" group) sends `DELETE /@{workspace}/settings/delete` with `confirmation`, the workspace's name typed. Only the owner may: an admin or a member gets 403, a non-member 404. Concierge has no deletion action, so `Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace` deletes the workspace in one transaction, with:

- its Concierge rows (memberships, the ownership, invitations and tenant invitations)
- spatie's team-scoped role and permission rows
- its settings group (`workspace.{id}`) and settings intents
- the access rows (entitlements, complimentary grants, capability overrides). `entitlements` and `complimentary_grants` are commercial records on RESTRICT foreign keys, so they're deleted explicitly before the workspace row.
- per-workspace user state
- MCP connections and their intents
- notes and note intents.

Each feature's rows are deleted explicitly, children first, before the workspace row, because notes, mcp `connections`, `settings_intents`, `entitlements` and `complimentary_grants` are on RESTRICT foreign keys. Tables the host didn't migrate are skipped. `audit_events` rows are never deleted: the audit trail outlives the workspace, and the table's foreign key decides what happens to `workspace_id`. The workspace's own `delete()` runs last, so a host model with soft deletes keeps its row.

Your own tables that reference the workspace are yours to clear, in your `PurgesWorkspaceData` handler (inside the same transaction, first). `WorkspaceDeleting` listeners and `WorkspaceDeleted` stay available for anything else.

## Delete your account

`/me/delete` (`Workspace/Account/Delete/Page`, under Account in the "Danger zone" group) sends `DELETE /me/delete` with your current `password`.

- **Sole-owner rule.** While you own any workspace, the deletion is refused with a 422 that names those workspaces, and the page lists them. Transfer ownership or delete each one first. Concierge keeps exactly one owner per workspace, so its owner is always its sole owner.
- **What it does otherwise.** Each membership that is still open (active or suspended) is ended through Concierge's `LeaveWorkspace`, which clears your roles there and dispatches `MembershipRemoved`. Then these rows are deleted with the user row, in one transaction:
  - your membership rows, including removed ones
  - your Concierge account row
  - your role rows
  - your capability overrides, user state and security events
  - your MCP connections.

  Your `PurgesAccountData` handler runs in the same transaction, after the workspaces are left and before these rows go.
- **Afterwards** you're signed out, the session is invalidated and its CSRF token regenerated.

## Deferred deletion

The default deletion runs your purge handler inside one transaction, under the workspace lock, in the request. For a large workspace that holds a long lock, and a stored file you delete mid-transaction can't come back if a later row delete rolls the deletion back. Deferred deletion fixes both. It's opt-in: a handler that doesn't opt in keeps the synchronous behaviour above, unchanged.

**Opt in** by having your bound handler also implement the deferral contract. `begin()` queues your job. It runs after the kit's transaction has committed, outside it. The kit never calls `purge()` in this mode.

```php
use Tey\Concierge\Contracts\Workspace;
use Tey\Kit\Features\Workspaces\Contracts\DefersWorkspacePurge;

final class PurgeWorkspace implements DefersWorkspacePurge
{
    public function purge(Workspace $workspace): void {} // not called when deferring

    public function begin(Workspace $workspace): void
    {
        PurgeWorkspaceJob::dispatch((int) $workspace->getKey());
    }
}
```

**Closed at once.** In a short transaction under the lock, the kit sets `deleting_at` and `deleting_by` on the workspace, dispatches `WorkspaceDeleting` and writes a `workspace.deletion_started` audit row. Nothing is deleted. From then on the workspace is closed:

- Every member's admission is refused with `workspace_deleting` (403), the owner's included. A non-member still gets `workspace_not_found`.
- It takes no new members: `conciergeAcceptsMembers()` is false, so Concierge refuses its invitations as `workspace_unavailable`, and the kit's people operations (invite, change role, remove, transfer) refuse with `workspace_deleting`.
- It's hidden from the switcher, the workspace list and the kit's home, because `memberOf()` leaves it out.
- A member who opens any of its pages is sent to the workspace list with an info toast: "{name} is being deleted. Deletion is in progress." A JSON request gets 403 `workspace_deleting`.
- It's never reopened. A `begin()` that throws leaves it closed, so you can dispatch your job again. The kit has no reversal API.

The owner who deleted it lands on the workspace list with the same notice. A JSON client gets `{"deleted": false, "deleting": true, "workspace_id": …}`.

**Finish.** Your job purges your data in whatever chunks and retries it needs, deletes your stored files, and then calls the kit's hook:

```php
use Tey\Kit\Features\Workspaces\Operations\FinishWorkspaceDeletion;

final class PurgeWorkspaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $workspaceId) {}

    public function handle(): void
    {
        Invoice::query()->where('workspace_id', $this->workspaceId)->chunkById(500, fn ($invoices) => $invoices->each->delete());
        Storage::disk('s3')->deleteDirectory('workspaces/'.$this->workspaceId);

        app(FinishWorkspaceDeletion::class)($this->workspaceId);
    }
}
```

`FinishWorkspaceDeletion` deletes the kit's and Concierge's rows, the same set as the synchronous mode, and then the workspace row, in one short transaction. `WorkspaceDeleted` is dispatched after its commit, with the `actorId` of the owner who closed it. The hook is idempotent: once the workspace is gone it returns `false` and does nothing, so a retried job finishes cleanly. It fails closed: a workspace that isn't being deleted is refused with `RuntimeException('workspace_not_deleting')`, and nothing is deleted. Make the job a plain queued job (or `NotTenantAware`), not `TenantAware`: the closed workspace refuses admission.

**Accounts** work the same way, with `Tey\Kit\Features\SessionUi\Contracts\DefersAccountPurge::begin(Model $user)`. The deletion's checks are unchanged (password, sole-owner rule). Then, in one transaction, the person leaves each workspace through `LeaveWorkspace`, `AccountDeleting` is dispatched, and the account is closed:

- Their Concierge account is deactivated, so sign-in is refused ("This account is deactivated.", Concierge's message) and `concierge.active` signs out any other session.
- Database sessions are ended and the remember-me token cycled.
- Passport access and refresh tokens are revoked, and Sanctum tokens deleted.
- A `kit_account_deletions` row records the workspaces they left.

The request signs them out. Your job ends with `app(FinishAccountDeletion::class)($userId)` (`Tey\Kit\Features\SessionUi\Operations\FinishAccountDeletion`), which deletes the kit's and Concierge's rows and the user row, then dispatches `AccountDeleted` after the commit. It's idempotent, and it refuses a user whose account isn't being deleted (`account_not_deleting`).

Deferred mode needs the `0001_01_02_000210` (workspaces) and `0001_01_02_000504` (session-ui) migrations. Without them a deferred deletion refuses with a configuration error and deletes nothing.

### Moving over from your own deletion page

If you kept your own deletion page and job, for example one that sets a closing flag and purges in a queued job:

1. Run `kit:update plan|apply`, then `php artisan migrate`.
2. Bind your purge handler as `PurgesWorkspaceData` and have it implement `DefersWorkspacePurge`. Its `begin()` dispatches your existing job.
3. In your job, drop your own deletion of the kit's and Concierge's rows (memberships, ownership, invitations, roles, settings and the workspace row), and end with `app(FinishWorkspaceDeletion::class)($workspaceId)` instead.
4. Retire your own closing flag in favour of `deleting_at`. If your workspace model sets `admitted`/`accepts_members` to false to close a workspace, you no longer need to: the kit's `conciergeAcceptsMembers()` is already false while `deleting_at` is set. Copy any in-flight closures across (`update workspaces set deleting_at = <your flag's timestamp> where …`) before you remove your column, so a job still running finds them closed.
5. Remove your page and route, and turn on `kit.lifecycle.workspace_delete`. Settings › Delete workspace is then the kit's.

Account deletion moves over the same way, with `DefersAccountPurge` and `FinishAccountDeletion`.

## Lifecycle events

| Event | When | Payload |
| --- | --- | --- |
| `Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting` | Inside the transaction, before any row is deleted. The workspace is still current. Deferred: inside the closing transaction. | `workspaceId`, `slug`, `memberUserIds` (every user with a membership row, in any status), `actorId` |
| `Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted` | After the transaction commits. Deferred: after the finish hook's commit. | the same |
| `Tey\Kit\Features\SessionUi\Events\AccountDeleting` | Inside the transaction, after the memberships are ended and before the user row is deleted | `userId`, `workspaceIds` (the workspaces the user left) |
| `Tey\Kit\Features\SessionUi\Events\AccountDeleted` | After the transaction commits | the same |

Naming follows Eloquent's `deleting`/`deleted` pair. `…Deleting` is informational: it has no veto, and it's dispatched synchronously while the transaction is open, so a listener can still read the rows. Its listeners must not queue work, because the transaction can still roll back. If a listener throws, the deletion fails and rolls back, like any other error inside it.

`…Deleted` implements `ShouldDispatchAfterCommit`. Laravel holds it until the outermost transaction commits and drops it if that transaction rolls back. So a listener never sees an uncommitted deletion and never runs for one that didn't happen, even when the deletion itself runs inside a transaction of yours. This is the event to purge on.

### A queued purge listener

By the time `WorkspaceDeleted` is handled, the workspace no longer exists. A `TenantAware` queued listener or job would try to make it current and fail to find it. Make the purge a plain queued listener (or mark it `NotTenantAware`), and work from the ids in the event:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted;

class PurgeWorkspaceData implements ShouldQueue
{
    public function handle(WorkspaceDeleted $event): void
    {
        Invoice::query()->where('workspace_id', $event->workspaceId)->delete();
        Storage::disk('s3')->deleteDirectory('workspaces/'.$event->workspaceId);
    }
}
```

Register it with `Event::listen(WorkspaceDeleted::class, PurgeWorkspaceData::class)`, or let event discovery find it. Use a listener class rather than a closure: a queued closure listener (`Event::listen(queueable(...))`) has to serialise the closure, and a class keeps the job inspectable and retryable.

`AccountDeleted` works the same way: the user can no longer be loaded, so a purge works from `userId` and `workspaceIds`.

`composer test:lifecycle-pages` covers all of this over HTTP with foreign keys enforced: on SQLite memory by default, and on PostgreSQL with `DB_CONNECTION=pgsql` and the `DB_*` variables, in a schema it creates and drops itself (CI runs both). It checks who may, the typed confirmations, the scoped-guest refusal through a stand-in on Concierge 0.8, each feature's rows going with the workspace, and after-commit dispatch: a queued listener is pushed with the ids, and nothing is dispatched when the transaction rolls back. A negative case skips one RESTRICT table's cleanup and checks that the deletion fails, rolls back entirely and announces nothing.
