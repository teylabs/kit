<?php

namespace Tey\Kit\Features;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;

/**
 * Laravel's migrator, aware of the kit's package migrations. Each adopted feature's package directory (database/migrations/<feature> in the kit) is
 * registered as a migration path beside the app's copies directory (database/migrations/kit/<feature>), and the migrator's file list drops each
 * package migration the app has a copy of there under any stamp: the name after the timestamp prefix matches, so `0001_01_02_000502_x.php` and
 * kit:add's stamped `2026_03_04_050607_000502_x.php` are one migration. Laravel keys migrations by name, so an app that ran its copies runs
 * none of the package's twins, and an app's copy, edited or not, stays the one that runs. `migrate`, `migrate:status` and the rollbacks all
 * read this one list.
 *
 * It also keeps the 0.18 renames independent of their date (asWritten()).
 */
final class PackageMigrator extends Migrator
{
    /** The 0.18 renames, by feature. An app's copy that sorts after its feature's rename runs with the rename undone around it (asWritten()). */
    public const RENAMES = [
        'access' => '2026_11_14_000304_rename_access_tables',
        'session-ui' => '2026_11_14_000506_rename_session_ui_tables',
        'mcp' => '2026_11_14_000605_rename_mcp_tables',
    ];

    /** @var array<string, string> feature id => the app's copies directory of that feature, for each feature registered */
    private array $copies = [];

    /** The kit's migrator in place of $migrator, with the paths it already had. */
    public static function from(Migrator $migrator, Application $app): self
    {
        if ($migrator instanceof self) {
            return $migrator;
        }
        $kit = new self($app['migration.repository'], $app['db'], $app['files'], $app['events']);
        foreach ($migrator->paths() as $path) {
            $kit->path($path);
        }

        return $kit;
    }

    /** The kit's migrations directory for one feature. */
    public static function directory(string $id): string
    {
        return dirname(__DIR__, 2).'/database/migrations/'.$id;
    }

    /** Registers one feature's migrations: the app's copies directory, then the package directory. */
    public function feature(string $id, string $copies): void
    {
        $this->path($copies);
        $this->path(self::directory($id));
        $this->copies[$id] = $copies;
    }

    /**
     * @param  string|array<int, string>  $paths
     * @return array<string, string>
     */
    public function getMigrationFiles($paths)
    {
        $paths = (array) $paths;
        $packages = array_map(self::directory(...), array_keys($this->copies));
        $files = parent::getMigrationFiles(array_values(array_diff($paths, $packages)));
        foreach ($this->copies as $id => $copies) {
            if (! in_array(self::directory($id), $paths, true)) {
                continue;
            }
            $own = array_map(self::name(...), array_filter($files, fn (string $file): bool => dirname($file) === $copies));
            $files += array_filter(parent::getMigrationFiles([self::directory($id)]), fn (string $file): bool => ! in_array(self::name($file), $own, true));
        }

        return (new Collection($files))->sortBy(fn (string $file, string $name): string => $name)->all();
    }

    protected function runUp($file, $batch, $pretend)
    {
        $this->asWritten($file, $pretend, fn () => parent::runUp($file, $batch, $pretend));
    }

    protected function runDown($file, $migration, $pretend)
    {
        $this->asWritten($file, $pretend, fn () => parent::runDown($file, $migration, $pretend));
    }

    /**
     * Runs one migration, up or down. An app's copy that sorts after its feature's 0.18 rename once that rename has run (kit:add stamped copies with
     * the day it ran, so on a fresh database they can follow it) was written for the old kit_ names: the rename is undone before it and done again
     * after it, so the copy meets the tables it was written for and the database keeps the new names, whatever the order, on the way up and down.
     * Both through the migrator's own connection handling, so `--database` applies.
     */
    private function asWritten(string $file, bool $pretend, Closure $run): void
    {
        $id = array_search(dirname($file), $this->copies, true);
        $rename = $id === false ? null : (self::RENAMES[$id] ?? null);
        if ($pretend || $rename === null || strcmp($this->getMigrationName($file), $rename) < 0 || ! in_array($rename, $this->repository->getRan(), true)) {
            $run();

            return;
        }
        $migration = $this->resolvePath(self::directory($id).'/'.$rename.'.php');
        $connection = $this->resolveConnection($migration->getConnection());
        $this->runMethod($connection, $migration, 'down');
        try {
            $run();
        } finally {
            $this->runMethod($connection, $migration, 'up');
        }
    }

    /** A migration file's name after its timestamp and any six-digit order: `create_kit_notes_table.php` for `0001_01_02_000701_…` and `2026_03_04_050607_000701_…` alike. */
    private static function name(string $file): string
    {
        return (string) preg_replace('/\A\d{4}_\d{2}_\d{2}_(?:\d{6}_)+/', '', basename($file));
    }
}
