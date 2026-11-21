<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Rls;

// Row-level security for the flag and tag tables, like the kit's other workspace tables (#1190).
// kit_taggables and kit_flag_dismissals always carry their record's workspace: rows without one are filled from the record first, the column becomes
// NOT NULL, and the migration refuses, naming the rows, when a record has no workspace to give (it never guesses). Both then take the standard policy.
// kit_tags also holds the shared flag vocabulary (workspace_id null): a workspace reads its own tags and the shared ones, and may write a null-workspace
// row only when it is a flag; every other write must be its own workspace's.
// Idempotent: each policy (Rls::POLICY, `kit_workspace`) is dropped and recreated, so an app that added the same policy itself ends with one.
// The RLS part is a no-op off PostgreSQL or with RLS off; run it as the owner (the admin/migrator connection).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['kit_taggables' => 'taggable', 'kit_flag_dismissals' => 'flaggable'] as $table => $morph) {
            $this->fill($table, $morph);
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('workspace_id')->nullable(false)->change());
            Rls::protect($table);
        }
        if (DB::getDriverName() !== 'pgsql' || ! config('kit.tenancy.rls.enabled', false)) {
            return;
        }
        $current = "nullif(current_setting('".Rls::SETTING."', true), '')::bigint";
        DB::statement('alter table kit_tags enable row level security');
        DB::statement('alter table kit_tags force row level security');
        DB::statement('drop policy if exists '.Rls::POLICY.' on kit_tags');
        DB::statement('create policy '.Rls::POLICY.' on kit_tags'
            ." using (workspace_id is null or workspace_id = {$current})"
            ." with check ((workspace_id is null and type = 'flag') or workspace_id = {$current})");
    }

    /** Fills a missing workspace_id from the record's own, then refuses when any row is still without one. */
    private function fill(string $table, string $morph): void
    {
        $forced = DB::getDriverName() === 'pgsql';
        if ($forced) {
            // The owner sees its own table's rows while it fills them; the policy is (re)created right after.
            DB::statement('alter table '.$table.' no force row level security');
        }
        foreach (DB::table($table)->whereNull('workspace_id')->distinct()->pluck($morph.'_type') as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! class_exists($class)) {
                continue;
            }
            $model = new $class;
            if (! Schema::hasColumn($model->getTable(), 'workspace_id')) {
                continue;
            }
            $grammar = DB::getQueryGrammar();
            DB::update('update '.$grammar->wrapTable($table).' set workspace_id = (select r.workspace_id from '.$grammar->wrapTable($model->getTable()).' r where r.'.$grammar->wrap($model->getKeyName()).' = '.$grammar->wrapTable($table).'.'.$morph.'_id) where workspace_id is null and '.$morph.'_type = ?', [$type]);
        }
        $left = DB::table($table)->whereNull('workspace_id')->limit(20)->get([$morph.'_type', $morph.'_id']);
        if ($left->isNotEmpty()) {
            throw new RuntimeException($table.' has rows whose record has no workspace to fill workspace_id from: '.$left->map(fn (object $row): string => $row->{$morph.'_type'}.' #'.$row->{$morph.'_id'})->implode(', ').'. Set their workspace_id (or delete them), then migrate again.');
        }
    }

    public function down(): void
    {
        foreach (['kit_tags', 'kit_flag_dismissals', 'kit_taggables'] as $table) {
            Rls::unprotect($table);
        }
        foreach (['kit_taggables', 'kit_flag_dismissals'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('workspace_id')->nullable()->change());
        }
    }
};
