<?php

namespace Tey\Kit;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tey\Kit\Features\Core\Defaults;
use Tey\Mod\Maintenance\FilePlan;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * What a host must do beyond the files a plan writes: the features' declared host steps, their npm requirements and
 * their optional integrations. Each is verified where the kit can and printed, never written: nothing here edits
 * package.json, .env.example, providers or the schedule, and nothing reads .env.
 *
 * Only `user_model` and `workspace_model` (Lineages::unmet), a new migration creating a table the host already has (collisions(); the workspaces
 * and permission tables are adopted in place instead, adoptions()) and a missing
 * host UI primitive (`host_ui`, hostUi()) block a plan; every other unmet step or npm requirement is reported as attention.
 */
final class HostRequirements
{
    /** New migrations (by name, without the date prefix) that adopt a host table in place instead of colliding with it. */
    private const ADOPTABLE = ['create_kit_workspaces_table' => 'workspaces', 'create_permission_tables' => 'permission'];

    /** Host step kinds the kit can verify, beyond the blocking `user_model` and `workspace_model`. */
    private const LISTED = ['env' => 'key', 'provider' => 'class', 'schedule' => 'command', 'config' => 'key', 'legacy_table' => 'table', 'theme' => 'file', 'mention' => 'file'];

    /** The semantic colour tokens the kit's components use (shadcn-vue's names), which kit.css defines at the lowest precedence. */
    public const THEME_TOKENS = ['background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'destructive', 'border', 'input', 'ring'];

    /** Fields a listed step may add: an env key may instead be kept as a file the host has (checked for existence only, never read); a config key names the value it must `not` have. */
    private const EXTRA = ['env' => ['or_file' => true], 'config' => ['not' => true], 'mention' => ['contains' => true, 'or_file' => true]];

    /** Refuses a malformed `host_steps`, `optional` or `requires.npm`; every field is optional. */
    public static function validate(string $id, array $feature): void
    {
        foreach ($feature['host_steps'] ?? [] as $kind => $step) {
            $field = self::LISTED[$kind] ?? null;
            $valid = match (true) {
                in_array($kind, ['user_model', 'workspace_model'], true) => is_array($step) && array_diff_key($step, ['implements' => true, 'uses' => true]) === [] && is_string($step['implements'] ?? null) && is_string($step['uses'] ?? null),
                $field !== null => is_array($step) && $step !== [] && array_is_list($step) && array_filter($step, fn ($entry): bool => ! is_array($entry) || array_diff_key($entry, [$field => true, 'why' => true] + (self::EXTRA[$kind] ?? [])) !== [] || ! is_string($entry[$field] ?? null) || $entry[$field] === '' || ! is_string($entry['why'] ?? null)) === [],
                default => false,
            };
            if (! $valid || ($kind === 'env' && array_filter($step, fn (array $entry): bool => ! preg_match('/\A[A-Z][A-Z0-9_]*\z/', $entry['key']) || (array_key_exists('or_file', $entry) && ! self::relative($entry['or_file']))) !== [])
                || ($kind === 'config' && array_filter($step, fn (array $entry): bool => ! is_string($entry['not'] ?? null)) !== [])
                || ($kind === 'mention' && array_filter($step, fn (array $entry): bool => ! self::relative($entry['file']) || ! is_string($entry['contains'] ?? null) || $entry['contains'] === '' || (array_key_exists('or_file', $entry) && ! self::relative($entry['or_file']))) !== [])) {
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
                // user_model and workspace_model are checked, and block, in Lineages::unmet.
                if (in_array($kind, ['user_model', 'workspace_model'], true)) {
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
        // A host with its own OAuth consent (it overrides Passport's AuthorizationViewResponse or adds its own approve middleware) keeps it with kit.mcp.consent=host. Attention, never blocking.
        if (isset($features['mcp']) && ($own = self::ownConsent($root)) !== null) {
            $host = config('kit.mcp.consent', 'kit') === 'host';
            $steps[] = ['feature' => 'mcp', 'kind' => 'config', 'step' => 'kit.mcp.consent', 'why' => 'the kit\'s consent would refuse your approve and write a second connection beside your grants', 'status' => $host ? 'met' : 'unmet', 'blocking' => false, 'message' => $host
                ? 'host: '.$own.' keeps its own consent'
                : $own.' runs its own OAuth consent. Set kit.mcp.consent to \'host\' (KIT_MCP_CONSENT=host) so the kit leaves Passport\'s authorize and approve as they are; else the kit\'s consent refuses your approve (403) and writes a second connection beside your grants.'];
        }
        // Workspace and account deletion are off unless the host turns them on AND binds the purge handler for its own data. On without one refuses: attention.
        if (($ui = isset($features['workspace-ui']) ? 'workspace-ui' : (isset($features['session-ui']) ? 'session-ui' : null)) !== null) {
            foreach (['workspace_delete' => 'Tey\\Kit\\Features\\Workspaces\\Contracts\\PurgesWorkspaceData', 'account_delete' => 'Tey\\Kit\\Features\\SessionUi\\Contracts\\PurgesAccountData'] as $flag => $contract) {
                $on = app()->bound('config') && filter_var(config('kit.lifecycle.'.$flag, false), FILTER_VALIDATE_BOOL);
                $bound = app()->bound($contract);
                $steps[] = ['feature' => $ui, 'kind' => 'config', 'step' => 'kit.lifecycle.'.$flag, 'why' => 'deletion runs only with the app\'s own data purged first', 'status' => ! $on || $bound ? 'met' : 'unmet', 'blocking' => false, 'message' => match (true) {
                    ! $on => 'off: deletion is disabled (its routes 404, its settings section hidden). To turn it on, set kit.lifecycle.'.$flag.' and bind '.$contract,
                    $bound => 'on: '.$contract.' is bound and runs first, in the deletion\'s transaction',
                    default => 'kit.lifecycle.'.$flag.' is on but no '.$contract.' is bound, so the deletion refuses. Bind the purge handler for your own data (one that does nothing, explicitly, if you have none).',
                }];
            }
        }
        // Installed lineage records are portable and must be committed, or a fresh clone and CI lose the adoption. Attention, never blocking.
        $records = self::ignoresInstalled($root) ? 'ignored' : (self::untrackedInstalled($root) ? 'untracked' : null);
        if ($records !== null) {
            $steps[] = ['feature' => 'kit', 'kind' => 'gitignore', 'step' => '.gitignore', 'why' => 'fresh clones and CI need the installed lineage records', 'status' => 'unmet', 'blocking' => false, 'message' => $records === 'ignored'
                ? '.gitignore ignores .mod-maintenance/installed/, so fresh clones and CI lose the adopted lineages. Commit the installed records (and .mod-maintenance/installed.json); keep only the local parts ignored. Replace the .mod-maintenance line(s) with: '.implode(' ', self::LOCAL_MAINTENANCE)
                : '.mod-maintenance/installed/ holds records git does not track, so other checkouts, fresh clones and CI lose the adopted lineages. Commit .mod-maintenance/installed/ (and .mod-maintenance/installed.json), and ignore only the local parts: '.implode(' ', self::LOCAL_MAINTENANCE)];
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
        array_push($warnings, ...self::copiedTypes($root));
        // The theming policy, as advice only: a surface without its foreground, a pair below 4.5:1, or a --kit-* override off the named surfaces.
        if (isset($features['core'])) {
            array_push($warnings, ...array_map(fn (string $advice): string => 'Theming (advisory): '.$advice, ThemeAdvisory::of(self::stylesheets($root, ['resources/css/app.css', ...self::viteEntries($root, 'css')]))));
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
        // A host that imports from @inertiajs/core (its types: Method, PageProps) must keep it declared (the standards' js-imports rule), so the advice is to pin
        // it to the version vue3 pins, not to remove it.
        $importer = self::importerOf($root, '@inertiajs/core');
        if ($nested !== null && $top !== null && $nested !== $top) {
            return [$importer !== null
                ? 'Two copies of @inertiajs/core are installed ('.$top.' and '.$nested.', which @inertiajs/vue3 pins), so their types disagree. Your code imports it ('.$importer.'), so keep it declared: set it in package.json to exactly '.$nested.' and run npm install.'
                : 'Two copies of @inertiajs/core are installed ('.$top.' and '.$nested.', which @inertiajs/vue3 pins), so their types disagree. Remove @inertiajs/core from package.json (or set it to exactly '.$nested.') and run npm install.'];
        }
        if (isset($declared['@inertiajs/core'])) {
            // The exact version vue3 pins (from its installed manifest, else the host's own exact vue3 version) is one copy, not two.
            $pinned = null;
            $manifest = $root.'/node_modules/@inertiajs/vue3/package.json';
            if (! is_link($manifest) && is_file($manifest)) {
                $pin = json_decode((string) file_get_contents($manifest), true, 32)['dependencies']['@inertiajs/core'] ?? null;
                $pinned = is_string($pin) ? $pin : null;
            }
            // Without node_modules (a production checkout), the lockfile's vue3 entry; then vue3's own declared version, exact or the base of its range.
            $lock = $root.'/package-lock.json';
            if ($pinned === null && ! is_link($lock) && is_file($lock)) {
                $pin = json_decode((string) file_get_contents($lock), true, 64)['packages']['node_modules/@inertiajs/vue3']['dependencies']['@inertiajs/core'] ?? null;
                $pinned = is_string($pin) ? $pin : null;
            }
            $exact = fn (?string $version): ?string => preg_match('/\A[=v]?(\d+\.\d+\.\d+)\z/', trim((string) $version), $match) === 1 ? $match[1] : null;
            $pinned ??= preg_match('/\A[\^~=v]?(\d+\.\d+\.\d+)\z/', trim($declared['@inertiajs/vue3'] ?? ''), $match) === 1 ? $match[1] : null;
            // Only a range, or an exact version other than vue3's, can install a second copy: core pinned at exactly vue3's version is one copy.
            if ($pinned !== null && $exact($declared['@inertiajs/core']) !== null && $exact($declared['@inertiajs/core']) === $exact($pinned)) {
                return [];
            }

            return [$importer !== null
                ? 'package.json declares @inertiajs/core ('.$declared['@inertiajs/core'].'), which @inertiajs/vue3 already pins exactly; a range that resolves to another version installs a second copy. Your code imports it ('.$importer.'), so keep it declared at @inertiajs/vue3\'s version'.($pinned !== null ? ' (exactly '.$pinned.')' : '').'.'
                : 'package.json declares @inertiajs/core ('.$declared['@inertiajs/core'].'), which @inertiajs/vue3 already pins exactly; a range that resolves to another version installs a second copy. Remove it from package.json.'];
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
     * reach is not checked. The workspaces feature's workspaces and permission tables are adopted in place instead
     * (adoptions()), and block only when the host's tables cannot be adopted.
     *
     * @param  array<string, array{state: string}>  $files
     * @param  array<string, string>  $bytes  the target's path => bytes
     * @return list<string>
     */
    public static function collisions(array $files, array $bytes): array
    {
        return self::tables($files, $bytes)['blocking'];
    }

    /**
     * A note for each host table a new migration in the plan adopts in place rather than creates: the workspaces table
     * (only the kit's missing columns are added) and spatie/laravel-permission's tables (nothing is created). Never blocks.
     *
     * @param  array<string, array{state: string}>  $files
     * @param  array<string, string>  $bytes  the target's path => bytes
     * @return list<string>
     */
    public static function adoptions(array $files, array $bytes): array
    {
        return self::tables($files, $bytes)['adopt'];
    }

    /**
     * Which host tables the plan's new migrations adopt in place: `workspaces` and/or `permission` (spatie/laravel-permission's five).
     *
     * @param  array<string, array{state: string}>  $files
     * @param  array<string, string>  $bytes  the target's path => bytes
     * @return list<string>
     */
    public static function adoptedTables(array $files, array $bytes): array
    {
        return array_values(array_unique(self::tables($files, $bytes)['tables']));
    }

    /** @return array{blocking: list<string>, adopt: list<string>, tables: list<string>} */
    private static function tables(array $files, array $bytes): array
    {
        $outcome = ['blocking' => [], 'adopt' => [], 'tables' => []];
        foreach (self::migrations($files)['new'] as $path) {
            // A migration the host already ran (the same file, restored or re-added) is skipped by migrate, so its tables are not a collision.
            try {
                if (DB::table((string) config('database.migrations.table', 'migrations'))->where('migration', basename($path, '.php'))->exists()) {
                    continue;
                }
            } catch (Throwable) {
            }
            $adoptable = self::ADOPTABLE[(string) preg_replace('/\A\d{4}_\d{2}_\d{2}_\d{6}_(?:\d{6}_)?/', '', basename($path, '.php'))] ?? null;
            if ($adoptable === 'permission') {
                $outcome = array_merge_recursive($outcome, self::permissionTables($path));

                continue;
            }
            preg_match_all('/Schema::create\(\s*[\'"]([^\'"]+)[\'"]/', $bytes[$path] ?? '', $matches);
            foreach ($matches[1] as $table) {
                try {
                    $exists = Schema::hasTable($table);
                } catch (Throwable) {
                    continue;
                }
                if (! $exists) {
                    continue;
                }
                if ($adoptable === $table) {
                    try {
                        $missing = array_values(array_diff(['admitted', 'settings_version', 'provisioning_invitation_id'], Schema::getColumnListing($table)));
                    } catch (Throwable) {
                        continue;
                    }
                    $outcome['tables'][] = $table;
                    $outcome['adopt'][] = 'adopt the host\'s '.$table.' table in place ('.$path.'): '.($missing === [] ? 'it has every column the kit needs; nothing is added' : 'add '.implode(', ', $missing).', with existing rows'.(in_array('admitted', $missing, true) ? ' admitted' : '').(in_array('settings_version', $missing, true) ? ' at settings version 1' : '')).'. No host column or row is dropped or altered.';
                } else {
                    $outcome['blocking'][] = 'The host database already has a '.$table.' table, which '.$path.' creates. Rename or drop the host table first; migrate would fail.';
                }
            }
        }

        return $outcome;
    }

    /** spatie/laravel-permission's tables as the host has them: all five are adopted (with the configured teams column), none are created, and anything between blocks. */
    private static function permissionTables(string $path): array
    {
        $names = (array) (Defaults::value(app(), 'permission', 'table_names') ?? []);
        $tables = array_intersect_key($names, array_flip(['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions']));
        try {
            $present = array_values(array_filter($tables, fn (string $table): bool => Schema::hasTable($table)));
        } catch (Throwable) {
            return [];
        }
        if ($present === []) {
            return [];
        }
        if (count($tables) !== 5 || count($present) !== 5) {
            return ['blocking' => ['The host database has some permission tables ('.implode(', ', $present).') but not all five, which '.$path.' creates. Create the rest with spatie/laravel-permission\'s migration, or drop these, first; migrate would fail.']];
        }
        if (Defaults::value(app(), 'permission', 'teams')) {
            $team = (string) Defaults::value(app(), 'permission', 'column_names.team_foreign_key');
            $missing = array_values(array_filter([$tables['roles'], $tables['model_has_permissions'], $tables['model_has_roles']], fn (string $table): bool => ! Schema::hasColumn($table, $team)));
            if ($missing !== []) {
                return ['blocking' => ['The host\'s permission tables '.implode(', ', $missing).' have no '.$team.' teams column, which the kit\'s permission.teams needs ('.$path.' adopts them). Set permission.column_names.team_foreign_key to the host\'s teams column in config/permission.php, or run php artisan permission:setup-teams and migrate first.']];
            }
        }

        return ['tables' => ['permission'], 'adopt' => ['adopt the host\'s permission tables in place ('.$path.'): '.implode(', ', $present).' exist, so nothing is created.']];
    }

    /** A plan result with each collision() as a blocking unmet step: kit:update and kit:adopt plans refuse it, as kit:add does. */
    public static function guarded(array $result, FilePlan $plan, ?string $root = null): array
    {
        $collisions = self::collisions($plan->files, $plan->target->files);
        $adoptions = self::adoptions($plan->files, $plan->target->files);
        if ($adoptions !== []) {
            $result['adoptions'] = $adoptions;
        }
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

    /** The .gitignore lines for what stays local under .mod-maintenance: the journals, the lock and owner.json (it holds an absolute root). */
    public const LOCAL_MAINTENANCE = ['/.mod-maintenance/journal-*.json', '/.mod-maintenance/lock', '/.mod-maintenance/owner.json'];

    /**
     * Whether the host ignores `.mod-maintenance/installed/`: git decides in a git checkout; elsewhere the root .gitignore is read
     * line by line (later lines win, and a negation cannot re-include a file whose directory is ignored).
     */
    public static function ignoresInstalled(string $root): bool
    {
        $probe = '.mod-maintenance/installed/probe.json';
        $inside = new Process(['git', 'rev-parse', '--is-inside-work-tree'], $root);
        $inside->run();
        if ($inside->isSuccessful() && trim($inside->getOutput()) === 'true') {
            $check = new Process(['git', 'check-ignore', '-q', '--no-index', '--', $probe], $root);
            $check->run();

            return $check->getExitCode() === 0;
        }
        $file = $root.'/.gitignore';
        if (! is_file($file) || is_link($file)) {
            return false;
        }
        $ignored = ['.mod-maintenance' => false, '.mod-maintenance/installed' => false, $probe => false];
        foreach (preg_split('/\R/', (string) file_get_contents($file)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $negated = str_starts_with($line, '!');
            $pattern = preg_replace('#(/\*\*?)$#', '/*', trim(ltrim($line, '!'), '/'));
            foreach (array_keys($ignored) as $path) {
                $matches = str_ends_with($pattern, '/*') ? dirname($path) === substr($pattern, 0, -2) : $pattern === $path || ($pattern === '.mod-maintenance/installed/*.json' && $path === $probe);
                if ($matches) {
                    $ignored[$path] = ! $negated;
                }
            }
        }

        return $ignored['.mod-maintenance'] || $ignored['.mod-maintenance/installed'] || $ignored[$probe];
    }

    /** In a git checkout: whether .mod-maintenance/installed/ (or installed.json) holds a file git does not track. Outside git, nothing to say. */
    public static function untrackedInstalled(string $root): bool
    {
        $inside = new Process(['git', 'rev-parse', '--is-inside-work-tree'], $root);
        $inside->run();
        if (! $inside->isSuccessful() || trim($inside->getOutput()) !== 'true') {
            return false;
        }
        $untracked = new Process(['git', 'ls-files', '--others', '--exclude-standard', '--', '.mod-maintenance/installed', '.mod-maintenance/installed.json'], $root);
        $untracked->run();

        return $untracked->isSuccessful() && trim($untracked->getOutput()) !== '';
    }

    /**
     * The first host file that runs its own OAuth consent, or null when none does. It reads app/, bootstrap/, routes/, config/ and every PSR-4 root the host's
     * composer.json maps (src/, say), inside the app and never through a link. A file runs its own consent when it:
     * - sets Passport's consent view (`Passport::authorizationView(`) or binds `AuthorizationViewResponse`;
     * - adds middleware to `passport.authorizations.approve`;
     * - binds or extends Passport's `AuthorizationController` or `ApproveAuthorizationController` (Laravel\Passport\Http\Controllers).
     */
    private static function ownConsent(string $root): ?string
    {
        $directories = ['app', 'bootstrap', 'routes', 'config'];
        $composer = $root.'/composer.json';
        $json = ! is_link($composer) && is_file($composer) ? json_decode((string) file_get_contents($composer), true, 32) : null;
        foreach ((array) ($json['autoload']['psr-4'] ?? []) as $paths) {
            foreach ((array) $paths as $path) {
                $path = rtrim((string) $path, '/');
                if ($path !== '' && self::relative($path) && ! str_starts_with($path, 'vendor')) {
                    $directories[] = $path;
                }
            }
        }
        $controllers = '(?:Approve)?AuthorizationController';
        $signals = [
            '/Passport\s*::\s*authorizationView\s*\(/',
            '/\bAuthorizationViewResponse\b/',
            '/passport\.authorizations\.approve[\'"]\s*\)\s*->\s*middleware\(/',
        ];
        foreach (array_values(array_unique($directories)) as $directory) {
            if (! is_dir($root.'/'.$directory) || is_link($root.'/'.$directory)) {
                continue;
            }
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && ! $file->isLink() && str_ends_with($file->getFilename(), '.php') && ! str_contains($file->getPathname(), '/cache/')) {
                    $files[] = $file->getPathname();
                }
            }
            sort($files);
            foreach ($files as $path) {
                $source = (string) file_get_contents($path);
                $passportController = str_contains($source, 'Laravel\\Passport\\Http\\Controllers')
                    && preg_match('/(?:->\s*(?:bind|singleton|extend)\s*\(\s*\\\\?(?:Laravel\\\\Passport\\\\Http\\\\Controllers\\\\)?'.$controllers.'::class|\bextends\s+\\\\?(?:Laravel\\\\Passport\\\\Http\\\\Controllers\\\\)?'.$controllers.'\b)/', $source) === 1;
                if ($passportController || array_filter($signals, fn (string $signal): bool => preg_match($signal, $source) === 1) !== []) {
                    return substr($path, strlen($root) + 1);
                }
            }
        }

        return null;
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
            // The host's stylesheet imports kit.css (which carries the tokens' defaults) or defines every token itself: else the kit's components render uncoloured. Attention, never blocking.
            'theme' => (function () use ($root, $step, $entry): array {
                $missing = self::missingTokens($root, $step);

                return [$missing === null || $missing === [], $missing === null ? 'imports kit.css (its default colour tokens)' : 'defines the colour tokens', $missing !== null && $missing !== []
                    ? 'The kit\'s components need colour tokens your stylesheet does not have ('.implode(', ', array_map(fn (string $token): string => '--'.$token, (array) $missing)).'). Import the kit\'s stylesheet after Tailwind in '.$step.': @import \'./kit.css\'; (its defaults give way to any theme of yours), or define them: '.$entry['why']
                    : ''];
            })(),
            // A host file that should mention something (its layout callback naming a shell): a cheap text check, so attention, never blocking.
            'mention' => (function () use ($root, $step, $entry): array {
                // A step on vite.config.ts reads whichever Vite config the host has (.ts, .js, .mjs); one on a script under resources/js also reads every script entry
                // the Vite config names (an app whose entry is next.ts, or several), and is met when any of them mentions it.
                // `or_file` is a second place the same mention may be (the kit's types in tsconfig.json or in the Vite config's globalTypeFiles).
                $expand = fn (string $file): array => preg_match('/\Avite\.config\.(?:ts|js|mjs)\z/', $file) === 1 ? array_filter([self::viteConfig($root)])
                    : (preg_match('/\Aresources\/js\/[^\/]+\.(?:ts|js|tsx|mts|mjs)\z/', $file) === 1 ? array_values(array_unique([$file, ...self::viteEntries($root, 'ts|js|tsx|mts|mjs')])) : [$file]);
                $candidates = array_values(array_unique([...$expand($step), ...(isset($entry['or_file']) ? $expand($entry['or_file']) : [])]));
                $present = array_values(array_filter($candidates, fn (string $file): bool => ! is_link($root.'/'.$file) && is_file($root.'/'.$file)));
                $found = array_values(array_filter($present, fn (string $file): bool => str_contains((string) file_get_contents($root.'/'.$file), $entry['contains'])));
                $where = $present === [] ? $step : implode(', ', $present);

                return [$found !== [], ($found[0] ?? $step).' mentions '.$entry['contains'], ($present !== [] ? $where.' does not mention '.$entry['contains'] : 'No '.$step.' here').': '.$entry['why']];
            })(),
        };

        return ['feature' => $id, 'kind' => $kind, 'step' => $step, 'why' => $entry['why']] + ($file === null ? [] : ['or_file' => $file]) + ['status' => $met ? 'met' : 'unmet', 'blocking' => false, 'message' => $met ? $done : $todo];
    }

    /**
     * The colour tokens the host's stylesheets lack: null when one of them imports kit.css (whose defaults cover them all), else each token neither defined as a
     * plain variable (`--primary:`) nor mapped in a theme (`--color-primary:`) in any of them. The stylesheets are the step's file, every CSS entry the Vite config
     * names (a second entry may be the one importing kit.css), and the local files each `@import`s, transitively. Every token is missing when none is there.
     *
     * @return list<string>|null
     */
    private static function missingTokens(string $root, string $file): ?array
    {
        $css = '';
        $found = false;
        foreach (self::stylesheets($root, [$file, ...self::viteEntries($root, 'css')]) as $contents) {
            $found = true;
            if (preg_match('/@import\s+(?:url\()?[\'"][^\'"]*kit\.css[\'"]/', $contents) === 1) {
                return null;
            }
            $css .= "\n".$contents;
        }
        if (! $found) {
            return self::THEME_TOKENS;
        }

        return array_values(array_filter(self::THEME_TOKENS, fn (string $token): bool => preg_match('/--(?:color-)?'.preg_quote($token, '/').'\s*:/', $css) !== 1));
    }

    /** The host's Vite config, relative to the root: vite.config.ts, .js or .mjs, the first that exists; null when none does. */
    private static function viteConfig(string $root): ?string
    {
        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mjs'] as $name) {
            if (! is_link($root.'/'.$name) && is_file($root.'/'.$name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The files of the given extensions (`css`, `ts|js`) a Vite config names as inputs (`input: [...]`, `laravel([...])`): each quoted, relative path with one of
     * them in the config. Text only: the config is never run.
     *
     * @return list<string>
     */
    private static function viteEntries(string $root, string $extensions): array
    {
        $config = self::viteConfig($root);
        if ($config === null) {
            return [];
        }
        preg_match_all('/[\'"]([^\'"\s]+\.(?:'.$extensions.'))[\'"]/', (string) file_get_contents($root.'/'.$config), $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $entry): bool => self::relative($entry) && ! str_starts_with($entry, 'vite.config.'))));
    }

    /**
     * Each stylesheet's contents, by path relative to the host root, following local `@import`s (relative paths only; never a link, never outside the root, at most
     * 50 files): a package or URL import is skipped.
     *
     * @param  list<string>  $files
     * @return array<string, string>
     */
    private static function stylesheets(string $root, array $files): array
    {
        $read = [];
        $queue = $files;
        while ($queue !== [] && count($read) < 50) {
            $file = self::normalize(array_shift($queue));
            $path = $root.'/'.$file;
            if ($file === null || isset($read[$file]) || is_link($path) || ! is_file($path)) {
                continue;
            }
            $read[$file] = (string) file_get_contents($path);
            preg_match_all('/@import\s+(?:url\()?[\'"](\.{1,2}\/[^\'"]+\.css)[\'"]/', $read[$file], $imports);
            foreach ($imports[1] as $import) {
                $queue[] = dirname($file).'/'.$import;
            }
        }

        return $read;
    }

    /** A relative path with `.` and `..` resolved, or null when it would leave the host root. */
    private static function normalize(string $path): ?string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
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

    /**
     * Whether the schedule runs the command. Laravel quotes option values when it compiles an event (`Schedule::command('model:prune', ['--model' => [X::class]])`
     * becomes `model:prune --model='X'`, several models repeated options), so quotes are dropped and the command's name and each of its options are matched as
     * tokens: the array, `::class` and `--model=` string forms all match, wherever the host
     * defines its schedule (routes/console.php, bootstrap/app.php's withSchedule, a Console Kernel).
     */
    private static function scheduled(string $command): bool
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (! is_string($event->command)) {
                continue;
            }
            // Token by token: the command's name, then each of its options anywhere after it (several models compile to repeated `--model=` options).
            $tokens = preg_split('/\s+/', trim(str_replace(["'", '"'], '', $event->command))) ?: [];
            $wanted = preg_split('/\s+/', trim($command)) ?: [];
            $at = array_search($wanted[0] ?? '', $tokens, true);
            if ($at !== false && array_diff(array_slice($wanted, 1), array_slice($tokens, $at + 1)) === []) {
                return true;
            }
        }

        return false;
    }

    /** The kit's generated types, shipped for a host with no TypeScript generator to copy (bin/kit-types.php writes it). */
    public const KIT_TYPES = __DIR__.'/../types/kit.d.ts';

    /**
     * A host with no TypeScript generator keeps a hand copy of the kit's types at resources/js/types/kit.d.ts. When the kit's types have moved on (a feature added
     * a ViewModel or Data type), the copy is pointed out with the types it lacks and the file to copy over it. Attention, never blocking; a host whose own
     * transformer writes its types (spatie/laravel-typescript-transformer in composer.json) is never told to copy.
     *
     * @return list<string>
     */
    private static function copiedTypes(string $root): array
    {
        $copy = $root.'/resources/js/types/kit.d.ts';
        if (is_link($copy) || ! is_file($copy) || ! is_file(self::KIT_TYPES)) {
            return [];
        }
        $composer = $root.'/composer.json';
        $requires = ! is_link($composer) && is_file($composer) ? json_decode((string) file_get_contents($composer), true, 32) : null;
        if (is_array($requires) && (isset($requires['require']['spatie/laravel-typescript-transformer']) || isset($requires['require-dev']['spatie/laravel-typescript-transformer']))) {
            return [];
        }
        $names = fn (string $types): array => preg_match_all('/\bexport type (\w+)\b/', $types, $found) ? array_values(array_unique($found[1])) : [];
        $shipped = (string) file_get_contents(self::KIT_TYPES);
        $held = (string) file_get_contents($copy);
        if ($held === $shipped) {
            return [];
        }
        $missing = array_values(array_diff($names($shipped), $names($held)));

        return ['resources/js/types/kit.d.ts is a copy of the kit\'s types from another release'.($missing === [] ? '' : ' and lacks '.implode(', ', $missing)).'. Copy vendor/tey/kit/types/kit.d.ts over it (or generate your types with spatie/laravel-typescript-transformer).'];
    }

    /**
     * The first host source file under resources/js that imports the package (`from 'pkg'`, `import type … from 'pkg'`, `import('pkg')`, or a subpath), relative to
     * the root; null when none does. Text only, at most 5,000 files, never through a link or into node_modules.
     */
    private static function importerOf(string $root, string $package): ?string
    {
        $base = $root.'/resources/js';
        if (is_link($base) || ! is_dir($base)) {
            return null;
        }
        $pattern = '/(?:\bfrom\s*|\bimport\s*\(\s*|\bimport\s+)[\'"]'.preg_quote($package, '/').'(?:\/[^\'"]*)?[\'"]/';
        $seen = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), fn (\SplFileInfo $file): bool => ! $file->isLink() && $file->getFilename() !== 'node_modules'));
        foreach ($files as $file) {
            if (++$seen > 5000) {
                break;
            }
            if ($file->isFile() && preg_match('/\.(?:ts|tsx|mts|js|mjs|vue)\z/', $file->getFilename()) === 1 && preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
                return substr($file->getPathname(), strlen($root) + 1);
            }
        }

        return null;
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
