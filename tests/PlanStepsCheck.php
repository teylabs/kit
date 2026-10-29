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
        in_array($field, ['npm', 'host_ui'], true) ? $manifest['requires'][$field] = $value : $manifest[$field] = $value;
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
    // The schedule's other forms: Laravel quotes an option's value when it compiles the event, so the array and ::class forms once looked unscheduled.
    $schedule = $app->make(Schedule::class);
    foreach ([
        'the array form' => fn (Schedule $fresh) => $fresh->command('model:prune', ['--model' => [PlanStepsModel::class]])->daily(),
        'a string option value' => fn (Schedule $fresh) => $fresh->command('model:prune', ['--model' => PlanStepsModel::class])->daily(),
        'several models' => fn (Schedule $fresh) => $fresh->command('model:prune', ['--model' => ['OtherModel', PlanStepsModel::class]])->daily(),
    ] as $form => $define) {
        $app->instance(Schedule::class, $fresh = new Schedule);
        $define($fresh);
        $step = array_column($kit->preview($root, 'host', ['core'], $bindings, $paths)['steps'], 'status', 'kind')['schedule'];
        check($step === 'met', 'a schedule step is met by '.$form.' (Schedule::command(\'model:prune\', [\'--model\' => ...]))');
    }
    $app->instance(Schedule::class, $fresh = new Schedule);
    $fresh->command('model:prune', ['--model' => ['OtherModel']])->daily();
    check(array_column($kit->preview($root, 'host', ['core'], $bindings, $paths)['steps'], 'status', 'kind')['schedule'] === 'unmet', 'and is not met by pruning another model');
    $app->instance(Schedule::class, $schedule);
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
    check(count($package['npm']) === count($core['requires']['npm']) && array_column($package['steps'], 'status') === ['unmet', 'unmet', 'unmet', 'unmet', 'unmet'] && $package['steps'][0]['kind'] === 'theme', 'the package declarations check against a bare host on cookie sessions (core\'s theme step first)');
    is_dir($root.'/storage') || mkdir($root.'/storage', 0700, true);
    file_put_contents($root.'/storage/oauth-private.key', 'synthetic');
    file_put_contents($root.'/storage/oauth-public.key', 'synthetic');
    check(array_column(HostRequirements::of($root, ['mcp' => $mcp])['steps'], 'status', 'step') === ['PASSPORT_PRIVATE_KEY' => 'met', 'PASSPORT_PUBLIC_KEY' => 'met', 'session.driver' => 'met', 'model:prune --model=Tey\\Kit\\Features\\Mcp\\Models\\McpIntent' => 'unmet'], 'an app keeping Passport keys as files gets no attention for them');
    // A host running its own OAuth consent: the plan points it at kit.mcp.consent=host, attention only; none for a host without one.
    $consentStep = fn (): ?array => array_column(HostRequirements::of($root, ['mcp' => $mcp])['steps'], null, 'step')['kit.mcp.consent'] ?? null;
    check($consentStep() === null, 'a host with no consent of its own gets no kit.mcp.consent step');
    is_dir($root.'/app/Providers') || mkdir($root.'/app/Providers', 0700, true);
    file_put_contents($root.'/app/Providers/ConsentProvider.php', "<?php\n\nuse Laravel\\Passport\\Contracts\\AuthorizationViewResponse;\n\n\$this->app->singleton(AuthorizationViewResponse::class, HostConsent::class);\n");
    $step = $consentStep();
    check($step !== null && $step['status'] === 'unmet' && ! $step['blocking'] && str_contains($step['message'], 'app/Providers/ConsentProvider.php') && str_contains($step['message'], "Set kit.mcp.consent to 'host'"), 'a host overriding AuthorizationViewResponse gets an unmet, non-blocking step recommending kit.mcp.consent=host');
    unlink($root.'/app/Providers/ConsentProvider.php');
    is_dir($root.'/routes') || mkdir($root.'/routes', 0700, true);
    file_put_contents($root.'/routes/consent.php', "<?php\n\nRoute::getRoutes()->getByName('passport.authorizations.approve')->middleware(HostApprove::class);\n");
    check(($consentStep()['status'] ?? null) === 'unmet', 'a host adding its own approve middleware gets the step too');
    config(['kit.mcp.consent' => 'host']);
    try {
        check(($consentStep()['status'] ?? null) === 'met', 'with kit.mcp.consent=host the step is met');
    } finally {
        config(['kit.mcp.consent' => 'kit']);
    }
    unlink($root.'/routes/consent.php');

    // host_ui: a UI primitive the feature imports and the host does not have breaks its build, so it blocks, with the exact command that adds it.
    $uiKit = new Lineages($lifecycle, catalogWith(['host_ui' => ['button', 'dropdown-menu', 'spinner/Spinner.vue']]));
    [$bare, $barePaths] = hostApp();
    exec('rm -rf '.escapeshellarg($bare.'/resources/js/components/ui'));
    $blocked = $uiKit->preview($bare, 'host', ['core'], $bindings, $barePaths);
    $command = 'host_ui: the kit\'s components import UI primitives this app does not have (button, dropdown-menu, spinner, under resources/js/components/ui). Add them first: npx shadcn-vue@latest add button dropdown-menu spinner';
    check(! $blocked['executable'] && $blocked['unmet'] === [$command] && in_array($command, $blocked['host_steps'], true), 'a host without the declared UI primitives gets one blocking host_ui step with the exact shadcn-vue command');
    check(array_column($blocked['host_ui'], 'status', 'path') === ['resources/js/components/ui/button/index.ts' => 'missing', 'resources/js/components/ui/dropdown-menu/index.ts' => 'missing', 'resources/js/components/ui/spinner/Spinner.vue' => 'missing'], 'each primitive resolves to its index.ts, or to the declared file');
    refuses(fn () => $uiKit->add($bare, 'host', ['core'], $bindings, $barePaths, $blocked['plan'], null, require $bare.'/verification/host-operations.txt'), 'not executable', 'kit:add refuses the blocked plan, writing nothing');
    check(! file_exists($bare.'/database/migrations/kit/core'), 'and wrote nothing');
    mkdir($bare.'/resources/js/components/ui/button', 0700, true);
    file_put_contents($bare.'/resources/js/components/ui/button/index.ts', "export {};\n");
    mkdir($bare.'/resources/js/components/ui/spinner', 0700, true);
    file_put_contents($bare.'/resources/js/components/ui/spinner/index.ts', "export {};\n");
    $partial = $uiKit->preview($bare, 'host', ['core'], $bindings, $barePaths);
    check(! $partial['executable'] && str_ends_with($partial['unmet'][0], 'npx shadcn-vue@latest add dropdown-menu spinner'), 'only the missing primitives are named: an index.ts alone does not meet a declared file');
    mkdir($bare.'/resources/js/components/ui/dropdown-menu', 0700, true);
    file_put_contents($bare.'/resources/js/components/ui/dropdown-menu/index.ts', "export {};\n");
    file_put_contents($bare.'/resources/js/components/ui/spinner/Spinner.vue', "<template><span /></template>\n");
    $ready = $uiKit->preview($bare, 'host', ['core'], $bindings, $barePaths);
    check($ready['executable'] && $ready['unmet'] === [] && array_unique(array_column($ready['host_ui'], 'status')) === ['met'] && $ready['plan'] !== $blocked['plan'], 'with every primitive in place the plan is executable again');
    foreach ([['Button'], ['button/'], ['../button'], ['spinner/Spinner.js'], 'button'] as $bad) {
        refuses(fn () => Blueprint::lineage('core', catalogWith(['host_ui' => $bad])), 'Invalid host UI requirement', 'the catalog refuses host_ui '.json_encode($bad));
    }
    // kit:update and kit:adopt plans block on the primitives the files they keep import.
    $files = ['resources/js/components/Kit/A.vue' => "<script setup lang=\"ts\">\nimport { Button } from '@/components/ui/button';\nimport Spinner from '@/components/ui/spinner/Spinner.vue';\nimport { Sheet } from \"@/components/ui/sheet/index\";\n</script>\n", 'resources/js/components/Kit/b.ts' => "export { DropdownMenu } from '@/components/ui/dropdown-menu';\n", 'app/X.php' => "<?php // from '@/components/ui/ignored'\n"];
    check(HostRequirements::importedHostUi($files) === ['button', 'dropdown-menu', 'sheet', 'spinner/Spinner.vue'], 'the primitives a plan\'s files import, in host_ui terms, from .vue and .ts files only');
    check(HostRequirements::hostUi($bare, ['button', 'sheet'])['blocking'] === ['host_ui: the kit\'s components import UI primitives this app does not have (sheet, under resources/js/components/ui). Add them first: npx shadcn-vue@latest add sheet'], 'an update that starts importing a primitive the host lacks blocks on it');

    // @inertiajs/core: declared beside @inertiajs/vue3, or installed twice, is attention (types disagree), never blocking.
    $inertia = ['@inertiajs/vue3' => '^3.0'];
    packageJson($root, ['@inertiajs/vue3' => '3.7.1']);
    exec('rm -rf '.escapeshellarg($root.'/node_modules'));
    check(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'] === [], 'vue3 alone: no warning');
    packageJson($root, ['@inertiajs/vue3' => '3.7.1', '@inertiajs/core' => '^3.0']);
    $warned = HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]]);
    check(count($warned['warnings']) === 1 && str_contains($warned['warnings'][0], 'declares @inertiajs/core (^3.0), which @inertiajs/vue3 already pins exactly'), 'a separate @inertiajs/core declaration is pointed out');
    packageJson($root, ['@inertiajs/vue3' => '3.9.1', '@inertiajs/core' => '3.9.1']);
    check(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'] === [], 'core declared at exactly the version vue3 pins is one copy: no warning');
    mkdir($root.'/node_modules/@inertiajs/vue3', 0700, true);
    file_put_contents($root.'/node_modules/@inertiajs/vue3/package.json', json_encode(['name' => '@inertiajs/vue3', 'version' => '3.7.1', 'dependencies' => ['@inertiajs/core' => '3.7.1']]));
    packageJson($root, ['@inertiajs/vue3' => '^3.7', '@inertiajs/core' => '3.7.1']);
    check(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'] === [], 'or at the version the installed vue3 pins');
    packageJson($root, ['@inertiajs/vue3' => '^3.7', '@inertiajs/core' => '3.9.1']);
    check(count(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings']) === 1, 'another version is still pointed out');
    exec('rm -rf '.escapeshellarg($root.'/node_modules'));
    packageJson($root, ['@inertiajs/vue3' => '3.7.1', '@inertiajs/core' => '^3.0']);
    foreach (['node_modules/@inertiajs/core' => '3.9.1', 'node_modules/@inertiajs/vue3/node_modules/@inertiajs/core' => '3.7.1'] as $dir => $version) {
        mkdir($root.'/'.$dir, 0700, true);
        file_put_contents($root.'/'.$dir.'/package.json', json_encode(['name' => '@inertiajs/core', 'version' => $version]));
    }
    $twice = HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]]);
    check(str_contains($twice['warnings'][0], 'Two copies of @inertiajs/core are installed (3.9.1 and 3.7.1, which @inertiajs/vue3 pins)') && str_contains($twice['warnings'][0], 'set it to exactly 3.7.1'), 'two installed copies are named, with the fix');
    check(str_contains($twice['warnings'][0], 'Remove @inertiajs/core from package.json'), 'with no import of it, the advice is to remove it');
    // A host that imports its types (Method, PageProps) must keep it declared (the standards' js-imports rule): the advice pins it instead of removing it.
    @mkdir($root.'/resources/js/types', 0700, true);
    file_put_contents($root.'/resources/js/types/page.ts', "import type { Method, PageProps } from '@inertiajs/core';\nexport type Visit = { method: Method; props: PageProps };\n");
    $kept = HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'][0];
    check(str_contains($kept, 'Your code imports it (resources/js/types/page.ts), so keep it declared: set it in package.json to exactly 3.7.1') && ! str_contains($kept, 'Remove'), 'two copies with a type import: keep it declared, at exactly the version vue3 pins');
    exec('rm -rf '.escapeshellarg($root.'/node_modules'));
    $kept = HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'][0];
    check(str_contains($kept, "so keep it declared at @inertiajs/vue3's version (exactly 3.7.1)") && ! str_contains($kept, 'Remove'), 'a loose declaration with a type import: keep it, at vue3\'s version');
    file_put_contents($root.'/resources/js/types/page.ts', "// we used to import from '@inertiajs/core-utils'\nimport { router } from '@inertiajs/vue3';\n");
    check(str_contains(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'][0], 'Remove it from package.json'), 'another package\'s name is not an import of it');
    unlink($root.'/resources/js/types/page.ts');
    // A host with no TypeScript generator keeps a hand copy of the kit's types: an outdated copy is pointed out with the types it lacks and the file to copy.
    exec('rm -rf '.escapeshellarg($root.'/node_modules'));
    packageJson($root, ['@inertiajs/vue3' => '3.7.1']);
    $typeWarnings = fn (): array => array_values(array_filter(HostRequirements::of($root, ['core' => ['requires' => ['npm' => $inertia]]])['warnings'], fn (string $warning): bool => str_contains($warning, 'kit.d.ts')));
    check($typeWarnings() === [], 'no copy of the kit\'s types: nothing to say');
    $shipped = (string) file_get_contents(HostRequirements::KIT_TYPES);
    file_put_contents($root.'/resources/js/types/kit.d.ts', preg_replace('/export type ResetPasswordViewModel = \{.*?\n\};\n/s', '', $shipped));
    $stale = $typeWarnings();
    check(count($stale) === 1 && str_contains($stale[0], 'lacks ResetPasswordViewModel') && str_contains($stale[0], 'Copy vendor/tey/kit/types/kit.d.ts over it'), 'a hand copy missing a type the kit added names it and the file to copy');
    file_put_contents($root.'/resources/js/types/kit.d.ts', $shipped);
    check($typeWarnings() === [], 'a current copy: nothing to say');
    file_put_contents($root.'/resources/js/types/kit.d.ts', '// old');
    file_put_contents($root.'/composer.json', json_encode(['require' => ['spatie/laravel-typescript-transformer' => '^3.0']]));
    check($typeWarnings() === [], 'a host whose own transformer writes its types is never told to copy');
    unlink($root.'/composer.json');
    unlink($root.'/resources/js/types/kit.d.ts');
    check(str_contains((string) file_get_contents(HostRequirements::KIT_TYPES), 'export type ResetPasswordViewModel'), 'the shipped types include the view models');
    $declared = Blueprint::lineage('core')['feature']['requires'];
    check(! isset($declared['npm']['@inertiajs/core']) && $declared['npm']['@inertiajs/vue3'] === '^3.0' && $declared['npm']['clsx'] === '^2.1.1' && $declared['npm']['tailwind-merge'] === '^3.0.0', 'core requires @inertiajs/vue3 (which pins its own core), and the clsx and tailwind-merge its cn uses');
    check($declared['host_ui'] === ['button', 'checkbox', 'dropdown-menu', 'input', 'label', 'sheet', 'spinner/Spinner.vue'], 'core declares every UI primitive it imports');

    // theme: the host's stylesheet imports kit.css (which carries the default colour tokens) or defines every token; else attention, never blocking.
    $themeKit = new Lineages($lifecycle, catalogWith(['host_steps' => ['theme' => [['file' => 'resources/css/app.css', 'why' => 'the kit is coloured with semantic tokens']]]]));
    [$themed, $themedPaths] = hostApp();
    $themeStep = fn (): array => $themeKit->preview($themed, 'host', ['core'], $bindings, $themedPaths)['steps'][0];
    @mkdir($themed.'/resources/css', 0700, true);
    @unlink($themed.'/resources/css/app.css');
    $missing = $themeStep();
    check($missing['status'] === 'unmet' && ! $missing['blocking'] && str_contains($missing['message'], '--primary, --primary-foreground') && str_contains($missing['message'], "@import './kit.css';"), 'a host with no stylesheet is told which tokens are missing and how to get them, without blocking');
    file_put_contents($themed.'/resources/css/app.css', "@import 'tailwindcss';\n");
    check($themeStep()['status'] === 'unmet', 'a stylesheet without the tokens or kit.css is unmet');
    file_put_contents($themed.'/resources/css/app.css', "@import 'tailwindcss';\n@import \"./kit.css\";\n");
    check($themeStep()['status'] === 'met' && $themeStep()['message'] === 'imports kit.css (its default colour tokens)', 'importing kit.css meets it: its defaults cover every token');
    file_put_contents($themed.'/resources/css/app.css', ":root { --primary: red; }\n@theme inline { --color-ring: var(--x); }\n");
    $partial = $themeStep();
    check($partial['status'] === 'unmet' && ! str_contains($partial['message'], '--primary,') && ! str_contains($partial['message'], '--ring') && str_contains($partial['message'], '--background'), 'a partial theme names only the tokens it lacks (a plain variable or a --color- mapping both count)');
    file_put_contents($themed.'/resources/css/app.css', ':root {'.implode('', array_map(fn (string $token): string => '--'.$token.': #000; ', HostRequirements::THEME_TOKENS)).'}');
    check($themeStep()['status'] === 'met' && $themeStep()['message'] === 'defines the colour tokens', 'a host theme defining every token meets it');
    // Another CSS entry: kit.css imported from a second Vite input, or from a file app.css imports, meets it too (it once read app.css alone).
    file_put_contents($themed.'/resources/css/app.css', "@import 'tailwindcss';\n");
    file_put_contents($themed.'/resources/css/admin.css', "@import 'tailwindcss';\n@import './kit.css';\n");
    file_put_contents($themed.'/vite.config.ts', "laravel({ input: ['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.ts'] })\n");
    check($themeStep()['status'] === 'met', 'kit.css imported from a second Vite CSS entry meets it');
    unlink($themed.'/vite.config.ts');
    check($themeStep()['status'] === 'unmet', 'and without that entry in the Vite config it does not');
    mkdir($themed.'/resources/css/theme', 0700, true);
    file_put_contents($themed.'/resources/css/app.css', "@import 'tailwindcss';\n@import './theme/index.css';\n");
    file_put_contents($themed.'/resources/css/theme/index.css', "@import '../vendor/kit.css';\n");
    check($themeStep()['status'] === 'met', 'kit.css reached through app.css\'s own @import chain meets it');
    file_put_contents($themed.'/resources/css/theme/index.css', ':root {'.implode('', array_map(fn (string $token): string => '--'.$token.': #000; ', array_slice(HostRequirements::THEME_TOKENS, 0, 9))).'}');
    file_put_contents($themed.'/resources/css/app.css', "@import './theme/index.css';\n:root {".implode('', array_map(fn (string $token): string => '--'.$token.': #000; ', array_slice(HostRequirements::THEME_TOKENS, 9))).'}');
    check($themeStep()['status'] === 'met', 'tokens split across an imported file and app.css meet it');
    file_put_contents(dirname($themed).'/outside-tokens.css', ':root {'.implode('', array_map(fn (string $token): string => '--'.$token.': #000; ', HostRequirements::THEME_TOKENS)).'}');
    file_put_contents($themed.'/resources/css/app.css', "@import '../../../outside-tokens.css';\n@import './app.css';\n");
    check($themeStep()['status'] === 'unmet', 'an import outside the host root is not followed, and an import cycle ends');
    @unlink($themed.'/resources/css/admin.css');
    @unlink(dirname($themed).'/outside-tokens.css');
    refuses(fn () => Blueprint::lineage('core', catalogWith(['host_steps' => ['theme' => [['file' => 'resources/css/app.css']]]])), 'Invalid host step', 'a theme step needs its why');
    check(Blueprint::lineage('core')['feature']['host_steps']['theme'][0]['file'] === 'resources/css/app.css', 'core declares the theme step on resources/css/app.css');

    // kit.css: every token at the lowest precedence, with a value in light and dark.
    $kitCss = (string) file_get_contents(dirname(__DIR__).'/resources/features/core/css/kit.css.stub');
    preg_match('/@theme default inline \{(.*?)\}/s', $kitCss, $defaults);
    preg_match('/:where\(:root\) \{(.*?)\}/s', $kitCss, $light);
    preg_match('/:where\(\.dark\) \{(.*?)\}/s', $kitCss, $dark);
    foreach (HostRequirements::THEME_TOKENS as $token) {
        check(str_contains($defaults[1] ?? '', '--color-'.$token.': var(--'.$token.');') && preg_match('/--'.preg_quote($token, '/').':\s/', $light[1] ?? '') === 1 && preg_match('/--'.preg_quote($token, '/').':\s/', $dark[1] ?? '') === 1, 'kit.css maps --color-'.$token.' at default precedence, with zero-specificity light and dark values');
    }
    // Every semantic token a kit stub colours with is one kit.css defaults.
    $used = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/resources/features', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (preg_match('/\.(vue|ts)\.stub$/', $file->getFilename())) {
            preg_match_all('/\b(?:bg|text|border|ring|outline|fill|stroke|divide|placeholder|from|to|via)-((?:background|foreground|card|popover|primary|secondary|muted|accent|destructive|input|ring|border)(?:-foreground)?)\b/', (string) file_get_contents($file->getPathname()), $classes);
            array_push($used, ...$classes[1]);
        }
    }
    $used = array_values(array_unique($used));
    check($used !== [] && array_diff($used, HostRequirements::THEME_TOKENS) === [], 'every semantic token the kit\'s stubs use has a default in kit.css ('.count($used).' used)');

    echo "Plan requirement checks on synthetic host apps only; no npm, no .env reads, no migrations run.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error."\n");
    exit(1);
}
