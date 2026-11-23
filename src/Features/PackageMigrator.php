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
 * It also keeps the 0.18 renames independent of their date (asWritten()), and leaves out an adopted feature's domain tables (DOMAIN).
 */
final class PackageMigrator extends Migrator
{
    /** The 0.18 renames, by feature. An app's copy that sorts after its feature's rename runs with the rename undone around it (asWritten()). */
    public const RENAMES = [
        'access' => '2026_11_14_000304_rename_access_tables',
        'session-ui' => '2026_11_14_000506_rename_session_ui_tables',
        'mcp' => '2026_11_14_000605_rename_mcp_tables',
    ];

    /**
     * The migrations that create and change each feature's domain tables, the first creating them (since 0.19 the app's own once it adopts the
     * feature, DomainModels). For an adopted feature they are listed only where that first one has run: on a fresh database the app's adopt migration
     * creates the tables under the app's names, and on one where the kit created them every one of them stays listed (a pending one runs, on the kit's
     * names, before the adopt migration renames them; one that ran stays for the rollbacks).
     */
    public const DOMAIN = [
        'access' => ['create_kit_access_tables.php'],
        'session-ui' => ['create_kit_security_events_table.php', 'add_user_foreign_key_to_kit_security_events_table.php'],
        'notes' => ['create_kit_notes_table.php'],
        'finalize' => ['create_kit_finalize_tables.php', 'add_snapshot_to_kit_finalizations.php'],
        'deliver' => ['create_kit_deliver_tables.php', 'add_finalization_ref_to_kit_deliveries.php'],
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

        $files = $this->withoutAdopted($files);
        $after = $this->afterCopies($files);

        return (new Collection($files))->sortBy(fn (string $file, string $name): string => $after[$name] ?? $name)->all();
    }

    /**
     * Where each package migration sorts when the app has stamped copies of its feature: just after the app's copy with the highest order below
     * its own (`0001_01_02_000211_…` follows `2026_10_29_…_000210_…`), so a package migration added after kit:add stamped the copies never runs
     * ahead of the tables they create. Only the undated baselines (`0001_01_02_`) move: the dated 0.18 renames keep their place (asWritten()). A
     * package migration with no such copy, or one already later, keeps its own name.
     *
     * @param  array<string, string>  $files
     * @return array<string, string> migration name => sort key
     */
    private function afterCopies(array $files): array
    {
        $keys = [];
        foreach ($this->copies as $id => $copies) {
            $own = $package = [];
            foreach ($files as $name => $file) {
                $order = self::order($file);
                if ($order === null) {
                    continue;
                }
                if (dirname($file) === $copies) {
                    $own[$order] = $name;
                } elseif (dirname($file) === self::directory($id) && str_starts_with(basename($file), '0001_01_02_')) {
                    $package[$name] = $order;
                }
            }
            ksort($own);
            foreach ($package as $name => $order) {
                $before = null;
                foreach ($own as $copyOrder => $copy) {
                    if ($copyOrder < $order) {
                        $before = $copy;
                    }
                }
                if ($before !== null && strcmp($before, $name) > 0) {
                    $keys[$name] = $before.'~'.$name;
                }
            }
        }

        return $keys;
    }

    /** A kit migration's six-digit order (`000211`), the last one after its timestamp; null for a file without one. */
    private static function order(string $file): ?string
    {
        return preg_match('/\A\d{4}_\d{2}_\d{2}_(?:\d{6}_)*(\d{6})_/', basename($file), $m) === 1 ? $m[1] : null;
    }

    /**
     * $files less each adopted feature's domain migrations (DOMAIN) where the first of them never ran, from the package or the app's copies.
     *
     * @param  array<string, string>  $files
     * @return array<string, string>
     */
    private function withoutAdopted(array $files): array
    {
        $adopted = array_values(array_filter(array_keys($this->copies), fn (string $id): bool => isset(self::DOMAIN[$id]) && DomainModels::adopted($id)));
        if ($adopted === []) {
            return $files;
        }
        $ran = $this->repositoryExists() ? array_map(fn (string $migration): string => self::name($migration.'.php'), $this->repository->getRan()) : [];
        $names = [];
        foreach ($adopted as $id) {
            if (! in_array(self::DOMAIN[$id][0], $ran, true)) {
                $names = [...$names, ...self::DOMAIN[$id]];
            }
        }

        return array_filter($files, fn (string $file): bool => ! in_array(self::name($file), $names, true));
    }

    protected function runUp($file, $batch, $pretend)
    {
        $this->withKitMeta($file, $pretend, fn () => $this->asWritten($file, $pretend, fn () => parent::runUp($file, $batch, $pretend)));
    }

    protected function runDown($file, $migration, $pretend)
    {
        $this->withKitMeta($file, $pretend, fn () => $this->asWritten($file, $pretend, fn () => parent::runDown($file, $migration, $pretend)));
    }

    /**
     * Runs one migration. An adopt migration kit:scaffold --adopt wrote before 0.43 (`*_adopt_*_tables.php`) reads and writes kit_meta, which core's
     * 000102 renames to app_meta, and on a fresh database that rename runs first. Where only app_meta exists, such a migration runs with it named
     * kit_meta again, and the name is put back after it, so its records land in app_meta. A database that still has kit_meta runs it as it is.
     */
    private function withKitMeta(string $file, bool $pretend, Closure $run): void
    {
        if ($pretend || ! preg_match('/_adopt_[a-z_]+_tables\.php\z/', $file) || ! str_contains($source = (string) file_get_contents($file), "'kit_meta'") || str_contains($source, "'app_meta'")) {
            $run();

            return;
        }
        $schema = $this->resolveConnection($this->resolvePath($file)->getConnection())->getSchemaBuilder();
        if ($schema->hasTable('kit_meta') || ! $schema->hasTable('app_meta')) {
            $run();

            return;
        }
        $schema->rename('app_meta', 'kit_meta');
        try {
            $run();
        } finally {
            $schema->rename('kit_meta', 'app_meta');
        }
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
