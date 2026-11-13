<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// 0.18: the session-ui feature's infrastructure tables lose the kit_ prefix. Each rename runs only while the old table exists and the new one does not;
// with both names of any table present it refuses, naming both, before renaming any, and never merges them. Order does not matter: an app's copy dated after
// it was written for the old names, and the kit's migrator undoes this rename around it, up or down (PackageMigrator::asWritten()).
return new class extends Migration
{
    /** Old name => new name. */
    private const TABLES = [
        'kit_user_state' => 'user_state',
        'kit_account_deletions' => 'account_deletions',
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
