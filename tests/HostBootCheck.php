<?php

// What the composition root boots for a host that adopted only some features, from kit.features alone (an unset list fails closed, logged once), and
// that kit:cache and kit:clear are no-ops; synthetic SQLite memory, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Tey\Kit\Blueprint;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\CacheCommand;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\PackageMigrator;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\Shell\ShellProvider;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;
use Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
/** A booted synthetic host that adopted the named features (an explicit `kit.features`). */
function host(?array $features, array $config = [], array $providers = [], ?string $appName = null, array $files = []): Application
{
    $root = sys_get_temp_dir().'/kit-boot-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    // The brand goes in the app's own config/brand.php, its only source since 0.17.
    if (isset($config['brand'])) {
        file_put_contents($root.'/config/brand.php', '<?php return '.var_export($config['brand'], true).';');
        unset($config['brand']);
    }
    foreach ($files as $path => $bytes) {
        @mkdir(dirname($root.'/'.$path), 0700, true);
        file_put_contents($root.'/'.$path, $bytes);
    }
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => $features, 'models' => ['user' => User::class]] + $config, true).';');
    file_put_contents($root.'/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]];");
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'] + ($appName === null ? [] : ['name' => $appName]), true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/mail.php', "<?php return ['from' => ['address' => 'host@example.test', 'name' => 'Host Own Name']];");
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([...$providers, FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    return $app;
}
function health(Application $app): ?string
{
    try {
        $app['events']->dispatch(new DiagnosingHealth);
    } catch (RuntimeException $error) {
        return $error->getMessage();
    }

    return null;
}
function listening(Application $app, string $event, string $listener): bool
{
    foreach ($app['events']->getListeners($event) as $closure) {
        $reflection = new ReflectionFunction($closure);
        if (str_contains(serialize(array_keys($reflection->getStaticVariables())), 'listener') && in_array($listener, array_map(fn ($v) => is_string($v) ? $v : '', $reflection->getStaticVariables()), true)) {
            return true;
        }
    }

    return false;
}

try {
    // A host that adopted only core, with its own tenancy and its own migrations.
    $core = host(['core']);
    check(health($core) !== null && str_contains(health($core), 'kit_meta') && ! str_contains(health($core), 'workspaces'), 'core-only: /up asks for core\'s kit_meta and for no workspace table');
    $core['db']->connection()->getSchemaBuilder()->create('users', fn ($t) => $t->id());
    $core['db']->connection()->getSchemaBuilder()->create('kit_meta', fn ($t) => $t->string('key'));
    check(health($core) === null, 'core-only: /up is healthy without workspaces, concierge_memberships or roles');
    check($core['config']->get('mail.from') === ['address' => 'host@example.test', 'name' => 'Host Own Name'] && $core['config']->get('mail.reply_to') === null, 'core-only: mail.from and mail.reply_to are the host\'s');
    check(! listening($core, NoPendingMigrations::class, CatalogSync::class), 'core-only: CatalogSync is not registered');

    // A registered refusal below 500 is expected and never reported; an unregistered code, or one registered as 5xx, still is.
    $core->make(Registry::class)->errors(['bench_unavailable' => 404, 'ledger_offline' => 503]);
    Facade::setFacadeApplication($core);
    $exceptions = Exceptions::fake();
    report(new RuntimeException('bench_unavailable'));
    report(new RuntimeException('not_a_registered_code'));
    report(new RuntimeException('ledger_offline'));
    $reported = array_map(fn (Throwable $e): string => $e->getMessage(), $exceptions->reported());
    check($reported === ['not_a_registered_code', 'ledger_offline'], 'core-only: a registered 4xx refusal is not reported; an unregistered code and a registered 5xx code are');

    // A host with its own tenancy that adopted core and shell: the shell's generated layout, with no session, workspace or access provider.
    $shell = host(['core', 'shell']);
    check($shell->getProvider(ShellProvider::class) !== null && $shell->getProvider(SessionUiProvider::class) === null && $shell->getProvider(WorkspacesProvider::class) === null && $shell->getProvider(AccessProvider::class) === null, 'core+shell: only the core and shell providers register');
    check(! str_contains((string) health($shell), 'workspaces') && array_map(fn ($route) => $route->uri(), $shell['router']->getRoutes()->getRoutes()) === array_map(fn ($route) => $route->uri(), $core['router']->getRoutes()->getRoutes()) && ! $shell->bound(LoginResponse::class), 'core+shell: no workspace table, no kit routes beyond core-only\'s, no Fortify');

    // kit.features is the only source. Unset, nothing registers, even beside install history and an app id (what 0.17 read), and the error is logged once per process.
    $log = fn (Application $app): string => (string) @file_get_contents($app->storagePath('logs/laravel.log'));
    $history = ['.mod-maintenance/installed/'.hash('sha256', 'tey/kit:core').'.json' => '{"name":"tey/kit:core"}', 'config/kit-lifecycle.php' => "<?php return ['app_id' => 'host'];"];
    $unset = host(null, ['app_id' => 'host'], [], null, $history);
    check($unset->getProvider(CoreProvider::class) === null && ! $unset->bound(Registry::class), 'kit.features unset: no feature registers, beside install history and an app id');
    check(substr_count($log($unset), FeaturesServiceProvider::UNSET) === 1 && str_contains($log($unset), '.ERROR: '), 'kit.features unset: the error is logged');
    check($log(host(null)) === '', 'it is logged once per process: a second unset app logs nothing');
    (new ReflectionProperty(FeaturesServiceProvider::class, 'unsetLogged'))->setValue(null, false);
    // Nothing adopted: no health listener at all.
    $none = host([]);
    check($none->getProvider(CoreProvider::class) === null && substr_count($log($none), FeaturesServiceProvider::UNSET) === 1, 'an empty kit.features is unset too: nothing registers, and the error is logged');
    check(health($none) === null && $none['config']->get('mail.reply_to') === null, 'no features: /up is healthy and mail is untouched');
    check(get_class($unset->make('migrator')) === Migrator::class && get_class($none->make('migrator')) === Migrator::class, 'no features: the app keeps its own migrator, never the kit\'s');
    check(host(['core'])->make('migrator') instanceof PackageMigrator, 'a listed feature: the migrator is the kit\'s');

    $config = host(['core', 'workspaces'], [], [], null, ['.mod-maintenance/installed/'.hash('sha256', 'tey/kit:access').'.json' => '{"name":"tey/kit:access"}']);
    check($config->getProvider(WorkspacesProvider::class) !== null && $config->getProvider(AccessProvider::class) === null && $config->make(Registry::class)->adopts('workspaces') && ! $config->make(Registry::class)->adopts('access'), 'kit.features alone decides: the listed features register and nothing else');
    check($config['config']->get('kit.workspaces.adopt') === null && $config['config']->get('kit.access.adopt') === null && host(['core'], ['workspaces' => ['adopt' => true], 'access' => ['adopt' => true]])['config']->get('kit.workspaces.adopt') === true, 'the adopt flags are kit.workspaces.adopt and kit.access.adopt as configured, never read from a record');

    // kit:cache and kit:clear stay registered, still run by optimize and optimize:clear, and do nothing.
    $before = scandir($config->bootstrapPath('cache'));
    $kernel = $config->make(Kernel::class);
    check($kernel->call('kit:cache') === 0 && str_contains($kernel->output(), CacheCommand::NOTHING) && $kernel->call('kit:clear') === 0 && str_contains($kernel->output(), CacheCommand::NOTHING), 'kit:cache and kit:clear succeed and say there is nothing to cache since 0.18');
    check(scandir($config->bootstrapPath('cache')) === $before, 'kit:cache writes nothing');
    check((ServiceProvider::$optimizeCommands['kit'] ?? null) === 'kit:cache' && (ServiceProvider::$optimizeClearCommands['kit'] ?? null) === 'kit:clear', 'optimize runs kit:cache and optimize:clear runs kit:clear');

    // The explicit opt-in applies the brand without session-ui.
    $opt = host(['core'], ['brand_mail' => true]);
    check($opt['config']->get('mail.from.name') !== 'Host Own Name', 'brand_mail => true applies the brand to mail on a core-only host');

    // Full adoption keeps today's behaviour.
    $full = host(['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui']);
    $message = health($full);
    check($message !== null && str_contains($message, 'the table workspaces is missing') && str_contains($message, 'concierge_memberships') && str_contains($message, 'roles') && str_contains($message, 'kit_meta'), 'full adoption: the health checks name every table');
    // Unset brand_mail with the brand still at its placeholders: mail stays the host's, and /up never fails on placeholders (a default never takes /up red).
    check($full['config']->get('mail.from.name') === 'Host Own Name', 'full adoption with an unfilled brand: mail stays the host\'s');
    check($message === null || ! str_contains($message, 'brand'), 'full adoption with an unfilled brand: /up does not fail on brand placeholders');
    // With the brand filled in, session-ui applies it to mail.
    $filled = host(['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], ['brand' => ['logomark' => '/mark.svg', 'accent' => '#0f766e', 'hello_email' => 'hi@acme.test', 'support_email' => 'help@acme.test', 'privacy_email' => 'privacy@acme.test', 'mail_from_address' => 'mail@acme.test', 'legal_name' => 'Acme Ltd', 'postal_address' => '2 Real Road', 'terms_url' => '/legal/terms', 'privacy_url' => '/legal/privacy']], [], 'Acme');
    check($filled['config']->get('mail.from.name') !== 'Host Own Name' && $filled['config']->get('mail.reply_to') !== null, 'full adoption with a filled brand: the brand is applied to mail');
    check(listening($full, NoPendingMigrations::class, CatalogSync::class), 'full adoption: CatalogSync is registered');
    // A migrate on a cold cache (the workspace columns not cached, so Cache::forget answers false): the cache listener must not stop the event,
    // or CatalogSync never runs and a deploy silently skips new roles and permissions.
    $sync = new class
    {
        public int $runs = 0;

        public function handle(object $event): void
        {
            $this->runs++;
        }
    };
    $full->instance(CatalogSync::class, $sync);
    $full['cache']->forget('kit-workspace-columns');
    $full['events']->dispatch(new MigrationsEnded('up'));
    check($sync->runs === 1, 'full adoption: CatalogSync runs after a migrate even with the workspace columns uncached');
    // The kit's home is "/" by default; a host with its own landing page moves it (kit.home) and keeps "/".
    $home = fn (Application $app): array => [tap($app['router']->getRoutes())->refreshNameLookups()->getByName('home')?->uri(), array_values(array_filter(array_map(fn ($route): string => $route->uri(), $app['router']->getRoutes()->getRoutes()), fn (string $uri): bool => $uri === '/'))];
    check($home($full) === ['/', ['/']], 'by default the kit\'s home is "/"');
    $landing = host(['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], ['home' => '/app']);
    check($home($landing) === ['app', []], 'kit.home moves the kit\'s home to /app and leaves "/" to the host\'s own landing page');
    $owns = Blueprint::lineage('session-ui')['feature']['owns'];
    $owned = fn (string $path): bool => array_filter($owns, fn (string $prefix): bool => $path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/')) !== [];
    check(! $owned('resources/js/pages/Welcome/Page.vue') && ! $owned('resources/js/pages/Welcome/Partials/Showcase.vue') && ! $owned('resources/js/pages/Welcome.vue'), 'session-ui does not own a host\'s landing page under pages/Welcome');
    $workspaceOwns = Blueprint::lineage('workspace-ui')['feature']['owns'];
    check($owned('resources/js/pages/Welcome/Timezone/Page.vue') && $owned('resources/js/pages/Welcome/Photo/Page.vue') && ! $owned('resources/js/pages/Welcome/WorkspaceTimezone/Page.vue') && in_array('resources/js/pages/Welcome/WorkspaceTimezone', $workspaceOwns, true), 'it owns only its two workspace-free welcome steps there; workspace-ui owns the workspace-timezone step');
    check($full->getProvider(ShellProvider::class) !== null, 'a kit.features list from before the split (session-ui, no shell) boots with shell');

    // Auth-only: session-ui without workspace-ui. The sign-in pages and the flows around them, and none of the workspace or account pages.
    $auth = host(['core', 'workspaces', 'access', 'session-ui'], [], [FortifyServiceProvider::class]);
    $names = fn (Application $app): array => array_values(array_filter(array_map(fn ($route): ?string => $route->getName(), tap($app['router']->getRoutes())->refreshNameLookups()->getRoutes())));
    check($auth->getProvider(SessionUiProvider::class) !== null && $auth->getProvider(WorkspaceUiProvider::class) === null, 'auth-only: session-ui registers and workspace-ui does not');
    $present = ['login', 'password.request', 'password.reset', 'password.email', 'password.update', 'two-factor.login', 'verification.notice', 'verification.verify', 'focus.welcome', 'focus.welcome.show', 'account.switch', 'avatars.show', 'me.avatar.store'];
    check(array_diff($present, $names($auth)) === [], 'auth-only: the sign-in, password reset, two-factor, verify-email and welcome routes exist ('.implode(', ', array_diff($present, $names($auth))).')');
    $absent = ['home', 'workspaces.dashboard.index', 'short.workspaces.search.index', 'workspaces.search.index', 'workspaces.settings.index', 'workspaces.people.index', 'me.index', 'me.profile', 'me.password', 'me.security', 'focus.no-workspace', 'invitations.show', 'demo.login'];
    check(array_intersect($absent, $names($auth)) === [] && array_diff($absent, $names($full)) === [], 'auth-only: no home, dashboard, search, settings landing, People, account, no-workspace, invitation or demo sign-in route; full adoption has each (workspace-ui\'s since 0.6.0 for the invitation and the demo sign-in)');
    check(! in_array('me.welcome.dismiss', $names($auth), true) && ! in_array('me.welcome.dismiss', $names($full), true) && in_array('focus.welcome', $names($full), true), 'the welcome card\'s dismiss route is gone since 0.17 (auth-only and full adoption); /welcome stays');
    check($home($auth) === [null, []], 'auth-only: the kit serves nothing at "/", which stays the host\'s');
    check($auth['config']->get('fortify.home') === '/' && Fortify::redirects('login') === '/' && $full['config']->get('fortify.home') === '/', 'after sign-in Fortify sends the person to kit.home: auth-only the app\'s own home, with workspace-ui the last workspace (else the list)');
    check($full['config']->get('concierge.workspaces.no_workspace_url') === '/no-workspace' && str_contains((string) tap($full['router']->getRoutes())->refreshNameLookups()->getByName('workspaces.index')?->getActionName(), 'WorkspaceController'), 'with workspace-ui: Concierge\'s no-workspace page and the /me/workspaces chooser are as before');
    check($auth['config']->get('concierge.workspaces.no_workspace_url') === '/' && ! str_contains((string) tap($auth['router']->getRoutes())->refreshNameLookups()->getByName('workspaces.index')?->getActionName(), 'WorkspaceController'), 'auth-only: Concierge\'s no-workspace address is kit.home, and /me/workspaces is no chooser');
    $appHome = host(['core', 'workspaces', 'access', 'session-ui'], ['home' => '/app']);
    check($appHome['config']->get('fortify.home') === '/app', 'auth-only: with kit.home set, sign-in lands there');
    $registry = $auth->make(Registry::class);
    check($registry->errorPage() === null && $registry->widgets() === [] && $registry->capabilityFor('dashboard.read') === null && array_intersect(['profile', 'password', 'security'], array_column($registry->settingsSections('personal'), 'key')) === [], 'auth-only: no error page, widget, dashboard ability or account section is contributed');
    // The pages themselves over HTTP (sign-in, its redirect, an invitation, the missing account pages): composer test:auth-only.
    $branded = host(['core', 'workspaces', 'access', 'settings', 'session-ui'], ['brand_mail' => false]);
    check($branded['config']->get('mail.from.name') === 'Host Own Name', 'brand_mail => false keeps mail the host\'s even with session-ui');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
