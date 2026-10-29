<?php

namespace Tey\Kit\Features\SessionUi;

use RuntimeException;
use Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData;
use Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData;
use Tey\Kit\Features\Workspaces\Operations\DeleteWorkspace;

/**
 * Workspace and account deletion are off unless the host turns them on (`kit.lifecycle.workspace_delete`, `kit.lifecycle.account_delete`) AND binds the
 * purge handler for its own data. Off: the routes are 404 and the settings sections hidden. On without a handler: the deletion refuses, nothing deleted.
 */
final class Deletions
{
    public const WORKSPACE = 'workspace_delete';

    public const ACCOUNT = 'account_delete';

    private const CONTRACTS = [self::WORKSPACE => PurgesWorkspaceData::class, self::ACCOUNT => PurgesAccountData::class];

    public static function enabled(string $kind): bool
    {
        return filter_var(config('kit.lifecycle.'.$kind, false), FILTER_VALIDATE_BOOL);
    }

    /** @return 'off'|'unbound'|'on' */
    public static function state(string $kind): string
    {
        return ! self::enabled($kind) ? 'off' : (app()->bound(self::CONTRACTS[$kind]) ? 'on' : 'unbound');
    }

    /** The bound handler, or a configuration error when the flag is on without one (checked before anything is deleted). */
    public static function handler(string $kind): PurgesWorkspaceData|PurgesAccountData
    {
        if ($kind === self::WORKSPACE) {
            return DeleteWorkspace::purger();
        }
        if (! self::enabled($kind)) {
            throw new RuntimeException('Account deletion is off: set kit.lifecycle.account_delete and bind '.PurgesAccountData::class.' to turn it on.');
        }
        if (! app()->bound(PurgesAccountData::class)) {
            throw new RuntimeException('kit.lifecycle.account_delete is on but no '.PurgesAccountData::class.' is bound. Bind the purge handler for your own data (one that does nothing, explicitly, if you have none). Nothing was deleted.');
        }

        return app(PurgesAccountData::class);
    }
}
