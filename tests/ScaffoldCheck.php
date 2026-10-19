<?php

// Point to a public dependency install; no package-private API or app boot needed.
$autoload = $argv[1] ?? null;
if (! is_string($autoload) || ! is_file($autoload)) {
    fwrite(STDERR, "Pass a Composer autoload.php with Laravel13 and Mod0.7.2.\n");
    exit(2);
}
$loader = require $autoload;
$loader->addPsr4('Tey\\StarterBlueprint\\', dirname(__DIR__).'/src');
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tey\Mod\ModServiceProvider;
use Tey\StarterBlueprint\Blueprint;
use Tey\StarterBlueprint\BlueprintServiceProvider;

function blueprintCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    } echo 'PASS '.$message."\n";
}
try {
    $blueprint = Blueprint::release('A');
    blueprintCheck($blueprint->sourceReference() === Blueprint::release('A')->sourceReference(), 'same immutable blueprint produces same source reference');
    blueprintCheck($blueprint->sourceReference() !== Blueprint::release('B')->sourceReference(), 'changed blueprint bytes have a different source reference');
    try {
        $blueprint->stub('../outside');
        throw new RuntimeException('Traversal accepted');
    } catch (RuntimeException $e) {
        blueprintCheck($e->getMessage() === 'Invalid blueprint resource.', 'resource traversal refused');
    }
    $root = sys_get_temp_dir().'/starter-blueprint-scaffold-'.bin2hex(random_bytes(6));
    foreach (['bootstrap/cache', 'config', 'app', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/composer.json', json_encode(['name' => 'example/consumer', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    file_put_contents($root.'/config/app.php', "<?php return ['name'=>'Synthetic consumer','env'=>'testing','key'=>'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','cipher'=>'AES-256-CBC'];");
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, BlueprintServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $exit = $kernel->call('starter:foundation', ['--dry-run' => true, '--json' => true, '--no-interaction' => true]);
    $output = $kernel->output();
    $plan = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    if ($exit !== 0 || $plan['would_write'] !== true) {
        fwrite(STDERR, $output);
    }
    blueprintCheck($exit === 0 && $plan['would_write'] === true, 'released Mod scaffold produces executable preview');
    blueprintCheck(! is_file($root.'/app/Support/Starter/BlueprintPolicy.php'), 'preview writes no generated file');
    $second = $kernel->call('starter:foundation', ['--dry-run' => true, '--json' => true, '--no-interaction' => true]);
    blueprintCheck($kernel->output() === $output, 'repeat preview is byte-identical');
    $kernel->call('starter:foundation', ['name' => 'CustomPolicy', '--dry-run' => true, '--json' => true, '--no-interaction' => true]);
    $custom = json_decode($kernel->output(), true, flags: JSON_THROW_ON_ERROR);
    blueprintCheck($custom['files'][0]['path'] === $plan['files'][0]['path'], 'alternate public name cannot escape declared policy ownership');
    config()->set('mod.layout', 'ddd');
    try {
        (new BlueprintServiceProvider($app))->boot();
        throw new RuntimeException('Unsupported layout accepted');
    } catch (RuntimeException $e) {
        blueprintCheck($e->getMessage() === 'Blueprint requires the unmodified laravel layout.', 'unsupported layout refuses before generation');
    } finally {
        config()->set('mod.layout', 'laravel');
    }
    $exit = $kernel->call('starter:foundation', ['--no-interaction' => true]);
    $path = $root.'/app/Support/Starter/BlueprintPolicy.php';
    blueprintCheck($exit === 0 && is_file($path), 'released Mod generates declared file at intended path');
    blueprintCheck(str_contains(file_get_contents($path), "BLUEPRINT_VERSION = '0.1.0'"), 'generated policy records selected blueprint version');
    $before = hash_file('sha256', $path);
    $exit = $kernel->call('starter:foundation', ['--no-interaction' => true]);
    blueprintCheck(hash_file('sha256', $path) === $before, 'ordinary regeneration preserves existing file');
    echo json_encode(['blueprint' => $blueprint->sourceReference(), 'root' => $root, 'plan' => $plan, 'generated_hash' => $before], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e."\n");
    exit(1);
}
