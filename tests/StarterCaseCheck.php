<?php

// Starter-kit hosts: a declared path the host has under another case (pages/auth for pages/Auth) is a named conflict in the plan,
// kit:add --adopt-case renames it and the render names, kit:adopt --json speaks kit:add's plan schema, and an ignored
// .mod-maintenance/installed/ is pointed out. Synthetic catalog and host app, a throwaway git checkout, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Process;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\HostRequirements;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const PAGE = 'resources/js/pages/Auth/Login/Page.vue';

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
/** The fixture catalog with session-ui declaring an auth page under pages/Auth, pinned independently of Blueprint. */
function catalog(): Catalog
{
    $root = sys_get_temp_dir().'/kit-starter-case-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    $manifest = json_decode(file_get_contents($root.'/session-ui/feature.json'), true);
    $manifest['owns'][] = 'resources/js/pages/Auth/Login';
    $manifest['files'][PAGE] = ['stub' => 'login-page.vue.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
    file_put_contents($root.'/session-ui/login-page.vue.stub', "<template><main>Sign in</main></template>\n");
    file_put_contents($root.'/session-ui/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    $pins = [];
    foreach (['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes'] as $id) {
        $directory = $root.'/'.$id;
        $stubs = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if ($relative !== 'feature.json' && ! str_contains('/'.$relative, '/.')) {
                $stubs[$relative] = hash('sha256', file_get_contents($file->getPathname()));
            }
        }
        ksort($stubs);
        $pins[$id] = 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $directory.'/feature.json'), $stubs]));
    }

    return Catalog::at($root, $pins, 'sha256:'.hash_file('sha256', $root.'/blueprint.json'));
}
function run(string $root, array $command): string
{
    $process = new Process($command, $root);
    $process->mustRun();

    return $process->getOutput();
}
/** A disposable host app, booted with a trusted binding; a starter-kit one keeps its auth pages in lowercase pages/auth. */
function hostApp(Lineages $kit, bool $starter, bool $git): array
{
    $root = sys_get_temp_dir().'/kit-starter-case-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/host-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'verification'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () { return true; };');
    if ($starter) {
        mkdir($root.'/resources/js/pages/auth', 0700, true);
        file_put_contents($root.'/resources/js/pages/auth/Login.vue', "<template><main>Starter sign in</main></template>\n");
        file_put_contents($root.'/routes/auth.php', "<?php\n\nuse Inertia\\Inertia;\n\nRoute::get('login', fn () => Inertia::render('auth/Login'));\nRoute::get('register', fn () => Inertia::render(\"auth/Register\", ['open' => true]));\nRoute::inertia('/forgot', 'auth/ForgotPassword');\n// An unrelated string stays: 'auth/Login'\n");
        mkdir($root.'/app/Http/Controllers/Auth', 0700, true);
        file_put_contents($root.'/app/Http/Controllers/Auth/LoginController.php', "<?php\n\nnamespace App\\Http\\Controllers\\Auth;\n\nfinal class LoginController\n{\n    public function create()\n    {\n        return inertia( 'auth/Login', ['status' => null]);\n    }\n}\n");
    }
    $root = realpath($root);
    if ($git) {
        run($root, ['git', 'init', '-q']);
        run($root, ['git', 'add', '-A']);
        run($root, ['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', '-c', 'commit.gpgsign=false', 'commit', '-q', '-m', 'starter']);
    }
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $app->instance(LifecycleHost::class, new class(new Lifecycle, $paths, $kit, require $root.'/verification/host-operations.txt') implements CatalogHost, LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths, private Lineages $kit, private Closure $check) {}

        public function catalog(): Catalog
        {
            return (new ReflectionProperty(Lineages::class, 'catalog'))->getValue($this->kit);
        }

        public function lifecycle(): Lifecycle
        {
            return $this->lifecycle;
        }

        public function appId(): string
        {
            return 'host';
        }

        public function bindings(): array
        {
            return ['frontend' => 'SyntheticLayout'];
        }

        public function inputPaths(): array
        {
            return $this->paths;
        }

        public function operationCheck(): Closure
        {
            return $this->check;
        }
    });
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return [$root, $paths, $kernel];
}
function tree(string $root): array
{
    $tree = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (! str_starts_with($relative, '.git/') && ! str_starts_with($relative, 'storage/') && ! str_starts_with($relative, 'bootstrap/cache/')) {
            $tree[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($tree);

    return $tree;
}
function json(Kernel $kernel): array
{
    return json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
}

try {
    $catalog = catalog();
    $kit = new Lineages(new Lifecycle, $catalog);

    // The plan names the case conflict and is not executable; nothing throws, nothing is written.
    [$root, , $kernel] = hostApp($kit, true, true);
    $before = tree($root);
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--json' => true]) !== 0, 'kit:add --json on a starter-kit host exits nonzero');
    $plan = json($kernel);
    $conflict = ['feature' => 'session-ui', 'declared' => PAGE, 'existing' => 'resources/js/pages/auth', 'kind' => 'directory'];
    check($plan['executable'] === false && $plan['case_conflicts'] === [$conflict], 'the --json plan reports the case conflict, naming the declared and the existing path, and is not executable');
    $row = array_column($plan['features'], null, 'feature')['session-ui'];
    check($row['executable'] === false && $row['case_conflicts'] === [array_diff_key($conflict, ['feature' => true])], 'the session-ui row carries the conflict and is not executable');
    $step = 'Case conflict: the kit declares resources/js/pages/Auth but the host has resources/js/pages/auth. Rename resources/js/pages/auth to resources/js/pages/Auth (and the Inertia page names under it), or run php artisan kit:add session-ui --adopt-case to do it.';
    check(in_array($step, $plan['unmet'], true) && in_array($step, $plan['host_steps'], true), 'the conflict is a blocking host step that says how to fix it');
    check($kernel->call('kit:add', ['features' => ['session-ui']]) !== 0 && str_contains($out = $kernel->output(), 'case conflict '.PAGE.' (the host has resources/js/pages/auth)') && str_contains($out, 'host step (unmet): '.$step) && str_contains($out, 'The plan is not executable.'), 'the text plan names the conflict and its host step');
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--apply' => true, '--expected-plan' => $plan['plan']]) !== 0 && str_contains($kernel->output(), 'Unmet host step: '.$step), 'apply refuses the conflicted plan');
    check(tree($root) === $before, 'the conflicted plan and refusal write nothing');

    // --adopt-case without --apply lists the renames and rewrites, nothing written.
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--adopt-case' => true, '--json' => true]) === 0, 'kit:add --adopt-case previews');
    $preview = json($kernel);
    check($preview['case_adoption']['renames'] === ['resources/js/pages/auth' => 'resources/js/pages/Auth'], 'the preview renames pages/auth to pages/Auth');
    check($preview['case_adoption']['rewrites'] === [
        'app/Http/Controllers/Auth/LoginController.php' => [['from' => "inertia( 'auth/", 'to' => "inertia( 'Auth/"]],
        'routes/auth.php' => [['from' => "Inertia::render('auth/", 'to' => "Inertia::render('Auth/"], ['from' => 'Inertia::render("auth/', 'to' => 'Inertia::render("Auth/'], ['from' => "Route::inertia('/forgot', 'auth/", 'to' => "Route::inertia('/forgot', 'Auth/"]],
    ], 'the preview rewrites each render name in routes and controllers, and nothing else');
    check(tree($root) === $before, 'the --adopt-case preview writes nothing');

    // --adopt-case --apply: git mv to the declared case and the render names rewritten, then it stops; nothing is installed.
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--adopt-case' => true, '--apply' => true, '--json' => true]) === 0, 'kit:add --adopt-case --apply renames');
    $result = json($kernel);
    check($result['status'] === 'renamed' && $result['case_adoption']['git'] === true && $result['next'] === ['php artisan kit:add session-ui', 'php artisan kit:add session-ui --apply --expected-plan=<the plan it prints> --intent=<the intent it prints>'], 'the result says it used git mv and names the preview and the apply as next');
    check(Lineages::adopted($root, 'host', $catalog) === [] && ! is_file($root.'/'.PAGE) && ! is_dir($root.'/.mod-maintenance/installed'), '--adopt-case --apply installs nothing');
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--adopt-case' => true, '--apply' => true]) === 0 && str_contains($out = $kernel->output(), 'No case conflicts to adopt.') && str_contains($out, 'Nothing installed. Review the plan, then apply it:'), 'a second --adopt-case --apply has nothing to rename and prints the next steps');
    $pages = array_values(array_diff(scandir($root.'/resources/js/pages'), ['.', '..']));
    check($pages === ['Auth'], 'the host directory is now pages/Auth, in the declared case');
    check(is_file($root.'/resources/js/pages/Auth/Login.vue'), 'the starter\'s own page moved with it');
    $index = run($root, ['git', 'diff', '--cached', '--name-status', '-M']);
    check(str_contains($index, "R100\tresources/js/pages/auth/Login.vue\tresources/js/pages/Auth/Login.vue"), 'git records the case-only rename');
    $routes = file_get_contents($root.'/routes/auth.php');
    check(str_contains($routes, "Inertia::render('Auth/Login')") && str_contains($routes, 'Inertia::render("Auth/Register"') && str_contains($routes, "Route::inertia('/forgot', 'Auth/ForgotPassword')") && str_contains($routes, "// An unrelated string stays: 'auth/Login'"), 'the route file renders Auth/…, and an unrelated string is untouched');
    check(str_contains(file_get_contents($root.'/app/Http/Controllers/Auth/LoginController.php'), "inertia( 'Auth/Login'"), 'the controller renders Auth/Login');
    // The usual preview is now executable, and its reviewed plan applies.
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--json' => true]) === 0, 'the following preview exits 0');
    $fresh = json($kernel);
    check($fresh['executable'] === true && ! isset($fresh['case_conflicts']), 'the following preview is executable, with no case conflict');
    check($kernel->call('kit:add', ['features' => ['session-ui'], '--apply' => true, '--expected-plan' => $fresh['plan'], '--json' => true]) === 0 && json($kernel)['status'] === 'completed', 'the reviewed plan applies');
    check(array_keys(Lineages::adopted($root, 'host', $catalog)) === ['core', 'workspaces', 'session-ui'] && is_file($root.'/'.PAGE) && is_file($root.'/resources/js/pages/Auth/Login.vue'), 'session-ui is installed, with its requirements, beside the starter\'s page');

    // A declared case already beside the host's is refused.
    [$root, , $kernel] = hostApp($kit, true, false);
    @mkdir($root.'/resources/js/pages/Auth');
    $sensitive = count(array_diff(scandir($root.'/resources/js/pages'), ['.', '..'])) === 2;
    if ($sensitive) {
        $before = tree($root);
        check($kernel->call('kit:add', ['features' => ['session-ui'], '--adopt-case' => true, '--apply' => true]) !== 0 && str_contains($kernel->output(), 'already exist under another case') && tree($root) === $before, 'a rename target that already exists beside it is refused, with no write');
    } else {
        // A case-insensitive filesystem cannot hold both; the plain (non-git) two-step rename runs instead.
        check($kernel->call('kit:add', ['features' => ['session-ui'], '--adopt-case' => true, '--apply' => true, '--json' => true]) === 0 && json($kernel)['case_adoption']['git'] === false && array_values(array_diff(scandir($root.'/resources/js/pages'), ['.', '..'])) === ['Auth'], 'outside git, a case-only rename takes two steps on a case-insensitive filesystem');
        echo "SKIP the both-cases refusal needs a case-sensitive filesystem\n";
    }

    // kit:adopt --json: only JSON, in kit:add's plan schema.
    [$root, , $kernel] = hostApp($kit, false, false);
    $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
    $add = json($kernel);
    foreach ($kit->artifact('core', ['frontend' => 'SyntheticLayout'])->files as $path => $bytes) {
        @mkdir(dirname($root.'/'.$path), 0700, true);
        file_put_contents($root.'/'.$path, $bytes);
    }
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan', '--json' => true]) === 0, 'kit:adopt plan --json plans');
    $adopt = json($kernel);
    check(array_diff(array_keys($add), array_keys($adopt)) === [] && array_diff(array_keys($add['features'][0]), array_keys($adopt['features'][0])) === [], 'kit:adopt --json carries every key of kit:add\'s plan and of its feature row');
    check($adopt['features'][0]['action'] === 'adopt' && $adopt['executable'] === true && is_string($adopt['plan']) && str_starts_with($adopt['apply'], 'php artisan kit:adopt core apply'), 'the adoption row, executable flag and apply command are there');
    check(gettype($adopt['host_steps']) === gettype($add['host_steps']) && array_is_list($adopt['host_steps']) && array_is_list($adopt['features']) && is_array($adopt['features'][0]['files']), 'the shared keys have kit:add\'s shapes');
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan', '--json' => true, '--from' => 'v-unknown']) !== 0 && json($kernel)['status'] === 'refused', 'a refused kit:adopt --json is JSON too');

    // An ignored .mod-maintenance/installed/ is attention, never blocking; the recommended lines clear it. In git and outside it.
    foreach ([true, false] as $git) {
        [$root, $paths, $kernel] = hostApp($kit, false, $git);
        $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
        check(! in_array('gitignore', array_column(json($kernel)['steps'], 'kind'), true), ($git ? 'git: ' : 'no git: ').'no .gitignore, no step');
        file_put_contents($root.'/.gitignore', "/vendor\n/.mod-maintenance\n");
        $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
        $plan = json($kernel);
        $steps = array_values(array_filter($plan['steps'], fn (array $step): bool => $step['kind'] === 'gitignore'));
        check(count($steps) === 1 && $steps[0]['status'] === 'unmet' && $steps[0]['blocking'] === false && str_contains($steps[0]['message'], implode(' ', HostRequirements::LOCAL_MAINTENANCE)) && $plan['executable'] && $plan['attention'], ($git ? 'git: ' : 'no git: ').'an ignored installed/ is a non-blocking attention step with the replacement lines');
        check(in_array($steps[0]['message'], $plan['host_steps'], true) && in_array($steps[0]['message'], $plan['warnings'], true), ($git ? 'git: ' : 'no git: ').'the step is a host_steps line and a warning');
        file_put_contents($root.'/.gitignore', "/vendor\n".implode("\n", HostRequirements::LOCAL_MAINTENANCE)."\n");
        $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
        check(! in_array('gitignore', array_column(json($kernel)['steps'], 'kind'), true), ($git ? 'git: ' : 'no git: ').'the recommended lines clear the step');
        file_put_contents($root.'/.gitignore', "/.mod-maintenance/*\n!/.mod-maintenance/installed/\n");
        $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
        check(! in_array('gitignore', array_column(json($kernel)['steps'], 'kind'), true), ($git ? 'git: ' : 'no git: ').'re-including installed/ clears it too');
        file_put_contents($root.'/.gitignore', ".mod-maintenance/installed/\n");
        $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
        check(in_array('gitignore', array_column(json($kernel)['steps'], 'kind'), true), ($git ? 'git: ' : 'no git: ').'ignoring installed/ alone raises it');
    }

    // Records git does not track (not ignored, never committed) are attention too; committing them clears it.
    [$root, $paths, $kernel] = hostApp($kit, false, true);
    $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
    $preview = json($kernel);
    check($kernel->call('kit:add', ['features' => ['core'], '--apply' => true, '--expected-plan' => $preview['plan'], '--json' => true]) === 0 && is_dir($root.'/.mod-maintenance/installed'), 'core is installed in the git fixture, with records');
    file_put_contents($root.'/.gitignore', implode("\n", HostRequirements::LOCAL_MAINTENANCE)."\n");
    $kernel->call('kit:add', ['features' => ['workspaces'], '--json' => true]);
    $steps = array_values(array_filter(json($kernel)['steps'], fn (array $step): bool => $step['kind'] === 'gitignore'));
    check(count($steps) === 1 && $steps[0]['blocking'] === false && str_contains($steps[0]['message'], 'holds records git does not track') && str_contains($steps[0]['message'], 'Commit .mod-maintenance/installed/') && str_contains($steps[0]['message'], implode(' ', HostRequirements::LOCAL_MAINTENANCE)), 'untracked installed records are a non-blocking step that says to commit them');
    run($root, ['git', 'add', '-A']);
    run($root, ['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', '-c', 'commit.gpgsign=false', 'commit', '-q', '-m', 'records']);
    $kernel->call('kit:add', ['features' => ['workspaces'], '--json' => true]);
    check(! in_array('gitignore', array_column(json($kernel)['steps'], 'kind'), true), 'committing the records clears the step');

    // kit:update with kit files but no records names the uncommitted-records cause first, then kit:adopt.
    [$root, , $kernel] = hostApp($kit, false, false);
    foreach ($kit->artifact('core', ['frontend' => 'SyntheticLayout'])->files as $path => $bytes) {
        @mkdir(dirname($root.'/'.$path), 0700, true);
        file_put_contents($root.'/'.$path, $bytes);
    }
    check($kernel->call('kit:update', ['action' => 'plan']) !== 0 && str_contains($out = $kernel->output(), 'records in .mod-maintenance/installed/ may not be committed; commit them from that checkout') && strpos($out, 'may not be committed') < strpos($out, 'php artisan kit:adopt core plan'), 'kit:update names uncommitted records as the first cause, before the kit:adopt route');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
