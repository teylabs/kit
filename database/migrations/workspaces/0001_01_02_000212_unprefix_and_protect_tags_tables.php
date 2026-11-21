<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Rls;

// The flag and tag tables lose their kit prefix, then get row-level security like the kit's other workspace tables (#1190).
// kit_tags, kit_taggables and kit_flag_dismissals become tags, taggables and flag_dismissals; the migration refuses when the app already has a table of
// a new name (spatie/laravel-tags' own `tags`, say), naming it, and never merges the two.
// taggables and flag_dismissals always carry their record's workspace: rows without one are filled from the record first, the column becomes NOT NULL,
// and the migration refuses, naming the rows, when a record has no workspace to give (it never guesses). Both then take the standard policy.
// tags also holds the shared flag vocabulary (workspace_id null): a workspace reads its own tags and the shared ones, and may write a null-workspace row
// only when it is a flag; every other write must be its own workspace's.
// The policy is `workspace_isolation` (Rls::ISOLATION). Idempotent: it is dropped and recreated, and an app's own `kit_workspace` policy on these tables
// is dropped, so each table ends with one. The RLS part is a no-op off PostgreSQL or with RLS off; run it as the owner (the admin/migrator connection).
return new class extends Migration
{
    private const RENAMES = ['kit_tags' => 'tags', 'kit_taggables' => 'taggables', 'kit_flag_dismissals' => 'flag_dismissals'];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            if (Schema::hasTable($from) && Schema::hasTable($to)) {
                throw new RuntimeException('A '.$to.' table already exists, so '.$from.' cannot be renamed to it. Rename or drop the app\'s '.$to.' table, then migrate again.');
            }
        }
        foreach (self::RENAMES as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
        foreach (['taggables' => 'taggable', 'flag_dismissals' => 'flaggable'] as $table => $morph) {
            $this->fill($table, $morph);
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('workspace_id')->nullable(false)->change());
            Rls::unprotect($table);
            Rls::protect($table, policy: Rls::ISOLATION);
        }
        if (DB::getDriverName() !== 'pgsql' || ! config('kit.tenancy.rls.enabled', false)) {
            return;
        }
        $current = "nullif(current_setting('".Rls::SETTING."', true), '')::bigint";
        DB::statement('drop policy if exists '.Rls::POLICY.' on tags');
        DB::statement('alter table tags enable row level security');
        DB::statement('alter table tags force row level security');
        DB::statement('drop policy if exists '.Rls::ISOLATION.' on tags');
        DB::statement('create policy '.Rls::ISOLATION.' on tags'
            ." using (workspace_id is null or workspace_id = {$current})"
            ." with check ((workspace_id is null and type = 'flag') or workspace_id = {$current})");
    }

    /** Fills a missing workspace_id from the record's own, then refuses when any row is still without one. */
    private function fill(string $table, string $morph): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // The owner sees its own table's rows while it fills them; the policy is (re)created right after.
            DB::statement('alter table '.$table.' no force row level security');
        }
        foreach (DB::table($table)->whereNull('workspace_id')->distinct()->pluck($morph.'_type') as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! is_subclass_of($class, Model::class)) {
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
        foreach (['tags', 'flag_dismissals', 'taggables'] as $table) {
            Rls::unprotect($table, Rls::ISOLATION);
        }
        foreach (['taggables', 'flag_dismissals'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('workspace_id')->nullable()->change());
        }
        foreach (self::RENAMES as $from => $to) {
            Schema::rename($to, $from);
        }
    }
};
