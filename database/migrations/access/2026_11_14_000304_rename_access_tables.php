<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// 0.18: the access feature's infrastructure tables lose the kit_ prefix. Each rename runs only while the old table exists and the new one does not;
// with both present it refuses, naming both, and never merges them. Dated after the kit's own creates and an app's migrations from before 0.18, so a
// fresh database ends with the new names too.
return new class extends Migration
{
    /** Old name => new name. */
    private const TABLES = [
        'kit_entitlements' => 'plan_entitlements',
        'kit_permission_defaults' => 'permission_defaults',
        'kit_capability_overrides' => 'capability_overrides',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $from => $to) {
            $this->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $from => $to) {
            $this->rename($to, $from);
        }
    }

    private function rename(string $from, string $to): void
    {
        $old = Schema::hasTable($from);
        if ($old && Schema::hasTable($to)) {
            throw new RuntimeException('Both '.$from.' and '.$to.' exist, so the kit will not rename '.$from.' to '.$to.': it never merges two tables. Keep the one that holds the data, drop or rename the other, then migrate again.');
        }
        if ($old) {
            Schema::rename($from, $to);
        }
    }
};
