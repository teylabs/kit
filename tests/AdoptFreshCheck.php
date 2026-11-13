<?php

// An adopting host on a fresh database. kit:add plans the adoption of the host's own workspaces and permission tables, records it in the
// workspaces lineage's installed record (which kit.workspaces.adopt / kit.access.adopt read), and publishes the kit's migrations with a stamp
// after the host's own, so `migrate` on an empty database runs the host's migrations first and ends with the same schema as adopting over an
// existing database. A non-adopting host keeps the catalog's names; an existing install keeps its names on kit:update and re-runs nothing.
// Synthetic catalog copy (the real workspaces and access migration stubs), synthetic host app and tables. SQLite memory by default;
// DB_CONNECTION=pgsql runs the migrate comparisons against the PostgreSQL the DB_* variables name, in the kit_fresh_*, kit_prod_* and kit_plain_*
// schemas, which it drops and recreates: point it only at a throwaway database.
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Catalog;
use Tey\Kit\Features\DiscoveryCache;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Workspaces\Adoptions;
use Tey\Kit\KitServiceProvider;
use Tey\Kit\Lifecycle;
use Tey\Kit\Lineages;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const KIT = __DIR__.'/../resources/features';

/** Delegates every file:// call and records the paths opened for reading, so a check can prove which files a step reads. */
final class OpenedFiles
{
    /** @var list<string> */
    public static array $opened = [];

    /** @var resource|null */
    public $context;

    /** @var resource|false */
    private $handle = false;

    /** @return list<string> the paths `$run` opened */
    public static function during(Closure $run): array
    {
        self::$opened = [];
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
        try {
            $run();
        } finally {
            stream_wrapper_restore('file');
        }

        return self::$opened;
    }

    private static function real(Closure $call): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $call();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        self::$opened[] = $path;
        $this->handle = self::real(fn () => @fopen($path, $mode));

        return $this->handle !== false;
    }

    public function stream_read(int $count): string|false
    {
        return self::real(fn () => fread($this->handle, $count));
    }

    public function stream_write(string $data): int|false
    {
        return self::real(fn () => fwrite($this->handle, $data));
    }

    public function stream_eof(): bool
    {
        return self::real(fn () => feof($this->handle));
    }

    public function stream_stat(): array|false
    {
        return self::real(fn () => fstat($this->handle));
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return self::real(fn () => fseek($this->handle, $offset, $whence) === 0);
    }

    public function stream_tell(): int|false
    {
        return self::real(fn () => ftell($this->handle));
    }

    public function stream_flush(): bool
    {
        return self::real(fn () => fflush($this->handle));
    }

    public function stream_lock(int $operation): bool
    {
        return self::real(fn () => flock($this->handle, $operation));
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    public function stream_close(): void
    {
        self::real(fn () => fclose($this->handle));
    }

    public function url_stat(string $path, int $flags): array|false
    {
        return self::real(fn () => ($flags & STREAM_URL_STAT_LINK) ? @lstat($path) : @stat($path));
    }

    /** @var list<string> */
    private array $entries = [];

    public function dir_opendir(string $path, int $options): bool
    {
        $entries = self::real(fn () => @scandir($path));
        $this->entries = $entries === false ? [] : $entries;

        return $entries !== false;
    }

    public function dir_readdir(): string|false
    {
        return array_shift($this->entries) ?? false;
    }

    public function dir_rewinddir(): bool
    {
        return true;
    }

    public function dir_closedir(): bool
    {
        return true;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return self::real(fn () => match ($option) {
            STREAM_META_TOUCH => touch($path, ...array_filter((array) $value)),
            STREAM_META_ACCESS => chmod($path, $value),
            default => false,
        });
    }

    public function unlink(string $path): bool
    {
        return self::real(fn () => @unlink($path));
    }

    public function rename(string $from, string $to): bool
    {
        return self::real(fn () => @rename($from, $to));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::real(fn () => @mkdir($path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE)));
    }
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function refused(Closure $run): ?string
{
    try {
        $run();
    } catch (Throwable $error) {
        return $error->getMessage();
    }

    return null;
}
function copyTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        $path = $destination.substr($entry->getPathname(), strlen($source));
        $entry->isDir() ? mkdir($path, 0700) : file_put_contents($path, file_get_contents($entry->getPathname()));
    }
}
/** Pins for a catalog copy, computed independently of Blueprint. */
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
/**
 * The fixture catalog with the kit's real workspaces and access migrations (their catalog names), at $version; $extra adds workspaces
 * migrations (name => bytes), as a later release would.
 */
function catalog(string $version, array $extra = []): Catalog
{
    $root = sys_get_temp_dir().'/kit-adopt-fresh-catalog-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/catalog', $root);
    foreach (['workspaces', 'access'] as $id) {
        $manifest = json_decode(file_get_contents($root.'/'.$id.'/feature.json'), true);
        $manifest['owns'][] = 'database/migrations/kit/'.$id;
        mkdir($root.'/'.$id.'/migrations');
        $stubs = [];
        foreach (glob(KIT.'/'.$id.'/migrations/*.stub') as $stub) {
            $stubs[basename($stub)] = file_get_contents($stub);
        }
        if ($id === 'workspaces') {
            foreach ($extra as $name => $bytes) {
                $stubs[$name.'.stub'] = $bytes;
            }
        }
        foreach ($stubs as $name => $bytes) {
            file_put_contents($root.'/'.$id.'/migrations/'.$name, $bytes);
            $manifest['files']['database/migrations/kit/'.$id.'/'.basename($name, '.stub')] = ['stub' => 'migrations/'.$name, 'scaffold' => 'kit', 'ownership' => 'generated'];
        }
        file_put_contents($root.'/'.$id.'/feature.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    file_put_contents($root.'/blueprint.json', str_replace('"version": "0.1.0"', '"version": "'.$version.'"', file_get_contents($root.'/blueprint.json')));

    return repin($root);
}
/** A disposable host app, booted, with the host's own migrations (a 2024 workspaces table and spatie's permission tables) in database/migrations. */
function hostApp(): array
{
    $root = sys_get_temp_dir().'/kit-adopt-fresh-'.bin2hex(random_bytes(8));
    copyTree(__DIR__.'/fixtures/host-app', $root);
    foreach (['bootstrap/cache', 'storage/logs', 'storage/framework/cache', 'verification', 'database/migrations'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $paths = [];
    foreach (['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'] as $id) {
        $paths[$id] = 'verification/'.$id.'.txt';
        file_put_contents($root.'/'.$paths[$id], 'synthetic fixture '.$id);
    }
    file_put_contents($root.'/verification/executor-provenance.txt', json_encode((new Lifecycle)->runtime()));
    file_put_contents($root.'/verification/host-operations.txt', '<?php return function () { return true; };');
    // Core's audit_events (unconstrained workspace_id) sorts first, as the kit's core migrations do; the host's own tables follow, with real dates.
    file_put_contents($root.'/database/migrations/0001_01_01_000100_create_audit_events_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('audit_events', function (Blueprint $t): void {
                    $t->id();
                    $t->unsignedBigInteger('workspace_id')->nullable();
                    $t->string('event');
                });
            }
        };
        PHP);
    file_put_contents($root.'/database/migrations/2024_03_01_120000_create_workspaces_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('workspaces', function (Blueprint $t): void {
                    $t->id();
                    $t->string('name');
                    $t->boolean('accepts_members')->default(false);
                    $t->timestamps();
                });
            }
        };
        PHP);
    copy(dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub', $root.'/database/migrations/2024_03_01_120001_create_permission_tables.php');
    $root = realpath($root);
    $app = Application::configure($root)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $config = $app->make('config');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array', 'serialize' => false]);
    $config->set('permission', array_replace_recursive(require dirname(__DIR__).'/vendor/spatie/laravel-permission/config/permission.php', require dirname(__DIR__).'/config/defaults/permission.php', ['cache' => ['store' => 'array']]));

    return [$root, $paths, $app];
}
/** A fresh, empty connection named $name: SQLite memory, or a newly created PostgreSQL schema. */
function freshConnection(Application $app, string $name): string
{
    $config = $app->make('config');
    if (getenv('DB_CONNECTION') === 'pgsql') {
        $pg = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => getenv('DB_DATABASE') ?: 'kit_adopt_check', 'username' => getenv('DB_USERNAME') ?: 'postgres', 'password' => getenv('DB_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'sslmode' => 'prefer'];
        $config->set('database.connections.pg_admin', $pg + ['search_path' => 'public']);
        DB::connection('pg_admin')->statement('drop schema if exists '.$name.' cascade');
        DB::connection('pg_admin')->statement('create schema '.$name);
        $config->set('database.connections.'.$name, $pg + ['search_path' => $name]);
    } else {
        $config->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }
    DB::purge($name);

    return $name;
}
/** Runs `migrate` over $paths on $connection (the default connection while it runs, as artisan migrate does); returns the names it ran. */
function migrate(Application $app, string $connection, array $paths): array
{
    $config = $app->make('config');
    $previous = $config->get('database.default');
    $config->set('database.default', $connection);
    DB::setDefaultConnection($connection);
    try {
        $migrator = $app->make('migrator');
        $migrator->setConnection($connection);
        $repository = $migrator->getRepository();
        $repository->setSource($connection);
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        return array_map(fn (string $file): string => basename($file, '.php'), $migrator->run($paths));
    } finally {
        $config->set('database.default', $previous);
        DB::setDefaultConnection($previous);
    }
}
/** The schema as Laravel introspects it: tables, columns, indexes and foreign keys, without the schema name. */
function schemaOf(string $connection): array
{
    $schema = Schema::connection($connection);
    $out = [];
    foreach ($schema->getTables() as $table) {
        if (getenv('DB_CONNECTION') === 'pgsql' && ($table['schema'] ?? $connection) !== $connection) {
            continue;
        }
        $name = $table['name'];
        $out[$name] = [
            'columns' => array_map(fn (array $c): array => [$c['name'], $c['type_name'], $c['type'], $c['nullable'], $c['default'], $c['auto_increment']], $schema->getColumns($name)),
            'indexes' => array_map(fn (array $i): array => [$i['name'], $i['columns'], $i['unique'], $i['primary']], $schema->getIndexes($name)),
            'foreign' => array_map(fn (array $f): array => [$f['name'], $f['columns'], $f['foreign_table'], $f['foreign_columns'], $f['on_update'], $f['on_delete']], $schema->getForeignKeys($name)),
        ];
        sort($out[$name]['indexes']);
        sort($out[$name]['foreign']);
    }
    ksort($out);

    return $out;
}
/** The kit migrations a lineage put under database/migrations/kit, by basename. */
function kitMigrations(string $root): array
{
    $names = array_map('basename', glob($root.'/database/migrations/kit/*/*.php'));
    sort($names);

    return $names;
}
function hostTables(): void
{
    Schema::create('workspaces', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->boolean('accepts_members')->default(false);
        $t->timestamps();
    });
    (require dirname(__DIR__).'/vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
}

try {
    $lifecycle = new Lifecycle;
    $bindings = ['frontend' => 'SyntheticLayout'];
    $pgsql = getenv('DB_CONNECTION') === 'pgsql';
    $suffix = bin2hex(random_bytes(3));
    $v1 = catalog('0.1.0');
    $kit = new Lineages($lifecycle, $v1);
    $plainNames = array_map(fn (string $stub): string => basename($stub, '.stub'), [...glob(KIT.'/workspaces/migrations/*.stub'), ...glob(KIT.'/access/migrations/*.stub')]);
    sort($plainNames);

    // 1. The adopting host: its workspaces and spatie's tables exist where kit:add plans (production), and its own migrations are in the codebase.
    [$root, $paths, $app] = hostApp();
    hostTables();
    $stamp = Lineages::stamp($root);
    check($stamp > '2024_03_01_120001' && $stamp >= gmdate('Y_m_d').'_000000', 'the stamp sorts after every host migration and is no earlier than today ('.$stamp.')');
    $preview = $kit->preview($root, 'host', ['workspaces', 'access'], $bindings, $paths);
    check($preview['executable'] && count($preview['adoptions']) === 2, 'kit:add plans the adoption of the host\'s workspaces and permission tables, executable');
    check(count(array_filter($preview['host_steps'], fn (string $step): bool => str_contains($step, 'in your create_workspaces migration\'s down(), drop any audit_events foreign key on workspace_id before dropping the table'))) === 1, 'and lists the rollback host step: drop the kit\'s audit key in the host\'s create_workspaces down() first');
    $planned = array_merge(...array_map(fn (array $row): array => array_keys($row['files'] ?? []), $preview['features']));
    check(in_array('database/migrations/kit/workspaces/'.$stamp.'_000200_create_kit_workspaces_table.php', $planned, true) && in_array('database/migrations/kit/access/'.$stamp.'_000300_create_kit_access_tables.php', $planned, true) && preg_grep('#/0001_01_02_#', $planned) === [], 'the plan publishes every workspaces and access migration with the stamp, keeping the kit\'s order after it');
    check($kit->preview($root, 'host', ['workspaces', 'access'], $bindings, $paths)['plan'] === $preview['plan'], 'the stamp comes from the checkout, so a second preview has the same plan identity');
    $result = $kit->add($root, 'host', ['workspaces', 'access'], $bindings, $paths, $preview['plan'], null, require $root.'/verification/host-operations.txt');
    check($result['status'] === 'completed', 'the reviewed adopting plan applies');
    $adoptNames = kitMigrations($root);
    $expected = array_map(fn (string $name): string => str_replace('0001_01_02_', $stamp.'_', $name), $plainNames);
    sort($expected);
    check($adoptNames === $expected, 'the kit\'s migrations are on disk with the stamp ('.count($adoptNames).' files)');
    $record = Checkout::artifact($root, 'host', Lineages::name('workspaces'));
    check(($record?->metadata['adopt'] ?? null) === ['stamp' => $stamp, 'tables' => ['permission', 'workspaces']], 'the workspaces lineage\'s installed record holds the adoption: the stamp and the adopted tables');
    check((Checkout::artifact($root, 'host', Lineages::name('access'))?->metadata['adopt']['stamp'] ?? null) === $stamp, 'the access lineage\'s record holds its stamp, so its updates keep it');
    // The flags: null in config reads the record (FeaturesServiceProvider), and an app's own value wins.
    $config = $app->make('config');
    $config->set('kit.app_id', 'host');
    $config->set('kit.features', []);
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    $app->register(FeaturesServiceProvider::class, true);
    check($config->get('kit.workspaces.adopt') === true && $config->get('kit.access.adopt') === true, 'kit.workspaces.adopt and kit.access.adopt read true from the installed record');
    $config->set('kit.workspaces.adopt', false);
    $config->set('kit.access.adopt', null);
    $app->register(FeaturesServiceProvider::class, true);
    check($config->get('kit.workspaces.adopt') === false && $config->get('kit.access.adopt') === true, 'a value the app sets wins over the record');

    // The discovery cache (#939): `optimize` (kit:cache) stores what register() reads from the record, so a request opens none of it.
    // The fingerprint hashes every regular file under .mod-maintenance on each lookup, so a cached lookup opens them too, but only as whole
    // runs of exactly what fingerprint() opens; a live read opens records outside such a run.
    $readsRecord = function (array $opened) use ($root): bool {
        $record = array_values(preg_grep('#/\.mod-maintenance/#', $opened));
        $hashed = array_values(preg_grep('#/\.mod-maintenance/#', OpenedFiles::during(fn () => DiscoveryCache::fingerprint($root))));

        return $hashed === [] ? $record !== [] : array_filter(array_chunk($record, count($hashed)), fn (array $run): bool => $run !== $hashed) !== [];
    };
    $registerFresh = function () use ($app, $config): void {
        $config->set('kit.workspaces.adopt', null);
        $config->set('kit.access.adopt', null);
        $app->register(FeaturesServiceProvider::class, true);
    };
    $discovered = FeaturesServiceProvider::discover($root, 'host');
    check($discovered === ['lineages' => ['core', 'workspaces', 'access'], 'carry' => false, 'tables' => ['permission', 'workspaces']], 'discover() reads the adopted lineages and tables from the record');
    DiscoveryCache::clear($app);
    check($readsRecord(OpenedFiles::during($registerFresh)) && $config->get('kit.workspaces.adopt') === true, 'without the cache, register() reads the installed record (the recorder sees it)');
    check($app->make(Kernel::class)->call('kit:cache') === 0 && is_file(DiscoveryCache::path($app)), 'kit:cache writes bootstrap/cache/kit-features.php');
    DiscoveryCache::forget();
    $opened = OpenedFiles::during($registerFresh);
    check(! $readsRecord($opened) && $config->get('kit.workspaces.adopt') === true && $config->get('kit.access.adopt') === true, 'with the cache, register() reads no file of the installed record (it opens them only for the fingerprint\'s hash) and sets the same flags');
    $hashedFiles = array_map(fn (string $path): string => substr($path, strlen($root) + 1), array_values(preg_grep('#/\.mod-maintenance/#', $opened)));
    $expectedHashed = array_map(fn (string $path): string => substr($path, strlen($root) + 1), [...glob($root.'/.mod-maintenance/installed/*.json'), $root.'/.mod-maintenance/owner.json']);
    sort($hashedFiles);
    sort($expectedHashed);
    check($hashedFiles === $expectedHashed && glob($root.'/.mod-maintenance/journal-*.json') !== [] && is_file($root.'/.mod-maintenance/lock'), 'and it hashes each installed record and owner.json once ('.count($hashedFiles).' files: one lookup per register), never a journal or the lock');
    $config->set('kit.features', null);
    $features = fn (FeaturesServiceProvider $provider): array => (fn (): array => $this->features())->call($provider);
    DiscoveryCache::clear($app);
    $live = $features(new FeaturesServiceProvider($app));
    $app->make(Kernel::class)->call('kit:cache');
    DiscoveryCache::forget();
    $cachedOpened = OpenedFiles::during(function () use ($app, $features, &$cached): void {
        $cached = $features(new FeaturesServiceProvider($app));
    });
    check($live === ['core', 'workspaces', 'access'] && $cached === $live && ! $readsRecord($cachedOpened), 'with the lineages as the features, the cached read gives the live answer ('.implode(', ', $live).') and opens no record file');
    $config->set('kit.features', []);
    // Any change to the record is a miss, so a request reads it live, with the live read's verification.
    $records = glob($root.'/.mod-maintenance/installed/*.json');
    $bytes = file_get_contents($records[0]);
    file_put_contents($records[0], '{"not": "a record"}');
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === null, 'a changed record misses the cache');
    $registerFresh();
    check($config->get('kit.workspaces.adopt') === null && $config->get('kit.access.adopt') === null, 'and an unreadable record leaves the flags null, as without a cache (fail closed)');
    file_put_contents($records[0], $bytes);
    check(DiscoveryCache::entry($app, 'host') === null, 'restoring the bytes still misses (the restored file\'s mtime is newer than the fingerprint\'s), until kit:cache runs again');
    check($app->make(Kernel::class)->call('kit:cache') === 0 && DiscoveryCache::entry($app, 'host') !== null, 'kit:cache re-caches the restored record');
    // The root and its parents change whenever anything in them does (a log, a session, the home directory): that must not miss.
    sleep(1);
    file_put_contents($root.'/.kit-cache-churn', 'x');
    touch(dirname($root));
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') !== null, 'a new file in the app root, or a touched parent directory, still hits the cache (their times are not fingerprinted, only what they are)');
    @unlink($root.'/.kit-cache-churn');
    DiscoveryCache::forget();
    // A build image extracted or copied (Laravel Cloud runs optimize in the build): a new root directory, and every fingerprinted file a copy
    // with a new inode and ctime but the same mtime, size, mode and contents. The cache must still hit.
    $fingerprinted = array_map(fn (string $source): string => dirname(__DIR__).'/'.$source, ['composer.json', 'src/Catalog.php', 'src/Lineages.php', 'src/Features/FeaturesServiceProvider.php', 'src/Features/DiscoveryCache.php', 'resources/features/session-ui/feature.json']);
    @mkdir($root.'/vendor/composer', 0700, true);
    file_put_contents($root.'/vendor/composer/installed.json', '{"packages": []}');
    $fingerprinted[] = $root.'/vendor/composer/installed.json';
    $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/.mod-maintenance', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($tree as $file) {
        $file->isFile() && $fingerprinted[] = $file->getPathname();
    }
    check($app->make(Kernel::class)->call('kit:cache') === 0, 'kit:cache before the extraction');
    clearstatcache();
    $statsBefore = array_map('lstat', $fingerprinted);
    $directories = [];
    foreach ($tree as $directory) {
        $directory->isDir() && $directories[$directory->getPathname()] = [$directory->getMTime(), $directory->getPerms()];
    }
    $directories[$root.'/.mod-maintenance'] = [filemtime($root.'/.mod-maintenance'), fileperms($root.'/.mod-maintenance')];
    $rootBefore = lstat($root);
    sleep(1);
    rename($root, $root.'.build');
    mkdir($root);
    chmod($root, $rootBefore['mode'] & 07777);
    foreach (scandir($root.'.build') as $entry) {
        $entry === '.' || $entry === '..' || rename($root.'.build/'.$entry, $root.'/'.$entry);
    }
    rmdir($root.'.build');
    foreach ($fingerprinted as $i => $file) {
        copy($file, $file.'.extract');
        chmod($file.'.extract', $statsBefore[$i]['mode'] & 07777);
        rename($file.'.extract', $file);
        touch($file, $statsBefore[$i]['mtime']);
    }
    foreach ($directories as $directory => [$mtime, $perms]) {
        touch($directory, $mtime);
    }
    clearstatcache();
    $statsAfter = array_map('lstat', $fingerprinted);
    $copied = array_filter(array_keys($fingerprinted), fn (int $i): bool => $statsAfter[$i]['ino'] !== $statsBefore[$i]['ino'] && $statsAfter[$i]['ctime'] > $statsBefore[$i]['ctime']
        && [$statsAfter[$i]['size'], $statsAfter[$i]['mtime'], $statsAfter[$i]['mode']] === [$statsBefore[$i]['size'], $statsBefore[$i]['mtime'], $statsBefore[$i]['mode']]);
    check(count($copied) === count($fingerprinted) && lstat($root)['ino'] !== $rootBefore['ino'], 'the extraction gave all '.count($fingerprinted).' fingerprinted files a new inode and ctime (same size, mtime and mode), and the root a new inode');
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === $discovered, 'after the extraction the cache still hits, with the same answers');
    check(! $readsRecord(OpenedFiles::during($registerFresh)) && $config->get('kit.workspaces.adopt') === true, 'and register() still reads no record file');
    unlink($root.'/vendor/composer/installed.json');
    // What ctime used to catch: a same-size rewrite of a record that keeps its mtime. The per-request contents hash catches it.
    $app->make(Kernel::class)->call('kit:cache');
    $mtime = filemtime($records[0]);
    $mutated = preg_replace('/[a-f]/', 'x', $bytes, 1);
    file_put_contents($records[0], $mutated);
    touch($records[0], $mtime);
    clearstatcache();
    DiscoveryCache::forget();
    check($mutated !== $bytes && filesize($records[0]) === strlen($bytes) && filemtime($records[0]) === $mtime && DiscoveryCache::entry($app, 'host') === null, 'a same-size rewrite of a .mod-maintenance record that keeps its mtime is a miss');
    file_put_contents($records[0], $bytes);
    touch($records[0], $mtime);
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === $discovered, 'and restoring its bytes and mtime hits again (the fingerprint holds no ctime)');
    // Copilot's review of #940, one check each.
    $cacheFile = DiscoveryCache::path($app);
    $rewriteEntry = function (Closure $change) use ($cacheFile): void {
        $data = require $cacheFile;
        $data['entries']['host'] = $change($data['entries']['host']);
        file_put_contents($cacheFile, '<?php return '.var_export($data, true).';');
        DiscoveryCache::forget();
    };
    // 1. A record changed while kit:cache reads it: the read is repeated, so the cache never pairs a new fingerprint with old answers.
    $reads = 0;
    DiscoveryCache::write($app, 'host', function () use (&$reads, $records, $root): array {
        if (++$reads === 1) {
            file_put_contents($records[0], file_get_contents($records[0]));
            clearstatcache();

            return ['lineages' => ['core'], 'carry' => false, 'tables' => []];
        }

        return FeaturesServiceProvider::discover($root, 'host');
    });
    check($reads === 2 && DiscoveryCache::entry($app, 'host') === $discovered, 'a record changed during kit:cache\'s read is read again, and the cache holds the second read\'s answers');
    // 1b. A same-second, same-size rewrite of installed.json between the before and after states keeps its fingerprint (size and mtime
    // unchanged; installed.json is not hashed per request), so only the contents hash taken at build time catches it: the first read's
    // answers are never published.
    @mkdir($root.'/vendor/composer', 0700, true);
    $installedJson = $root.'/vendor/composer/installed.json';
    while (fmod(microtime(true), 1.0) > 0.3) {
        usleep(20_000);
    }
    file_put_contents($installedJson, '{"packages": [], "x": "A"}');
    clearstatcache();
    $statBefore = lstat($installedJson);
    $reads = 0;
    $published = [];
    DiscoveryCache::write($app, 'host', function () use (&$reads, &$statAfter, $installedJson, $root): array {
        if (++$reads === 1) {
            file_put_contents($installedJson, '{"packages": [], "x": "B"}');
            clearstatcache();
            $statAfter = lstat($installedJson);

            return ['lineages' => ['core'], 'carry' => false, 'tables' => []];
        }

        return FeaturesServiceProvider::discover($root, 'host');
    });
    $sameStat = [$statBefore['size'], $statBefore['mtime'], $statBefore['mode']] === [$statAfter['size'], $statAfter['mtime'], $statAfter['mode']];
    check($sameStat, 'the rewrite kept the fingerprint (same size, mtime and mode), so the check below rests on the contents hash');
    check($reads === 2 && DiscoveryCache::entry($app, 'host') === $discovered, 'a same-second, same-size rewrite during the read is caught by the contents hash: the read is repeated and the first read\'s answers are never published');
    unlink($installedJson);
    $app->make(Kernel::class)->call('kit:cache');
    // 2. A record that does not verify fails kit:cache (so optimize fails), and nothing is cached.
    file_put_contents($records[0], '{"not": "a record"}');
    check($app->make(Kernel::class)->call('kit:cache') === 1 && ! is_file($cacheFile), 'kit:cache fails (exit 1) on a record that does not verify, and writes nothing');
    file_put_contents($records[0], $bytes);
    check($app->make(Kernel::class)->call('kit:cache') === 0, 'and succeeds again once the record verifies');
    // 3. An entry not in the exact shape is a miss.
    foreach (['lineages holding an array' => ['lineages' => [['core']]], 'lineages keyed, not a list' => ['lineages' => ['a' => 'core']], 'a table that is not a string' => ['tables' => [1]], 'carry not a bool' => ['carry' => 1], 'tables missing' => ['tables' => '(drop)']] as $label => $change) {
        $app->make(Kernel::class)->call('kit:cache');
        $rewriteEntry(function (array $entry) use ($change): array {
            foreach ($change as $key => $value) {
                if ($value === '(drop)') {
                    unset($entry[$key]);
                } else {
                    $entry[$key] = $value;
                }
            }

            return $entry;
        });
        check(DiscoveryCache::entry($app, 'host') === null, 'an entry with '.$label.' is a miss');
    }
    // 4. The kit's sources of the answers are fingerprinted: session-ui's feature.json, and the host's vendor/composer/installed.json.
    $app->make(Kernel::class)->call('kit:cache');
    $manifest = dirname(__DIR__).'/resources/features/session-ui/feature.json';
    $manifestMtime = filemtime($manifest);
    touch($manifest, $manifestMtime + 1);
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === null, 'a changed session-ui feature.json (any kit update) is a miss');
    touch($manifest, $manifestMtime);
    @mkdir($root.'/vendor/composer', 0700, true);
    file_put_contents($root.'/vendor/composer/installed.json', '{"packages": []}');
    $app->make(Kernel::class)->call('kit:cache');
    file_put_contents($root.'/vendor/composer/installed.json', '{"packages": [], "dev": true}');
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === null, 'a changed vendor/composer/installed.json (any composer install or update) is a miss');
    // 4b. The rest of what the live read checks: owner.json, and any entry anywhere in the .mod-maintenance tree.
    $owner = $root.'/.mod-maintenance/owner.json';
    $ownerBytes = is_file($owner) ? file_get_contents($owner) : null;
    $app->make(Kernel::class)->call('kit:cache');
    file_put_contents($owner, $ownerBytes ?? '{"format": 1}');
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === null, 'a changed .mod-maintenance/owner.json is a miss'.($ownerBytes === null ? ' (created, as the fixture has none)' : ''));
    $ownerBytes === null ? unlink($owner) : file_put_contents($owner, $ownerBytes);
    $app->make(Kernel::class)->call('kit:cache');
    @mkdir($root.'/.mod-maintenance/stage', 0700);
    file_put_contents($root.'/.mod-maintenance/stage/entry', 'x');
    DiscoveryCache::forget();
    check(DiscoveryCache::entry($app, 'host') === null, 'a new entry deeper in the .mod-maintenance tree is a miss');
    unlink($root.'/.mod-maintenance/stage/entry');
    rmdir($root.'/.mod-maintenance/stage');
    // 5. A cached `tables: null` (the workspaces artifact unreadable) is kept: register() opens no record file and leaves the flags null.
    $app->make(Kernel::class)->call('kit:cache');
    $rewriteEntry(fn (array $entry): array => ['tables' => null] + $entry);
    $hit = DiscoveryCache::entry($app, 'host');
    check($hit !== null && $hit['tables'] === null, 'a cached null tables is a hit');
    $opened = OpenedFiles::during($registerFresh);
    check(! $readsRecord($opened) && $config->get('kit.workspaces.adopt') === null && $config->get('kit.access.adopt') === null, 'and register() keeps it: no record file opened, the flags stay null');
    $app->make(Kernel::class)->call('kit:cache');
    check((Illuminate\Support\ServiceProvider::$optimizeCommands['kit'] ?? null) === 'kit:cache' && (Illuminate\Support\ServiceProvider::$optimizeClearCommands['kit'] ?? null) === 'kit:clear', 'optimize runs kit:cache and optimize:clear runs kit:clear');
    check($app->make(Kernel::class)->call('kit:clear') === 0 && ! is_file(DiscoveryCache::path($app)) && DiscoveryCache::entry($app, 'host') === null, 'kit:clear removes the cache');
    $config->set('kit.workspaces.adopt', false);
    $config->set('kit.access.adopt', null);
    $config->set('kit.workspaces.adopt', true);

    // 2. Fresh database: the host's migrations and the kit's stamped ones, in one migrate.
    $fresh = freshConnection($app, 'kit_fresh_'.$suffix);
    $ran = migrate($app, $fresh, [$root.'/database/migrations', $root.'/database/migrations/kit/workspaces', $root.'/database/migrations/kit/access']);
    check(array_slice($ran, 0, 3) === ['0001_01_01_000100_create_audit_events_table', '2024_03_01_120000_create_workspaces_table', '2024_03_01_120001_create_permission_tables'] && array_slice($ran, 3) === array_map(fn (string $n): string => basename($n, '.php'), $adoptNames), 'migrate on a fresh database runs the host\'s migrations, then every kit migration ('.count($ran).' in all)');
    $auditKey = array_map(fn (string $n): string => $stamp.'_'.$n, ['000205_make_audit_events_workspace_id_nullable', '000206_null_orphaned_audit_events_workspace_ids', '000207_audit_events_workspace_id_null_on_delete', '000208_add_workspace_name_to_audit_events']);
    check(array_values(array_intersect($ran, $auditKey)) === $auditKey && Schema::connection($fresh)->hasColumn('audit_events', 'workspace_name'), 'the audit key migrations (000205-000208) are stamped and run on the fresh adopting database, leaving audit_events.workspace_name');
    DB::setDefaultConnection($fresh);
    check(Adoptions::adopted('workspaces') && Adoptions::added('workspaces') === ['provisioning_invitation_id', 'settings_version', 'admitted', 'slug', 'deleting_at', 'deleting_by'], 'the kit adopted the host\'s workspaces table on the fresh database, adding only its own columns');
    DB::setDefaultConnection('sqlite');

    // The production adoption, as before this fix: the host's tables first, then the kit's migrations under the catalog's names.
    $prod = freshConnection($app, 'kit_prod_'.$suffix);
    $plainDir = $root.'/../'.basename($root).'-plain';
    foreach (['workspaces', 'access'] as $id) {
        @mkdir($plainDir.'/'.$id, 0700, true);
        foreach (glob(KIT.'/'.$id.'/migrations/*.stub') as $stub) {
            copy($stub, $plainDir.'/'.$id.'/'.basename($stub, '.stub'));
        }
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($plainDir)));
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    migrate($app, $prod, [$root.'/database/migrations']);
    migrate($app, $prod, [$plainDir.'/workspaces', $plainDir.'/access']);
    $config->set('kit.workspaces.adopt', true);
    $config->set('kit.access.adopt', true);
    // migrate --pretend on the adopting host before its first kit migrate: the host's tables exist, the kit's migrations are pending.
    $pretend = freshConnection($app, 'kit_pretend_'.$suffix);
    migrate($app, $pretend, [$root.'/database/migrations']);
    $config->set('database.default', $pretend);
    DB::setDefaultConnection($pretend);
    $pretendOutput = new BufferedOutput;
    try {
        $migrator = $app->make('migrator');
        $migrator->setConnection($pretend);
        $migrator->getRepository()->setSource($pretend);
        $migrator->setOutput(new OutputStyle(new ArrayInput([]), $pretendOutput));
        $pretendError = refused(fn () => $migrator->run([$plainDir.'/workspaces', $plainDir.'/access'], ['pretend' => true]));
    } finally {
        $config->set('database.default', 'sqlite');
        DB::setDefaultConnection('sqlite');
    }
    $pretendSql = $pretendOutput->fetch();
    check($pretendError === null, 'migrate --pretend on an adopting host does not throw'.($pretendError === null ? '' : ' ('.$pretendError.')'));
    check(preg_match('/alter table "?workspaces"? add column "?provisioning_invitation_id"?/i', $pretendSql) === 1 && str_contains($pretendSql, 'kit_adoptions') && ! str_contains($pretendSql, 'create table "workspaces"') && ! str_contains($pretendSql, 'create table "roles"'), 'and it lists the adoption\'s statements (columns added to the host\'s workspaces, the adoption record) without creating the host\'s tables');
    check(! Schema::connection($pretend)->hasColumn('workspaces', 'provisioning_invitation_id') && ! Schema::connection($pretend)->hasTable('kit_adoptions'), 'and it changes nothing');
    $freshSchema = schemaOf($fresh);
    $prodSchema = schemaOf($prod);
    check($freshSchema === $prodSchema, 'the fresh adopting schema equals the production adoption: '.count($freshSchema).' tables, their columns, indexes and foreign keys');
    check(isset($freshSchema['entitlements'], $freshSchema['complimentary_grants'], $freshSchema['kit_entitlements'], $freshSchema['kit_capability_overrides'], $freshSchema['kit_adoptions']) && in_array(['workspace_id'], array_column($freshSchema['audit_events']['foreign'], 1), true), 'and it has the access tables, the adoption record and the audit_events foreign key to the host\'s workspaces');
    // The bug this fixes: the catalog's names on a fresh adopting database run before the host's own migration, which then fails.
    $broken = freshConnection($app, 'kit_plain_'.$suffix);
    $config->set('kit.workspaces.adopt', null);
    $config->set('kit.access.adopt', null);
    check(str_contains((string) refused(fn () => migrate($app, $broken, [$root.'/database/migrations', $plainDir.'/workspaces', $plainDir.'/access'])), 'already exists'), 'with the catalog\'s names, a fresh adopting migrate fails (the kit creates workspaces before the host does)');
    // With the flag on, the kit's workspaces migration never creates the table, and says why.
    $config->set('kit.workspaces.adopt', true);
    $config->set('kit.access.adopt', true);
    $empty = freshConnection($app, 'kit_plain_'.$suffix);
    DB::setDefaultConnection($empty);
    try {
        check(str_contains((string) refused(fn () => (require $plainDir.'/workspaces/0001_01_02_000200_create_kit_workspaces_table.php')->up()), 'kit.workspaces.adopt is on') && ! Schema::hasTable('workspaces'), 'with kit.workspaces.adopt on and no host table yet, the kit\'s migration refuses and creates nothing');
        check(str_contains((string) refused(fn () => (require $plainDir.'/workspaces/0001_01_02_000201_create_permission_tables.php')->up()), 'kit.access.adopt is on') && ! Schema::hasTable('roles'), 'with kit.access.adopt on and no host permission tables yet, the permission migration refuses and creates nothing');
    } finally {
        DB::setDefaultConnection('sqlite');
    }

    // 3. A later release adds a workspaces migration: the adopting install keeps every name, and the new one takes the install's stamp.
    $config->set('kit.features', null);
    $config->set('kit.app_id', null);
    $added = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('kit_fresh_markers', function (Blueprint \$t): void {\n            \$t->id();\n            \$t->foreignId('workspace_id')->constrained();\n        });\n    }\n};\n";
    $kit2 = new Lineages($lifecycle, catalog('0.2.0', ['0001_01_02_000299_create_kit_fresh_markers_table.php' => $added]));
    // Core first: a feature's requires must be at the release before it updates.
    $core = $kit2->updatePlan($root, 'host', 'core', $bindings, $paths);
    check($kit2->updateApply($core, 'core', $paths, 'adopt-fresh-core', require $root.'/verification/host-operations.txt')['status'] === 'completed', 'core updates to the release first');
    $plan = $kit2->updatePlan($root, 'host', 'workspaces', $bindings, $paths);
    $created = array_keys(array_filter($plan->files, fn (array $file): bool => $file['state'] === 'create'));
    check($created === ['database/migrations/kit/workspaces/'.$stamp.'_000299_create_kit_fresh_markers_table.php'], 'kit:update plans only the new migration, under the install\'s stamp');
    $update = $kit2->updateApply($plan, 'workspaces', $paths, 'adopt-fresh-update', require $root.'/verification/host-operations.txt');
    check($update['status'] === 'completed' && array_diff($adoptNames, kitMigrations($root)) === [], 'the update applies and renames nothing');
    check((Checkout::artifact($root, 'host', Lineages::name('workspaces'))?->metadata['adopt'] ?? null) === ['stamp' => $stamp, 'tables' => ['permission', 'workspaces']], 'the updated record keeps the adoption');
    check(migrate($app, $fresh, [$root.'/database/migrations', $root.'/database/migrations/kit/workspaces', $root.'/database/migrations/kit/access']) === [$stamp.'_000299_create_kit_fresh_markers_table'], 'migrate after the update runs only the new migration; nothing is re-run');

    // 4. A non-adopting host: no host tables where kit:add plans; the catalog's names, and the kit creates its tables.
    [$plainRoot, $plainPaths, $plainApp] = hostApp();
    foreach (glob($plainRoot.'/database/migrations/2024_*') as $file) {
        unlink($file);
    }
    $preview = $kit->preview($plainRoot, 'host', ['workspaces', 'access'], $bindings, $plainPaths);
    check($preview['executable'] && $preview['adoptions'] === [], 'a host without its own tables plans no adoption');
    check(array_filter($preview['host_steps'], fn (string $step): bool => str_contains($step, 'create_workspaces migration')) === [], 'and no rollback host step');
    check($kit->add($plainRoot, 'host', ['workspaces', 'access'], $bindings, $plainPaths, $preview['plan'], null, require $plainRoot.'/verification/host-operations.txt')['status'] === 'completed', 'its plan applies');
    $plainInstalled = kitMigrations($plainRoot);
    check($plainInstalled === $plainNames, 'its migrations keep the catalog\'s names (0001_01_02_*)');
    check(! array_key_exists('adopt', Checkout::artifact($plainRoot, 'host', Lineages::name('workspaces'))?->metadata ?? ['adopt' => true]), 'its installed record holds no adoption');
    $plainConfig = $plainApp->make('config');
    $plainConfig->set('kit.app_id', 'host');
    $plainConfig->set('kit.features', []);
    $plainApp->register(FeaturesServiceProvider::class, true);
    check($plainConfig->get('kit.workspaces.adopt') === null && $plainConfig->get('kit.access.adopt') === null, 'and the adopt flags stay null');
    $own = freshConnection($plainApp, 'kit_plain_'.$suffix);
    $ran = migrate($plainApp, $own, [$plainRoot.'/database/migrations', $plainRoot.'/database/migrations/kit/workspaces', $plainRoot.'/database/migrations/kit/access']);
    check(count($ran) === count($plainNames) + 1 && Schema::connection($own)->hasTable('workspaces') && Schema::connection($own)->hasTable('roles') && ! Schema::connection($own)->hasTable(Adoptions::TABLE), 'on a fresh database the kit creates the workspaces and permission tables, and records no adoption');

    // 5. An existing install keeps its names on kit:update, even with the tables in the database now, and re-runs nothing.
    $plainConfig->set('kit.features', null);
    $plainConfig->set('kit.app_id', null);
    DB::setDefaultConnection($own);
    try {
        $kit2->updateApply($kit2->updatePlan($plainRoot, 'host', 'core', $bindings, $plainPaths), 'core', $plainPaths, 'adopt-fresh-plain-core', require $plainRoot.'/verification/host-operations.txt');
        $plan = $kit2->updatePlan($plainRoot, 'host', 'workspaces', $bindings, $plainPaths);
    } finally {
        DB::setDefaultConnection('sqlite');
    }
    $created = array_keys(array_filter($plan->files, fn (array $file): bool => $file['state'] === 'create'));
    check($created === ['database/migrations/kit/workspaces/0001_01_02_000299_create_kit_fresh_markers_table.php'], 'kit:update on an existing install plans the new migration under the catalog\'s name');
    check($kit2->updateApply($plan, 'workspaces', $plainPaths, 'adopt-fresh-plain-update', require $plainRoot.'/verification/host-operations.txt')['status'] === 'completed' && array_diff($plainInstalled, kitMigrations($plainRoot)) === [], 'the update applies and renames no installed migration');
    check(migrate($plainApp, $own, [$plainRoot.'/database/migrations', $plainRoot.'/database/migrations/kit/workspaces', $plainRoot.'/database/migrations/kit/access']) === ['0001_01_02_000299_create_kit_fresh_markers_table'], 'migrate after the update runs only the new migration');

    if ($pgsql) {
        foreach (['kit_fresh_', 'kit_prod_', 'kit_plain_'] as $prefix) {
            DB::connection('pg_admin')->statement('drop schema if exists '.$prefix.$suffix.' cascade');
        }
    }
    echo 'Fresh-database adoption on '.($pgsql ? 'PostgreSQL' : 'SQLite memory').' with a synthetic catalog copy (the real workspaces and access migrations) and synthetic host migrations; kit:add and kit:update through Lineages, not the artisan commands; no MySQL run.'."\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().' in '.$error->getFile().':'.$error->getLine()."\n");
    exit(1);
}
