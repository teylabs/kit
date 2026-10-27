<?php

namespace Tey\Kit\Features\Notes\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Notes\Colours;
use Throwable;

/**
 * Copies notes from a table an earlier release or the app kept them in (`notes`, `app_notes`) into `kit_notes`. The source is only read: never altered,
 * emptied or dropped. Each copied row remembers where it came from (`<table>:<id>`), so a second run copies nothing twice. It needs `id`,
 * `workspace_id` and `body`; `author_id`, `colour`, `pinned`, `created_at`, `updated_at` and `deleted_at` are used when present. It skips (and
 * reports) rows already imported, soft-deleted rows, rows with an empty body and rows whose workspace no longer exists. A colour outside the
 * palette becomes the default, and is reported.
 */
class ImportNotesCommand extends Command
{
    protected $signature = 'kit:notes:import {--from= : The table to copy from, e.g. notes or app_notes} {--dry-run : Report what would be copied, and copy nothing}';

    protected $description = 'Copy notes from a legacy table into kit_notes (idempotent; the source table is never changed)';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        try {
            if (! preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/', $from) || in_array($from, ['kit_notes', 'kit_note_intents'], true)) {
                throw new \RuntimeException('Name the legacy table with --from (e.g. --from=notes).');
            }
            if (! Schema::hasTable($from) || ! Schema::hasTable('kit_notes')) {
                throw new \RuntimeException(Schema::hasTable('kit_notes') ? 'There is no '.$from.' table.' : 'Run php artisan migrate first: kit_notes does not exist.');
            }
            $columns = Schema::getColumnListing($from);
            foreach (['id', 'workspace_id', 'body'] as $required) {
                if (! in_array($required, $columns, true)) {
                    throw new \RuntimeException($from.' has no '.$required.' column, so it is not a notes table this command can read.');
                }
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $has = fn (string $column): bool => in_array($column, $columns, true);
        $workspaces = DB::table('workspaces')->pluck('id')->map(fn ($id): int => (int) $id)->flip();
        $report = ['copied' => 0, 'already_imported' => 0, 'deleted' => 0, 'empty' => 0, 'no_workspace' => 0, 'colour_defaulted' => 0];
        $dry = (bool) $this->option('dry-run');

        DB::table($from)->orderBy('id')->chunk(500, function ($rows) use ($from, $has, $workspaces, &$report, $dry): void {
            $imported = DB::table('kit_notes')->whereIn('imported_from', $rows->map(fn ($row): string => $from.':'.$row->id)->all())->pluck('imported_from')->flip();
            foreach ($rows as $row) {
                $origin = $from.':'.$row->id;
                $skip = match (true) {
                    isset($imported[$origin]) => 'already_imported',
                    $has('deleted_at') && $row->deleted_at !== null => 'deleted',
                    trim((string) $row->body) === '' => 'empty',
                    ! isset($workspaces[(int) $row->workspace_id]) => 'no_workspace',
                    default => null,
                };
                if ($skip !== null) {
                    $report[$skip]++;

                    continue;
                }
                $colour = $has('colour') && in_array($row->colour, Colours::keys(), true) ? $row->colour : Colours::default();
                if ($has('colour') && $colour !== $row->colour) {
                    $report['colour_defaulted']++;
                }
                if ($dry) {
                    $report['copied']++;

                    continue;
                }
                $now = now();
                // A concurrent run that copied the row first wins on the unique (workspace_id, imported_from) index; this one counts it as already imported.
                $inserted = DB::table('kit_notes')->insertOrIgnore([
                    'workspace_id' => (int) $row->workspace_id,
                    'author_id' => $has('author_id') && $row->author_id !== null ? (int) $row->author_id : null,
                    'body' => (string) $row->body,
                    'colour' => $colour,
                    'pinned' => $has('pinned') ? (bool) $row->pinned : false,
                    'version' => 1,
                    'imported_from' => $origin,
                    'created_at' => $has('created_at') && $row->created_at !== null ? $row->created_at : $now,
                    'updated_at' => $has('updated_at') && $row->updated_at !== null ? $row->updated_at : $now,
                ]);
                $report[$inserted === 1 ? 'copied' : 'already_imported']++;
            }
        });
        $this->line(json_encode(['status' => $dry ? 'dry_run' : 'imported', 'from' => $from, 'source_changed' => false] + $report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
