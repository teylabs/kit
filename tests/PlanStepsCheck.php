<?php

// Host steps, npm requirements, optional integrations and migrations in kit:add and kit:update plans; synthetic fixtures, no network, no npm.
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Tey\Kit\Blueprint;
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

final class PlanStepsProvider extends ServiceProvider {}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function refuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Expected refusal: '.$message);
}
function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
/** Pins for an edited catalog copy, computed independently of Blueprint. */
function repin(string $root): Catalog
{
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
/** A catalog copy whose core declares the given fields, with one migration stub. */
function catalogWith(array $fields): Catalog
{
    $root = sys_get_temp_dir().'/kit-plan-steps-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    $manifest = json_decode(file_get_contents($root.'/core/feature.json'), true);
    $manifest['owns'][] = 'database/migrations/kit/core';
    $manifest['files']['database/migrations/kit/core/0001_01_02_000100_create_core_table.php'] = ['stub' => 'migration.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
    file_put_contents($root.'/core/migration.php.stub', "<?php\n\nreturn new class {};\n");
    foreach ($fields as $field => $value) {
        $field === 'npm' ? $manifest['requires']['npm'] = $value : $manifest[$field] = $value;
    }
    file_put_contents($root.'/core/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    return repin($root);
}
/** A disposable host app, booted, with the host verification inputs a trusted binding names. */
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-plan-steps-'.bin2hex(random_bytes(8));
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
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return [$root, $paths, $app, $kernel];
}
function packageJson(string $root, array $dependencies): void
{
    file_put_contents($root.'/package.json', json_encode(['private' => true, 'dependencies' => $dependencies]));
}

try {
    $lifecycle = new Lifecycle;
    $bindings = ['frontend' => 'SyntheticLayout'];
    $steps = [
        'env' => [['key' => 'PLAN_STEPS_KEY', 'why' => 'the feature reads it']],
        'provider' => [['class' => PlanStepsProvider::class, 'why' => 'it registers the feature']],
        'schedule' => [['command' => 'model:prune --model=PlanStepsModel', 'why' => 'expired rows are pruned']],
    ];
    $optional = [['id' => 'typed-props', 'title' => 'Typed props', 'do' => 'Register the extension.']];
    $kit = new Lineages($lifecycle, catalogWith(['host_steps' => $steps, 'optional' => $optional, 'npm' => ['reka-ui' => '>=2.11.0']]));

    // Every step unmet and the npm requirement missing: reported prominently, never blocking.
    [$root, $paths, $app] = hostApp();
    $preview = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check(array_diff(['plan', 'executable', 'features', 'claim', 'claimable', 'missing_packages', 'host_steps', 'unmet'], array_keys($preview)) === [], 'the preview keeps every existing key');
    check(is_array($preview['host_steps']) && array_is_list($preview['host_steps']) && array_filter($preview['host_steps'], 'is_string') === $preview['host_steps'] && $preview['unmet'] === [], 'host_steps stays a list of strings and unmet stays the blocking list');
    check($preview['executable'] && $preview['attention'], 'unmet non-blocking steps and npm leave the plan executable, with attention');
    check(array_column($preview['steps'], 'status', 'kind') === ['env' => 'unmet', 'provider' => 'unmet', 'schedule' => 'unmet'] && ! in_array(true, array_column($preview['steps'], 'blocking'), true), 'env, provider and schedule are each unmet and not blocking');
    check(in_array('Add PLAN_STEPS_KEY to .env.example and set it in each environment: the feature reads it', $preview['host_steps'], true), 'an unmet step is also a host_steps line');
    check($preview['npm']['reka-ui'] === ['constraint' => '>=2.11.0', 'declared' => null, 'status' => 'missing', 'installed' => null, 'installed_status' => null, 'feature' => 'core'], 'an undeclared npm requirement is missing');
    check(count($preview['warnings']) === 4 && str_contains($preview['warnings'][3], 'npm install reka-ui@">=2.11.0"'), 'each unmet step and the missing npm package is a warning');
    check($preview['optional'] === [['feature' => 'core', 'id' => 'typed-props', 'title' => 'Typed props', 'do' => 'Register the extension.', 'status' => 'manual']], 'an optional integration is listed as manual');
    check($preview['features'][0]['migrations'] === ['new' => ['database/migrations/kit/core/0001_01_02_000100_create_core_table.php'], 'changed' => [], 'existing' => []], 'the feature lists its new migration');
    check(in_array('php artisan migrate', $preview['host_steps'], true), 'a new migration still asks for migrate');
    check(! file_exists($root.'/package.json') && ! file_exists($root.'/.env.example') && ! is_dir($root.'/node_modules'), 'the preview writes no package.json, .env.example or node_modules');

    // Meeting each step changes its status and the plan identity.
    $plans = [$preview['plan']];
    file_put_contents($root.'/.env.example', "APP_NAME=Host\nPLAN_STEPS_KEY=\n");
    $app->register(PlanStepsProvider::class);
    $app->make(Schedule::class)->command('model:prune --model=PlanStepsModel')->daily();
    packageJson($root, ['reka-ui' => '^2.11.1']);
    $met = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check(array_unique(array_column($met['steps'], 'status')) === ['met'] && $met['steps'][0]['message'] === 'documented in .env.example; set it in each environment', 'documented, registered and scheduled steps are met');
    check($met['npm']['reka-ui']['status'] === 'passed' && ! $met['attention'] && $met['warnings'] === [], 'a declared range within the requirement passes, and nothing needs attention');
    check(! in_array($met['plan'], $plans, true), 'a changed requirement outcome changes the plan identity');
    $plans[] = $met['plan'];
    refuses(fn () => $kit->add($root, 'host', ['core'], $bindings, $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt'), 'expected plan', 'apply refuses the plan reviewed before the host met its steps');

    // npm: the declared range is bound; the installed version is reported, not bound.
    foreach (['^2.10.0' => 'outdated', '>=2.11.0 <3' => 'passed', 'workspace:*' => 'unverifiable', 'latest' => 'unverifiable', 'github:unovue/reka-ui' => 'unverifiable'] as $range => $status) {
        packageJson($root, ['reka-ui' => $range]);
        $outcome = $kit->preview($root, 'host', ['core'], $bindings, $paths);
        check($outcome['npm']['reka-ui']['status'] === $status && $outcome['attention'] === ($status !== 'passed') && $outcome['executable'], 'declared '.$range.' is '.$status);
        $plans[] = $outcome['plan'];
    }
    packageJson($root, ['reka-ui' => '^2.11.1']);
    mkdir($root.'/node_modules/reka-ui', 0700, true);
    file_put_contents($root.'/node_modules/reka-ui/package.json', json_encode(['name' => 'reka-ui', 'version' => '2.10.4']));
    $stale = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check($stale['npm']['reka-ui']['installed'] === '2.10.4' && $stale['npm']['reka-ui']['installed_status'] === 'outdated' && $stale['attention'] && str_contains($stale['warnings'][0], 'Run npm install'), 'an older installed version is reported with a warning');
    file_put_contents($root.'/node_modules/reka-ui/package.json', json_encode(['name' => 'reka-ui', 'version' => '2.12.0']));
    $fresh = $kit->preview($root, 'host', ['core'], $bindings, $paths);
    check($fresh['npm']['reka-ui']['installed_status'] === 'passed' && $stale['plan'] === $met['plan'] && $fresh['plan'] === $met['plan'], 'the installed version is not bound: an npm ci between preview and apply keeps the plan');
    check(count(array_unique($plans)) === count($plans), 'each distinct outcome has its own plan identity');
    $result = $kit->add($root, 'host', ['core'], $bindings, $paths, $fresh['plan'], null, require $root.'/verification/host-operations.txt');
    check($result['status'] === 'completed', 'the reviewed plan applies');

    // kit:add text: the ATTENTION block comes first; JSON carries the same keys.
    [$root, $paths, $app, $kernel] = hostApp();
    $app->instance(LifecycleHost::class, new class($lifecycle, $paths, $kit, require $root.'/verification/host-operations.txt') implements CatalogHost, LifecycleHost
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
            return 'cli';
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
    check($kernel->call('kit:add', ['features' => ['core']]) === 0, 'kit:add previews an attention plan with exit 0');
    $text = $kernel->output();
    $lines = explode("\n", trim($text));
    check(str_starts_with($lines[0], 'ATTENTION:') && str_contains($lines[1], '! Add PLAN_STEPS_KEY'), 'the text plan opens with the ATTENTION block');
    foreach (['  migrations new: database/migrations/kit/core/', 'step [unmet] core env: PLAN_STEPS_KEY', 'npm [missing] reka-ui >=2.11.0 (package.json: none, installed: none)', 'optional core typed-props: Typed props. Register the extension.'] as $line) {
        check(str_contains($text, $line), 'the text plan prints: '.trim($line));
    }
    $kernel->call('kit:add', ['features' => ['core'], '--json' => true]);
    $json = json_decode($kernel->output(), true);
    check($json['attention'] === true && isset($json['steps'], $json['npm'], $json['optional'], $json['warnings'], $json['features'][0]['migrations']), 'kit:add --json carries steps, npm, optional, attention, warnings and migrations');

    // kit:update plan --lineage: the step's feature's requirements and migrations, beside the existing keys.
    $preview = $kit->preview($root, 'cli', ['core'], $bindings, $paths);
    $kit->add($root, 'cli', ['core'], $bindings, $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt');
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'core']);
    $update = json_decode($kernel->output(), true);
    check(isset($update['executable'], $update['files']) && $update['migrations'] === ['new' => [], 'changed' => [], 'existing' => ['database/migrations/kit/core/0001_01_02_000100_create_core_table.php']] && $update['attention'] && count($update['steps']) === 3, 'kit:update plan --lineage reports migrations and the feature\'s requirements');
    check(! isset($update['changed_migrations']), 'changed_migrations stays absent when nothing changed');
    check(HostRequirements::migrations(['database/migrations/a.php' => ['state' => 'create'], 'database/migrations/b.php' => ['state' => 'replace'], 'database/migrations/c.php' => ['state' => 'unchanged'], 'database/migrations/d.php' => ['state' => 'remove'], 'app/E.php' => ['state' => 'create']]) === ['new' => ['database/migrations/a.php'], 'changed' => ['database/migrations/b.php'], 'existing' => ['database/migrations/c.php']], 'migrations split into new, changed and existing; removals and other files are left out');

    // Malformed declarations are refused when the catalog is read.
    foreach ([
        ['host_steps' => ['cron' => [['command' => 'x', 'why' => 'y']]]],
        ['host_steps' => ['env' => [['key' => 'lower_case', 'why' => 'y']]]],
        ['host_steps' => ['env' => [['key' => 'A', 'why' => 'y', 'value' => 'secret']]]],
        ['host_steps' => ['schedule' => []]],
        ['optional' => [['id' => 'x', 'title' => 'y']]],
        ['npm' => ['reka-ui' => 'not a range']],
    ] as $fields) {
        refuses(fn () => Blueprint::lineage('core', catalogWith($fields)), 'Invalid', 'the catalog refuses '.json_encode($fields));
    }

    // The package catalog's real declarations.
    $mcp = Blueprint::lineage('mcp')['feature'];
    check(array_column($mcp['host_steps']['env'], 'key') === ['PASSPORT_PRIVATE_KEY', 'PASSPORT_PUBLIC_KEY'] && $mcp['host_steps']['schedule'][0]['command'] === 'model:prune --model=Tey\\Kit\\Features\\Mcp\\Models\\McpIntent', 'mcp declares its Passport keys and the McpIntent prune');
    $core = Blueprint::lineage('core')['feature'];
    check(array_column($core['optional'], 'id') === ['explanation-types'] && $core['requires']['npm']['reka-ui'] === '>=2.11.0', 'core declares the optional Explanation types and its npm requirements');
    $package = HostRequirements::of($root, ['core' => $core, 'mcp' => $mcp]);
    check(count($package['npm']) === count($core['requires']['npm']) && array_column($package['steps'], 'status') === ['unmet', 'unmet', 'unmet'], 'the package declarations check against a bare host');

    echo "Plan requirement checks on synthetic host apps only; no npm, no .env reads, no migrations run.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error."\n");
    exit(1);
}
