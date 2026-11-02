<?php

// session-ui's per-group route switches and the home route's name (`kit.home_name`) (`kit.routes.session-ui.<group> => false`, SessionUiRoutes) on synthetic hosts booted through the composition root, with
// a host routes/web.php of its own: synthetic SQLite memory, no network. Covers registration, names, the host's own invitation route and the boot-time refusal.
use Glhd\Gretel\Registry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Laravel\Fortify\FortifyServiceProvider;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\SessionUiRoutes;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function groupsCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
/** A booted synthetic host with every UI feature, `$routes` as its `kit.routes.session-ui`, and its own invitation route in routes/web.php. */
function groupsHost(array $routes, array $config = [], string $hostRoutes = ''): Application
{
    $root = sys_get_temp_dir().'/kit-groups-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config', 'routes'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($root)));
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => User::class], 'routes' => ['session-ui' => $routes]] + $config, true).';');
    file_put_contents($root.'/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]];");
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', '<?php return '.var_export(['key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC'], true).';');
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    // The host's own invitation flow, loaded before the kit's routes, at the kit's address and names.
    file_put_contents($root.'/routes/web.php', "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('invitations/{token}', fn (string \$token) => 'host invitation '.\$token)->name('invitations.show');\nRoute::post('invitations/{token}', fn () => 'host accept')->name('invitations.accept');\n".$hostRoutes);
    $app = Application::configure($root)->withRouting(web: $root.'/routes/web.php')->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([FortifyServiceProvider::class, FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();
    $app['router']->getRoutes()->refreshNameLookups();

    return $app;
}
$names = fn (Application $app): array => array_values(array_filter(array_map(fn ($route): ?string => $route->getName(), $app['router']->getRoutes()->getRoutes())));
$action = fn (Application $app, string $name): string => (string) $app['router']->getRoutes()->getByName($name)?->getActionName();
$actions = fn (Application $app): string => implode(' ', array_map(fn ($route): string => $route->getActionName(), $app['router']->getRoutes()->getRoutes()));
$matched = fn (Application $app, string $method, string $uri): string => $app['router']->getRoutes()->match(Request::create($uri, $method))->getActionName();
$token = str_repeat('a', 32);

try {
    $on = groupsHost([]);
    $groups = ['invitations.show', 'focus.welcome', 'focus.welcome.show', 'focus.welcome.skip', 'me.welcome.dismiss', 'me.passkey-offer.dismiss', 'demo.login'];
    groupsCheck(array_diff($groups, $names($on)) === [], 'every group is on by default: invitations, welcome, passkey-offer and demo-login routes exist ('.implode(', ', array_diff($groups, $names($on))).')');
    groupsCheck(str_contains($matched($on, 'GET', '/invitations/'.$token), 'InvitationController'), 'by default the kit serves /invitations/{token} (its routes load after the host\'s), as before');

    $off = groupsHost(['invitations' => false]);
    groupsCheck(! str_contains($actions($off), 'SessionUi\\Http\\InvitationController@') && str_contains($actions($on), 'SessionUi\\Http\\InvitationController@'), 'invitations off: none of the kit\'s invitation-link routes is registered (the People and workspace invitation management stays)');
    groupsCheck($off['router']->getRoutes()->getByName('invitations.show')?->uri() === 'invitations/{token}' && str_contains($matched($off, 'POST', '/invitations/'.$token), 'Closure') && ! str_contains($matched($off, 'POST', '/invitations/'.$token), 'Invitation'), 'invitations off: the host\'s own invitations.show and accept resolve, by name and by URL');
    groupsCheck(! str_contains($actions($off), 'InvitationAccountController') && str_contains($actions($on), 'InvitationAccountController'), 'invitations off: the invitation\'s account route is gone too');
    groupsCheck(array_diff(['focus.welcome', 'focus.welcome.show', 'me.welcome.dismiss', 'me.passkey-offer.dismiss', 'demo.login', 'me.profile', 'home'], $names($off)) === [], 'invitations off: welcome, passkey-offer, the demo sign-in and the other pages stay');
    groupsCheck(SessionUiRoutes::on('welcome') && ! SessionUiRoutes::on('invitations'), 'SessionUiRoutes::on reads the switches');

    $quiet = groupsHost(['welcome' => false, 'passkey-offer' => false, 'demo-login' => false]);
    groupsCheck(array_intersect(['focus.welcome', 'focus.welcome.show', 'focus.welcome.update', 'focus.welcome.store', 'focus.welcome.skip', 'me.welcome.dismiss', 'me.passkey-offer.dismiss', 'demo.login'], $names($quiet)) === [], 'welcome, passkey-offer and demo-login off: their routes and names are absent');
    groupsCheck(str_contains($action($quiet, 'invitations.show'), 'InvitationController'), 'with the other groups off, the kit\'s invitation link stays');
    groupsCheck(! SessionUiRoutes::on('passkey-offer') && ! SessionUiRoutes::on('welcome'), 'SessionUiRoutes::on reports both off, which is what the passkey-offer share and the welcome redirect, card and after-join guards read');

    $whole = groupsHost(['enabled' => false]);
    groupsCheck(! in_array('focus.welcome', $names($whole), true) && ! SessionUiRoutes::on('welcome'), 'kit.routes.session-ui.enabled => false still turns off every group');

    foreach ([['invitation' => false], ['invitations' => 'no'], ['welcome' => 0]] as $bad) {
        try {
            groupsHost($bad);
            groupsCheck(false, 'refused: '.json_encode($bad));
        } catch (InvalidArgumentException $error) {
            groupsCheck(str_contains($error->getMessage(), 'kit.routes.session-ui.'.array_key_first($bad)), 'refused at boot with a clear error: '.json_encode($bad).' ('.$error->getMessage().')');
        }
    }
    try {
        SessionUiRoutes::on('invitation');
        groupsCheck(false, 'an unknown group name in kit code is refused');
    } catch (LogicException) {
        groupsCheck(true, 'an unknown group name in kit code is refused');
    }

    // kit.home_name: a host that owns `home` (its public landing at "/") names the kit's home `app.home` at /app; the kit's breadcrumbs root there.
    $byName = fn (Application $app, string $name): ?Illuminate\Routing\Route => $app['router']->getRoutes()->getByName($name);
    $kitHome = groupsHost([], ['home' => '/app', 'home_name' => 'app.home'], "Route::get('/', fn () => 'host landing')->name('home');\n");
    groupsCheck($byName($kitHome, 'app.home')?->uri() === 'app' && str_contains($byName($kitHome, 'app.home')->getActionName(), 'HomeController'), 'kit.home_name: the kit\'s home is named app.home, at /app');
    groupsCheck($byName($kitHome, 'home')?->uri() === '/' && str_contains($byName($kitHome, 'home')->getActionName(), 'Closure'), 'kit.home_name: the host\'s own `home` route keeps its name and its "/"');
    $gretel = $kitHome->make(Registry::class);
    $parent = fn (string $name): ?string => $gretel->get($byName($kitHome, $name))?->parent?->resolve([], $gretel)?->name;
    groupsCheck($parent('me.index') === 'app.home' && $parent('focus.welcome') === 'app.home' && $parent('focus.no-workspace') === 'app.home', 'kit.home_name: the account, welcome and no-workspace trails root at app.home ('.json_encode([$parent('me.index'), $parent('focus.welcome')]).')');
    $default = groupsHost([]);
    groupsCheck($byName($default, 'home')?->uri() === '/' && $default->make(Registry::class)->get($byName($default, 'me.index'))?->parent?->resolve([], $default->make(Registry::class))?->name === 'home', 'by default the kit\'s home is still `home` at "/", and trails root there');
    try {
        groupsHost([], ['home_name' => 'bad name!']);
        groupsCheck(false, 'a malformed kit.home_name is refused');
    } catch (InvalidArgumentException $error) {
        groupsCheck(str_contains($error->getMessage(), 'kit.home_name'), 'a malformed kit.home_name is refused at boot ('.$error->getMessage().')');
    }

    echo "Session-ui route groups: registration, names and the host's own invitation route on synthetic hosts. kit.home_name on a host that owns `home`. The guarded pages themselves over HTTP are not driven here.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
