<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// 0.18: the mcp feature's infrastructure tables lose the kit_ prefix. Each rename runs only while the old table exists and the new one does not;
// with both names of any table present it refuses, naming both, before renaming any, and never merges them. Order does not matter: once it has run, the
// kit runs it again when each migrate ends (PackageMigrator::renames()), so an app's copy dated after it that creates an old name is renamed too.
return new class extends Migration
{
    /** Old name => new name. */
    private const TABLES = [
        'kit_mcp_intents' => 'mcp_intents',
        'kit_mcp_registrations' => 'mcp_registrations',
        'kit_mcp_runs' => 'mcp_runs',
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
     * Renames each table whose old name exists, after checking every pair: when any pair has both names, it refuses before renaming a single
     * table, so a database without transactional DDL is never left half renamed.
     *
     * @param  array<string, string>  $tables  old name => new name
     */
    private function rename(array $tables): void
    {
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from) && Schema::hasTable($to)) {
                throw new RuntimeException('Both '.$from.' and '.$to.' exist, so the kit will not rename '.$from.' to '.$to.': it never merges two tables. Keep the one that holds the data, drop or rename the other, then migrate again.');
            }
        }
        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
    }
};
