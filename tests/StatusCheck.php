<?php

// Synthetic console checkout only; no consumer, product, browser or database acceptance.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\CatalogHost;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\LifecycleHost;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
require __DIR__.'/InstalledSeam.php';
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

function snapshot(string $root): array
{
    clearstatcache();
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
        $files[substr($file->getPathname(), strlen($root) + 1)] = $file->isDir() ? 'directory' : hash_file('sha256', $file->getPathname());
    }
    ksort($files);

    return $files;
}

$root = sys_get_temp_dir().'/kit-status-'.bin2hex(random_bytes(8));
try {
    simulateInstalled('tey/concierge', '0.7.6');
    foreach (['bootstrap/cache', 'storage/logs', 'config'] as $directory) {
        mkdir($root.'/'.$directory, 0700, true);
    }
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $before = snapshot($root);
    check($kernel->call('kit:status') === 0 && trim($kernel->output()) === 'No kit features adopted.', 'no record succeeds without a host binding');
    check($kernel->call('kit:status', ['--json' => true]) === 0, 'no record JSON succeeds');
    check(json_decode($kernel->output(), true, flags: JSON_THROW_ON_ERROR) === ['message' => 'No kit features adopted.', 'blueprint_features' => [], 'lineages' => []], 'no record JSON contains the empty status');
    check(snapshot($root) === $before, 'empty status creates no files or directories');

    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = $id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic '.$id);
    }
    file_put_contents($root.'/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/host-operations.txt', '<?php return fn () => true;');
    $catalog = require __DIR__.'/fixtures/v01.php';
    $app->instance(LifecycleHost::class, new class($root, $paths, $catalog) implements LifecycleHost, CatalogHost
    {
        public function __construct(private string $root, private array $paths, private Catalog $catalog) {}

        public function lifecycle(): Lifecycle
        {
            return new Lifecycle;
        }

        public function appId(): string
        {
            return 'status-fixture';
        }

        public function bindings(): array
        {
            return [];
        }

        public function inputPaths(): array
        {
            return $this->paths;
        }

        public function operationCheck(): Closure
        {
            return require $this->root.'/host-operations.txt';
        }

        public function catalog(): Catalog
        {
            return $this->catalog;
        }
    });
    check($kernel->call('kit:update', ['action' => 'plan', '--features' => 'foundation,defaults']) === 0, 'fixture adoption plans through kit:update');
    $plan = json_decode($kernel->output(), true, flags: JSON_THROW_ON_ERROR);
    check($kernel->call('kit:update', ['action' => 'apply', '--features' => 'foundation,defaults', '--intent' => 'status-adoption', '--expected-plan' => $plan['plan'], '--exclusive-checkout' => true]) === 0, 'fixture adoption applies through kit:update');
    $artifact = (new Lifecycle)->artifact(Blueprint::all($catalog), []);
    $expected = ['blueprint_features' => ['foundation', 'defaults'], 'lineages' => [['name' => $artifact->name, 'version' => $artifact->version, 'identity' => $artifact->identity(), 'features' => ['foundation', 'defaults'], 'files' => array_fill_keys(array_keys($artifact->files), 'unchanged')]]];
    $app->forgetInstance(LifecycleHost::class);
    $before = snapshot($root);
    check($kernel->call('kit:status', ['--json' => true]) === 0 && json_decode($kernel->output(), true, flags: JSON_THROW_ON_ERROR) === $expected, 'fresh status reports installed features, identity, version and every unchanged file without a host');
    $code = $kernel->call('kit:status');
    $output = $kernel->output();
    check($code === 0 && str_contains($output, $artifact->identity()) && str_contains($output, $artifact->version) && str_contains($output, 'foundation, defaults'), 'text reports the same installed provenance');
    foreach ($expected['lineages'][0]['files'] as $path => $state) {
        check(str_contains($output, $path.': unchanged'), 'text reports unchanged '.$path);
    }
    check(snapshot($root) === $before, 'fresh status changes no checkout or maintenance files');

    [$edited, $missing] = array_keys($artifact->files);
    file_put_contents($root.'/'.$edited, 'local edit');
    unlink($root.'/'.$missing);
    $expected['lineages'][0]['files'][$edited] = 'edited';
    $expected['lineages'][0]['files'][$missing] = 'missing';
    $before = snapshot($root);
    check($kernel->call('kit:status', ['--json' => true]) === 0 && json_decode($kernel->output(), true, flags: JSON_THROW_ON_ERROR) === $expected, 'JSON distinguishes edited and missing owned files');
    $code = $kernel->call('kit:status');
    $output = $kernel->output();
    check($code === 0 && str_contains($output, $edited.': edited (conflict on next update)') && str_contains($output, $missing.': missing'), 'text identifies edits as next-update conflicts and missing files');
    check(snapshot($root) === $before, 'status preserves edits, absence and all maintenance bytes');

    $recordPath = $root.'/.mod-maintenance/installed/'.hash('sha256', $artifact->name).'.json';
    $record = file_get_contents($recordPath);
    file_put_contents($recordPath, '{broken');
    $before = snapshot($root);
    check($kernel->call('kit:status') !== 0 && snapshot($root) === $before, 'malformed record fails closed without writes');
    file_put_contents($recordPath, str_replace($artifact->identity(), 'sha256:'.str_repeat('0', 64), $record));
    check($kernel->call('kit:status') !== 0, 'tampered artifact identity fails closed');
    file_put_contents($recordPath, $record);
    symlink($root.'/'.$edited, $root.'/'.$missing);
    check($kernel->call('kit:status') !== 0 && is_link($root.'/'.$missing), 'linked owned file refuses without altering the link');
    unlink($root.'/'.$missing);
    unlink($recordPath);
    symlink($root.'/'.$edited, $recordPath);
    check($kernel->call('kit:status') !== 0 && is_link($recordPath), 'linked installed record refuses without altering the link');
    unlink($recordPath);
    mkdir($recordPath);
    $before = snapshot($root);
    check($kernel->call('kit:status') !== 0 && snapshot($root) === $before, 'directory at installed record is not mistaken for no adoption');
    // An adopted feature's host steps are checked again on status: the shell's @kit/shell and @kit/ui aliases (vite and tsconfig) and the kit's shipped types.
    $requirements = new ReflectionMethod(Tey\Kit\Console\StatusCommand::class, 'requirements');
    $command = $app->make(Tey\Kit\Console\StatusCommand::class);
    $command->setLaravel($app);
    // Keyed by file and what it is for ("vite.config.ts @kit/ui"): each step's file and the alias or file its why names first.
    $shellSteps = function () use ($requirements, $command): array {
        $steps = [];
        foreach ($requirements->invoke($command, ['core', 'shell'])['steps'] as $step) {
            if ($step['feature'] === 'shell' && preg_match('/@kit\/(?:shell|ui)|tey\/kit\/types\/kit\.d\.ts/', $step['why'], $match) === 1) {
                $steps[$step['step'].' '.$match[0]] = $step['status'];
            }
        }

        return $steps;
    };
    $wanted = ['vite.config.ts @kit/shell', 'vite.config.ts @kit/ui', 'tsconfig.json @kit/shell', 'tsconfig.json @kit/ui', 'tsconfig.json tey/kit/types/kit.d.ts'];
    $unmet = $shellSteps();
    check(array_map(fn (string $key): ?string => $unmet[$key] ?? null, $wanted) === array_fill(0, 5, 'unmet'), 'status reports the shell\'s @kit/shell and @kit/ui aliases and the kit\'s shipped types unmet on a host without them');
    $messages = implode("\n", $requirements->invoke($command, ['core', 'shell'])['warnings']);
    check(str_contains($messages, "'@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts')") && str_contains($messages, '"@kit/shell": ["./resources/js/pages/Workspace/Partials/index.ts"]')
        && str_contains($messages, "'@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts')") && str_contains($messages, '"@kit/ui": ["./resources/js/components/Kit/index.ts"]')
        && str_contains($messages, '"vendor/tey/kit/types/kit.d.ts"') && ! str_contains($messages, 'tey/kit/src'), 'and each gives the exact snippet (the types from the kit\'s shipped kit.d.ts, not by transforming its src)');
    file_put_contents($root.'/vite.config.ts', "resolve: { alias: { '@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts'), '@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts') } }\n");
    file_put_contents($root.'/tsconfig.json', '{"compilerOptions": {"paths": {"@kit/ui": ["./resources/js/components/Kit/index.ts"], "@kit/shell": ["./resources/js/pages/Workspace/Partials/index.ts"]}}, "include": ["resources/js/**/*", "vendor/tey/kit/types/kit.d.ts"]}');
    check(array_map(fn (string $key): ?string => $shellSteps()[$key] ?? null, $wanted) === array_fill(0, 5, 'met'), 'and met once the host has them');
    // The kit's types listed in the Vite config's plugin-vue globalTypeFiles (for defineProps) meet the types step as well as a tsconfig include.
    file_put_contents($root.'/tsconfig.json', '{"compilerOptions": {"paths": {"@kit/ui": ["./resources/js/components/Kit/index.ts"], "@kit/shell": ["./resources/js/pages/Workspace/Partials/index.ts"]}}}');
    check(($shellSteps()['tsconfig.json tey/kit/types/kit.d.ts'] ?? null) === 'unmet', 'without the include or globalTypeFiles the types step is unmet');
    file_put_contents($root.'/vite.config.ts', "resolve: { alias: { '@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts'), '@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts') } }, plugins: [vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })]\n");
    check(($shellSteps()['tsconfig.json tey/kit/types/kit.d.ts'] ?? null) === 'met', 'the kit\'s types in plugin-vue\'s globalTypeFiles meet it too');
    // The connection ability every MCP connection must hold: unmet (attention) when nothing defines it, with the key and the fix named.
    $abilityStep = function () use ($requirements, $command): array {
        foreach ($requirements->invoke($command, ['core', 'workspaces', 'access', 'mcp'])['steps'] as $step) {
            if ($step['step'] === 'kit.mcp.connection_ability') {
                return [$step['status'], $step['message']];
            }
        }
        throw new RuntimeException('no connection_ability step');
    };
    [$status, $message] = $abilityStep();
    check($status === 'unmet' && str_contains($message, "kit.mcp.connection_ability is 'settings.read'") && str_contains($message, 'permission_denied') && str_contains($message, 'settings feature'), 'without the settings feature the default connection ability settings.read is undefined: attention names the key, the effect and the fix');
    $app['config']->set('kit.mcp.connection_ability', 'nope.read');
    check($abilityStep()[0] === 'unmet', 'an ability nothing defines is unmet');
    Illuminate\Support\Facades\Gate::define('nope.read', fn (): bool => true);
    check($abilityStep()[0] === 'met' && str_contains($abilityStep()[1], 'nope.read is a defined ability'), 'an ability the Gate defines is met');
    $app['config']->set('kit.mcp.connection_ability', 'reports.read');
    // This app adopts no feature at boot, so nothing binds the Registry: hold one instance, as a host's FeaturesServiceProvider does, then declare the ability on it.
    $app->instance(Tey\Kit\Features\Core\Registry::class, $statusRegistry = $app->make(Tey\Kit\Features\Core\Registry::class));
    $statusRegistry->abilities(['reports.read' => 'workspace.reports']);
    check($abilityStep()[0] === 'met', 'an ability a feature declares through the Registry is met');
    check(! in_array('kit.mcp.connection_ability', array_column($requirements->invoke($command, ['core', 'shell'])['steps'], 'step'), true), 'the step appears only with the mcp feature');
    $app['config']->set('kit.mcp.connection_ability', 'settings.read');
    // The production build needs the kit's types in plugin-vue's script.globalTypeFiles (tsconfig serves the editor only): its own step, read statically, "can't verify" when the config can't be read.
    $viteTypes = function () use ($requirements, $command): array {
        foreach ($requirements->invoke($command, ['core', 'shell'])['steps'] as $step) {
            if ($step['kind'] === 'vite_global_types') {
                return [$step['status'], $step['message']];
            }
        }
        throw new RuntimeException('no vite_global_types step');
    };
    [$status, $message] = $viteTypes();
    check($status === 'met' && str_contains($message, 'vite.config.ts lists vendor/tey/kit/types/kit.d.ts'), 'the globalTypeFiles step is met by the kit\'s types in plugin-vue\'s list');
    file_put_contents($root.'/vite.config.ts', "plugins: [vue({ script: { globalTypeFiles: [path.resolve(__dirname, 'vendor/tey/kit/types/kit.d.ts')] } })]\n");
    check($viteTypes()[0] === 'met', 'a resolved path to the same file meets it');
    file_put_contents($root.'/vite.config.ts', "plugins: [vue()]\n");
    [$status, $message] = $viteTypes();
    check($status === 'unmet' && str_contains($message, "vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })") && str_contains($message, 'production build'), 'a Vite config without globalTypeFiles is unmet, with the exact snippet to add');
    file_put_contents($root.'/vite.config.ts', "plugins: [vue({ script: { globalTypeFiles: ['resources/js/types/other.d.ts'] } })]\n");
    [$status, $message] = $viteTypes();
    check($status === 'unmet' && str_contains($message, 'without the kit\'s types') && str_contains($message, "'vendor/tey/kit/types/kit.d.ts'"), 'a globalTypeFiles list without the kit\'s types is unmet and names the entry to add');
    file_put_contents($root.'/vite.config.ts', "const types = kitTypes();\nexport default { plugins: [vue({ script: { globalTypeFiles: types } })] };\n");
    [$status, $message] = $viteTypes();
    check($status === 'unmet' && str_contains($message, "can't verify") && str_contains($message, "kit.host.vite_global_types = 'manual'"), 'a globalTypeFiles the kit cannot read statically is "can\'t verify", never met, and names the manual escape');
    $app['config']->set('kit.host.vite_global_types', 'manual');
    [$status, $message] = $viteTypes();
    check($status === 'met' && str_contains($message, 'verified manually (kit.host.vite_global_types)'), 'kit.host.vite_global_types = manual records a person\'s verification: met, shown as such');
    $app['config']->set('kit.host.vite_global_types', 'yes');
    check($viteTypes()[0] === 'unmet', 'any other value is not the escape');
    $app['config']->set('kit.host.vite_global_types', null);
    file_put_contents($root.'/vite.config.ts', '');
    [$status, $message] = $viteTypes();
    check($status === 'unmet' && str_contains($message, "can't verify"), 'an empty Vite config is "can\'t verify"');
    unlink($root.'/vite.config.ts');
    [$status, $message] = $viteTypes();
    check($status === 'unmet' && str_contains($message, "can't verify") && str_contains($message, 'No vite.config.ts'), 'no Vite config at all is "can\'t verify"');
    file_put_contents($root.'/vite.config.js', "export default { plugins: [vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })] };\n");
    check($viteTypes()[0] === 'met' && str_contains($viteTypes()[1], 'vite.config.js'), 'a vite.config.js is read the same way');
    unlink($root.'/vite.config.js');
    file_put_contents($root.'/vite.config.ts', "resolve: { alias: { '@kit/ui': path.resolve(__dirname, 'resources/js/components/Kit/index.ts'), '@kit/shell': path.resolve(__dirname, 'resources/js/pages/Workspace/Partials/index.ts') } }, plugins: [vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })]\n");
    check(in_array('vite_global_types', array_column($requirements->invoke($command, ['core', 'shell'])['steps'], 'kind'), true) && ! in_array('vite_global_types', array_column($requirements->invoke($command, ['core']) ['steps'], 'kind'), true), 'the step belongs to the shell feature, which ships the typed pages');
    check(is_file(dirname(__DIR__).'/types/kit.d.ts') && str_contains((string) file_get_contents(dirname(__DIR__).'/types/kit.d.ts'), 'export type ShellData'), 'the file the step names ships with the kit and declares the shell\'s types');
    check($requirements->invoke($command, ['foundation', 'defaults']) === null, 'features the catalog does not know add no requirements');
    // Core joins StartKitRequest to `web` only when the group exists as it boots; status names a request start that is off (attention, never blocking).
    $requestStart = new ReflectionMethod(Tey\Kit\Console\StatusCommand::class, 'requestStart');
    $httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $startStep = function (array $groups) use ($requestStart, $command, $httpKernel): array {
        $httpKernel->setMiddlewareGroups($groups);
        $result = $requestStart->invoke($command, ['core', 'shell'], null);
        $steps = array_values(array_filter($result['steps'], fn (array $step): bool => $step['step'] === 'web StartKitRequest'));

        return [$steps[0]['status'] ?? null, $steps[0]['message'] ?? '', $result['attention'], $result['warnings']];
    };
    [$state, , $attention, $warnings] = $startStep(['web' => [Tey\Kit\Features\Core\Http\Middleware\StartKitRequest::class, 'host-middleware']]);
    check($state === 'met' && $attention === false && $warnings === [], 'web with StartKitRequest: the request start step is met, no attention');
    [$state, , $attention] = $startStep(['web' => ['Tey\\Kit\\Features\\SessionUi\\Http\\Middleware\\StartKitRequest']]);
    check($state === 'met' && $attention === false, 'the deprecated session-ui name counts as present');
    [$state, $message, $attention, $warnings] = $startStep(['web' => ['host-middleware']]);
    check($state === 'unmet' && $attention === true && $warnings === [$message] && str_starts_with($message, 'StartKitRequest is not in the web middleware group') && str_contains($message, 'request memos are off') && str_contains($message, 'lost after a flush') && str_contains($message, 'first in web'), 'web without StartKitRequest: attention says the request start is off, with the fix');
    [$state, $message, $attention] = $startStep(['api' => ['host-middleware']]);
    check($state === 'unmet' && $attention === true && str_starts_with($message, 'The app defines no web middleware group') && str_contains($message, 'before the kit\'s providers boot'), 'no web group: attention too');
    check($requestStart->invoke($command, ['foundation'], null) === null, 'without core, no request start step');
    echo "Status checks only; no consumer, product, browser or database acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
} finally {
    (new Filesystem)->deleteDirectory($root);
}
