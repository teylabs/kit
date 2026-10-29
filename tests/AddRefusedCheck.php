<?php

// kit:add --json preview on a host without a package a feature needs (a single-tenant app without spatie/laravel-multitenancy): the preview refuses, as
// kit:adopt does, with status "refused", the reason and the composer require to run, and never loads a class built on the missing package (a missing trait is
// a fatal error no catch can turn into a refusal). The host's configured tenant model here uses a trait that does not exist, so loading it would end the run.
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
        check($code !== 0 && $preview['status'] === 'refused' && $preview['executable'] === false, 'kit:add '.$feature.' --json without spatie/laravel-multitenancy refuses (status "refused"), with no fatal');
        check(str_contains($preview['error'], 'workspaces') && str_contains($preview['error'], 'spatie/laravel-multitenancy ^4.2'), 'and says which feature needs which package');
        check(in_array('composer require spatie/laravel-multitenancy:"^4.2"', $preview['hint'], true) && str_contains(end($preview['hint']), 'php artisan kit:add '.$feature), 'and the hint is the composer require, then the same kit:add again');
        check(! class_exists('RefusedFixture\\Tenant', false), 'the host\'s tenant model was never loaded');
    }
    $code = $kernel->call('kit:add', ['features' => ['workspaces']]);
    check($code !== 0 && str_contains($kernel->output(), 'spatie/laravel-multitenancy'), 'the text preview names the package too');

    restoreInstalled();
    $kernel->call('kit:add', ['features' => ['no-such-feature'], '--json' => true]);
    $refused = json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR);
    check($refused['status'] === 'refused' && is_string($refused['error']) && is_array($refused['hint']), 'any other refusal under --json is JSON in the same shape, as kit:adopt prints');
    echo "kit:add refusal checks on a synthetic host app, no network. The fatal itself is shown by the fixture model, never loaded.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
} finally {
    exec('rm -rf '.escapeshellarg($root));
}
