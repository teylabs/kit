<?php

// kit:query-report, the on-demand per-page query report (the command itself; no app's query counts are checked here). It finds the pages the app declares
// (WorkspaceRoutes::page's full form, PersonalPages::page) and the routes --route matches; reports each page's status, queries and exact duplicates, with
// the median of --repeat runs; rolls back what a page writes, in the request and in its terminating callbacks, on every connection; refuses production
// without --force; lists pages it can't fill as skipped and reports a failing page without stopping. SQLite memory, real Concierge, synthetic people.
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Inertia\ServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Monolog\Handler\TestHandler;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Concierge;
use Tey\Concierge\ConciergeServiceProvider;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\QueryReportCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\WorkspaceRoutes;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class ReportUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(ReportUser::class, 'App\Models\User');

function reportCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $base = sys_get_temp_dir().'/kit-query-report-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('env', 'local');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'local'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => ReportUser::class]]],
        // A file session, a database cache and a file store laravel-data names itself: the report makes each an array store, so none is written.
        'cache' => ['default' => 'database', 'stores' => ['database' => ['driver' => 'database', 'connection' => null, 'table' => 'cache', 'lock_connection' => null, 'lock_table' => null], 'file' => ['driver' => 'file', 'path' => $base.'/storage/cache'], 'array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'file', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage/sessions'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        // A second connection (its own memory database): the report rolls back each connection a page touches, not only the default.
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false], 'other' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'queue' => ['default' => 'database', 'connections' => ['database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]], 'failed' => ['driver' => 'null']],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'kit@example.test', 'name' => 'Kit']],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'workspace-ui'], 'models' => ['user' => ReportUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => TestHandler::class]]],
    ]));
    mkdir($base.'/storage/sessions', 0777, true);
    $app->instance('request', Request::create('https://kit.test'));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([
        EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class,
        DatabaseServiceProvider::class, RoutingServiceProvider::class, AuthServiceProvider::class,
        SessionServiceProvider::class, ViewServiceProvider::class, TranslationServiceProvider::class,
        ValidationServiceProvider::class, EncryptionServiceProvider::class, CookieServiceProvider::class,
        BusServiceProvider::class, HashServiceProvider::class, PipelineServiceProvider::class, QueueServiceProvider::class, MailServiceProvider::class, NotificationServiceProvider::class,
        ModServiceProvider::class, PermissionServiceProvider::class, MultitenancyServiceProvider::class, LaravelSettingsServiceProvider::class, LaravelDataServiceProvider::class,
        PennantServiceProvider::class, FeaturesServiceProvider::class, ConciergeServiceProvider::class, PassportServiceProvider::class, FortifyServiceProvider::class, ServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    $app->make(Kernel::class)->setMiddlewareGroups(['web' => [StartSession::class, HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Authenticate::class);
    $app['router']->aliasMiddleware('throttle', ThrottleRequests::class);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    Schema::create('cache', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    $stubs = [];
    foreach (glob(dirname(__DIR__).'/database/migrations/*/*.php') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach ($stubs as $name => $file) {
        (require $file)->up();
        if (str_contains($name, '000200_create_kit_workspaces_table')) {
            foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $migration) {
                (require $migration)->up();
            }
        }
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach (['testing', 'other'] as $connection) {
        Schema::connection($connection)->create('fixture_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::connection($connection)->table('fixture_rows')->insert([['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);
    }

    // The fixture pages, declared as an app declares its own, plus one plain GET route no page declaration names.
    $hits = 0;
    $seen = [];
    $growing = 0;
    Route::middleware('web')->group(function () use (&$hits, &$seen, &$growing): void {
        WorkspaceRoutes::define(function (bool $full) use (&$hits, &$seen, &$growing): void {
            WorkspaceRoutes::page(Route::get('fixture/empty', fn () => ['ok' => true]), $full, 'session-ui', 'fixture.empty', 'Empty');
            // Three reads of one row (the same SQL and bindings: two exact duplicates), then three reads of three rows (one statement, no duplicate).
            WorkspaceRoutes::page(Route::get('fixture/duplicates', function () use (&$hits): array {
                $hits++;
                foreach ([1, 1, 1] as $id) {
                    DB::table('fixture_rows')->where('id', $id)->first();
                }
                foreach (['a', 'b', 'c'] as $name) {
                    DB::table('fixture_rows')->where('name', $name)->first();
                }

                return ['ok' => true];
            }), $full, 'session-ui', 'fixture.duplicates', 'Duplicates');
            // Writes on both connections, in a transaction of its own and outside one, and again after the response, in a terminating callback.
            WorkspaceRoutes::page(Route::get('fixture/write', function () use (&$seen): array {
                DB::table('fixture_rows')->insert(['name' => 'written']);
                DB::transaction(fn () => DB::table('fixture_rows')->where('id', 1)->update(['name' => 'changed']));
                DB::connection('other')->table('fixture_rows')->delete();
                app()->terminating(fn () => DB::table('fixture_rows')->insert(['name' => 'after the response']));
                $seen[] = [DB::table('fixture_rows')->count(), DB::connection('other')->table('fixture_rows')->count()];

                return ['ok' => true];
            }), $full, 'session-ui', 'fixture.write', 'Write');
            // n² distinct queries on the n-th request: 1 to warm up, then 4, 9 and 16, whose median (9) is not their mean.
            WorkspaceRoutes::page(Route::get('fixture/growing', function () use (&$growing): array {
                $growing++;
                for ($i = 0; $i < $growing ** 2; $i++) {
                    DB::select('select ? as n', [$i]);
                }

                return ['ok' => true];
            }), $full, 'session-ui', 'fixture.growing', 'Growing');
            WorkspaceRoutes::page(Route::get('fixture/broken', fn () => throw new RuntimeException('fixture_broken')), $full, 'session-ui', 'fixture.broken', 'Broken');
            WorkspaceRoutes::page(Route::get('fixture/things/{thing}', fn (string $workspace, string $thing) => ['thing' => $thing]), $full, 'session-ui', 'fixture.things.show', 'Thing');
        });
        Route::get('fixture/plain', fn () => 'plain')->name('fixture.plain');
    });
    $app['router']->getRoutes()->refreshNameLookups();
    $app->bootstrapWith([]);

    $app['config']->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    $registry = $app->make(Registry::class);
    $spaces = [];
    foreach (['acme' => 'Acme', 'globex' => 'Globex'] as $slug => $name) {
        $spaces[$slug] = Workspace::create(['name' => $name, 'slug' => $slug]);
        $spaces[$slug]->execute(function () use ($registry, $spaces, $slug): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($spaces[$slug]);
            }
        });
    }
    // Ada owns Acme and is an admin of Globex; Grace is a member of Acme only.
    $people = [];
    foreach (['ada' => ['acme' => 'owner', 'globex' => 'admin'], 'grace' => ['acme' => 'member']] as $name => $roles) {
        $people[$name] = ReportUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        foreach ($roles as $slug => $role) {
            ConciergeModels::membership()::query()->create(['workspace_id' => $spaces[$slug]->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
            app(Concierge::class)->context()->run($spaces[$slug], fn () => $people[$name]->assignRole($role), [$people[$name]]);
        }
    }

    $report = function (array $options = []) use ($app): array {
        $command = new QueryReportCommand;
        $command->setLaravel($app);
        $output = new BufferedOutput;
        $exit = $command->run(new ArrayInput($options), $output);

        return [$exit, $output->fetch()];
    };
    $json = function (array $options = []) use ($report): array {
        [$exit, $output] = $report(['--json' => true] + $options);
        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('--json printed no JSON (exit '.$exit.'): '.substr($output, 0, 2000));
        }

        return [$exit, $decoded];
    };
    $byRoute = fn (array $pages): array => array_column($pages, null, 'route');
    $rows = fn (string $connection): array => DB::connection($connection)->table('fixture_rows')->orderBy('id')->pluck('name')->all();
    $writes = fn (): array => ['rows' => $rows('testing'), 'other' => $rows('other'), 'cache' => DB::table('cache')->count(), 'jobs' => DB::table('jobs')->count(), 'sessions' => count(glob($app->storagePath('sessions').'/*')), 'files' => count(glob($app->storagePath('cache').'/*'))];
    $before = $writes();

    $artisan = new Illuminate\Console\Application($app, $app['events'], 'check');
    reportCheck($artisan->has('kit:query-report') && $artisan->get('kit:query-report') instanceof QueryReportCommand, 'the kit registers kit:query-report with artisan');

    // Production: refused without --force, before any page is requested.
    $app->instance('env', 'production');
    [$refusedExit, $refused] = $report(['--json' => true]);
    $hitsRefused = $hits;
    [$forcedExit, $forced] = $json(['--force' => true]);
    $app->instance('env', 'local');
    reportCheck($refusedExit === 1 && str_contains($refused, 'Refusing to run in production') && $hitsRefused === 0, 'in production it refuses without --force (exit 1) and requests no page');
    reportCheck($forcedExit === 0 && isset($byRoute($forced['pages'])['fixture.duplicates']) && $hits > 0, 'in production with --force it runs');

    // The pages: what the app declares, in the full form only, and the routes --route adds.
    $growing = 0;
    [$exit, $all] = $json(['--route' => ['fixture.plain']]);
    $pages = $byRoute($all['pages']);
    $skipped = $byRoute($all['skipped']);
    reportCheck($exit === 0 && $all['user'] === $people['ada']->id && $all['workspace'] === 'acme', 'by default it signs in as the first active member, Ada, in her first workspace, Acme');
    foreach (['workspaces.dashboard.index' => '/@acme', 'workspaces.settings.general' => '/@acme/settings/general', 'me.profile' => '/me/profile', 'fixture.duplicates' => '/@acme/fixture/duplicates'] as $route => $path) {
        reportCheck(($pages[$route]['path'] ?? null) === $path, 'it finds the declared page '.$route.' at '.$path.' ('.json_encode($pages[$route]['path'] ?? null).')');
    }
    reportCheck(array_filter(array_keys($pages + $skipped), fn (?string $route): bool => str_starts_with((string) $route, 'short.')) === [], 'it requests no short form: that is the same page under the session\'s workspace');
    [, $undeclared] = $json();
    reportCheck(isset($pages['fixture.plain']) && ! isset($byRoute($undeclared['pages'])['fixture.plain']), 'an undeclared GET route is requested only when --route matches it');
    reportCheck(($pages['fixture.plain']['status'] ?? null) === 200 && $pages['fixture.plain']['duplicates'] === 0, 'the matched route is reported like a page');

    // Counts and duplicates, against the empty fixture page (the same shell and middleware, no queries of its own).
    $empty = $pages['fixture.empty'];
    $duplicates = $pages['fixture.duplicates'];
    $repeated = array_column(array_map(fn (array $statement): array => $statement + ['key' => $statement['sql']], $duplicates['repeated']), null, 'key');
    reportCheck($empty['status'] === 200 && $duplicates['status'] === 200 && $duplicates['queries'] - $empty['queries'] === 6, 'the fixture page runs 6 queries more than the empty page ('.$duplicates['queries'].' and '.$empty['queries'].')');
    reportCheck($duplicates['duplicates'] - $empty['duplicates'] === 2, 'of them 2 are exact duplicates: the same row read three times; three rows read once each are none ('.$duplicates['duplicates'].' and '.$empty['duplicates'].')');
    reportCheck(($repeated['select * from "fixture_rows" where "id" = ? limit 1'] ?? null) === ['sql' => 'select * from "fixture_rows" where "id" = ? limit 1', 'connection' => 'testing', 'count' => 3, 'duplicates' => 2, 'key' => 'select * from "fixture_rows" where "id" = ? limit 1']
        && ($repeated['select * from "fixture_rows" where "name" = ? limit 1']['count'] ?? null) === 3 && $repeated['select * from "fixture_rows" where "name" = ? limit 1']['duplicates'] === 0, 'its repeated statements are listed with their counts: the row read ×3 (2 exact), the name lookup ×3 (0 exact)');
    reportCheck($duplicates['repeated'][0]['count'] >= $duplicates['repeated'][array_key_last($duplicates['repeated'])]['count'] && is_numeric($duplicates['time_ms']) && $duplicates['time_ms'] > 0, 'repeated statements come most repeated first, and the page has its time');

    // --repeat: the median of the runs, after one warm-up request that is not counted.
    $growing = 0;
    [, $repeatedRuns] = $json(['--repeat' => '3']);
    $runs = $byRoute($repeatedRuns['pages']);
    reportCheck($repeatedRuns['repeat'] === 3 && $runs['fixture.growing']['queries'] - $runs['fixture.empty']['queries'] === 9, '--repeat=3 reports the median of three runs after a warm-up: 4, 9 and 16 queries give 9, not their mean ('.($runs['fixture.growing']['queries'] - $runs['fixture.empty']['queries']).' more than the empty page)');
    [$badRepeat] = $report(['--repeat' => '0']);
    reportCheck($badRepeat === 1, '--repeat=0 is refused');

    // Writes: the page saw its own writes, and none is left on either connection, in the cache, the queue or the session files.
    reportCheck($seen !== [] && $seen[0] === [4, 0], 'the write page saw its writes inside its request ('.json_encode($seen[0] ?? null).')');
    reportCheck($writes() === $before && $before['rows'] === ['a', 'b', 'c'] && $before['other'] === ['a', 'b', 'c'], 'every write is rolled back: the inserted, updated and deleted rows on both connections, the terminating callback\'s insert, and no cache entry (database or file store), job or session written ('.json_encode($writes()).')');
    reportCheck(DB::connection()->transactionLevel() === 0 && DB::connection('other')->transactionLevel() === 0, 'and no transaction is left open');

    // The report measures what a page costs without it: reads run outside any transaction until the page writes. Inside one, the kit runs each dashboard
    // widget in a savepoint, and a widget refused to the person rolls its savepoint back, which empties the request memo: an open transaction alone would
    // add queries the page never runs. The request outside runs on array cache stores and an array session, as the report's do.
    $app['config']->set(['cache.stores' => ['database' => ['driver' => 'array'], 'file' => ['driver' => 'array'], 'array' => ['driver' => 'array']], 'session.driver' => 'array']);
    $app['cache']->forgetDriver(['database', 'file', 'array']);
    $log = null;
    DB::listen(function ($query) use (&$log): void {
        if ($log !== null) {
            $log[] = $query->sql.' '.json_encode($query->bindings);
        }
    });
    $outside = function (string $path) use ($app, $people, &$log): array {
        $request = Request::create('https://kit.test'.$path, 'GET', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $app->forgetScopedInstances();
        Once::flush();
        $app['auth']->forgetGuards();
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        $app->instance('request', $request);
        $log = [];
        try {
            $app['auth']->guard('web')->onceUsingId($people['ada']->id);
            $response = $app->make(Kernel::class)->handle($request);
            $app->make(Kernel::class)->terminate($request, $response);
        } finally {
            [$queries, $log] = [$log, null];
        }

        return [$response->getStatusCode(), count($queries), count($queries) - count(array_unique($queries))];
    };
    $outside('/@acme');
    $dashboard = $pages['workspaces.dashboard.index'];
    reportCheck($outside('/@acme') === [200, $dashboard['queries'], $dashboard['duplicates']], 'the dashboard counts the same in the report as in a request outside it: '.$dashboard['queries'].' queries, '.$dashboard['duplicates'].' duplicates ('.json_encode($outside('/@acme')).')');

    // Skipped and failing pages.
    reportCheck(($skipped['fixture.things.show']['reason'] ?? null) === 'no value for {thing}' && $skipped['fixture.things.show']['path'] === '/@{workspace}/fixture/things/{thing}' && ! isset($pages['fixture.things.show']), 'a page whose parameter it can\'t fill is listed as skipped, with the reason');
    reportCheck(($pages['fixture.broken']['status'] ?? null) === 500 && isset($pages['workspaces.dashboard.index']), 'a page that fails is reported with its status (500), and the run carries on');

    // The summary, recomputed from the pages it reports.
    $ok = array_values(array_filter($all['pages'], fn (array $page): bool => $page['status'] === 200));
    $counts = array_column($ok, 'queries');
    sort($counts);
    $median = count($counts) % 2 === 1 ? $counts[intdiv(count($counts), 2)] : ($counts[count($counts) / 2 - 1] + $counts[count($counts) / 2]) / 2;
    $worst = array_reduce($ok, fn (?array $carry, array $page): array => $carry === null || $page['queries'] > $carry['queries'] ? $page : $carry);
    reportCheck($all['summary'] === ['pages' => count($all['pages']), 'ok' => count($ok), 'skipped' => count($all['skipped']), 'median_queries' => $median, 'worst_queries' => $worst['queries'], 'worst_page' => ['route' => $worst['route'], 'path' => $worst['path']], 'duplicate_floor' => min(array_column($ok, 'duplicates'))]
        && $all['summary']['ok'] < $all['summary']['pages'] && $all['summary']['duplicate_floor'] === 0, 'the summary counts the pages and the skipped ones, and over the 200s gives the median and worst query counts, the worst page and the duplicate floor ('.json_encode($all['summary']).')');

    // --only-duplicates, the other people and the text report.
    $growing = 0;
    [, $onlyDuplicates] = $json(['--only-duplicates' => true, '--route' => ['fixture.plain']]);
    $shown = $byRoute($onlyDuplicates['pages']);
    reportCheck(isset($shown['fixture.duplicates']) && ! isset($shown['fixture.plain']) && array_filter($onlyDuplicates['pages'], fn (array $page): bool => $page['duplicates'] === 0) === [] && $onlyDuplicates['skipped'] === [] && $onlyDuplicates['summary'] === $all['summary'], '--only-duplicates lists only the pages with exact duplicates; the summary still covers every page');
    [, $grace] = $json(['--user' => 'grace@example.test']);
    [$notMember, $notMemberOutput] = $report(['--user' => 'grace@example.test', '--workspace' => 'globex']);
    [$unknown] = $report(['--user' => 'nobody@example.test']);
    reportCheck($grace['user'] === $people['grace']->id && $grace['workspace'] === 'acme', '--user takes an email: Grace, in her only workspace');
    reportCheck($notMember === 1 && str_contains($notMemberOutput, 'not an active member of workspace globex') && $unknown === 1, 'a person who is not a member of --workspace, or no such person, is refused (exit 1)');
    [, $globex] = $json(['--workspace' => (string) $spaces['globex']->id]);
    reportCheck($globex['workspace'] === 'globex' && $globex['user'] === $people['ada']->id && ($byRoute($globex['pages'])['fixture.empty']['path'] ?? null) === '/@globex/fixture/empty', '--workspace takes an id: its first active member, with its pages under its slug');
    [$textExit, $text] = $report();
    reportCheck($textExit === 0 && str_contains($text, 'GET /@acme/fixture/duplicates  fixture.duplicates') && str_contains($text, '3×   select * from "fixture_rows" where "id" = ? limit 1  (2 exact)')
        && str_contains($text, "Skipped\n") && str_contains($text, 'GET /@{workspace}/fixture/things/{thing}  fixture.things.show: no value for {thing}') && preg_match('/\n\d+ pages \(\d+ answered 200\), \d+ skipped\. Over the 200s: median [\d.]+ queries, worst \d+ \(.+\), duplicate floor \d+ per page\.\n$/', $text) === 1, 'the text report lists each page, its repeated statements, the skipped pages and the summary line');
} catch (Throwable $error) {
    echo 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n";
    exit(1);
}
echo "kit:query-report checks on SQLite memory (two connections) with real Concierge, spatie teams, Pennant and settings, through the HTTP kernel. Not covered: PostgreSQL with RLS, MySQL (whose schema changes commit), a real artisan process and consumer apps' own pages.\n";
