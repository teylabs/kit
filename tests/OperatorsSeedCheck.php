<?php

// kit:operators:seed --from-platform-owners, the concierge.manage-tenants Gate on operators, and the empty-operators attention in kit:add/kit:adopt
// requirements, kit:status and the local/testing boot log; synthetic SQLite files in a temporary directory, no network.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Traits\HasRoles;
use Tey\Concierge\Contracts\Member;
use Tey\Kit\Catalog;
use Tey\Kit\Console\StatusCommand;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\HostRequirements;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class OperatorsUser extends Model implements Illuminate\Contracts\Auth\Authenticatable, Member
{
    use HasRoles, Illuminate\Auth\Authenticatable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function conciergeEmail(): string
    {
        return (string) $this->email;
    }

    public function conciergeHasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function conciergeDisplayName(): string
    {
        return (string) $this->name;
    }
}

/** Counts every query from before the feature providers boot. */
final class OperatorsQueryCounter extends ServiceProvider
{
    public static int $queries = 0;

    public function boot(): void
    {
        $this->app['events']->listen(QueryExecuted::class, fn () => self::$queries++);
    }
}

$owners = ['  Owner@Northwind.test ', 'ops@acme.test', 'unverified@northwind.test', 'inactive@acme.test', 'nobody@acme.test'];

/** A booted synthetic host on the SQLite file $database, with core and workspaces adopted. */
function operatorsHost(string $root, string $database, string $env, array $owners): Application
{
    foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs', 'config'] as $dir) {
        is_dir($root.'/'.$dir) || mkdir($root.'/'.$dir, 0700, true);
    }
    file_put_contents($root.'/config/kit.php', '<?php return '.var_export(['features' => ['core', 'workspaces'], 'models' => ['user' => OperatorsUser::class]], true).';');
    file_put_contents($root.'/config/concierge.php', '<?php return '.var_export(['models' => ['user' => OperatorsUser::class], 'tenant_invitations' => ['platform_owners' => $owners]], true).';');
    file_put_contents($root.'/config/database.php', '<?php return '.var_export(['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false]]], true).';');
    file_put_contents($root.'/config/cache.php', "<?php return ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]];");
    file_put_contents($root.'/config/app.php', "<?php return ['env' => '".$env."', 'key' => 'base64:".base64_encode(random_bytes(32))."', 'cipher' => 'AES-256-CBC'];");
    file_put_contents($root.'/config/session.php', "<?php return ['driver' => 'array'];");
    file_put_contents($root.'/config/logging.php', "<?php return ['default' => 'single', 'channels' => ['single' => ['driver' => 'single', 'path' => '".$root."/storage/logs/laravel.log']]];");
    @unlink($root.'/storage/logs/laravel.log');
    $app = Application::configure($root)->withMiddleware(fn ($m) => null)->withExceptions(fn ($e) => null)->withProviders([OperatorsQueryCounter::class, FeaturesServiceProvider::class])->create();
    OperatorsQueryCounter::$queries = 0;
    $app->make(Kernel::class)->bootstrap();
    Facade::setFacadeApplication($app);

    return $app;
}
function bootLog(string $root): string
{
    return is_file($root.'/storage/logs/laravel.log') ? (string) file_get_contents($root.'/storage/logs/laravel.log') : '';
}

$root = sys_get_temp_dir().'/kit-operators-'.bin2hex(random_bytes(8));
mkdir($root, 0700, true);
$root = realpath($root);
$database = $root.'/database.sqlite';
touch($database);
try {
    // Boot with no tables at all, in testing: the boot warning's guard keeps boot working.
    $app = operatorsHost($root, $database, 'testing', $owners);
    check($app->isBooted(), 'testing boot survives a database without users or operators tables');
    $missing = operatorsHost($root, $root.'/no-such-dir/database.sqlite', 'testing', $owners);
    check($missing->isBooted(), 'testing boot survives an unreachable database');

    $app = operatorsHost($root, $database, 'testing', $owners);
    $schema = $app['db']->connection()->getSchemaBuilder();
    $schema->create('users', function ($t): void {
        $t->id();
        $t->string('name');
        $t->string('email');
        $t->timestamp('email_verified_at')->nullable();
    });
    $schema->create('concierge_accounts', function ($t): void {
        $t->unsignedBigInteger('user_id')->primary();
        $t->string('status', 16);
        $t->timestamps();
    });
    // The workspaces feature's own operators migration.
    (require dirname(__DIR__).'/resources/features/workspaces/migrations/0001_01_02_000202_create_kit_operators_table.php.stub')->up();
    $now = '2026-01-01 00:00:00';
    $owner = OperatorsUser::query()->create(['name' => 'Northwind Owner', 'email' => 'owner@northwind.test', 'email_verified_at' => $now]);
    $ops = OperatorsUser::query()->create(['name' => 'Acme Ops', 'email' => 'OPS@acme.test ', 'email_verified_at' => $now]);
    $unverified = OperatorsUser::query()->create(['name' => 'Unverified', 'email' => 'unverified@northwind.test', 'email_verified_at' => null]);
    $inactive = OperatorsUser::query()->create(['name' => 'Inactive', 'email' => 'inactive@acme.test', 'email_verified_at' => $now]);
    $stranger = OperatorsUser::query()->create(['name' => 'Not An Owner', 'email' => 'stranger@acme.test', 'email_verified_at' => $now]);
    $app['db']->table('concierge_accounts')->insert(['user_id' => $inactive->id, 'status' => 'deactivated']);
    $operators = fn (): array => $app['db']->table('operators')->orderBy('actor_id')->pluck('actor_id')->map(fn ($id): int => (int) $id)->all();
    $kernel = $app->make(Kernel::class);

    // The empty-operators attention, before seeding.
    $lineages = new Lineages(new Lifecycle, Catalog::package());
    $requirements = $lineages->requirements($root, ['core', 'workspaces']);
    $step = array_values(array_filter($requirements['steps'], fn (array $step): bool => $step['kind'] === 'operators'));
    check(count($step) === 1 && $step[0]['status'] === 'unmet' && $step[0]['blocking'] === false && $step[0]['step'] === 'php artisan kit:operators:seed --from-platform-owners', 'kit:add/kit:adopt requirements carry the seed as a non-blocking operators step');
    check($requirements['attention'] && str_contains($step[0]['message'], 'would seed: owner@northwind.test, ops@acme.test)') && ! str_contains($step[0]['message'], 'unverified@') && ! str_contains($step[0]['message'], 'inactive@'), 'the step names exactly the verified, active matches and the command');
    check(! array_filter($lineages->requirements($root, ['core'])['steps'], fn (array $step): bool => $step['kind'] === 'operators'), 'a plan without workspaces has no operators step');
    $status = new ReflectionMethod(StatusCommand::class, 'requirements');
    $command = $app->make(StatusCommand::class);
    $command->setLaravel($app);
    $statusRequirements = $status->invoke($command, ['core', 'workspaces']);
    $statusLines = implode("\n", [...HostRequirements::attention($statusRequirements), ...HostRequirements::lines($statusRequirements)]);
    check(str_contains($statusLines, 'ATTENTION') && str_contains($statusLines, 'operators is empty') && str_contains($statusLines, 'step [unmet] workspaces operators: php artisan kit:operators:seed --from-platform-owners'), 'kit:status warns when platform_owners is set and operators is empty');

    // The boot warning: logged in testing, and production boot runs no query for it.
    operatorsHost($root, $database, 'testing', $owners);
    check(str_contains(bootLog($root), 'operators is empty'), 'testing boot logs the empty-operators warning');
    operatorsHost($root, $database, 'production', $owners);
    check(OperatorsQueryCounter::$queries === 0 && ! str_contains(bootLog($root), 'operators is empty'), 'production boot runs no database query and logs nothing');
    $app = operatorsHost($root, $database, 'testing', $owners);
    $kernel = $app->make(Kernel::class);

    // The Gate: platform_owners alone no longer grants tenant admin.
    $allows = fn (Model $user): bool => Gate::forUser($user)->allows('concierge.manage-tenants');
    check(! $allows($owner) && ! $allows($ops), 'a platform-owner email that is not an operator is refused');

    // Dry run writes nothing.
    check($kernel->call('kit:operators:seed', ['--from-platform-owners' => true, '--dry-run' => true]) === 0, 'dry run succeeds');
    $output = $kernel->output();
    check($operators() === [], 'dry run writes nothing');
    check(str_contains($output, 'Dry run') && str_contains($output, '2 matched, 0 already operators, 2 skipped, 1 unmatched') && str_contains($output, 'Would seed: owner@northwind.test'), 'dry run reports what it would seed');
    check($kernel->call('kit:operators:seed', ['--dry-run' => true]) !== 0, 'the source must be named');

    // Seed.
    check($kernel->call('kit:operators:seed', ['--from-platform-owners' => true]) === 0, 'seeding succeeds');
    $output = $kernel->output();
    check($operators() === [(int) $owner->id, (int) $ops->id], 'only the verified, active matches are seeded, matched by trimmed, lower-cased email');
    check(str_contains($output, 'Seeded: owner@northwind.test') && str_contains($output, 'Seeded: ops@acme.test'), 'matched emails are reported');
    check(str_contains($output, 'Skipped, unverified: unverified@northwind.test') && str_contains($output, 'Skipped, inactive: inactive@acme.test'), 'unverified and inactive users are skipped, with why');
    check(str_contains($output, 'No user: nobody@acme.test'), 'unmatched emails are reported');

    // Idempotent.
    check($kernel->call('kit:operators:seed', ['--from-platform-owners' => true]) === 0, 'seeding again succeeds');
    $output = $kernel->output();
    check($operators() === [(int) $owner->id, (int) $ops->id] && str_contains($output, '0 matched, 2 already operators') && str_contains($output, 'Already an operator: owner@northwind.test'), 'seeding again is idempotent and reports existing operators');

    // The Gate after seeding.
    check($allows($owner) && $allows($ops), 'a seeded operator is allowed');
    check(! $allows($stranger) && ! $allows($unverified) && ! $allows($inactive), 'a non-operator, an unverified and an inactive user are refused');
    $app['db']->table('operators')->insert(['actor_id' => $unverified->id]);
    $app['db']->table('operators')->insert(['actor_id' => $inactive->id]);
    check(! $allows(OperatorsUser::query()->find($unverified->id)) && ! $allows(OperatorsUser::query()->find($inactive->id)), 'an operator row on an unverified or inactive account is still refused');

    // The attention clears once operators has a row.
    check(! array_filter($lineages->requirements($root, ['core', 'workspaces'])['steps'], fn (array $step): bool => $step['kind'] === 'operators'), 'the operators step clears after seeding');
    $statusRequirements = $status->invoke($command, ['core', 'workspaces']);
    check($statusRequirements === null || ! str_contains(implode("\n", HostRequirements::attention($statusRequirements)), 'operators is empty'), 'the kit:status warning clears after seeding');
    operatorsHost($root, $database, 'testing', $owners);
    check(! str_contains(bootLog($root), 'operators is empty'), 'the boot warning clears after seeding');

    // No platform owners configured: nothing to warn about.
    $app['db']->table('operators')->delete();
    operatorsHost($root, $database, 'testing', []);
    check(! array_filter($lineages->requirements($root, ['core', 'workspaces'])['steps'], fn (array $step): bool => $step['kind'] === 'operators') && ! str_contains(bootLog($root), 'operators is empty'), 'no warning when platform_owners is empty');
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
} finally {
    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
