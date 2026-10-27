<?php

// kit:install, then kit:add, on a fresh-Laravel-shaped app with no host binding; synthetic SQLite memory, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
/** A disposable fresh app: no LifecycleHost, no kit files, a lock file as composer leaves one. */
function freshApp(): string
{
    $root = sys_get_temp_dir().'/kit-install-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/fresh-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.lock', "{\"packages\": []}\n");

    return realpath($root);
}
/** Boots the app as `php artisan` would: its bootstrap providers, plus the discovered package providers. */
function console(string $root, array $providers = []): Kernel
{
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class, ...$providers])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return $kernel;
}
function tree(string $root): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
    }
    ksort($files);

    return $files;
}

/** The workspaces host step, as the preview prints it for the fresh app's User. */
const USER_STEP = "App\\Models\\User must implement Tey\\Concierge\\Contracts\\Member. In app/Models/User.php, add the two imports, implement the contract on the class declaration and use the trait in its body:\n    use Tey\\Concierge\\Contracts\\Member;\n    use Tey\\Kit\\Features\\Workspaces\\Concerns\\HasWorkspaces;\n    class User extends ... implements Member\n        use HasWorkspaces;";

/** The same step for a User that implements the contract, through Concierge's own traits, but not the kit's trait. */
const TRAIT_STEP = "App\\Models\\User implements Tey\\Concierge\\Contracts\\Member but must also use Tey\\Kit\\Features\\Workspaces\\Concerns\\HasWorkspaces. In app/Models/User.php, add the import and use the trait in the class body:\n    use Tey\\Kit\\Features\\Workspaces\\Concerns\\HasWorkspaces;\n        use HasWorkspaces;";

// A class loads once per process, and Mod's discovery loads the app's classes at boot: each unmet User is checked
// in a child process, on its own fresh app. `unmet` is the fresh app's own User; `no-trait` has the contract only.
if (in_array($argv[2] ?? null, ['unmet', 'no-trait'], true)) {
    try {
        $root = freshApp();
        $loader->addPsr4('App\\', $root.'/app', true);
        $step = USER_STEP;
        if ($argv[2] === 'no-trait') {
            $user = file_get_contents($root.'/app/Models/User.php');
            $user = str_replace("use Illuminate\\Foundation\\Auth\\User as Authenticatable;\n", "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\nuse Spatie\\Permission\\Traits\\HasRoles;\nuse Tey\\Concierge\\Concerns\\InteractsWithMemberships;\nuse Tey\\Concierge\\Contracts\\Member;\n", $user);
            $user = str_replace("extends Authenticatable\n{\n", "extends Authenticatable implements Member\n{\n    use HasRoles, InteractsWithMemberships;\n\n", $user);
            file_put_contents($root.'/app/Models/User.php', $user);
            $step = TRAIT_STEP;
        }
        check(console($root)->call('kit:install') === 0, $argv[2].': kit:install exits 0 on its own fresh app');
        $kernel = console($root);
        $before = tree($root);
        $code = $kernel->call('kit:add', ['features' => ['workspaces']]);
        $output = $kernel->output();
        check($code !== 0 && str_contains($output, 'host step (unmet): '.$step) && str_contains($output, 'The plan is not executable'), $argv[2].': the preview lists the unmet User host step with the lines to add, and is not executable');
        $kernel->call('kit:add', ['features' => ['workspaces'], '--json' => true]);
        $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
        check(! $preview['executable'] && $preview['unmet'] === [$step] && in_array($step, $preview['host_steps'], true), $argv[2].': the JSON preview lists it as unmet');
        $code = $kernel->call('kit:add', ['features' => ['workspaces'], '--apply' => true, '--expected-plan' => $preview['plan']]);
        check($code !== 0 && str_contains($kernel->output(), 'Unmet host step: '.$step), $argv[2].': the apply is refused while the step is unmet');
        check(tree($root) === $before && Lineages::adopted($root, 'fresh-app') === [], $argv[2].': the refusal writes nothing and adopts nothing');
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, (string) $error."\n");
        exit(1);
    }
}

try {
    // A fresh app's User is not a workspace member, nor is one with the contract but not the trait: kit:add names the step and refuses.
    foreach (['unmet', 'no-trait'] as $variant) {
        $child = proc_open([PHP_BINARY, __FILE__, $argv[1], $variant], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        echo stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        check(proc_close($child) === 0, 'the '.$variant.' host step checks pass'.($error === '' ? '' : ': '.$error));
    }

    $root = freshApp();
    // Every fresh app in this process shares the same App\ classes; the host reads its root from base_path().
    $loader->addPsr4('App\\', $root.'/app', true);
    // The host adds the lines the step names, before anything loads the class; kit never writes app/Models/User.php.
    $user = file_get_contents($root.'/app/Models/User.php');
    $user = str_replace("use Illuminate\\Foundation\\Auth\\User as Authenticatable;\n", "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\nuse Tey\\Concierge\\Contracts\\Member;\nuse Tey\\Kit\\Features\\Workspaces\\Concerns\\HasWorkspaces;\n", $user);
    $user = str_replace("extends Authenticatable\n{\n", "extends Authenticatable implements Member\n{\n    use HasWorkspaces;\n\n", $user);
    file_put_contents($root.'/app/Models/User.php', $user);

    // Without kit:install, kit:add still fails closed.
    $kernel = console($root);
    $before = tree($root);
    check($kernel->call('kit:add', ['features' => ['workspaces']]) !== 0 && str_contains($kernel->output(), 'A trusted LifecycleHost binding is required.'), 'without kit:install, kit:add refuses');
    check(tree($root) === $before, 'the refusal writes nothing');

    // kit:install writes the app-owned host and registers its provider.
    check($kernel->call('kit:install') === 0, 'kit:install exits 0');
    $output = $kernel->output();
    foreach (['app/Kit/KitHost.php', 'app/Kit/operations.php', 'config/kit-lifecycle.php', 'config/kit-executor.json', 'app/Providers/KitHostServiceProvider.php'] as $path) {
        check(is_file($root.'/'.$path) && str_contains($output, 'wrote '.$path), 'kit:install writes and prints '.$path);
    }
    check(str_contains($output, 'bootstrap/providers.php: added App\Providers\KitHostServiceProvider::class') && str_contains($output, 'app/Kit/operations.php: the operation check'), 'kit:install prints the provider change and what to review');
    check(file_get_contents($root.'/bootstrap/providers.php') === "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\KitHostServiceProvider::class,\n];\n", 'the provider line is appended; the host\'s list is otherwise unchanged');
    check(json_decode(file_get_contents($root.'/config/kit-executor.json'), true) === (new Lifecycle)->runtime(), 'the executor provenance is the current runtime');
    check((require $root.'/config/kit-lifecycle.php')['app_id'] === 'fresh-app', 'the app id is the slug of app.name');
    $installed = tree($root);
    check($kernel->call('kit:install') === 0 && str_contains($kernel->output(), 'already installed') && tree($root) === $installed, 'a second kit:install changes nothing');

    // A fresh boot binds the host; kit:add previews and applies.
    $kernel = console($root);
    $installed = tree($root);
    check($kernel->call('kit:install') === 0 && str_contains($kernel->output(), 'already installed') && tree($root) === $installed, 'kit:install after the host is bound is still a no-op');
    $code = $kernel->call('kit:add', ['features' => ['workspaces']]);
    check($code === 0, 'kit:install then kit:add workspaces previews (exit 0)'.($code === 0 ? '' : ': '.$kernel->output()));
    $output = $kernel->output();
    check(str_contains($output, 'core: add') && str_contains($output, 'workspaces: add') && ! str_contains($output, 'unmet') && str_contains($output, 'Nothing written') && tree($root) === $installed, 'with the step met, the preview lists core and workspaces and writes nothing');
    $kernel->call('kit:add', ['features' => ['workspaces'], '--json' => true]);
    $plan = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'];

    // The installed operation check fails closed until the host schema exists, then resume finishes.
    check($kernel->call('kit:add', ['features' => ['workspaces'], '--apply' => true, '--expected-plan' => $plan, '--intent' => 'fresh']) !== 0 && str_contains($kernel->output(), 'Stopped at core'), 'apply stops at core while the host schema is missing');
    check(Lineages::adopted($root, 'fresh-app') === [], 'nothing is adopted while the check fails');
    $schema = $kernel->call('migrate:install') === 0 ? app('db')->connection()->getSchemaBuilder() : throw new RuntimeException('migrate:install failed');
    $schema->create('users', function ($table): void {
        $table->id();
    });
    check($kernel->call('kit:add', ['features' => ['workspaces'], '--resume' => true, '--intent' => 'fresh']) === 0 && str_contains($kernel->output(), 'done: core, workspaces'), 'resume completes once the host schema exists');
    check(array_keys(Lineages::adopted($root, 'fresh-app')) === ['core', 'workspaces'] && is_dir($root.'/database/migrations/kit/workspaces'), 'core and workspaces are adopted, migrations delivered');
    check(array_intersect_key(tree($root), $installed) === $installed, 'kit:add leaves the installed host files alone');

    // A stale executor provenance (tey/mod upgraded since kit:install) is named once, with its fix, instead of failing the plan opaquely.
    $executor = file_get_contents($root.'/config/kit-executor.json');
    file_put_contents($root.'/config/kit-executor.json', json_encode(['installed_mod_version' => 'v0.9.1'])."\n");
    $code = $kernel->call('kit:add', ['features' => ['settings']]);
    $output = $kernel->output();
    check($code !== 0 && substr_count($output, 'executor_stale') === 1 && str_contains($output, 'config/kit-executor.json records tey/mod v0.9.1, but this app runs '.(new Lifecycle)->runtime()['installed_mod_version']) && str_contains($output, 'php artisan kit:update refresh-executor'), 'a stale executor provenance blocks the plan once, by name, with the refresh command');
    check($kernel->call('kit:update', ['action' => 'refresh-executor']) === 0 && str_contains($kernel->output(), 'was v0.9.1') && file_get_contents($root.'/config/kit-executor.json') === $executor, 'kit:update refresh-executor records the running tey/mod again');
    check($kernel->call('kit:update', ['action' => 'refresh-executor']) === 0 && str_contains($kernel->output(), 'nothing changed'), 'a second refresh changes nothing');
    file_put_contents($root.'/config/kit-executor.json', "not json\n");
    check($kernel->call('kit:update', ['action' => 'refresh-executor']) !== 0 && file_get_contents($root.'/config/kit-executor.json') === "not json\n", 'a provenance file that holds no recorded runtime is refused, untouched');
    file_put_contents($root.'/config/kit-executor.json', $executor);

    // The app lists its features in config/kit.php (an explicit list overrides the lineages): kit:add switches each added feature on there.
    file_put_contents($root.'/config/kit.php', "<?php\n\nreturn ['features' => ['core', 'workspaces'], 'app_id' => null];\n");
    config(['kit.features' => ['core', 'workspaces']]);

    // With the schema in place, --apply completes end to end on SQLite memory.
    $kernel->call('kit:add', ['features' => ['settings'], '--json' => true]);
    $plan = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR)['plan'];
    $code = $kernel->call('kit:add', ['features' => ['settings'], '--apply' => true, '--expected-plan' => $plan]);
    $output = $kernel->output();
    check($code === 0 && str_contains($output, 'access: completed') && str_contains($output, 'settings: completed'), '--apply completes end to end');
    check(array_keys(Lineages::adopted($root, 'fresh-app')) === ['core', 'workspaces', 'access', 'settings'], 'settings is adopted with its requirements');
    check((require $root.'/config/kit.php')['features'] === ['core', 'workspaces', 'access', 'settings'] && config('kit.features') === ['core', 'workspaces', 'access', 'settings'] && str_contains($output, 'config/kit.php: kit.features now names access, settings'), 'kit:add adds the new features to the app\'s kit.features list, in canonical order, and says so');
    // A list the kit cannot edit safely (computed, here from env) is left alone and named as the edit to make.
    file_put_contents($root.'/config/kit.php', "<?php\n\nreturn ['features' => explode(',', (string) env('KIT_FEATURES', 'core,workspaces,access,settings'))];\n");
    $switched = Tey\Kit\FeatureSwitch::on($root, ['mcp'], ['core', 'workspaces', 'access', 'settings']);
    check($switched['changed'] === [] && str_contains($switched['attention'][0] ?? '', "Add 'mcp' to it") && str_contains(file_get_contents($root.'/config/kit.php'), "env('KIT_FEATURES'"), 'a computed list is never rewritten; the attention names the feature to add');
    check(Tey\Kit\FeatureSwitch::on($root, ['mcp'], null) === ['changed' => [], 'attention' => []], 'with kit.features null the lineage switches the feature on, so nothing is edited');
    unlink($root.'/config/kit.php');
    config(['kit.features' => null]);

    // The host's next step, `php artisan migrate`, on a fresh boot with the adopted features' packages discovered.
    $kernel = console($root, [PermissionServiceProvider::class, MultitenancyServiceProvider::class, ConciergeServiceProvider::class, FeaturesServiceProvider::class]);
    $code = $kernel->call('migrate', ['--force' => true]);
    $output = $kernel->output();
    check($code === 0 && str_contains($output, 'create_concierge_tables') && str_contains($output, 'create_kit_workspaces_table'), 'php artisan migrate succeeds on SQLite memory, Concierge\'s migrations included'.($code === 0 ? '' : ': '.$output));

    // An existing host file is refused, never overwritten, and nothing else is written.
    $other = freshApp();
    mkdir($other.'/app/Kit', 0700);
    file_put_contents($other.'/app/Kit/KitHost.php', '<?php // the app\'s own host');
    $kernel = console($other);
    $before = tree($other);
    $code = $kernel->call('kit:install');
    $output = $kernel->output();
    check($code !== 0 && str_contains($output, 'Refusing to overwrite existing files') && str_contains($output, 'app/Kit/KitHost.php') && file_get_contents($other.'/app/Kit/KitHost.php') === '<?php // the app\'s own host', 'a pre-existing app/Kit/KitHost.php is refused and listed');
    check(tree($other) === $before, 'the refusal writes nothing, bootstrap/providers.php included');
    echo "kit:install checks on a synthetic fresh app only; package providers registered by hand, no discovery, HTTP or consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
