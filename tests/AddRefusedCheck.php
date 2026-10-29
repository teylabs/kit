<?php

// kit:add --json preview on a host without a package a feature needs (a single-tenant app without spatie/laravel-multitenancy): the plan is what it always was
// (`composer require` among its steps, not executable until then, as Mod's compatibility check says), and it never loads a class built on the missing package (a missing trait is a fatal error): the host model
// checks are deferred until the package is in. The host's configured tenant model here uses a trait that does not exist, so loading it would end the run.
// A genuine refusal (an unknown feature) is `{status: "refused", error, hint}` under --json, the shape kit:adopt prints.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
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

$root = sys_get_temp_dir().'/kit-add-refused-'.bin2hex(random_bytes(8));
try {
    exec('cp -R '.escapeshellarg(__DIR__.'/fixtures/host-app').' '.escapeshellarg($root));
    foreach (['bootstrap/cache', 'storage/logs', 'verification', 'app/Tenancy'] as $dir) {
        @mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () { return true; };');
    // A tenant model whose trait is gone, as the kit's Workspace is without spatie/laravel-multitenancy.
    file_put_contents($root.'/app/Tenancy/Tenant.php', "<?php\n\nnamespace RefusedFixture;\n\nfinal class Tenant extends \\Illuminate\\Database\\Eloquent\\Model\n{\n    use \\Spatie\\Multitenancy\\Models\\Concerns\\NoSuchTrait;\n}\n");
    $loader->addPsr4('RefusedFixture\\', $root.'/app/Tenancy');
    // Published config naming it, as an app with its own tenant model has: the plan reads these files, not the kit's defaults.
    @mkdir($root.'/config', 0700, true);
    file_put_contents($root.'/config/concierge.php', "<?php\n\nreturn ['models' => ['workspace' => 'RefusedFixture\\\\Tenant']];\n");
    file_put_contents($root.'/config/multitenancy.php', "<?php\n\nreturn ['tenant_model' => 'RefusedFixture\\\\Tenant'];\n");
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $app->instance(LifecycleHost::class, new class(new Lifecycle, $paths) implements LifecycleHost
    {
        public function __construct(private Lifecycle $lifecycle, private array $paths) {}

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
            return [];
        }

        public function inputPaths(): array
        {
            return $this->paths;
        }

        public function operationCheck(): Closure
        {
            return fn () => true;
        }
    });
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    simulateMissing('spatie/laravel-multitenancy');

    foreach (['workspaces', 'session-ui'] as $feature) {
        $code = $kernel->call('kit:add', ['features' => [$feature], '--json' => true]);
        $preview = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
        check(($preview['missing_packages']['spatie/laravel-multitenancy'] ?? null) === '^4.2' && in_array('composer require spatie/laravel-multitenancy:"^4.2"', $preview['host_steps'], true), 'kit:add '.$feature.' --json without spatie/laravel-multitenancy plans as before, with no crash: the composer require is among its steps');
        // As before this fix: Mod plans the feature as not executable while a package it requires fails compatibility, so the plan waits on the composer require.
        check($preview['executable'] === false && $preview['status'] === 'blocked' && ! isset($preview['error']) && $preview['features'][array_search('workspaces', array_column($preview['features'], 'feature'), true)]['compatibility']['spatie/laravel-multitenancy'] !== 'passed', 'and the plan is what it was before: blocked on the package (Mod\'s compatibility), not refused');
        check(count($preview['deferred']) === 1 && str_starts_with($preview['deferred'][0], 'workspaces: the host model checks (user_model, workspace_model) are deferred') && in_array('re-run php artisan kit:add '.$feature.' after composer require: the host model checks for workspaces are deferred until then', $preview['host_steps'], true), 'the skipped model checks are deferred, named in the plan with the re-run');
        check(! class_exists('RefusedFixture\\Tenant', false), 'the host\'s tenant model was never loaded');
    }
    $code = $kernel->call('kit:add', ['features' => ['workspaces']]);
    check(str_contains($kernel->output(), 'spatie/laravel-multitenancy'), 'the text preview names the package too');

    restoreInstalled();
    check($kernel->call('kit:add', ['features' => ['no-such-feature'], '--json' => true]) !== 0, 'an unknown feature is refused');
    $refused = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    check($refused['status'] === 'refused' && is_string($refused['error']) && is_array($refused['hint']), 'a genuine refusal under --json is {status: refused, error, hint}, as kit:adopt prints');
    check($kernel->call('kit:adopt', ['feature' => 'session-ui', 'action' => 'plan', '--json' => true]) !== 0, 'kit:adopt session-ui without workspaces is refused');
    $refused = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    check($refused['status'] === 'refused' && str_contains($refused['error'], 'requires feature workspaces'), 'a missing feature requirement is refused, with the same shape');
    echo "kit:add refusal checks on a synthetic host app, no network. The fatal itself is shown by the fixture model, never loaded.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
} finally {
    exec('rm -rf '.escapeshellarg($root));
}
