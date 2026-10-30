<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Brand;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Features\Core\Http\Middleware\StartKitRequest;
use Tey\Kit\Features\SessionUi\SafeRedirect;
use Tey\Kit\HostRequirements;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Throwable;

final class StatusCommand extends Command
{
    protected $signature = 'kit:status {--json : Print the installed status as JSON}';

    protected $description = 'Show adopted features and local edits to owned files (read-only)';

    public function handle(): int
    {
        try {
            $status = (new Lifecycle)->status($this->laravel->basePath());
            $requirements = $status === null ? null : $this->requirements($status['blueprint_features']);
            $requirements = $status === null ? $requirements : $this->home($status['blueprint_features'], $requirements);
            $requirements = $status === null ? $requirements : $this->requestStart($status['blueprint_features'], $requirements);
            $requirements = $status === null ? $requirements : $this->mailLockup($requirements);
            if ($requirements !== null) {
                $status['requirements'] = $requirements;
            }
            if ($this->option('json')) {
                $this->line(json_encode($status ?? ['message' => 'No kit features adopted.', 'blueprint_features' => [], 'lineages' => []], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } elseif ($status === null) {
                $this->line('No kit features adopted.');
            } else {
                $this->line('Adopted features: '.implode(', ', $status['blueprint_features']));
                foreach ($status['lineages'] as $lineage) {
                    $this->line('Lineage: '.$lineage['name'].' '.$lineage['version']);
                    $this->line('Identity: '.$lineage['identity']);
                    foreach ($lineage['files'] as $path => $state) {
                        $this->line($path.': '.$state.($state === 'edited' ? ' (conflict on next update)' : ''));
                    }
                }
                foreach ($requirements === null ? [] : [...HostRequirements::attention($requirements), ...HostRequirements::lines($requirements)] as $line) {
                    $this->line($line);
                }
                foreach ($requirements['failures'] ?? [] as $failure) {
                    $this->error('FAILED: '.$failure);
                }
            }

            // A failure (a host-owned config/multitenancy.php without the workspaces switch tasks) fails the status, never only warns.
            return ($requirements['failures'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
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
            : 'kit.brand.mail_lockup is '.$lockup.' but no PNG file is there (a path in public/ or an absolute file path), so mail shows the product\'s name instead of the lockup. Add the PNG, or fix KIT_BRAND_MAIL_LOCKUP.';
        $requirements['steps'][] = ['feature' => 'core', 'kind' => 'file', 'step' => 'kit.brand.mail_lockup', 'why' => 'the mail header shows the lockup', 'status' => $present ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if (! $present) {
            $requirements['warnings'][] = $message;
            $requirements['attention'] = true;
        }

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
