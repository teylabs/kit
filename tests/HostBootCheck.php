<?php

// What the composition root boots for a host that adopted only some features; synthetic SQLite memory, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\FeaturesServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
/** A booted synthetic host that adopted the named features (an explicit `kit.features`). */
function host(array $features, array $config = []): Application
{
    $root = sys_get_temp_dir().'/kit-boot-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => $features, 'models' => ['user' => Illuminate\Foundation\Auth\User::class]] + $config, true).';');
    file_put_contents($root.'/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]];");
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', "<?php return ['key' => 'base64:".base64_encode(random_bytes(32))."', 'cipher' => 'AES-256-CBC'];");
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/mail.php', "<?php return ['from' => ['address' => 'host@example.test', 'name' => 'Host Own Name']];");
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([FeaturesServiceProvider::class])->create();
    $app->make(Kernel::class)->bootstrap();

    return $app;
}
function health(Application $app): ?string
{
    try {
        $app['events']->dispatch(new DiagnosingHealth);
    } catch (RuntimeException $error) {
        return $error->getMessage();
    }

    return null;
}
function listening(Application $app, string $event, string $listener): bool
{
    foreach ($app['events']->getListeners($event) as $closure) {
        $reflection = new ReflectionFunction($closure);
        if (str_contains(serialize(array_keys($reflection->getStaticVariables())), 'listener') && in_array($listener, array_map(fn ($v) => is_string($v) ? $v : '', $reflection->getStaticVariables()), true)) {
            return true;
        }
    }

    return false;
}

try {
    // A host that adopted only core, with its own tenancy and its own migrations.
    $core = host(['core']);
    check(health($core) !== null && str_contains(health($core), 'kit_meta') && ! str_contains(health($core), 'workspaces'), 'core-only: /up asks for core\'s kit_meta and for no workspace table');
    $core['db']->connection()->getSchemaBuilder()->create('users', fn ($t) => $t->id());
    $core['db']->connection()->getSchemaBuilder()->create('kit_meta', fn ($t) => $t->string('key'));
    check(health($core) === null, 'core-only: /up is healthy without workspaces, concierge_memberships or roles');
    check($core['config']->get('mail.from') === ['address' => 'host@example.test', 'name' => 'Host Own Name'] && $core['config']->get('mail.reply_to') === null, 'core-only: mail.from and mail.reply_to are the host\'s');
    check(! listening($core, NoPendingMigrations::class, \Tey\Kit\Features\Core\Deploy\CatalogSync::class), 'core-only: CatalogSync is not registered');

    // Nothing adopted: no health listener at all.
    $none = host([]);
    check(health($none) === null && $none['config']->get('mail.reply_to') === null, 'no features: /up is healthy and mail is untouched');

    // The explicit opt-in applies the brand without session-ui.
    $opt = host(['core'], ['brand_mail' => true]);
    check($opt['config']->get('mail.from.name') !== 'Host Own Name', 'brand_mail => true applies the brand to mail on a core-only host');

    // Full adoption keeps today's behaviour.
    $full = host(['core', 'workspaces', 'access', 'settings', 'session-ui']);
    $message = health($full);
    check($message !== null && str_contains($message, 'the table workspaces is missing') && str_contains($message, 'concierge_memberships') && str_contains($message, 'roles') && str_contains($message, 'kit_meta'), 'full adoption: the health checks name every table');
    check($full['config']->get('mail.from.name') !== 'Host Own Name' && $full['config']->get('mail.reply_to') !== null, 'full adoption: the brand is applied to mail');
    check(listening($full, NoPendingMigrations::class, \Tey\Kit\Features\Core\Deploy\CatalogSync::class), 'full adoption: CatalogSync is registered');
    $branded = host(['core', 'workspaces', 'access', 'settings', 'session-ui'], ['brand_mail' => false]);
    check($branded['config']->get('mail.from.name') === 'Host Own Name', 'brand_mail => false keeps mail the host\'s even with session-ui');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
