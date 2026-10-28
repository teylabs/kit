<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\Schema;

/**
 * The workspaces table the kit's migrations create, or adopt in place when the host already has one. An adopted table keeps
 * every host column and row; the kit only adds the columns it needs. Nothing is recorded: a table with any column the kit
 * did not create is the host's, and the kit's down() migrations never drop it or its columns.
 */
final class WorkspaceTable
{
    /** The columns the kit's own migrations create. */
    public const KIT_COLUMNS = ['id', 'name', 'provisioning_invitation_id', 'settings_version', 'admitted', 'slug'];

    /** True when the workspaces table exists and has a column the kit's migrations did not create: the host's own, adopted in place. */
    public static function adopted(string $table = 'workspaces'): bool
    {
        return Schema::hasTable($table) && array_diff(Schema::getColumnListing($table), self::KIT_COLUMNS) !== [];
    }
}
