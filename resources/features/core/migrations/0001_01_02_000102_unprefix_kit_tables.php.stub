<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// No `kit` in the schema (0.43). kit_meta becomes app_meta, kit_adoptions app_adoptions (only where an in-place adoption made it; most apps have
// none) and kit_media_uploads media_uploads (when the app created it under the old default name and `kit.media.uploads_table` is the new default).
// It refuses when the app already has a table of a new name, naming it, and never merges two tables. Stored morph types under the 0.42 `kit-` aliases
// (`kit-note`, `kit-user-avatar`, …) are rewritten to the plain ones in every `*_type` column; the old aliases still resolve, so nothing orphans
// in between. The flag and tag tables are renamed by workspaces' 000212.
return new class extends Migration
{
    private const ALIASES = ['note', 'finalization', 'finalization-confirmation', 'delivery', 'delivery-recipient', 'delivery-event', 'security-event', 'user-avatar'];

    public function up(): void
    {
        $renames = $this->renames();
        foreach ($renames as $from => $to) {
            if (Schema::hasTable($to)) {
                throw new RuntimeException('A '.$to.' table already exists, so '.$from.' cannot be renamed to it. Rename or drop the app\'s '.$to.' table, then migrate again.');
            }
        }
        foreach ($renames as $from => $to) {
            Schema::rename($from, $to);
        }
        $this->rewrite(fn (string $alias): array => ['kit-'.$alias, $alias]);
    }

    public function down(): void
    {
        $this->rewrite(fn (string $alias): array => [$alias, 'kit-'.$alias]);
        foreach (['app_meta' => 'kit_meta', 'app_adoptions' => 'kit_adoptions', 'media_uploads' => 'kit_media_uploads'] as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to) && ($from !== 'media_uploads' || config('kit.media.uploads_table', 'media_uploads') === 'media_uploads')) {
                Schema::rename($from, $to);
            }
        }
    }

    /** @return array<string, string> the old tables present, to their new names */
    private function renames(): array
    {
        $renames = ['kit_meta' => 'app_meta', 'kit_adoptions' => 'app_adoptions'];
        if (config('kit.media.uploads_table', 'media_uploads') === 'media_uploads') {
            $renames['kit_media_uploads'] = 'media_uploads';
        }

        return array_filter($renames, fn (string $to, string $from): bool => Schema::hasTable($from), ARRAY_FILTER_USE_BOTH);
    }

    /** @param  Closure(string): array{string, string}  $pair  an alias to its [from, to] stored values */
    private function rewrite(Closure $pair): void
    {
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            foreach (Schema::getColumnListing($table['name']) as $column) {
                if (! str_ends_with($column, '_type')) {
                    continue;
                }
                foreach (self::ALIASES as $alias) {
                    [$from, $to] = $pair($alias);
                    DB::table($table['name'])->where($column, $from)->update([$column => $to]);
                }
            }
        }
    }
};
