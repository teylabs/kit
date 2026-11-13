<?php

namespace Tey\Kit\Features;

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
 */
final class PackageMigrator extends Migrator
{
    /** The 0.18 renames, by feature. FeaturesServiceProvider runs them again when a migrate ends (renames()), so their order does not matter. */
    public const RENAMES = [
        'access' => '2026_11_14_000304_rename_access_tables',
        'session-ui' => '2026_11_14_000506_rename_session_ui_tables',
        'mcp' => '2026_11_14_000605_rename_mcp_tables',
    ];

    /** @var array<string, string> each package directory registered => the app's copies directory of that feature */
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
        $this->copies[self::directory($id)] = $copies;
    }

    /**
     * @param  string|array<int, string>  $paths
     * @return array<string, string>
     */
    public function getMigrationFiles($paths)
    {
        $paths = (array) $paths;
        $files = parent::getMigrationFiles(array_values(array_diff($paths, array_keys($this->copies))));
        foreach (array_intersect_key($this->copies, array_flip($paths)) as $directory => $copies) {
            $own = array_map(self::name(...), array_filter($files, fn (string $file): bool => dirname($file) === $copies));
            $files += array_filter(parent::getMigrationFiles([$directory]), fn (string $file): bool => ! in_array(self::name($file), $own, true));
        }

        return (new Collection($files))->sortBy(fn (string $file, string $name): string => $name)->all();
    }

    /**
     * Runs again each 0.18 rename of $features that the database has run (the migrations table names it): a no-op once its old tables are gone,
     * and a refusal when both names of a table exist. An app's copy dated after a rename (kit:add stamped copies with the day it ran) creates an
     * old name after it on a fresh database; this renames that too. A rename not run yet, or rolled back, is left to the migration.
     *
     * @param  list<string>  $features
     */
    public function renames(array $features): void
    {
        if (! $this->repositoryExists()) {
            return;
        }
        $ran = $this->repository->getRan();
        foreach (array_intersect_key(self::RENAMES, array_flip($features)) as $id => $name) {
            if (in_array($name, $ran, true)) {
                (require self::directory($id).'/'.$name.'.php')->up();
            }
        }
    }

    /** A migration file's name after its timestamp and any six-digit order: `create_kit_notes_table.php` for `0001_01_02_000701_…` and `2026_03_04_050607_000701_…` alike. */
    private static function name(string $file): string
    {
        return (string) preg_replace('/\A\d{4}_\d{2}_\d{2}_(?:\d{6}_)+/', '', basename($file));
    }
}
