<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Adoptions;

// 0.51: access's entitlements table takes a prefix (workspace_entitlements), as a generic noun must (docs/domain-tables.md, Table names). The rename
// runs only while the old table exists; with both names present it refuses before renaming anything, and never merges them. Rows, keys and the id
// sequence move with the table. An app that names its own table in kit.tables.entitlement keeps it: nothing is renamed. A host table the kit adopted
// in place (app_adoptions) is the host's, so the rename refuses until config/kit.php names it. An app's copy dated after it was written for the old
// name, and the kit's migrator undoes this rename around it (PackageMigrator::asWritten()).
return new class extends Migration
{
    /** Old name => new name. */
    private const TABLES = [
        'entitlements' => 'workspace_entitlements',
    ];

    public function up(): void
    {
        $this->rename(self::TABLES);
    }

    public function down(): void
    {
        $this->rename(array_flip(array_reverse(self::TABLES, true)));
    }

    /**
     * Renames each table whose old name exists, after checking every pair: when any pair has both names, or the old one is the host's, it refuses
     * before renaming a single table, so a database without transactional DDL is never left half renamed.
     *
     * @param  array<string, string>  $tables  old name => new name
     */
    private function rename(array $tables): void
    {
        if (config('kit.tables.entitlement') !== null) {
            return;
        }
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from) && Schema::hasTable($to)) {
                throw new RuntimeException('Both '.$from.' and '.$to.' exist, so the kit will not rename '.$from.' to '.$to.': it never merges two tables. Keep the one that holds the data, drop or rename the other, then migrate again.');
            }
            if (Schema::hasTable($from) && Adoptions::adopted($from)) {
                throw new RuntimeException('The '.$from.' table is the host\'s, adopted in place, so the kit will not rename it to '.$to.'. Keep it by naming it in config/kit.php (\'tables\' => [\'entitlement\' => \''.$from.'\']), then migrate again.');
            }
        }
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
    }
};
