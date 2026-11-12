<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Brand;
use Tey\Kit\BrandConfig;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
use Tey\Kit\Features\Core\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\SessionUi\SafeRedirect;
use Tey\Kit\HostRequirements;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Throwable;

final class StatusCommand extends Command
{
    protected $signature = 'kit:status {--json : Print the installed status as JSON} {--check : Exit 1 on an unmarked edit to a kit-owned file (drift); a file marked `kit: tweaked <reason>` is listed, never a failure}';

    protected $description = 'Show adopted features and local edits to owned files (read-only)';

    public function handle(): int
    {
        try {
            $status = (new Lifecycle)->status($this->laravel->basePath());
            $requirements = $status === null ? null : $this->requirements($status['blueprint_features']);
            $requirements = $status === null ? $requirements : $this->home($status['blueprint_features'], $requirements);
            $requirements = $status === null ? $requirements : $this->requestStart($status['blueprint_features'], $requirements);
            $requirements = $status === null ? $requirements : $this->mailLockup($requirements);
            $requirements = $status === null ? $requirements : $this->cardNames($status['blueprint_features'], $requirements, $this->laravel->basePath('resources/js'));
            $requirements = $status === null ? $requirements : $this->permissions($status['blueprint_features'], $requirements);
            if ($requirements !== null) {
                $status['requirements'] = $requirements;
            }
            // Where each brand key comes from: config (config/brand.php) or default.
            if ($status !== null && BrandConfig::sources() !== []) {
                $status['brand_sources'] = BrandConfig::sources();
            }
            // KIT_BRAND_* variables still set: ignored since 0.17, named so a leftover in .env, CI or a deploy is found. Reported with or without adopted features.
            $ignoredEnv = array_values(array_filter(array_map(BrandConfig::envName(...), (array) config('kit.brand_env', []))));
            if ($status !== null && $ignoredEnv !== []) {
                $status['brand_env_ignored'] = $ignoredEnv;
            }
            if (! $this->option('json') && $ignoredEnv !== []) {
                $this->warn('Ignored KIT_BRAND_* variables (tey/kit 0.17 removed env brand values): '.implode(', ', $ignoredEnv).'. Run php artisan kit:brand:migrate to move them into config/brand.php, then remove them from .env, CI and deploy settings.');
            }
            // A colour that resolved invalid and took the default; only kit:status and kit:brand:migrate boot past one in local and testing.
            foreach ($this->option('json') ? [] : (array) config('kit.brand_invalid', []) as $key) {
                $this->warn('kit.brand.'.$key.' is not a valid colour and is using the kit default: set a #rrggbb colour, or null, in config/brand.php.');
            }
            if ($this->option('json')) {
                $this->line(json_encode($status ?? ['message' => 'No kit features adopted.', 'blueprint_features' => [], 'lineages' => [], ...($ignoredEnv !== [] ? ['brand_env_ignored' => $ignoredEnv] : [])], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } elseif ($status === null) {
                $this->line('No kit features adopted.');
            } else {
                $this->line('Adopted features: '.implode(', ', $status['blueprint_features']));
                foreach ($status['lineages'] as $lineage) {
                    $this->line('Lineage: '.$lineage['name'].' '.$lineage['version']);
                    $this->line('Identity: '.$lineage['identity']);
                    foreach ($lineage['files'] as $path => $state) {
                        $this->line($path.': '.$state.match ($state) {
                            'edited' => ' (conflict on next update; mark a deliberate tweak with `// kit: tweaked <reason>`)',
                            'tweaked' => ' (graduation candidate: '.$lineage['tweaks'][$path].'; kit:update keeps it)',
                            default => '',
                        });
                    }
                }
                $sources = $status['brand_sources'] ?? [];
                if ($sources !== []) {
                    $width = max(array_map('strlen', array_keys($sources)));
                    $this->line('Brand (key, source):');
                    foreach ($sources as $key => $source) {
                        $this->line('  '.str_pad($key, $width).'  '.$source);
                    }
                }
                foreach ($requirements === null ? [] : [...HostRequirements::attention($requirements), ...HostRequirements::lines($requirements)] as $line) {
                    $this->line($line);
                }
                foreach ($requirements['failures'] ?? [] as $failure) {
                    $this->error('FAILED: '.$failure);
                }
            }

            // --check: an unmarked edit is drift and fails; a marked tweak is a graduation candidate and passes.
            $drift = [];
            foreach ($status['lineages'] ?? [] as $lineage) {
                foreach ($lineage['files'] as $path => $state) {
                    if ($state === 'edited') {
                        $drift[] = $path;
                    }
                }
            }
            if ($this->option('check') && $drift !== [] && ! $this->option('json')) {
                $this->error('Unmarked edits to kit-owned files: '.implode(', ', $drift).'. Revert them, or mark a deliberate tweak with `// kit: tweaked <reason>`.');
            }

            // A failure (a host-owned config/multitenancy.php without the workspaces switch tasks) fails the status, never only warns.
            return ($requirements['failures'] ?? []) === [] && (! $this->option('check') || $drift === []) ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * session-ui without workspace-ui sends sign-in (fortify.home) and Concierge's no-workspace redirect to kit.home, which is the app's own route then: with
     * none answering GET there, sign-in lands on a 404. Attention, never blocking. A kit.home off the app is not checked.
     *
     * @param  list<string>  $features
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function home(array $features, ?array $requirements): ?array
    {
        if (! in_array('session-ui', $features, true) || in_array('workspace-ui', $features, true)) {
            return $requirements;
        }
        $home = (string) config('kit.home', '/');
        $url = SafeRedirect::sameOrigin($home, '');
        if ($url === '') {
            return $requirements;
        }
        try {
            $route = $this->laravel['router']->getRoutes()->match(Request::create(parse_url($url, PHP_URL_PATH) ?: '/', 'GET'));
            $answers = $route instanceof Route && ! $route->isFallback;
        } catch (Throwable) {
            $answers = false;
        }
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = $answers ? 'a route answers GET '.$home
            : 'No route answers GET '.$home.' (kit.home). Without workspace-ui, sign-in and Concierge\'s no-workspace redirect land there, so they would 404. Add a route of your own at kit.home, or set KIT_HOME to one, or adopt workspace-ui.';
        $requirements['steps'][] = ['feature' => 'session-ui', 'kind' => 'route', 'step' => 'kit.home', 'why' => 'sign-in lands on kit.home', 'status' => $answers ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if (! $answers) {
            $requirements['warnings'][] = $message;
            $requirements['attention'] = true;
        }

        return $requirements;
    }

    /**
     * Core prepends StartKitRequest to `web` only when the kernel already defines that group as core boots. A `web` defined later, or never, leaves
     * the kit's request start off without a sound: no request memos, and the kit's Inertia props lost after a flush. Attention, never blocking. The
     * deprecated session-ui name is a class_alias of the same class, so it counts too.
     *
     * @param  list<string>  $features
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function requestStart(array $features, ?array $requirements): ?array
    {
        if (! in_array('core', $features, true)) {
            return $requirements;
        }
        $kernel = $this->laravel->bound(Kernel::class) ? $this->laravel->make(Kernel::class) : null;
        $groups = $kernel !== null && method_exists($kernel, 'getMiddlewareGroups') ? $kernel->getMiddlewareGroups() : [];
        $names = [StartKitRequest::class, 'Tey\\Kit\\Features\\SessionUi\\Http\\Middleware\\StartKitRequest'];
        $web = array_key_exists('web', $groups);
        $running = $web && array_intersect(array_map(fn (mixed $m): string => is_string($m) ? ltrim($m, '\\') : '', (array) $groups['web']), $names) !== [];
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = $running ? 'StartKitRequest runs first in the web group'
            : ($web ? 'StartKitRequest is not in the web middleware group' : 'The app defines no web middleware group')
                .', so the kit\'s per-request start is not running: request memos are off and the kit\'s Inertia props can be lost after a flush. Define the web group before the kit\'s providers boot (bootstrap/app.php withMiddleware), or add '.StartKitRequest::class.' first in web.';
        $requirements['steps'][] = ['feature' => 'core', 'kind' => 'middleware', 'step' => 'web StartKitRequest', 'why' => 'the kit\'s per-request start', 'status' => $running ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if (! $running) {
            $requirements['warnings'][] = $message;
            $requirements['attention'] = true;
        }

        return $requirements;
    }

    /**
     * The mail header shows `kit.brand.mail_lockup` embedded inline. A configured path with no file behind it, or a file that is not a PNG, quietly falls back to the
     * product's name, so it is attention, never blocking. Unset is not checked.
     *
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function mailLockup(?array $requirements): ?array
    {
        $lockup = Brand::get('mail_lockup');
        if (! is_string($lockup) || trim($lockup) === '') {
            return $requirements;
        }
        $present = Brand::mailLockupFile() !== null;
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = $present ? $lockup.' is a PNG'
            : 'kit.brand.mail_lockup is '.$lockup.' but no PNG file is there (a path in public/ or an absolute file path), so mail shows the product\'s name instead of the lockup. Add the PNG, or fix mail_lockup in config/brand.php.';
        $requirements['steps'][] = ['feature' => 'core', 'kind' => 'file', 'step' => 'kit.brand.mail_lockup', 'why' => 'the mail header shows the lockup', 'status' => $present ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if (! $present) {
            $requirements['warnings'][] = $message;
            $requirements['attention'] = true;
        }

        return $requirements;
    }

    /**
     * v0.16 swapped two card names: ActionCard is now the plain panel (title, #description, #actions) and SummaryCard the header-band card (#icon, #body,
     * #footer). An app's page still written for the old names renders the wrong card, so a `<ActionCard>` using #icon, #body, #footer or an editUrl, or a
     * `<SummaryCard>` using #description, is attention (never blocking), with the file and the rename. Only the app's own .vue files are read, not the kit's.
     *
     * @param  list<string>  $features
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function cardNames(array $features, ?array $requirements, string $root): ?array
    {
        if (! in_array('core', $features, true) || ! is_dir($root)) {
            return $requirements;
        }
        $old = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', (string) $file);
            if (! str_ends_with($path, '.vue') || str_starts_with($path, rtrim(str_replace('\\', '/', $root), '/').'/components/Kit/')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            $relative = 'resources/js/'.ltrim(substr($path, strlen(str_replace('\\', '/', $root))), '/');
            if (preg_match('~<ActionCard\b(?:(?!</ActionCard>).)*?(?:#(?:icon|body|footer)\b|v-slot:(?:icon|body|footer)\b|\bedit-?url\b)~is', $source) === 1) {
                $old[] = $relative.': ActionCard with #icon, #body, #footer or editUrl is the old header-band card; rename it SummaryCard';
            }
            if (preg_match('~<SummaryCard\b(?:(?!</SummaryCard>).)*?(?:#description\b|v-slot:description\b)~is', $source) === 1) {
                $old[] = $relative.': SummaryCard with #description is the old panel; rename it ActionCard';
            }
        }
        sort($old);
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = $old === [] ? 'no card uses the pre-0.16 names'
            : 'Cards written for the pre-0.16 names (docs/cards.md, Upgrading to 0.16): '.implode('; ', $old).'.';
        $requirements['steps'][] = ['feature' => 'core', 'kind' => 'file', 'step' => 'card names', 'why' => 'v0.16 swapped ActionCard and SummaryCard', 'status' => $old === [] ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if ($old !== []) {
            $requirements['warnings'][] = $message;
            $requirements['attention'] = true;
        }

        return $requirements;
    }

    /**
     * Declared permissions the database does not hold: every check on them is denied, so the pages they gate are a 404 for everyone. Blocking: `php artisan migrate`
     * (CatalogSync) or `php artisan kit:permissions:sync` creates them. Not checked without the access feature, or when the database cannot be read.
     *
     * @param  list<string>  $features
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function permissions(array $features, ?array $requirements): ?array
    {
        if (! in_array('access', $features, true)) {
            return $requirements;
        }
        try {
            $missing = CatalogSync::unsynced();
        } catch (Throwable) {
            return $requirements;
        }
        if ($missing === []) {
            return $requirements;
        }
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = 'Declared permissions are not in the database ('.implode(', ', $missing).'), so every check on them is denied and the pages they gate are a 404. Run php artisan migrate --force (it syncs them) or php artisan kit:permissions:sync.';
        $requirements['steps'][] = ['feature' => 'access', 'kind' => 'database', 'step' => 'kit:permissions:sync', 'why' => 'declared permissions must exist to be granted', 'status' => 'unmet', 'blocking' => true, 'message' => $message];
        $requirements['warnings'][] = $message;
        $requirements['attention'] = true;
        $requirements['executable'] = false;

        return $requirements;
    }

    /**
     * The adopted features' host steps and npm requirements, checked now as kit:add checked them before the apply, so a host step that has since
     * gone unmet (an alias removed, a config edited) shows here too. Null when no adopted feature the catalog knows declares any.
     *
     * @param  list<string>  $features
     * @return array<string, mixed>|null
     */
    private function requirements(array $features): ?array
    {
        $host = $this->laravel->bound(LifecycleHost::class) ? $this->laravel->make(LifecycleHost::class) : null;
        $catalog = $host instanceof CatalogHost ? $host->catalog() : Catalog::package();
        $known = array_values(array_intersect($features, $catalog->featureIds()));
        if ($known === []) {
            return null;
        }
        $requirements = (new Lineages(new Lifecycle, $catalog))->requirements($this->laravel->basePath(), $known);

        return $requirements['steps'] === [] && $requirements['npm'] === [] ? null : $requirements;
    }
}
