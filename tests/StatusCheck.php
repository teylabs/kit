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
    echo "Status checks only; no consumer, product, browser or database acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
} finally {
    (new Filesystem)->deleteDirectory($root);
}
