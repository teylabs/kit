<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Brand;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
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
            $requirements = $status === null ? $requirements : $this->logomark($status['blueprint_features'], $requirements);
            $requirements = $status === null ? $requirements : $this->mailLockup($requirements);
            $requirements = $status === null ? $requirements : $this->permissions($status['blueprint_features'], $requirements);
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
            }

            return self::SUCCESS;
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
     * The shell's Wordmark shows `kit.brand.logomark` (`/logomark.svg` by default). A public path with no file behind it 404s in every shell bar and favicon, so
     * a missing file is attention, never blocking. A full URL, or no logomark (the monogram), is not checked.
     *
     * @param  list<string>  $features
     * @param  array<string, mixed>|null  $requirements
     * @return array<string, mixed>|null
     */
    private function logomark(array $features, ?array $requirements): ?array
    {
        $logomark = Brand::get('logomark');
        if (! in_array('shell', $features, true) || ! is_string($logomark) || ! str_starts_with($logomark, '/') || str_starts_with($logomark, '//')) {
            return $requirements;
        }
        $path = (string) parse_url($logomark, PHP_URL_PATH);
        $present = is_file(public_path(ltrim($path, '/')));
        $requirements ??= ['steps' => [], 'npm' => [], 'optional' => [], 'attention' => false, 'warnings' => []];
        $message = $present ? 'public'.$path.' is there'
            : 'kit.brand.logomark is '.$logomark.' but public'.$path.' does not exist, so the shell\'s mark and favicon 404. Add the SVG there, or set KIT_BRAND_LOGOMARK to your own file.';
        $requirements['steps'][] = ['feature' => 'shell', 'kind' => 'file', 'step' => 'kit.brand.logomark', 'why' => 'the shell\'s mark is the logomark', 'status' => $present ? 'met' : 'unmet', 'blocking' => false, 'message' => $message];
        if (! $present) {
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
