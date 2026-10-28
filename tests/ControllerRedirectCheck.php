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
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Http\SettingsController;
use Tey\Kit\Features\Workspaces\Http\InvitationsController;
use Tey\Kit\Features\Workspaces\Http\MovedWorkspacePageController;
use Tey\Kit\Features\Workspaces\Operations\AcceptWorkspaceInvitation;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A stand-in for a feature's controller: the kit ships no sample resource, so the page checks use workspace pages at /cases. */
class CasesStubController
{
    public function index(): string
    {
        return 'cases';
    }
}

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
    // Breadcrumbs: every page route declares one, so the router needs gretel's macros and registry.
    $app->singleton(Glhd\Gretel\Registry::class, fn () => new Glhd\Gretel\Registry($app['router']));
    Glhd\Gretel\Macros::register($app->make(Glhd\Gretel\Registry::class));
    Request::macro('validate', fn (array $rules): array => validator($this->all(), $rules)->validate());
    $session = new Store('redirect-check', new ArraySessionHandler(120));
    $app['redirect']->setSession($session);
    $app->instance('session', $session);

    // Replace only the persistence boundary and operations; the controllers execute unchanged.
    $app->instance(TenantBoundary::class, new class {
        public function run(int $workspace, callable $operation): mixed
        {
            return $operation();
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

    // No database here: workspace 42 is `acme`.
    WorkspaceUrl::lookupUsing(fn (int $workspace): string => 'acme');
    $user = new User;
    $user->setRawAttributes(['id' => 7]);
    $request = Request::create('https://kit.test', 'POST', ['token' => 'synthetic-invitation']);
    $request->setUserResolver(fn () => $user);
    // No database to count memberships in: this user has several workspaces, so links take the full form.
    $request->attributes->set('kit.short_urls', false);

    $loadRoutes = function () use ($app): void {
        $registry = FeaturesServiceProvider::registry(FeaturesServiceProvider::selected(['core', 'workspaces', 'access', 'settings', 'mcp']));
        // A stand-in feature, so the page checks need no shipped sample resource: workspace pages at /cases, in the primary nav.
        $casesFile = sys_get_temp_dir().'/kit-cases-routes-'.md5(__FILE__).'.php';
        file_put_contents($casesFile, "<?php\nuse Illuminate\\Support\\Facades\\Route;\nuse Tey\\Kit\\Features\\Workspaces\\WorkspaceRoutes;\nWorkspaceRoutes::define(function (bool \$full): void {\n    WorkspaceRoutes::page(Route::get('cases', [CasesStubController::class, 'index']), \$full, 'cases', 'workspaces.cases.index', 'Cases');\n});\n");
        $registry->routes($casesFile, 'web', FeaturesServiceProvider::routeOptions('cases'))->navigation('cases.read', 'Cases', 'cases');
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
        foreach (['workspaces', 'settings', 'cases', 'mcp'] as $feature) {
            $options[$feature] = $custom ? ['prefix' => 'custom/'.$feature, 'name' => 'custom.'.$feature.'.'] : [];
        }
        $app['config']->set('kit.routes', $options);
        $loadRoutes();
        // Guard: every page the kit routes (a GET it serves, bar the permanent redirects) declares a breadcrumb.
        foreach ($app['router']->getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && str_starts_with($route->getActionName(), 'Tey\\Kit\\') && ! str_contains($route->getActionName(), 'Moved')) {
                libraryCheck($app->make(Glhd\Gretel\Registry::class)->get($route) !== null, $route->uri().' declares a breadcrumb'.($custom ? ' (custom prefixes)' : ''));
            }
        }
        if (! $custom) {
            // The `@` that marks a tenant: generated literally, and a request with it percent-encoded resolves the same.
            libraryCheck(route('workspaces.cases.index', ['workspace' => 'acme']) === 'https://kit.test/@acme/cases', 'route() emits a literal @');
            foreach (['/@acme/cases', '/%40acme/cases'] as $path) {
                $matched = $app['router']->getRoutes()->match(Request::create('https://kit.test'.$path));
                libraryCheck($matched->getName() === 'workspaces.cases.index' && $matched->parameter('workspace') === 'acme', $path.' resolves to the workspace cases');
            }
            $short = $app['router']->getRoutes()->match(Request::create('https://kit.test/cases'));
            libraryCheck($short->getName() === 'short.workspaces.cases.index' && $short->getActionName() === CasesStubController::class.'@index' && $short->parameter('workspace') === null, '/cases is the short form of the same page');
            foreach (['/@Acme/cases', '/@-acme/cases', '/@a_b/cases'] as $path) {
                try {
                    $app['router']->getRoutes()->match(Request::create('https://kit.test'.$path));
                    libraryCheck(false, $path.' is refused');
                } catch (Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    libraryCheck(true, $path.' is not a workspace page');
                }
            }
            $moved = $app['router']->getRoutes()->match(Request::create('https://kit.test/workspaces/42/cases/9'));
            libraryCheck($moved->getActionName() === MovedWorkspacePageController::class && $moved->parameter('workspace') === '42' && $moved->parameter('path') === 'cases/9', 'the old /workspaces/{workspace}/... URL reaches the permanent redirect');
            libraryCheck($app['router']->getRoutes()->match(Request::create('https://kit.test/me/workspaces'))->getName() === 'workspaces.index', 'the workspace list is /me/workspaces');
        }
        $cases = [
            ['settings.update', fn ($r) => (new SettingsController)->update($r, 42), 'settings', 'settings/general', 'workspaces.settings.general', SettingsController::class.'@show', 200],
            ['connections.store', fn ($r) => (new ConnectionController)->store($r, 42), 'mcp', 'settings/connections', 'workspaces.connections.index', ConnectionController::class.'@index', 201],
            ['connections.destroy', fn ($r) => (new ConnectionController)->destroy($r, 42, 9), 'mcp', 'settings/connections', 'workspaces.connections.index', ConnectionController::class.'@index', 200],
            ['workspaces.accept', fn ($r) => (new InvitationsController)->store($r), 'cases', 'cases', 'workspaces.cases.index', CasesStubController::class.'@index', 200],
        ];
        foreach ($cases as [$label, $call, $feature, $path, $name, $action, $jsonStatus]) {
            $expected = 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').'@acme/'.$path;
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
                libraryCheck($matched->getName() === ($custom ? 'custom.'.$feature.'.' : '').$name && $matched->getActionName() === $action && $matched->parameter('workspace') === 'acme', $labelled.' resolves to the named GET controller and workspace');
            }
        }
        $toast = $session->get(Inertia\Support\SessionKey::FLASH_DATA)['toast'] ?? null;
        libraryCheck($toast !== null && $toast['type'] === 'success' && $toast['message'] === 'Workspace settings saved.' && $toast['from'] === 'save' && $toast['style'] === 'pulse' && ! $session->has('status'), 'settings redirect flashes a success toast from Save, and no unrendered status');

        $request->headers->set('Accept', 'text/html');
        foreach ([['cases'], []] as $navigation) {
            $fallback = new Registry;
            foreach ($navigation as $suffix) {
                $fallback->navigation($suffix.'.read', ucfirst($suffix), $suffix);
            }
            $app->instance(Registry::class, $fallback);
            $feature = $navigation === [] ? 'workspaces' : 'cases';
            $path = $navigation === [] ? 'me/workspaces' : '@acme/cases';
            $response = (new InvitationsController)->store($request);
            libraryCheck($response->getTargetUrl() === 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').$path, 'invitation fallback follows '.$feature.' prefixes');
            libraryCheck($app['router']->getRoutes()->match(Request::create($response->getTargetUrl()))->getName() === ($custom ? 'custom.'.$feature.'.' : '').'workspaces.'.($navigation === [] ? 'index' : 'cases.index'), 'invitation fallback resolves to its named GET route');
        }

        foreach ([['settings'], ['settings', 'cases']] as $disabled) {
            foreach ($disabled as $feature) {
                $app['config']->set('kit.routes.'.$feature.'.enabled', false);
            }
            $loadRoutes();
            $feature = count($disabled) === 1 ? 'cases' : 'workspaces';
            $path = $feature === 'cases' ? '@acme/cases' : 'me/workspaces';
            $response = (new InvitationsController)->store($request);
            libraryCheck($response->getTargetUrl() === 'https://kit.test/'.($custom ? 'custom/'.$feature.'/' : '').$path, 'invitation skips disabled '.implode(', ', $disabled).' routes');
            libraryCheck($app['router']->getRoutes()->match(Request::create($response->getTargetUrl()))->getName() === ($custom ? 'custom.'.$feature.'.' : '').'workspaces.'.($feature === 'cases' ? 'cases.index' : 'index'), 'disabled-route fallback resolves to a registered GET route');
        }

        Route::get('host/welcome/{workspace}', fn () => 'welcome')->name('host.welcome');
        $app['router']->getRoutes()->refreshNameLookups();
        $app['config']->set('kit.invitation_redirect', 'host.welcome');
        libraryCheck((new InvitationsController)->store($request)->getTargetUrl() === 'https://kit.test/host/welcome/42', 'explicit invitation redirect is a full host route name');
        $app['config']->set('kit.invitation_redirect', null);
    }
}

controllerRedirectChecks();
