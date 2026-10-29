# Ownership transfer, deletion and lifecycle events

workspace-ui adds three owner and account actions: transferring a workspace's ownership, deleting a workspace, and deleting your own account. Each deletion dispatches a pair of events so a host can clear its own data.

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
- the access rows (entitlements, complimentary grants, capability overrides)
- per-workspace user state
- MCP connections and their intents
- notes and note intents.

Tables the host didn't migrate are skipped. `audit_events` rows are never deleted: the audit trail outlives the workspace, and the table's foreign key decides what happens to `workspace_id`. The workspace's own `delete()` runs last, so a host model with soft deletes keeps its row.

Your own tables that reference the workspace are yours to clear, either in a `WorkspaceDeleting` listener (inside the same transaction) or from `WorkspaceDeleted`.

## Delete your account

`/me/delete` (`Workspace/Account/Delete/Page`, under Account in the "Danger zone" group) sends `DELETE /me/delete` with your current `password`.

- **Sole-owner rule.** While you own any workspace, the deletion is refused with a 422 that names those workspaces, and the page lists them. Transfer ownership or delete each one first. Concierge keeps exactly one owner per workspace, so its owner is always its sole owner.
- **What it does otherwise.** Each membership that is still open (active or suspended) is ended through Concierge's `LeaveWorkspace`, which clears your roles there and dispatches `MembershipRemoved`. Then these rows are deleted with the user row, in one transaction:
  - your membership rows, including removed ones
  - your Concierge account row
  - your role rows
  - your capability overrides, user state and security events
  - your MCP connections.
- **Afterwards** you're signed out, the session is invalidated and its CSRF token regenerated.

## Lifecycle events

| Event | When | Payload |
| --- | --- | --- |
| `Tey\Kit\Features\Workspaces\Events\WorkspaceDeleting` | Inside the transaction, before any row is deleted. The workspace is still current. | `workspaceId`, `slug`, `memberUserIds` (every user with a membership row, in any status), `actorId` |
| `Tey\Kit\Features\Workspaces\Events\WorkspaceDeleted` | After the transaction commits | the same |
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

`composer test:lifecycle-pages` covers all of this over HTTP. It checks who may, the typed confirmations, the scoped-guest refusal through a stand-in on Concierge 0.8, and after-commit dispatch: a queued listener is pushed with the ids, and nothing is dispatched when the transaction rolls back.
