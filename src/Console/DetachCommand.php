<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\FeatureSwitch;
use Tey\Kit\LifecycleHost;
use Tey\Mod\Maintenance\Checkout;
use Throwable;

/**
 * The one-off before tey/kit 0.18, which reads the features only from `kit.features` and retires the installed records. It writes into
 * config/kit.php what the app boots from its records today (the features, in catalog order, and the adopt flags they turn on), so nothing changes
 * at runtime; reports each file the records list and each migration copy against the kit's stub; and prints what to delete by hand. It deletes
 * nothing. The answers come from the same reads the composition root makes (FeaturesServiceProvider::discover(), boots() and adopts()).
 */
final class DetachCommand extends Command
{
    /** Besides .github/workflows and composer.json's scripts: where a host's CI or deploy may run kit:status, kit:update or kit:cache. */
    private const DEPLOY = ['.gitlab-ci.yml', 'bitbucket-pipelines.yml', '.circleci', 'Envoy.blade.php', 'deploy.php', 'deploy.sh', 'deploy', 'scripts', 'Dockerfile', 'docker', '.docker', 'docker-compose.yml', 'compose.yaml', 'fly.toml', 'nixpacks.toml', 'Procfile', 'render.yaml', 'vapor.yml', '.platform', '.ebextensions', 'Makefile'];

    protected $signature = 'kit:detach {--dry-run : Print what would be written, and write nothing} {--force : Replace a kit.features list in config/kit.php that differs from the records} {--json : Print the report as JSON}';

    protected $description = 'Prepare for tey/kit 0.18: write kit.features and the adopt flags from the installed records into config/kit.php, report the recorded files and migration copies, and list what to delete (deletes nothing)';

    public function handle(): int
    {
        $root = $this->laravel->basePath();
        try {
            $appId = config('kit.app_id') ?? ($this->laravel->bound(LifecycleHost::class) ? $this->laravel->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id'));
            if (! is_string($appId) || $appId === '') {
                throw new RuntimeException('no kit app id (kit.app_id, the LifecycleHost binding or kit-lifecycle.app_id)');
            }
            $pending = Checkout::pending($root, $appId);
            if ($pending !== []) {
                throw new RuntimeException('an update is unfinished ('.implode(', ', array_column($pending, 'name')).'); finish it with kit:update resume first');
            }
            $entry = FeaturesServiceProvider::discover($root, $appId);
            if ($entry['lineages'] === []) {
                throw new RuntimeException('no installed tey/kit lineage');
            }
            if ($entry['tables'] === null) {
                throw new RuntimeException('the workspaces lineage\'s adopted tables could not be read');
            }
            $features = FeaturesServiceProvider::boots($entry);
            $adopts = FeaturesServiceProvider::adopts($entry['tables']);
            $report = ['app_id' => $appId, 'features' => $features, 'adopt' => $adopts, 'lineages' => $this->lineages($root, $appId), 'migrations' => $this->migrations($root)];
        } catch (Throwable $exception) {
            $this->error('The installed records could not be read: '.$exception->getMessage().'. Nothing written.');

            return self::FAILURE;
        }
        $report['config'] = $this->config($root, $entry, $features, $adopts);
        $report['delete'] = $this->checklist($root, $report['migrations']);
        $report['references'] = $this->references($root);
        $this->option('json') ? $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) : $this->describe($report);

        return in_array($report['config']['status'], ['refused', 'manual'], true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Each installed lineage's files: an owned file is `unchanged` (its bytes are the recorded ones), `edited` or `missing`; a file the record lists
     * as the app's (kit:update keep's `held`, or kit:adopt's `kept`) is `kept`, or `missing`. kit:update claim leaves no mark: a claimed file is
     * owned again at its claimed bytes, so it reads as unchanged until edited.
     *
     * @return list<array{lineage: string, version: string, files: array<string, string>}>
     */
    private function lineages(string $root, string $appId): array
    {
        $lineages = [];
        foreach (array_keys(Checkout::installed($root, $appId)) as $name) {
            $record = Checkout::artifact($root, $appId, $name) ?? throw new RuntimeException('no record for '.$name);
            $files = [];
            foreach ($record->files as $path => $bytes) {
                $files[$path] = self::state($root.'/'.$path, $bytes);
            }
            foreach (['held', 'kept'] as $key) {
                foreach (array_keys(is_array($record->metadata[$key] ?? null) ? $record->metadata[$key] : []) as $path) {
                    $files[$path] = file_exists($root.'/'.$path) || is_link($root.'/'.$path) ? 'kept' : 'missing';
                }
            }
            ksort($files);
            $lineages[] = ['lineage' => $name, 'version' => $record->version, 'files' => $files];
        }

        return $lineages;
    }

    private static function state(string $file, string $bytes): string
    {
        if (! file_exists($file) && ! is_link($file)) {
            return 'missing';
        }

        return ! is_link($file) && is_file($file) && file_get_contents($file) === $bytes ? 'unchanged' : 'edited';
    }

    /**
     * Each copy under database/migrations/kit/<feature>/: `identical` to the kit's stub of the same name, rendered as kit:add rendered it
     * (Blueprint::lineage()), `edited`, or `not the kit's` when the package has no such stub. An adopting host's copy carries kit:add's stamp in
     * place of the catalog's 0001_01_02 date (Lineages::stampedPath()); it is compared with the stub under its catalog name.
     *
     * @return list<array{feature: string, path: string, status: string, stub: ?string, stamped: bool}>
     */
    private function migrations(string $root): array
    {
        $copies = [];
        $known = Catalog::package()->featureIds();
        foreach (glob($root.'/database/migrations/kit/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $id = basename($directory);
            $stubs = [];
            if (in_array($id, $known, true)) {
                $lineage = Blueprint::lineage($id);
                foreach ($lineage['files'] as $path => $bytes) {
                    if (dirname($path) === 'database/migrations/kit/'.$id) {
                        $stubs[basename($path)] = ['bytes' => $bytes, 'stub' => 'resources/features/'.$id.'/'.$lineage['feature']['files'][$path]['stub']];
                    }
                }
            }
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $name = basename($file);
                $catalog = preg_match('/\A\d{4}_\d{2}_\d{2}_\d{6}_(\d{6}_[a-z0-9_]+\.php)\z/', $name, $match) ? '0001_01_02_'.$match[1] : $name;
                $stub = $stubs[$catalog] ?? null;
                $status = $stub === null ? 'not the kit\'s' : (! is_link($file) && file_get_contents($file) === $stub['bytes'] ? 'identical' : 'edited');
                $copies[] = ['feature' => $id, 'path' => 'database/migrations/kit/'.$id.'/'.$name, 'status' => $status, 'stub' => $stub['stub'] ?? null, 'stamped' => $catalog !== $name];
            }
        }

        return $copies;
    }

    /**
     * config/kit.php gets `'features' => [...]` and, for each adopt flag the records turn on, `env('KIT_<KEY>_ADOPT', true)` in place of the published
     * `env('KIT_<KEY>_ADOPT')`: where the variable is set it still wins, as it does today, and elsewhere the flag is the record's true. Each edit is
     * an anchored replacement and the rest of the file stays byte for byte; with no config/kit.php, the kit-config stub is published first. Nothing
     * is written when any edit cannot be made, or when the file's own `kit.features` boots other features than the records (unless --force).
     *
     * @param  array{lineages: list<string>, carry: bool, tables: list<string>|null}  $entry
     * @param  list<string>  $features
     * @param  list<string>  $adopts
     * @return array{path: string, status: string, current: mixed, records: list<string>, adopt: array<string, string>, published: bool, message?: string}
     */
    private function config(string $root, array $entry, array $features, array $adopts): array
    {
        $path = $root.'/config/kit.php';
        $published = ! file_exists($path) && ! is_link($path);
        $result = ['path' => 'config/kit.php', 'status' => 'manual', 'current' => null, 'records' => $features, 'adopt' => [], 'published' => $published];
        if (! $published && (is_link($path) || ! is_file($path))) {
            return $result + ['message' => 'config/kit.php is not a regular file. Set \'features\' => ['.self::ids($features).'] in it yourself.'];
        }
        $source = $published ? dirname(__DIR__, 2).'/config/kit.php' : $path;
        $before = (string) file_get_contents($source);
        try {
            $read = (static fn (string $file): mixed => require $file)($source);
        } catch (Throwable $exception) {
            return $result + ['message' => 'config/kit.php could not be read ('.$exception->getMessage().'). Nothing written.'];
        }
        $current = is_array($read) ? ($read['features'] ?? null) : null;
        $result['current'] = $current;
        $manual = [];
        $after = $before;
        if ($current !== $features) {
            if ($current !== null) {
                try {
                    $boots = is_array($current) && array_is_list($current) && array_filter($current, 'is_string') === $current ? FeaturesServiceProvider::boots($entry, $current) : throw new RuntimeException('not a list of feature ids');
                } catch (Throwable $exception) {
                    $boots = $exception->getMessage();
                }
                if ($boots !== $features && ! $this->option('force')) {
                    return ['status' => 'refused', 'message' => 'config/kit.php sets kit.features to '.(is_array($current) ? '['.self::ids($current).']' : var_export($current, true)).', which '.(is_array($boots) ? 'boots ['.self::ids($boots).']' : 'cannot boot ('.$boots.')').'. The records say ['.self::ids($features).']. Nothing written. Make the list match the records, or run again with --force to write the records\' list.'] + $result;
                }
            }
            // The one `features` key: a literal list of ids (rewritten in its own layout) or null (the published default).
            $list = "/(['\"])features\\1\\s*=>\\s*\\[([^\\]]*)\\]/";
            $null = "/^([ \\t]*)(['\"])features\\2\\s*=>\\s*(null)\\b/m";
            if (preg_match_all("/(['\"])features\\1\\s*=>/", $before) === 1 && preg_match($list, $before, $match, PREG_OFFSET_CAPTURE) === 1 && ($items = FeatureSwitch::relist($match[2][0], $features)) !== null) {
                $after = substr_replace($after, $items, $match[2][1], strlen($match[2][0]));
            } elseif (preg_match_all("/(['\"])features\\1\\s*=>/", $before) === 1 && preg_match($null, $before, $match, PREG_OFFSET_CAPTURE) === 1) {
                $after = substr_replace($after, '['.FeatureSwitch::relist("\n".$match[1][0], $features).']', $match[3][1], 4);
            } else {
                $manual[] = 'Set \'features\' => ['.self::ids($features).'] in config/kit.php (its features entry is not a literal list or null).';
            }
        }
        foreach ($adopts as $key) {
            $env = 'KIT_'.strtoupper($key).'_ADOPT';
            $anchor = "/(['\"])adopt\\1(\\s*=>\\s*)env\\((['\"])".$env.'\\3\\)/';
            if (preg_match_all($anchor, $after) === 1) {
                $after = (string) preg_replace_callback($anchor, fn (array $m): string => $m[1].'adopt'.$m[1].$m[2].'env('.$m[3].$env.$m[3].', true)', $after, 1);
                $result['adopt'][$key] = 'written';

                continue;
            }
            $value = is_array($read) && is_array($read[$key] ?? null) ? ($read[$key]['adopt'] ?? null) : null;
            if (is_bool($value)) {
                // The app sets it: its value wins over the record today, and keeps winning.
                $result['adopt'][$key] = $value ? 'set' : 'kept (the app sets false, which wins over the record)';
            } else {
                $manual[] = 'Set \''.$key.'\' => [\'adopt\' => env(\''.$env.'\', true)] in config/kit.php, keeping the rest of that array (the records say the host owns its '.($key === 'access' ? 'permission' : 'workspaces').' tables).';
            }
        }
        if ($manual !== []) {
            return ['status' => 'manual', 'message' => implode(' ', $manual).' Nothing written.'] + $result;
        }
        if ($after === $before && ! $published) {
            return ['status' => 'unchanged'] + $result;
        }
        if ($this->option('dry-run')) {
            return ['status' => $published ? 'would publish and write' : 'would write'] + $result;
        }
        if ($published && ! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $after);
        try {
            $written = (static fn (string $file): mixed => require $file)($path);
            $ok = is_array($written) && ($written['features'] ?? null) === $features;
            foreach (array_keys(array_filter($result['adopt'], fn (string $state): bool => $state === 'written')) as $key) {
                $ok = $ok && ($written[$key]['adopt'] ?? null) === env('KIT_'.strtoupper($key).'_ADOPT', true);
            }
        } catch (Throwable) {
            $ok = false;
        }
        if (! $ok) {
            $published ? unlink($path) : file_put_contents($path, $before);

            return ['status' => 'manual', 'message' => 'The edit to config/kit.php did not read back as the records; it was undone. Set \'features\' => ['.self::ids($features).'] yourself.'] + $result;
        }

        return ['status' => $published ? 'published and written' : 'written'] + $result;
    }

    /**
     * What to delete by hand once 0.18 is in: each path present, the provider line and the .gitignore lines with their line numbers, and the
     * identical migration copies (0.18 loads those from the package).
     *
     * @param  list<array{path: string, status: string}>  $migrations
     * @return list<string>
     */
    private function checklist(string $root, array $migrations): array
    {
        $delete = file_exists($root.'/.mod-maintenance') || is_link($root.'/.mod-maintenance') ? ['.mod-maintenance/'] : [];
        foreach (array_keys(InstallCommand::FILES) as $path) {
            if (file_exists($root.'/'.$path) || is_link($root.'/'.$path)) {
                $delete[] = $path;
            }
        }
        foreach (self::lines($root.'/bootstrap/providers.php', InstallCommand::providerLine()) as $number => $line) {
            $delete[] = 'bootstrap/providers.php:'.$number.': '.trim($line);
        }
        foreach (self::lines($root.'/.gitignore', '/\.mod-maintenance/') as $number => $line) {
            $delete[] = '.gitignore:'.$number.': '.trim($line);
        }
        foreach ($migrations as $copy) {
            if ($copy['status'] === 'identical') {
                $delete[] = $copy['path'];
            }
        }

        return $delete;
    }

    /**
     * Each kit:status, kit:update or kit:cache in composer.json's scripts, .github/workflows and the deploy files in DEPLOY: 0.18 removes the first
     * two and keeps kit:cache as a no-op.
     *
     * @return list<string>
     */
    private function references(string $root): array
    {
        $pattern = '/\bkit:(?:status|update|cache)\b/';
        $found = [];
        $composer = is_file($root.'/composer.json') ? json_decode((string) file_get_contents($root.'/composer.json'), true) : null;
        foreach (is_array($composer) && is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [] as $name => $commands) {
            foreach ((array) $commands as $command) {
                if (is_string($command) && preg_match($pattern, $command) === 1) {
                    $found[] = 'composer.json scripts.'.$name.': '.$command;
                }
            }
        }
        foreach (['.github/workflows', ...self::DEPLOY] as $entry) {
            $paths = is_dir($root.'/'.$entry) && ! is_link($root.'/'.$entry) ? self::tree($root, $entry) : [$entry];
            foreach ($paths as $relative) {
                foreach (self::lines($root.'/'.$relative, $pattern) as $number => $line) {
                    $found[] = $relative.':'.$number.': '.trim($line);
                }
            }
        }

        return $found;
    }

    /** @return list<string> every regular file under the directory, relative to the root */
    private static function tree(string $root, string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    /** @return array<int, string> each line of a regular text file matching the pattern, by its line number */
    private static function lines(string $file, string $pattern): array
    {
        if (is_link($file) || ! is_file($file) || filesize($file) > 1_048_576) {
            return [];
        }
        $lines = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $lines[$index + 1] = $line;
            }
        }

        return $lines;
    }

    /** @param  list<string>  $ids */
    private static function ids(array $ids): string
    {
        return implode(', ', $ids);
    }

    private function describe(array $report): void
    {
        $config = $report['config'];
        $this->line('kit:detach for app '.$report['app_id'].', read from the installed records. Nothing is deleted.');
        $this->newLine();
        $this->line('Features the app boots today: '.self::ids($report['features']));
        $this->line('Host tables adopted: '.($report['adopt'] === [] ? 'none' : implode(', ', array_map(fn (string $key): string => 'kit.'.$key.'.adopt', $report['adopt']))));
        $verb = ['written' => 'written', 'published and written' => 'published (the kit-config stub), then written', 'would write' => 'would be written (--dry-run)', 'would publish and write' => 'would be published and written (--dry-run)', 'unchanged' => 'already says so; unchanged'][$config['status']] ?? null;
        if ($verb !== null) {
            $this->line('config/kit.php '.$verb.": 'features' => [".self::ids($report['features']).']'.implode('', array_map(fn (string $key, string $state): string => ', kit.'.$key.'.adopt '.$state, array_keys($config['adopt']), $config['adopt'])).'.');
        } else {
            $this->error('config/kit.php: '.$config['message']);
            if ($config['status'] === 'refused') {
                $this->line('  config/kit.php: '.(is_array($config['current']) ? '['.self::ids($config['current']).']' : var_export($config['current'], true)));
                $this->line('  the records:    ['.self::ids($config['records']).']');
            }
        }
        $this->newLine();
        $this->line('Files the records list (unchanged, edited, kept or missing):');
        foreach ($report['lineages'] as $lineage) {
            $this->line('  '.$lineage['lineage'].' '.$lineage['version']);
            foreach ($lineage['files'] as $path => $state) {
                $this->line('    '.str_pad($state, 10).$path);
            }
        }
        $this->newLine();
        $this->line('Migration copies (identical ones load from the package in 0.18):');
        foreach ($report['migrations'] as $copy) {
            $this->line('  '.str_pad($copy['status'], 14).$copy['path'].($copy['stamped'] ? ' (stamped by kit:add)' : ''));
        }
        if ($report['migrations'] === []) {
            $this->line('  none');
        }
        $this->newLine();
        $this->line('Delete by hand, after moving to 0.18:');
        foreach ($report['delete'] as $item) {
            $this->line('  - '.$item);
        }
        $this->newLine();
        $this->line('kit:status, kit:update and kit:cache references (0.18 removes kit:status and kit:update; kit:cache stays, as a no-op):');
        foreach ($report['references'] === [] ? ['none found'] : $report['references'] as $reference) {
            $this->line('  '.$reference);
        }
    }
}
