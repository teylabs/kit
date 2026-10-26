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
/** A catalog copy whose core declares the given fields, with one migration stub (its bytes in `migration`, if given). */
function catalogWith(array $fields): Catalog
{
    $root = sys_get_temp_dir().'/kit-plan-steps-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    $manifest = json_decode(file_get_contents($root.'/core/feature.json'), true);
    $manifest['owns'][] = 'database/migrations/kit/core';
    $manifest['files']['database/migrations/kit/core/0001_01_02_000100_create_core_table.php'] = ['stub' => 'migration.php.stub', 'scaffold' => 'kit', 'ownership' => 'generated'];
    file_put_contents($root.'/core/migration.php.stub', $fields['migration'] ?? "<?php\n\nreturn new class {};\n");
    unset($fields['migration']);
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
    $app->instance(LifecycleHost::class, $host = new class($lifecycle, $paths, $kit, require $root.'/verification/host-operations.txt') implements CatalogHost, LifecycleHost
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
    check(preg_match('/^Nothing written\. Apply with: php artisan kit:add core --apply --expected-plan=sha256:[a-f0-9]{64} --intent=kit-add-[a-f0-9]{16}$/m', $text) === 1, 'the text plan prints the exact apply command, with its expected plan and intent');
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
    check(preg_match('/\Aphp artisan kit:update apply --lineage=core --expected-plan=sha256:[a-f0-9]{64} --exclusive-checkout --intent=kit-update-core-[a-f0-9]{16}\z/', $update['apply']) === 1 && ! isset($update['depends_on']), 'kit:update plan --lineage prints the exact apply command, with --expected-plan, --exclusive-checkout and --intent');
    check(str_contains(array_column($update['steps'], 'message', 'kind')['schedule'], '(routes/console.php or bootstrap/app.php (withSchedule))'), 'the schedule step names both places a schedule can live');

    // A lineage whose requirement has an update still pending says so, and names the order.
    $preview = $kit->preview($root, 'cli', ['workspaces'], $bindings, $paths);
    $kit->add($root, 'cli', ['workspaces'], $bindings, $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt');
    $moved = new Lineages($lifecycle, catalogWith(['host_steps' => $steps, 'optional' => $optional, 'npm' => ['reka-ui' => '>=2.11.0'], 'migration' => "<?php\n\n// changed\nreturn new class {};\n"]));
    check($kit->pendingRequirements($root, 'cli', 'workspaces', $bindings) === [] && $moved->pendingRequirements($root, 'cli', 'workspaces', $bindings) === ['core'], 'a requirement is pending once the catalog moves it past its installed lineage');
    $app->instance(LifecycleHost::class, new (get_class($host))($lifecycle, $paths, $moved, require $root.'/verification/host-operations.txt'));
    $kernel->call('kit:update', ['action' => 'plan', '--lineage' => 'workspaces']);
    $update = json_decode($kernel->output(), true);
    check($update['depends_on'] === ['core'] && $update['order'] === 'Apply core first: php artisan kit:update plan --lineage=core, then php artisan kit:update plan --lineage=workspaces.', 'a plan over a pending requirement names it and the order');

    // A new migration creating a table the host database already has blocks the plan, naming the table.
    $tableKit = new Lineages($lifecycle, catalogWith(['migration' => "<?php\n\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class {\n    public function up(): void\n    {\n        Schema::create('plan_steps_widgets', fn (\$t) => \$t->id());\n    }\n};\n"]));
    [$root, $paths] = hostApp();
    $clear = $tableKit->preview($root, 'host', ['core'], $bindings, $paths);
    check($clear['executable'] && $clear['unmet'] === [], 'a new migration whose table the host lacks does not block');
    Illuminate\Support\Facades\Schema::create('plan_steps_widgets', fn ($table) => $table->id());
    $blocked = $tableKit->preview($root, 'host', ['core'], $bindings, $paths);
    check(! $blocked['executable'] && count($blocked['unmet']) === 1 && str_contains($blocked['unmet'][0], 'already has a plan_steps_widgets table') && in_array($blocked['unmet'][0], $blocked['host_steps'], true), 'a new migration creating a table the host already has is a blocking unmet step naming the table');
    refuses(fn () => $tableKit->add($root, 'host', ['core'], $bindings, $paths, $blocked['plan'], null, require $root.'/verification/host-operations.txt'), 'plan_steps_widgets', 'apply refuses it');
    // The same migration the host already ran (a re-added or restored file) is skipped by migrate, so its table is no collision.
    Illuminate\Support\Facades\Schema::hasTable('migrations') || Illuminate\Support\Facades\Schema::create('migrations', function ($table) { $table->id(); $table->string('migration'); $table->integer('batch'); });
    Illuminate\Support\Facades\DB::table('migrations')->insert(['migration' => 'y', 'batch' => 1]);
    check(HostRequirements::collisions(['database/migrations/y.php' => ['state' => 'create']], ['database/migrations/y.php' => "Schema::create('plan_steps_widgets'"]) === [], 'a new migration the host already ran is not a collision');
    check(HostRequirements::collisions(['database/migrations/z.php' => ['state' => 'create']], ['database/migrations/z.php' => "Schema::create('plan_steps_widgets'"]) !== [], 'a new migration the host never ran still collides');
    check(HostRequirements::collisions(['database/migrations/x.php' => ['state' => 'unchanged']], ['database/migrations/x.php' => "Schema::create('plan_steps_widgets'"]) === [], 'a migration already in the app is not checked');
    // kit:adopt plans (the path a pre-lifecycle host takes) guard collisions the same way, and print the apply command.
    [$root, $paths, $app, $kernel] = hostApp();
    $app->instance(LifecycleHost::class, new (get_class($host))($lifecycle, $paths, $tableKit, require $root.'/verification/host-operations.txt'));
    // A pre-lifecycle host: core's files on disk by hand, but not its migration, which the adoption then creates.
    foreach ($tableKit->artifact('core', $bindings)->files as $path => $bytes) {
        if (! str_starts_with($path, 'database/migrations/')) {
            @mkdir(dirname($root.'/'.$path), 0700, true);
            file_put_contents($root.'/'.$path, $bytes);
        }
    }
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) === 0, 'kit:adopt plans a new migration whose table the host lacks as executable');
    $out = $kernel->output();
    $adopt = json_decode(trim(substr($out, strrpos(trim($out), "\n") ?: 0)), true);
    $command = 'php artisan kit:adopt core apply --intent=kit-adopt-'.substr($adopt['plan'], 7, 16).' --exclusive-checkout --expected-plan='.$adopt['plan'];
    check($adopt['executable'] && $adopt['apply'] === $command && str_contains($out, 'Nothing written. Apply with: '.$command."\n"), 'the kit:adopt plan prints the full apply command and carries it as apply');
    Illuminate\Support\Facades\Schema::create('plan_steps_widgets', fn ($table) => $table->id());
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) !== 0, 'kit:adopt refuses a plan whose new migration creates a table the host already has');
    $out = $kernel->output();
    $adopt = json_decode(trim(substr($out, strrpos(trim($out), "\n") ?: 0)), true);
    check(! $adopt['executable'] && count($adopt['unmet']) === 1 && str_contains($adopt['unmet'][0], 'already has a plan_steps_widgets table') && str_contains($out, 'Nothing written. The plan is not executable.'), 'the kit:adopt plan names the colliding table as a blocking unmet step');
    Illuminate\Support\Facades\Schema::create('migrations', function ($table) { $table->id(); $table->string('migration'); $table->integer('batch'); });
    Illuminate\Support\Facades\DB::table('migrations')->insert(['migration' => basename(array_key_first(array_filter($adopt['files'] ?? [], fn ($k) => str_starts_with($k, 'database/migrations/'), ARRAY_FILTER_USE_KEY)) ?? '', '.php'), 'batch' => 1]);
    check($kernel->call('kit:adopt', ['feature' => 'core', 'action' => 'plan']) === 0, 'kit:adopt skips a new migration the host already ran, as kit:add does');

    // kit:add's ATTENTION banner says whether the plan can still apply.
    $attentionKit = new Lineages($lifecycle, catalogWith(['host_steps' => $steps, 'migration' => "<?php\n\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class {\n    public function up(): void\n    {\n        Schema::create('plan_steps_widgets', fn (\$t) => \$t->id());\n    }\n};\n"]));
    [$root, $paths, $app, $kernel] = hostApp();
    $app->instance(LifecycleHost::class, new (get_class($host))($lifecycle, $paths, $attentionKit, require $root.'/verification/host-operations.txt'));
    check($kernel->call('kit:add', ['features' => ['core']]) === 0 && str_starts_with($kernel->output(), 'ATTENTION: the host must act before this plan works. Nothing here blocks the apply.'), 'the kit:add banner above an executable plan says nothing blocks the apply');
    Illuminate\Support\Facades\Schema::create('plan_steps_widgets', fn ($table) => $table->id());
    check($kernel->call('kit:add', ['features' => ['core']]) !== 0 && str_starts_with($out = $kernel->output(), 'ATTENTION: the host must act before this plan works. The plan is not executable') && ! str_contains($out, 'Nothing here blocks the apply'), 'the kit:add banner above a non-executable plan says it is not executable');

    check(HostRequirements::migrations(['database/migrations/a.php' => ['state' => 'create'], 'database/migrations/b.php' => ['state' => 'replace'], 'database/migrations/c.php' => ['state' => 'unchanged'], 'database/migrations/d.php' => ['state' => 'remove'], 'app/E.php' => ['state' => 'create']]) === ['new' => ['database/migrations/a.php'], 'changed' => ['database/migrations/b.php'], 'existing' => ['database/migrations/c.php']], 'migrations split into new, changed and existing; removals and other files are left out');

    // An env key the host may keep as a file instead: met when documented or when the file exists, which is never read.
    $fileKit = new Lineages($lifecycle, catalogWith(['host_steps' => ['env' => [['key' => 'FILE_KEY', 'or_file' => 'storage/file.key', 'why' => 'it signs tokens']]]]));
    $envStep = fn (string $root): array => $fileKit->preview($root, 'host', ['core'], $bindings, $paths)['steps'][0];
    [$root, $paths] = hostApp();
    $step = $envStep($root);
    check($step['status'] === 'unmet' && $step['or_file'] === 'storage/file.key' && $step['message'] === 'Add FILE_KEY to .env.example and set it in each environment, or keep it as storage/file.key: it signs tokens', 'with neither, the env step is unmet and names both ways');
    file_put_contents($root.'/.env.example', "FILE_KEY=\n");
    $step = $envStep($root);
    check($step['status'] === 'met' && $step['message'] === 'documented in .env.example; set it in each environment', 'documented in .env.example, the env step is met without the file');
    unlink($root.'/.env.example');
    is_dir($root.'/storage') || mkdir($root.'/storage', 0700, true);
    symlink($root.'/composer.json', $root.'/storage/file.key');
    check($envStep($root)['status'] === 'unmet', 'a linked file does not meet the env step');
    unlink($root.'/storage/file.key');
    file_put_contents($root.'/storage/file.key', 'secret');
    chmod($root.'/storage/file.key', 0000);
    $step = $envStep($root);
    check($step['status'] === 'met' && $step['message'] === 'kept as storage/file.key', 'the file alone meets the env step, by existence: it is unreadable here');
    chmod($root.'/storage/file.key', 0600);
    foreach (['../outside.key', '/etc/key', 'storage/./file.key', ''] as $bad) {
        refuses(fn () => Blueprint::lineage('core', catalogWith(['host_steps' => ['env' => [['key' => 'FILE_KEY', 'or_file' => $bad, 'why' => 'w']]]])), 'Invalid host step', 'or_file refuses '.json_encode($bad));
    }
    refuses(fn () => Blueprint::lineage('core', catalogWith(['host_steps' => ['schedule' => [['command' => 'x', 'or_file' => 'a', 'why' => 'w']]]])), 'Invalid host step', 'only env steps take or_file');

    // A config value the host must not have, checked here at plan time: attention, never blocking, since each deploy environment may differ.
    $configKit = new Lineages($lifecycle, catalogWith(['host_steps' => ['config' => [['key' => 'session.driver', 'not' => 'cookie', 'why' => 'consent needs a server-side session']]]]));
    $configStep = fn (): array => $configKit->preview($root, 'host', ['core'], $bindings, $paths);
    $before = config('session.driver');
    try {
        config(['session.driver' => 'cookie']);
        $preview = $configStep();
        check($preview['steps'][0]['status'] === 'unmet' && ! $preview['steps'][0]['blocking'] && $preview['executable'] && $preview['steps'][0]['message'] === 'Change session.driver from cookie in every environment: consent needs a server-side session', 'a cookie session driver is flagged as attention, not blocking');
        config(['session.driver' => 'database']);
        $step = $configStep()['steps'][0];
        check($step['status'] === 'met' && $step['message'] === 'not cookie here; check each environment', 'another session driver meets the config step, for this environment only');
    } finally {
        config(['session.driver' => $before]);
    }
    foreach ([['key' => 'session.driver', 'why' => 'w'], ['key' => 'session.driver', 'not' => ['cookie'], 'why' => 'w'], ['key' => 'session.driver', 'not' => 'cookie', 'is' => 'database', 'why' => 'w']] as $bad) {
        refuses(fn () => Blueprint::lineage('core', catalogWith(['host_steps' => ['config' => [$bad]]])), 'Invalid host step', 'the config step refuses '.json_encode($bad));
    }

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
    check(array_column($mcp['host_steps']['env'], 'or_file', 'key') === ['PASSPORT_PRIVATE_KEY' => 'storage/oauth-private.key', 'PASSPORT_PUBLIC_KEY' => 'storage/oauth-public.key'] && $mcp['host_steps']['schedule'][0]['command'] === 'model:prune --model=Tey\\Kit\\Features\\Mcp\\Models\\McpIntent' && $mcp['host_steps']['config'] === [['key' => 'session.driver', 'not' => 'cookie', 'why' => $mcp['host_steps']['config'][0]['why']]], 'mcp declares its Passport keys, each met by its passport:keys file too, the McpIntent prune and a non-cookie session driver');
    $core = Blueprint::lineage('core')['feature'];
    check(array_column($core['optional'], 'id') === ['explanation-types'] && $core['requires']['npm']['reka-ui'] === '>=2.11.0', 'core declares the optional Explanation types and its npm requirements');
    config(['session.driver' => 'cookie']);
    try {
        $package = HostRequirements::of($root, ['core' => $core, 'mcp' => $mcp]);
    } finally {
        config(['session.driver' => $before]);
    }
    check(count($package['npm']) === count($core['requires']['npm']) && array_column($package['steps'], 'status') === ['unmet', 'unmet', 'unmet', 'unmet'], 'the package declarations check against a bare host on cookie sessions');
    is_dir($root.'/storage') || mkdir($root.'/storage', 0700, true);
    file_put_contents($root.'/storage/oauth-private.key', 'synthetic');
    file_put_contents($root.'/storage/oauth-public.key', 'synthetic');
    check(array_column(HostRequirements::of($root, ['mcp' => $mcp])['steps'], 'status', 'step') === ['PASSPORT_PRIVATE_KEY' => 'met', 'PASSPORT_PUBLIC_KEY' => 'met', 'session.driver' => 'met', 'model:prune --model=Tey\\Kit\\Features\\Mcp\\Models\\McpIntent' => 'unmet'], 'an app keeping Passport keys as files gets no attention for them');

    echo "Plan requirement checks on synthetic host apps only; no npm, no .env reads, no migrations run.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error."\n");
    exit(1);
}
