<?php

namespace Tey\Kit\Console;

use Closure;
use RuntimeException;
use Tey\Mod\Maintenance\FilePlan;
use Throwable;

/** The fail-closed apply and the changed-migration review shared by kit:update and kit:adopt. */
trait FailsClosed
{
    /**
     * A fresh apply that does not complete leaves the checkout as it found it: every planned file is restored (or removed when the plan created it) and the
     * intent's journal goes, so nothing half-delivered stays behind and the next step is a fresh plan. Reopening an existing intent keeps Mod's resume.
     *
     * @param  Closure(): array  $apply
     */
    private function failClosed(FilePlan $plan, string $intent, Closure $apply): array
    {
        $journal = $plan->root.'/.mod-maintenance/journal-'.hash('sha256', $intent).'.json';
        if ($intent === '' || file_exists($journal)) {
            return $apply();
        }
        $before = [];
        foreach ($plan->files as $path => $entry) {
            if (($entry['state'] ?? null) !== 'unchanged') {
                $before[$path] = is_file($plan->root.'/'.$path) ? file_get_contents($plan->root.'/'.$path) : null;
            }
        }
        try {
            $result = $apply();
        } catch (Throwable $exception) {
            if (file_exists($journal)) {
                $this->restore($plan->root, $before, $journal);
            }
            throw $exception;
        }
        if ($result['status'] !== 'completed' && file_exists($journal)) {
            $result['rolled_back'] = $this->restore($plan->root, $before, $journal);
        }

        return $result;
    }

    /**
     * @param  array<string, ?string>  $before
     * @return list<string>
     */
    private function restore(string $root, array $before, string $journal): array
    {
        $restored = [];
        foreach ($before as $path => $contents) {
            // Mod validated every planned path as relative and inside the checkout.
            $file = $root.'/'.$path;
            $now = is_file($file) ? file_get_contents($file) : null;
            if ($now === $contents) {
                continue;
            }
            if ($contents === null) {
                unlink($file);
                // Drop the directories the apply created, up to the first one that holds anything else.
                for ($dir = dirname($file); $dir !== $root && is_dir($dir) && count(scandir($dir)) === 2; $dir = dirname($dir)) {
                    rmdir($dir);
                }
            } else {
                file_put_contents($file, $contents);
            }
            $restored[] = $path;
        }
        unlink($journal);

        return $restored;
    }

    /**
     * Already-run migrations the plan would replace. Laravel never runs a migration twice, so the new bytes would not
     * reach the database: each is flagged, and applies only once reviewed.
     *
     * @return list<string>
     */
    private static function changedMigrations(FilePlan $plan): array
    {
        return array_keys(array_filter($plan->files, fn (array $entry, string $path): bool => $entry['state'] === 'replace' && str_starts_with($path, 'database/migrations/'), ARRAY_FILTER_USE_BOTH));
    }

    /** Plan output for changed migrations: the list, and the host step to take for each. */
    private static function migrationNotes(FilePlan $plan): array
    {
        $changed = self::changedMigrations($plan);

        return $changed === [] ? [] : ['changed_migrations' => $changed, 'host_steps' => array_map(fn (string $path): string => 'If '.$path.' already ran here, its change will not reach the database: add a new migration that makes the same schema change.', $changed)];
    }

    private function refuseChangedMigrations(FilePlan $plan): void
    {
        $changed = self::changedMigrations($plan);
        if ($changed !== [] && ! $this->option('accept-changed-migrations')) {
            throw new RuntimeException("The plan replaces migrations that may already have run; review each and add a new migration for its schema change, then apply with --accept-changed-migrations:\n".implode("\n", array_map(fn (string $path): string => '  '.$path, $changed)));
        }
    }
}
