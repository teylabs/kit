<?php

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Http\ConnectionController;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Notes\Http\NoteController;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\Http\WorkspaceController;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;
use Tey\Kit\Features\Workspaces\TenantBoundary;

// Real Laravel routes, URL generation, responses and validation; synthetic operation results, no database or middleware.
function controllerRedirectChecks(): void
{
    $app = new Application(dirname(__DIR__));
    $app->instance('config', new Repository(['app' => ['url' => 'https://kit.test', 'locale' => 'en'], 'view' => ['paths' => []], 'kit' => ['models' => ['user' => User::class]]]));
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    $app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
    $app->register(Illuminate\View\ViewServiceProvider::class);
    $app->register(Illuminate\Translation\TranslationServiceProvider::class);
    $app->register(Illuminate\Validation\ValidationServiceProvider::class);
    Request::macro('validate', fn (array $rules): array => validator($this->all(), $rules)->validate());
    $session = new Store('redirect-check', new ArraySessionHandler(120));
    $app['redirect']->setSession($session);

    // Replace only the persistence boundary and operations; the controllers execute unchanged.
    $app->instance(TenantBoundary::class, new class {
        public function run(int $workspace, callable $operation): mixed
        {
            return $operation();
        }
    });
    $app->instance(WorkspaceNotes::class, new class {
        public function mutate(Principal $principal, string $operation, array $input): object
        {
            return new class {
                public function toArray(): array
                {
                    return ['note_id' => 9];
                }
            };
        }
    });
    $app->instance(SettingsUi::class, new class {
        public function update(Principal $principal, int $workspace, array $input): array
        {
            return ['display_name' => 'Fixture'];
        }
    });
    $app->instance(WorkspaceConnection::class, new class {
        public function approve(Principal $principal, array $input): array
        {
            return ['created' => true];
        }

        public function revoke(Principal $principal, int $connection): array
        {
            return ['revoked' => true];
        }
    });
    $app->instance(AcceptWorkspaceInvitation::class, new class {
        public function handle(Principal $principal, string $token): array
        {
            return ['workspace_id' => 42];
        }
    });

    $user = new User;
    $user->setRawAttributes(['id' => 7]);
    $request = Request::create('https://kit.test', 'POST', ['token' => 'synthetic-invitation']);
    $request->setUserResolver(fn () => $user);

    $loadRoutes = function () use ($app): void {
        $registry = FeaturesServiceProvider::registry(FeaturesServiceProvider::selected(['core', 'workspaces', 'access', 'settings', 'notes', 'mcp']));
        $app->instance(Registry::class, $registry);
        $app['router']->setRoutes(new Illuminate\Routing\RouteCollection);
        $web = new Registry;
        foreach ($registry->routeFiles() as $route) {
            if ($route['group'] === 'web') {
                $web->routes($route['file'], $route['group'], $route['options']);
            }
        }
        $web->loadRoutes();
        $app['router']->getRoutes()->refreshNameLookups();
        $app['url']->setRoutes($app['router']->getRoutes());
    };

    // Distinct per-feature prefixes catch accidentally using the source feature's prefix for a destination.
    foreach ([false, true] as $custom) {
        $options = [];
        foreach (['workspaces', 'settings', 'notes', 'mcp'] as $feature) {
            $options[$feature] = $custom ? ['prefix' => 'custom/'.$feature, 'name' => 'custom.'.$feature.'.'] : [];
        }
        $app['config']->set('kit.routes', $options);
        $loadRoutes();
        $cases = [
            ['notes.store', fn ($r) => (new NoteController)->store($r, 42), 'notes', 'notes', NoteController::class.'@index', 201],
            ['notes.update', fn ($r) => (new NoteController)->update($r, 42, 9), 'notes', 'notes', NoteController::class.'@index', 200],
            ['notes.destroy', fn ($r) => (new NoteController)->destroy($r, 42, 9), 'notes', 'notes', NoteController::class.'@index', 200],
            ['settings.update', fn ($r) => (new SettingsController)->update($r, 42), 'settings', 'settings', SettingsController::class.'@show', 200],
            ['connections.store', fn ($r) => (new ConnectionController)->store($r, 42), 'mcp', 'connections', ConnectionController::class.'@index', 201],
            ['connections.destroy', fn ($r) => (new ConnectionController)->destroy($r, 42, 9), 'mcp', 'connections', ConnectionController::class.'@index', 200],
            ['workspaces.accept', fn ($r) => (new WorkspaceController)->accept($r), 'notes', 'notes', NoteController::class.'@index', 200],
        ];
        foreach ($cases as [$label, $call, $feature, $suffix, $action, $jsonStatus]) {
            $expected = 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').'workspaces/42/'.$suffix;
            foreach (['html', 'inertia', 'json'] as $format) {
                $request->headers->set('Accept', $format === 'json' ? 'application/json' : 'text/html');
                $request->headers->remove('X-Inertia');
                if ($format === 'inertia') {
                    $request->headers->set('X-Inertia', 'true');
                }
                $response = $call($request);
                $labelled = $label.' '.$format.($custom ? ' custom prefixes' : ' default prefixes');
                if ($format === 'json') {
                    libraryCheck($response instanceof JsonResponse && $response->getStatusCode() === $jsonStatus, $labelled.' retains JSON status');
                    continue;
                }
                libraryCheck($response instanceof RedirectResponse && $response->getStatusCode() === 302 && $response->getTargetUrl() === $expected, $labelled.' redirects to '.$expected);
                $matched = $app['router']->getRoutes()->match(Request::create($response->getTargetUrl()));
                libraryCheck($matched->getName() === ($custom ? 'custom.'.$feature.'.' : '').'workspaces.'.$suffix.'.index' && $matched->getActionName() === $action && $matched->parameter('workspace') === '42', $labelled.' resolves to the named GET controller and workspace');
            }
        }
        libraryCheck($session->get('status') === 'Workspace settings saved.', 'settings redirect keeps its flash message');

        $request->headers->set('Accept', 'text/html');
        foreach ([['notes'], []] as $navigation) {
            $fallback = new Registry;
            foreach ($navigation as $suffix) {
                $fallback->navigation($suffix.'.read', ucfirst($suffix), $suffix);
            }
            $app->instance(Registry::class, $fallback);
            $feature = $navigation === [] ? 'workspaces' : 'notes';
            $path = $navigation === [] ? 'workspaces' : 'workspaces/42/notes';
            $response = (new WorkspaceController)->accept($request);
            libraryCheck($response->getTargetUrl() === 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').$path, 'invitation fallback follows '.$feature.' prefixes');
            libraryCheck($app['router']->getRoutes()->match(Request::create($response->getTargetUrl()))->getName() === ($custom ? 'custom.'.$feature.'.' : '').'workspaces.'.($navigation === [] ? 'index' : 'notes.index'), 'invitation fallback resolves to its named GET route');
        }

        foreach ([['settings'], ['settings', 'notes']] as $disabled) {
            foreach ($disabled as $feature) {
                $app['config']->set('kit.routes.'.$feature.'.enabled', false);
            }
            $loadRoutes();
            $feature = count($disabled) === 1 ? 'notes' : 'workspaces';
            $path = $feature === 'notes' ? 'workspaces/42/notes' : 'workspaces';
            $response = (new WorkspaceController)->accept($request);
            libraryCheck($response->getTargetUrl() === 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').$path, 'invitation skips disabled '.implode(', ', $disabled).' routes');
            libraryCheck($app['router']->getRoutes()->match(Request::create($response->getTargetUrl()))->getName() === ($custom ? 'custom.'.$feature.'.' : '').'workspaces.'.($feature === 'notes' ? 'notes.index' : 'index'), 'disabled-route fallback resolves to a registered GET route');
        }

        Route::get('host/welcome/{workspace}', fn () => 'welcome')->name('host.welcome');
        $app['router']->getRoutes()->refreshNameLookups();
        $app['config']->set('kit.invitation_redirect', 'host.welcome');
        libraryCheck((new WorkspaceController)->accept($request)->getTargetUrl() === 'https://kit.test/host/welcome/42', 'explicit invitation redirect is a full host route name');
        $app['config']->set('kit.invitation_redirect', null);
    }
}

controllerRedirectChecks();
