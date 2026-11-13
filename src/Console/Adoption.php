<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\DomainModels;
use Throwable;

/**
 * `kit:scaffold <feature> --adopt` and `--check`: a feature's domain tables become the app's (since 0.19). Adopt writes the app's models (each uses the
 * kit's trait and implements its contract), ONE migration that renames the kit's tables on a database where they exist and creates them on a fresh one,
 * and the `kit.models` and `kit.tables` entries in config/kit.php. It refuses before writing anything: a feature that is not adoptable or is already
 * adopted, a file it would write that exists (unless --force names it), a config/kit.php it cannot edit exactly, and a database that already has
 * both names of a table. Check only reads: each table the kit reads for the feature, against the columns the kit relies on.
 */
final class Adoption
{
    public function __construct(private Command $command, private Application $app) {}

    /** @param  list<string>  $force */
    public function adopt(string $feature, array $force): int
    {
        $keys = self::keys($feature);
        if (DomainModels::adopted($feature)) {
            throw new RuntimeException('The '.$feature.' feature is already adopted: config/kit.php names the app\'s '.implode(', ', DomainModels::adoptedKeys($feature)).'. Nothing was written.');
        }
        $root = $this->app->basePath();
        $slug = str_replace('-', '_', $feature);
        foreach (glob($this->app->databasePath('migrations').'/*_adopt_'.$slug.'_tables.php') ?: [] as $existing) {
            throw new RuntimeException('database/migrations/'.basename($existing).' exists: the '.$feature.' feature was adopted before. Nothing was written.');
        }
        $namespace = rescue(fn (): string => $this->app->getNamespace(), 'App\\', false);
        $files = [];
        $entries = ['models' => [], 'tables' => []];
        foreach ($keys as $key => [$contract, , , $table, $class]) {
            if ($contract === null) {
                $entries['tables'][$key] = $table;

                continue;
            }
            $source = (string) file_get_contents(self::stubs().'/models/'.$class.'.php.stub');
            $files['app/Models/'.$class.'.php'] = str_replace('namespace App\\Models;', 'namespace '.$namespace.'Models;', $source);
            $entries['models'][$key] = $namespace.'Models\\'.$class;
        }
        $migration = 'database/migrations/'.date('Y_m_d_His').'_adopt_'.$slug.'_tables.php';
        $files[$migration] = (string) file_get_contents(self::stubs().'/migrations/adopt_'.$slug.'_tables.php.stub');
        foreach ($force as $path) {
            if (! isset($files[$path])) {
                throw new RuntimeException('--force='.$path.' names no file this run writes.');
            }
        }
        foreach (array_keys($files) as $path) {
            for ($folder = dirname($path); $folder !== '.'; $folder = dirname($folder)) {
                if (is_link($root.'/'.$folder)) {
                    throw new RuntimeException('Refusing to write through a linked folder: '.$folder.' (for '.$path.')');
                }
            }
            if ((file_exists($root.'/'.$path) || is_link($root.'/'.$path)) && ! in_array($path, $force, true)) {
                throw new RuntimeException($path.' exists, so nothing was written (--force='.$path.' overwrites it). An app class of that name that is not the kit\'s model keeps its name: write the model by hand with the kit\'s trait and contract instead.');
            }
        }
        $this->refuseBothNames($keys);
        [$configPath, $config] = $this->config($entries);
        foreach ($files as $path => $bytes) {
            $target = $root.'/'.$path;
            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException('Could not create the folder for '.$path);
            }
            if (file_put_contents($target, $bytes) === false) {
                throw new RuntimeException('Could not write '.$path);
            }
            $this->command->line('wrote '.$path);
        }
        if (file_put_contents($configPath, $config) === false) {
            throw new RuntimeException('Could not write config/kit.php');
        }
        $named = [...array_map(fn (string $key): string => 'kit.models.'.$key, array_keys($entries['models'])), ...array_map(fn (string $key): string => 'kit.tables.'.$key, array_keys($entries['tables']))];
        $this->command->line('updated config/kit.php ('.implode(', ', $named).')');
        $this->command->line('Review '.$migration.', then run php artisan migrate. It renames '.implode(', ', array_map(fn (array $entry): string => $entry[2].' to '.$entry[3], array_filter($keys, fn (array $entry): bool => $entry[2] !== $entry[3]))).' where they exist, and creates them where they do not.');

        return Command::SUCCESS;
    }

    /** Read-only: each table the kit reads for the feature, against the columns the kit relies on. Exit 1 when a table or a column is missing. */
    public function check(string $feature): int
    {
        $keys = self::keys($feature);
        if (! Kit::enabled($feature)) {
            throw new RuntimeException('The '.$feature.' feature is not in kit.features, so the kit reads none of its tables.');
        }
        $missing = 0;
        foreach (array_keys($keys) as $key) {
            $table = Kit::table($key);
            if (! Schema::hasTable($table)) {
                $this->command->line('missing table '.$table.' ('.$key.')');
                $missing++;

                continue;
            }
            $absent = array_values(array_diff(DomainModels::COLUMNS[$key], Schema::getColumnListing($table)));
            foreach ($absent as $column) {
                $this->command->line('missing '.$table.'.'.$column.' ('.$key.')');
            }
            $missing += count($absent);
            if ($absent === []) {
                $this->command->line('ok '.$table.' ('.$key.', '.count(DomainModels::COLUMNS[$key]).' columns)');
            }
        }
        $this->command->line($feature.($missing === 0 ? ': every table has the columns the kit relies on.' : ': '.$missing.' missing. The kit reads and writes these; add them in a migration of your own.').' '.(DomainModels::adopted($feature) ? 'Adopted: the app\'s tables.' : 'Not adopted: the kit\'s tables.'));

        return $missing === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array<string, array{0: ?string, 1: ?string, 2: string, 3: string, 4: ?string}> */
    private static function keys(string $feature): array
    {
        return DomainModels::FEATURES[$feature] ?? throw new RuntimeException(match ($feature) {
            'workspaces' => 'The workspaces table is already the app\'s: bind your own workspace model (concierge.models.workspace and multitenancy.tenant_model), with kit.workspaces.adopt for an existing table.',
            default => $feature.' has no domain tables to adopt. Adoptable: '.implode(', ', array_keys(DomainModels::FEATURES)).'.',
        });
    }

    private static function stubs(): string
    {
        return dirname(__DIR__, 2).'/resources/adopt';
    }

    /**
     * Refuses when the database already has both names of a table: the migration would refuse too, and the app has two tables to reconcile first.
     *
     * @param  array<string, array{0: ?string, 1: ?string, 2: string, 3: string, 4: ?string}>  $keys
     */
    private function refuseBothNames(array $keys): void
    {
        try {
            $both = array_filter($keys, fn (array $entry): bool => $entry[2] !== $entry[3] && Schema::hasTable($entry[2]) && Schema::hasTable($entry[3]));
        } catch (Throwable $exception) {
            $this->command->warn('Could not read the database ('.$exception::class.'), so both names were not checked here; the migration checks them before it renames anything.');

            return;
        }
        if ($both !== []) {
            throw new RuntimeException('Both names exist: '.implode('; ', array_map(fn (array $entry): string => $entry[2].' and '.$entry[3], $both)).'. The kit never merges two tables: keep the one that holds the data, drop or rename the other, then adopt again. Nothing was written.');
        }
    }

    /**
     * config/kit.php with the entries set (published first when the app has none), and verified by loading it: each entry must read back as written,
     * or nothing is written and the entries are printed for the app to add by hand.
     *
     * @param  array{models: array<string, string>, tables: array<string, string>}  $entries
     * @return array{string, string}
     */
    private function config(array $entries): array
    {
        $path = $this->app->configPath('kit.php');
        $source = is_file($path) ? (string) file_get_contents($path) : (string) file_get_contents(dirname(__DIR__, 2).'/config/kit.php');
        $edited = $source;
        foreach ($entries as $array => $values) {
            // Each new entry goes at its array's start, so the last first keeps the entries in their order.
            foreach (array_reverse($values, true) as $key => $value) {
                $literal = $array === 'models' ? '\\'.$value.'::class' : var_export($value, true);
                $edited = self::set($edited, $array, $key, $literal);
            }
        }
        $loaded = null;
        $probe = tempnam(sys_get_temp_dir(), 'kit-config-');
        try {
            file_put_contents((string) $probe, $edited);
            $loaded = rescue(fn (): mixed => (static fn (string $file): mixed => require $file)((string) $probe), null, false);
        } finally {
            @unlink((string) $probe);
        }
        foreach ($entries as $array => $values) {
            foreach ($values as $key => $value) {
                $read = is_array($loaded) && is_array($loaded[$array] ?? null) ? ($loaded[$array][$key] ?? null) : null;
                if (! is_string($read) || ltrim($read, '\\') !== $value) {
                    $lines = [];
                    foreach ($entries as $a => $v) {
                        foreach ($v as $k => $x) {
                            $lines[] = "'".$a."' => ['".$k."' => ".($a === 'models' ? '\\'.$x.'::class' : var_export($x, true)).']';
                        }
                    }
                    throw new RuntimeException('Could not set kit.'.$array.'.'.$key.' in config/kit.php exactly, so nothing was written. Set these yourself, then run kit:scaffold again with --force for the files it reports: '.implode(', ', $lines));
                }
            }
        }
        if (! is_file($path)) {
            $this->command->line('published config/kit.php (the kit\'s defaults)');
        }

        return [$path, $edited];
    }

    /** $source with `'$key' => $literal` in its top-level `$array` array: the one entry already there replaced, else added at the array's start. */
    private static function set(string $source, string $array, string $key, string $literal): string
    {
        $entry = "/'".preg_quote($key, '/')."'\\s*=>\\s*[^,\\]\\n]+/";
        $count = preg_match_all($entry, $source);
        if ($count === 1) {
            return (string) preg_replace($entry, "'".$key."' => ".addcslashes($literal, '\\$'), $source);
        }
        if ($count > 1) {
            return $source;
        }
        $opening = "/^(    '".$array."' => \\[)(\\n?)(\\s*)/m";
        if (preg_match($opening, $source, $match) === 1) {
            $separator = $match[2] === "\n" ? "\n".$match[3] : ' ';

            return (string) preg_replace_callback($opening, fn (array $m): string => $m[1].$m[2].$m[3]."'".$key."' => ".$literal.','.$separator, $source, 1);
        }
        if ($array === 'tables' && preg_match("/^    'models' => \\[/m", $source) === 1) {
            return (string) preg_replace("/^(    'models' => \\[)/m", "    'tables' => ['".$key."' => ".addcslashes($literal, '\\$')."],\n\n$1", $source, 1);
        }

        return $source;
    }
}
