<?php

namespace Tey\Kit;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tey\Mod\Maintenance\FilePlan;
use Throwable;

/**
 * What a host must do beyond the files a plan writes: the features' declared host steps, their npm requirements and
 * their optional integrations. Each is verified where the kit can and printed, never written: nothing here edits
 * package.json, .env.example, providers or the schedule, and nothing reads .env.
 *
 * Only `user_model` (Lineages::unmet), a new migration creating a table the host already has (collisions()) and a missing
 * host UI primitive (`host_ui`, hostUi()) block a plan; every other unmet step or npm requirement is reported as attention.
 */
final class HostRequirements
{
    /** Host step kinds the kit can verify, beyond the blocking `user_model`. */
    private const LISTED = ['env' => 'key', 'provider' => 'class', 'schedule' => 'command', 'config' => 'key', 'legacy_table' => 'table'];

    /** Fields a listed step may add: an env key may instead be kept as a file the host has (checked for existence only, never read); a config key names the value it must `not` have. */
    private const EXTRA = ['env' => ['or_file' => true], 'config' => ['not' => true]];

    /** Refuses a malformed `host_steps`, `optional` or `requires.npm`; every field is optional. */
    public static function validate(string $id, array $feature): void
    {
        foreach ($feature['host_steps'] ?? [] as $kind => $step) {
            $field = self::LISTED[$kind] ?? null;
            $valid = match (true) {
                $kind === 'user_model' => is_array($step) && array_diff_key($step, ['implements' => true, 'uses' => true]) === [] && is_string($step['implements'] ?? null) && is_string($step['uses'] ?? null),
                $field !== null => is_array($step) && $step !== [] && array_is_list($step) && array_filter($step, fn ($entry): bool => ! is_array($entry) || array_diff_key($entry, [$field => true, 'why' => true] + (self::EXTRA[$kind] ?? [])) !== [] || ! is_string($entry[$field] ?? null) || $entry[$field] === '' || ! is_string($entry['why'] ?? null)) === [],
                default => false,
            };
            if (! $valid || ($kind === 'env' && array_filter($step, fn (array $entry): bool => ! preg_match('/\A[A-Z][A-Z0-9_]*\z/', $entry['key']) || (array_key_exists('or_file', $entry) && ! self::relative($entry['or_file']))) !== [])
                || ($kind === 'config' && array_filter($step, fn (array $entry): bool => ! is_string($entry['not'] ?? null)) !== [])) {
                throw new RuntimeException('Invalid host step: '.$id.'.'.$kind);
            }
        }
        $optional = $feature['optional'] ?? [];
        if (! is_array($optional) || ! array_is_list($optional) || array_filter($optional, fn ($entry): bool => ! is_array($entry) || array_keys($entry) !== ['id', 'title', 'do'] || array_filter($entry, fn ($value): bool => ! is_string($value) || $value === '') !== []) !== []) {
            throw new RuntimeException('Invalid optional integration: '.$id);
        }
        foreach ($feature['requires']['npm'] ?? [] as $package => $constraint) {
            if (! is_string($package) || $package === '' || ! is_string($constraint) || ! self::range($constraint)) {
                throw new RuntimeException('Invalid npm requirement: '.$id.'.'.$package);
            }
        }
        $ui = $feature['requires']['host_ui'] ?? [];
        if (! is_array($ui) || ! array_is_list($ui) || array_filter($ui, fn ($entry): bool => ! is_string($entry) || preg_match(self::HOST_UI, $entry) !== 1) !== []) {
            throw new RuntimeException('Invalid host UI requirement: '.$id);
        }
    }

    /** A host UI primitive: a shadcn-vue component name (`dropdown-menu`, met by its index.ts) or one file in it (`spinner/Spinner.vue`). */
    public const HOST_UI = '/\A[a-z][a-z0-9-]*(?:\/[A-Za-z][A-Za-z0-9]*\.(?:vue|ts))?\z/';

    /** Where the host's UI primitives live: the `@/components/ui` the kit's stubs import from. */
    public const HOST_UI_ROOT = 'resources/js/components/ui';

    /**
     * Each host UI primitive the given entries name, checked: `<name>` is met by HOST_UI_ROOT/<name>/index.ts, `<name>/<File>` by that file. `blocking` is the one
     * message (empty when everything is there) naming the missing primitives and the exact command that adds them; a missing primitive is a broken build, so it blocks.
     *
     * @param  list<string>  $entries
     * @return array{primitives: list<array{primitive: string, path: string, status: string}>, blocking: list<string>}
     */
    public static function hostUi(string $root, array $entries): array
    {
        $primitives = [];
        $missing = [];
        foreach (array_values(array_unique($entries)) as $entry) {
            $path = self::HOST_UI_ROOT.'/'.(str_contains($entry, '/') ? $entry : $entry.'/index.ts');
            $met = ! is_link($root.'/'.$path) && is_file($root.'/'.$path);
            $primitives[] = ['primitive' => $entry, 'path' => $path, 'status' => $met ? 'met' : 'missing'];
            if (! $met) {
                $missing[] = explode('/', $entry)[0];
            }
        }
        $missing = array_values(array_unique($missing));
        sort($missing);

        return ['primitives' => $primitives, 'blocking' => $missing === [] ? [] : ['host_ui: the kit\'s components import UI primitives this app does not have ('.implode(', ', $missing).', under '.self::HOST_UI_ROOT.'). Add them first: npx shadcn-vue@latest add '.implode(' ', $missing)]];
    }

    /**
     * The host UI primitives a set of files imports (`@/components/ui/<name>` or `@/components/ui/<name>/<File>`), in the same terms as a feature's `host_ui`.
     *
     * @param  array<string, string>  $files  path => bytes
     * @return list<string>
     */
    public static function importedHostUi(array $files): array
    {
        $entries = [];
        foreach ($files as $path => $bytes) {
            if (! preg_match('/\.(?:vue|ts)\z/', $path)) {
                continue;
            }
            preg_match_all("/from\s+['\"]@\/components\/ui\/([a-z][a-z0-9-]*(?:\/[A-Za-z][A-Za-z0-9]*\.(?:vue|ts))?)(?:\/index(?:\.ts)?)?['\"]/", $bytes, $matches);
            array_push($entries, ...$matches[1]);
        }
        $entries = array_values(array_unique($entries));
        sort($entries);

        return $entries;
    }

    /**
     * The requirements of the given features, checked against the host at $root.
     *
     * @param  array<string, array>  $features  feature id => manifest, in requires order
     * @return array{steps: list<array>, npm: array<string, array>, optional: list<array>, attention: bool, warnings: list<string>}
     */
    public static function of(string $root, array $features): array
    {
        $steps = [];
        $npm = [];
        $optional = [];
        $declared = self::declared($root);
        foreach ($features as $id => $feature) {
            foreach ($feature['host_steps'] ?? [] as $kind => $entries) {
                // user_model is checked, and blocks, in Lineages::unmet.
                if ($kind === 'user_model') {
                    continue;
                }
                foreach ($entries as $entry) {
                    $steps[] = self::step($root, $id, $kind, $entry);
                }
            }
            foreach ($feature['requires']['npm'] ?? [] as $package => $constraint) {
                $npm[$package] = self::npm($root, $package, $constraint, $declared[$package] ?? null) + ['feature' => $id];
            }
            foreach ($feature['optional'] ?? [] as $entry) {
                $optional[] = ['feature' => $id] + $entry + ['status' => 'manual'];
            }
        }
        $warnings = [];
        foreach ($steps as $step) {
            if ($step['status'] === 'unmet') {
                $warnings[] = $step['message'];
            }
        }
        foreach ($npm as $package => $outcome) {
            if ($outcome['status'] !== 'passed') {
                $warnings[] = match ($outcome['status']) {
                    'missing' => 'npm package '.$package.' '.$outcome['constraint'].' is not in package.json. Add it: npm install '.$package.'@"'.$outcome['constraint'].'"',
                    'outdated' => 'npm package '.$package.' is declared as '.$outcome['declared'].' in package.json, which allows versions below '.$outcome['constraint'].'. Raise it.',
                    default => 'npm package '.$package.' is declared as '.$outcome['declared'].', which the kit cannot check against '.$outcome['constraint'].'. Check it by hand.',
                };
            } elseif ($outcome['installed_status'] === 'outdated') {
                $warnings[] = 'npm package '.$package.' '.$outcome['installed'].' is installed in node_modules, below '.$outcome['constraint'].'. Run npm install.';
            }
        }

        if (isset($npm['@inertiajs/vue3'])) {
            array_push($warnings, ...self::inertiaCopies($root, $declared));
        }

        return ['steps' => $steps, 'npm' => $npm, 'optional' => $optional, 'attention' => $warnings !== [], 'warnings' => $warnings];
    }

    /**
     * Inertia's Vue adapter (`@inertiajs/vue3`) pins `@inertiajs/core` exactly. A host that also declares `@inertiajs/core` can end up with a second copy (the top-level
     * one npm resolved, and vue3's own nested under it), and the two copies' types disagree: PageProps augmentation stops applying. Attention, never blocking:
     * removing the host's own `@inertiajs/core` line (or matching vue3's exact version) fixes it.
     *
     * @param  array<string, string>  $declared
     * @return list<string>
     */
    private static function inertiaCopies(string $root, array $declared): array
    {
        $version = function (string $manifest): ?string {
            if (is_link($manifest) || ! is_file($manifest)) {
                return null;
            }
            $value = json_decode((string) file_get_contents($manifest), true, 32)['version'] ?? null;

            return is_string($value) ? $value : null;
        };
        $nested = $version($root.'/node_modules/@inertiajs/vue3/node_modules/@inertiajs/core/package.json');
        $top = $version($root.'/node_modules/@inertiajs/core/package.json');
        if ($nested !== null && $top !== null && $nested !== $top) {
            return ['Two copies of @inertiajs/core are installed ('.$top.' and '.$nested.', which @inertiajs/vue3 pins), so their types disagree. Remove @inertiajs/core from package.json (or set it to exactly '.$nested.') and run npm install.'];
        }
        if (isset($declared['@inertiajs/core'])) {
            // The exact version vue3 pins (from its installed manifest, else the host's own exact vue3 version) is one copy, not two.
            $pinned = null;
            $manifest = $root.'/node_modules/@inertiajs/vue3/package.json';
            if (! is_link($manifest) && is_file($manifest)) {
                $pin = json_decode((string) file_get_contents($manifest), true, 32)['dependencies']['@inertiajs/core'] ?? null;
                $pinned = is_string($pin) ? $pin : null;
            }
            $pinned ??= preg_match('/\A\d+\.\d+\.\d+\z/', $declared['@inertiajs/vue3'] ?? '') === 1 ? $declared['@inertiajs/vue3'] : null;
            if ($pinned !== null && trim($declared['@inertiajs/core']) === $pinned) {
                return [];
            }

            return ['package.json declares @inertiajs/core ('.$declared['@inertiajs/core'].'), which @inertiajs/vue3 already pins exactly; a range that resolves to another version installs a second copy. Remove it from package.json.'];
        }

        return [];
    }

    /**
     * The part a reviewed plan binds: each step's status, and each npm requirement's constraint, declared range and status.
     * The installed version is reported but not bound, so an `npm ci` between preview and apply does not refuse the plan.
     */
    public static function bound(array $requirements): array
    {
        return [
            array_map(fn (array $step): array => [$step['feature'], $step['kind'], $step['step'], $step['status']], $requirements['steps']),
            array_map(fn (array $outcome): array => [$outcome['constraint'], $outcome['declared'], $outcome['status']], $requirements['npm']),
        ];
    }

    /** The text plan lines: an ATTENTION block first, when anything needs the host, saying whether the plan can still apply. */
    public static function attention(array $requirements): array
    {
        if (! $requirements['attention']) {
            return [];
        }

        $verdict = ($requirements['executable'] ?? true) ? 'Nothing here blocks the apply.' : 'The plan is not executable: it has a blocking unmet step.';

        return ['ATTENTION: the host must act before this plan works. '.$verdict, ...array_map(fn (string $warning): string => '  ! '.$warning, $requirements['warnings'])];
    }

    /** The text plan lines for steps, npm requirements and optional integrations. */
    public static function lines(array $requirements): array
    {
        $lines = [];
        foreach ($requirements['steps'] as $step) {
            $lines[] = 'step ['.$step['status'].'] '.$step['feature'].' '.$step['kind'].': '.$step['step'].($step['status'] === 'met' ? ' ('.$step['message'].')' : '');
        }
        foreach ($requirements['npm'] as $package => $outcome) {
            $lines[] = 'npm ['.$outcome['status'].'] '.$package.' '.$outcome['constraint'].' (package.json: '.($outcome['declared'] ?? 'none').', installed: '.($outcome['installed'] ?? 'none').')';
        }
        foreach ($requirements['optional'] as $entry) {
            $lines[] = 'optional '.$entry['feature'].' '.$entry['id'].': '.$entry['title'].'. '.$entry['do'];
        }

        return $lines;
    }

    /**
     * The migrations among a plan's files: new ones it creates, changed ones it replaces, and the rest that already exist.
     *
     * @param  array<string, array{state: string}>  $files
     * @return array{new: list<string>, changed: list<string>, existing: list<string>}
     */
    public static function migrations(array $files): array
    {
        $migrations = ['new' => [], 'changed' => [], 'existing' => []];
        foreach ($files as $path => $file) {
            if (str_starts_with($path, 'database/migrations/') && ! in_array($file['state'], ['remove', 'release', 'gone'], true)) {
                $migrations[match ($file['state']) {
                    'create' => 'new',
                    'replace' => 'changed',
                    default => 'existing',
                }][] = $path;
            }
        }

        return $migrations;
    }

    /**
     * A blocking step for each table a new migration in the plan creates that the host database already has: migrate
     * would fail on it. Parsed from `Schema::create('<name>'` in the migration's bytes; a database the kit cannot
     * reach is not checked.
     *
     * @param  array<string, array{state: string}>  $files
     * @param  array<string, string>  $bytes  the target's path => bytes
     * @return list<string>
     */
    public static function collisions(array $files, array $bytes): array
    {
        $collisions = [];
        foreach (self::migrations($files)['new'] as $path) {
            // A migration the host already ran (the same file, restored or re-added) is skipped by migrate, so its tables are not a collision.
            try {
                if (DB::table((string) config('database.migrations.table', 'migrations'))->where('migration', basename($path, '.php'))->exists()) {
                    continue;
                }
            } catch (Throwable) {
            }
            preg_match_all('/Schema::create\(\s*[\'"]([^\'"]+)[\'"]/', $bytes[$path] ?? '', $matches);
            foreach ($matches[1] as $table) {
                try {
                    $exists = Schema::hasTable($table);
                } catch (Throwable) {
                    continue;
                }
                if ($exists) {
                    $collisions[] = 'The host database already has a '.$table.' table, which '.$path.' creates. Rename or drop the host table first; migrate would fail.';
                }
            }
        }

        return $collisions;
    }

    /** A plan result with each collision() as a blocking unmet step: kit:update and kit:adopt plans refuse it, as kit:add does. */
    public static function guarded(array $result, FilePlan $plan, ?string $root = null): array
    {
        $collisions = self::collisions($plan->files, $plan->target->files);
        if ($collisions !== []) {
            $result['executable'] = false;
            $result['unmet'] = [...$result['unmet'] ?? [], ...$collisions];
        }
        // The UI primitives the files this plan keeps import must be in the host: without them the build breaks.
        if ($root !== null) {
            $kept = array_filter($plan->target->files, fn (string $path): bool => ! in_array($plan->files[$path]['state'] ?? null, ['remove', 'release', 'gone'], true), ARRAY_FILTER_USE_KEY);
            $ui = self::hostUi($root, self::importedHostUi($kept));
            $result['host_ui'] = $ui['primitives'];
            if ($ui['blocking'] !== []) {
                $result['executable'] = false;
                $result['unmet'] = [...$result['unmet'] ?? [], ...$ui['blocking']];
            }
        }

        return $result;
    }

    /** A database the kit cannot reach has nothing to point out. */
    private static function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private static function step(string $root, string $id, string $kind, array $entry): array
    {
        $step = $entry[self::LISTED[$kind]];
        $file = $entry['or_file'] ?? null;
        [$met, $done, $todo] = match ($kind) {
            'env' => match (true) {
                in_array($step, self::documented($root), true) => [true, 'documented in .env.example; set it in each environment', ''],
                // Existence only: the file holds a secret and is never read.
                $file !== null && ! is_link($root.'/'.$file) && is_file($root.'/'.$file) => [true, 'kept as '.$file, ''],
                default => [false, '', 'Add '.$step.' to .env.example and set it in each environment'.($file === null ? '' : ', or keep it as '.$file).': '.$entry['why']],
            },
            'provider' => [app()->providerIsLoaded($step), 'registered', 'Register '.$step.' (bootstrap/providers.php): '.$entry['why']],
            'schedule' => [self::scheduled($step), 'scheduled', 'Schedule '.$step.' (routes/console.php or bootstrap/app.php (withSchedule)): '.$entry['why']],
            // The value here at plan time; each deploy environment may differ, so it is attention, never blocking.
            'config' => [config($step) !== $entry['not'], 'not '.$entry['not'].' here; check each environment', 'Change '.$step.' from '.$entry['not'].' in every environment: '.$entry['why']],
            // A table an earlier release or the app itself kept the feature's data in: never touched, only pointed out (the `why` names the import). Attention, never blocking.
            'legacy_table' => [! self::hasTable($step), 'no '.$step.' table here', 'The database has a '.$step.' table: '.$entry['why']],
        };

        return ['feature' => $id, 'kind' => $kind, 'step' => $step, 'why' => $entry['why']] + ($file === null ? [] : ['or_file' => $file]) + ['status' => $met ? 'met' : 'unmet', 'blocking' => false, 'message' => $met ? $done : $todo];
    }

    /** @return list<string> the keys .env.example names; values are never read into the result */
    private static function documented(string $root): array
    {
        $path = $root.'/.env.example';
        if (is_link($path) || ! is_file($path)) {
            return [];
        }
        preg_match_all('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/m', file_get_contents($path), $matches);

        return $matches[1];
    }

    private static function scheduled(string $command): bool
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (is_string($event->command) && str_contains($event->command, $command)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> the host package.json's dependencies and devDependencies */
    private static function declared(string $root): array
    {
        $path = $root.'/package.json';
        if (is_link($path) || ! is_file($path)) {
            return [];
        }
        $json = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        $declared = [];
        foreach (['devDependencies', 'dependencies'] as $section) {
            foreach (is_array($json[$section] ?? null) ? $json[$section] : [] as $package => $range) {
                if (is_string($package) && is_string($range)) {
                    $declared[$package] = $range;
                }
            }
        }

        return $declared;
    }

    /**
     * `passed` when every version the declared range allows meets the requirement, `outdated` when some do not,
     * `unverifiable` for a range the kit cannot read (workspace:, file:, git or a tag), and `missing` when undeclared.
     */
    private static function npm(string $root, string $package, string $constraint, ?string $declared): array
    {
        $parser = new VersionParser;
        $status = match (true) {
            $declared === null => 'missing',
            ! self::range($declared) => 'unverifiable',
            default => Intervals::isSubsetOf($parser->parseConstraints($declared), $parser->parseConstraints($constraint)) ? 'passed' : 'outdated',
        };
        $installed = null;
        $manifest = $root.'/node_modules/'.$package.'/package.json';
        if (! str_contains($package, '..') && ! is_link($manifest) && is_file($manifest)) {
            $version = json_decode(file_get_contents($manifest), true, 32)['version'] ?? null;
            $installed = is_string($version) ? $version : null;
        }
        $installedStatus = null;
        if ($installed !== null) {
            try {
                $installedStatus = Semver::satisfies($installed, $constraint) ? 'passed' : 'outdated';
            } catch (Throwable) {
                $installedStatus = 'unverifiable';
            }
        }

        return ['constraint' => $constraint, 'declared' => $declared, 'status' => $status, 'installed' => $installed, 'installed_status' => $installedStatus];
    }

    /** A plain relative path inside the app: no leading slash, dot segment or backslash. */
    private static function relative(mixed $path): bool
    {
        return is_string($path) && $path !== '' && ! str_starts_with($path, '/') && ! str_contains($path, '\\') && array_filter(explode('/', $path), fn (string $segment): bool => in_array($segment, ['', '.', '..'], true)) === [];
    }

    private static function range(string $range): bool
    {
        if (preg_match('/[:\/]|\A\s*\z|\A[a-z]/i', $range) && ! preg_match('/\A[x*]\z/i', trim($range))) {
            return false;
        }
        try {
            (new VersionParser)->parseConstraints($range);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
