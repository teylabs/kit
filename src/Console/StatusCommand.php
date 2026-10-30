<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
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
