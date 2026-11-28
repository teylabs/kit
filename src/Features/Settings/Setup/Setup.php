<?php

namespace Tey\Kit\Features\Settings\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Workspaces\Operators;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Settings → Setup and `workspace_setup_read`: every registered setup row, run now. Owners and admins (settings.write) see the workspace's rows; an
 * operator sees the app's rows too, since those describe the whole deployment. A row's check that throws is shown failing, never hidden.
 */
final class Setup
{
    /** @return array{rows: list<SetupRow>, app_rows: bool} */
    public function read(Principal $principal): array
    {
        $workspace = app(Access::class)->authorize($principal, 'settings.write');
        $operator = Operators::allows(RequestMemo::user($principal->actorId));
        $rows = [];
        foreach (app(Registry::class)->setupRows() as $entry) {
            if ($entry['scope'] === 'app' && ! $operator) {
                continue;
            }
            // Each check in its own transaction (a savepoint inside the request's): on Postgres a failed query aborts the open transaction, and every
            // later row would fail with 25P02. Rolled back, the failure stays on its own row.
            try {
                $row = DB::transaction(fn () => ($entry['check'])());
            } catch (\Throwable $error) {
                $row = SetupRow::of($entry['key'], 'failing', 'The check failed ('.class_basename($error).').');
            }
            if ($row === null) {
                continue;
            }
            if ($row->link !== null && ! preg_match('#\Ahttps?://#', $row->link['url'])) {
                $row->link['url'] = WorkspaceUrl::to((int) $workspace->getKey(), $row->link['url'], 'settings');
            }
            $rows[] = $row->placed($entry['key'], $entry['group']);
        }

        return ['rows' => $rows, 'app_rows' => $operator];
    }

    /** Sends a short test mail to the viewer through the app's mailer, and returns the address. */
    public function sendTest(Principal $principal): string
    {
        $workspace = app(Access::class)->authorize($principal, 'settings.write');
        $user = RequestMemo::user($principal->actorId) ?? throw new RuntimeException('permission_denied');
        $address = (string) $user->email;
        Notification::route('mail', $address)->notifyNow(new SetupTestMail);
        app(Audit::class)->write($principal->actorId, (int) $workspace->getKey(), 'workspace.setup.test_mail', []);

        return $address;
    }
}
