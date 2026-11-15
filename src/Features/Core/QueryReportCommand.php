<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Throwable;
use WeakMap;

/**
 * What each page costs in queries, on demand: every page the app declares (`WorkspaceRoutes::page`'s full form and `PersonalPages::page`), plus the GET routes
 * `--route` matches, requested in-process through the HTTP kernel as one signed-in person in one workspace. Each request runs inside a transaction on each
 * connection it writes to, rolled back after it, with the session and cache on array stores and mail, notifications and the queue faked: it writes nothing
 * and seeds nothing, so it reports on whatever data the configured database holds. Information, not a gate: a skipped or failing page is reported, and
 * only a refusal (production without --force, an unknown user or workspace) exits non-zero.
 */
final class QueryReportCommand extends Command
{
    /** Repeated statements listed per page in the text report; --json lists them all. */
    private const TOP = 5;

    protected $signature = 'kit:query-report
        {--route=* : Also request the GET routes whose name or URI matches (a name, or a pattern such as notes.* or reports/*)}
        {--user= : Sign in as this user, by id or email (default: the first active member)}
        {--workspace= : Open workspace pages in this workspace, by id or slug (default: the user\'s first)}
        {--repeat=1 : Request each page this many times and report the median}
        {--only-duplicates : List only the pages that run an exact duplicate query}
        {--json : Print the report as JSON}
        {--force : Run in production (each request is still rolled back)}';

    protected $description = 'Request each page in-process, rolled back, and report its queries and exact duplicates (information, not a gate)';

    /** @var ?list<array{0: string, 1: string, 2: string, 3: float}> the queries of the request being measured: connection, SQL, bindings, milliseconds */
    private ?array $log = null;

    /** @var ?array<string, Connection> while a request is measured, the connections it opened a transaction on; null between requests */
    private ?array $opened = null;

    /** @var WeakMap<Connection, true> the connections that open a transaction before a request's first write */
    private WeakMap $watched;

    /** The asset version the app's Inertia middleware answers with, sent on every later request so none is answered with a 409. */
    private ?string $version = null;

    public function handle(): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to run in production: it requests every page against the production database. Each request is rolled back; pass --force to run it anyway.');

            return self::FAILURE;
        }
        $repeat = filter_var($this->option('repeat'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($repeat === false) {
            $this->error('--repeat takes a whole number, 1 or more.');

            return self::FAILURE;
        }
        try {
            [$user, $workspace] = $this->signIn();
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        [$pages, $skipped] = $this->pages($workspace === null ? null : (string) $workspace->getAttribute('slug'));
        $measured = $this->isolated(function () use ($pages, $user, $repeat): array {
            $measured = [];
            foreach ($pages as $page) {
                // One request first, not counted: what a process builds on its first request (a cold cache, a catalog) is not the page's own cost.
                $this->request($page['url'], $user);
                $runs = [];
                for ($run = 0; $run < $repeat; $run++) {
                    $runs[] = $this->request($page['url'], $user);
                }
                unset($page['url']);
                $measured[] = $page + $this->measure($runs);
            }

            return $measured;
        });
        $summary = $this->summary($measured, count($skipped));
        if ($this->option('only-duplicates')) {
            $measured = array_values(array_filter($measured, fn (array $page): bool => $page['duplicates'] > 0));
            $skipped = [];
        }
        $report = ['user' => $user->getAuthIdentifier(), 'workspace' => $workspace?->getAttribute('slug'), 'repeat' => $repeat, 'pages' => $measured, 'skipped' => $skipped, 'summary' => $summary];
        if ($this->option('json')) {
            // Raw: a statement's `<` is never read as a console style tag.
            $this->output->writeln((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
        } else {
            $this->text($report);
        }

        return self::SUCCESS;
    }

    /**
     * The person to sign in as and the workspace to open: the ones given, else the first active membership that fits (the oldest), else the first user.
     *
     * @return array{0: Authenticatable&Model, 1: ?Model}
     */
    private function signIn(): array
    {
        $provider = $this->laravel['auth']->guard('web')->getProvider();
        $given = $this->option('user');
        $user = match (true) {
            $given === null => null,
            str_contains((string) $given, '@') => $provider->retrieveByCredentials(['email' => (string) $given]),
            default => $provider->retrieveById($given),
        };
        if ($given !== null && $user === null) {
            throw new RuntimeException('No user '.$given.'.');
        }
        $workspace = null;
        $slug = $this->option('workspace');
        if ($slug !== null && ! Kit::enabled('workspaces')) {
            throw new RuntimeException('--workspace needs the workspaces feature, which is off.');
        }
        if ($slug !== null) {
            $slug = (string) $slug;
            $workspace = ConciergeModels::workspace()::query()->where(fn ($query) => ctype_digit($slug) ? $query->whereKey($slug)->orWhere('slug', $slug) : $query->where('slug', $slug))->first()
                ?? throw new RuntimeException('No workspace '.$slug.'.');
        }
        if (Kit::enabled('workspaces')) {
            $model = ConciergeModels::membership();
            $memberships = $model::query()->where('status', MembershipStatus::Active)
                ->when($user !== null, fn ($query) => $query->where('user_id', $user->getAuthIdentifier()))
                ->when($workspace !== null, fn ($query) => $query->where('workspace_id', $workspace->getKey()))
                ->orderBy((new $model)->getKeyName())
                ->cursor();
            $member = false;
            foreach ($memberships as $membership) {
                $open = $workspace ?? ConciergeModels::workspace()::query()->find($membership->getAttribute('workspace_id'));
                // A workspace being deleted opens no page: the next membership's, unless that one was asked for.
                if ($open === null || ($workspace === null && method_exists($open, 'isDeleting') && $open->isDeleting())) {
                    continue;
                }
                $user ??= $provider->retrieveById($membership->getAttribute('user_id'));
                $workspace = $open;
                $member = true;
                break;
            }
            if (! $member && $workspace !== null) {
                throw new RuntimeException($user === null
                    ? 'Workspace '.$slug.' has no active member to sign in as: pass --user.'
                    : 'User '.$given.' is not an active member of workspace '.$slug.'.');
            }
        }
        if ($user === null && method_exists($provider, 'createModel')) {
            $model = $provider->createModel();
            $user = $model->newQuery()->orderBy($model->getKeyName())->first();
        }

        return [$user ?? throw new RuntimeException('No user to sign in as: the users table is empty. Pass --user, or seed a user first (this command writes nothing).'), $workspace];
    }

    /**
     * The pages to request, each with its URL, and the ones skipped with the reason. A workspace page opens in the chosen workspace; any other
     * parameter it needs and has no default for skips it, as does a route `--route` matched that answers no GET.
     *
     * @return array{0: list<array{route: ?string, method: string, path: string, url: string}>, 1: list<array{route: ?string, method: string, path: string, reason: string}>}
     */
    private function pages(?string $workspace): array
    {
        $patterns = (array) $this->option('route');
        $pages = [];
        $skipped = [];
        foreach ($this->laravel['router']->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $matched = array_filter($patterns, fn (string $pattern): bool => Str::is($pattern, (string) $route->getName()) || Str::is(ltrim($pattern, '/'), $route->uri())) !== [];
            if (! isset($route->action['kit_page']) && ! $matched) {
                continue;
            }
            $page = ['route' => $route->getName(), 'method' => 'GET', 'path' => '/'.ltrim($route->uri(), '/')];
            if (! in_array('GET', $route->methods(), true)) {
                $skipped[] = ['method' => implode('|', $route->methods())] + $page + ['reason' => 'not a GET route'];

                continue;
            }
            $parameters = [];
            $missing = [];
            foreach ($route->parameterNames() as $name) {
                if ($name === 'workspace' && $workspace !== null) {
                    $parameters['workspace'] = $workspace;
                } elseif (! array_key_exists($name, $route->defaults) && ! str_contains($route->uri(), '{'.$name.'?}')) {
                    $missing[] = '{'.$name.'}';
                }
            }
            if ($missing !== []) {
                $skipped[] = $page + ['reason' => $missing === ['{workspace}'] ? 'no workspace to open it in' : 'no value for '.implode(', ', $missing)];

                continue;
            }
            try {
                $url = $this->laravel['url']->toRoute($route, $parameters, true);
            } catch (UrlGenerationException $error) {
                $skipped[] = $page + ['reason' => $error->getMessage()];

                continue;
            }
            $path = (string) parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $pages[] = ['route' => $page['route'], 'method' => 'GET', 'path' => ($path === '' ? '/' : $path).($query === null ? '' : '?'.$query), 'url' => $url];
        }
        usort($pages, fn (array $a, array $b): int => [$a['path'], $a['route']] <=> [$b['path'], $b['route']]);
        usort($skipped, fn (array $a, array $b): int => [$a['path'], $a['route']] <=> [$b['path'], $b['route']]);

        return [$pages, $skipped];
    }

    /**
     * Runs the requests with the session and every cache store on array stores and mail, notifications and the queue faked, then puts back what it swapped.
     *
     * @template T
     *
     * @param  callable(): T  $requests
     * @return T
     */
    private function isolated(callable $requests): mixed
    {
        $app = $this->laravel;
        $config = $app['config'];
        $stores = ['session.driver' => $config->get('session.driver'), 'cache.stores' => $config->get('cache.stores', [])];
        $request = $app['request'];
        $fakes = [Mail::class => Mail::getFacadeRoot(), Notification::class => Notification::getFacadeRoot(), Queue::class => Queue::getFacadeRoot()];
        // The session and every cache store by name (the default and any a package names itself) are array stores while the report runs, so what they
        // read and write is never a query it counts. A database cache too: its writes would roll back, so every request would find it cold; in memory,
        // the uncounted first request warms it. A database session would be new on every request, which reads its row twice.
        $config->set(['session.driver' => 'array', 'cache.stores' => array_map(fn (): array => ['driver' => 'array', 'serialize' => false], $stores['cache.stores'])]);
        $app['cache']->forgetDriver(array_keys($stores['cache.stores']));
        $app->forgetInstance('session.store');
        $app->forgetInstance('cache.store');
        Mail::fake();
        Notification::fake();
        Queue::fake();
        // Registered once: a dispatcher can't forget one listener, so these only act while a request is measured.
        $app['events']->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            if ($this->log !== null) {
                $bindings = (string) json_encode($query->connection->prepareBindings($query->bindings), JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                $this->log[] = [$query->connectionName, $query->sql, $bindings, (float) $query->time];
            }
        });
        $this->watched = new WeakMap;
        foreach ($app['db']->getConnections() as $connection) {
            $this->watch($connection);
        }
        $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->watch($event->connection);
        });
        try {
            return $requests();
        } finally {
            foreach ($fakes as $facade => $root) {
                $facade::swap($root);
            }
            $config->set($stores);
            $app['cache']->forgetDriver(array_keys($stores['cache.stores']));
            $app->forgetInstance('session.store');
            $app->forgetInstance('cache.store');
            $app['auth']->forgetGuards();
            $app->instance('request', $request);
        }
    }

    /**
     * One request through the HTTP kernel as the person, as a fresh request would be (scoped instances, once() memos, the auth guards and the session store forgotten), inside
     * a transaction on each connection it writes to, rolled back afterwards. The person is loaded by the guard inside the measurement, as a session request loads them.
     *
     * @return array{status: int, queries: list<array{0: string, 1: string, 2: string, 3: float}>, time: float}
     */
    private function request(string $url, Authenticatable $user): array
    {
        $app = $this->laravel;
        $request = Request::create($url, 'GET', server: array_filter([
            'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            // An Inertia visit: the page's props as JSON, without rendering the root view (no built assets or SSR server needed).
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => $this->version,
        ], fn (?string $value): bool => $value !== null));
        $app->forgetScopedInstances();
        Once::flush();
        $app->instance('request', $request);
        $app['auth']->forgetGuards();
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        // Terminating callbacks the request registers run in its own terminate, and never again in a later one.
        $callbacks = property_exists($app, 'terminatingCallbacks') ? new ReflectionProperty($app, 'terminatingCallbacks') : null;
        $before = $callbacks?->getValue($app);
        $this->opened = [];
        $this->log = [];
        $start = hrtime(true);
        try {
            $app['auth']->guard('web')->onceUsingId($user->getAuthIdentifier());
            $kernel = $app->make(Kernel::class);
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            try {
                $kernel->terminate($request, $response);
            } catch (Throwable $error) {
                // A terminating callback that throws fails its page, reported as the kernel reports a request's failure; the run carries on.
                report($error);
                $status = 500;
            }
        } finally {
            $time = (hrtime(true) - $start) / 1e6;
            $log = $this->log;
            $opened = $this->opened;
            $this->log = null;
            $this->opened = null;
            $callbacks?->setValue($app, $before);
            // Every connection is rolled back first. A page that committed the transaction the report opened (an unbalanced commit) kept what it
            // wrote there: stop rather than report on, once nothing else is left open.
            $committed = [];
            foreach ($opened as $name => $connection) {
                if ($connection->transactionLevel() === 0) {
                    $committed[] = $name;
                } else {
                    $connection->rollBack(0);
                }
            }
            if ($committed !== []) {
                throw new RuntimeException('A page committed the transaction kit:query-report opened on the '.implode(', ', $committed).' '.Str::plural('connection', count($committed)).', so what it wrote there was kept. Stopped.');
            }
        }
        $this->version = $response->headers->get('X-Inertia-Version') ?? $this->version;

        return ['status' => $status, 'queries' => $log, 'time' => $time];
    }

    /**
     * Opens the request's transaction on a connection just before its first statement that is not a plain read, or before the page begins a
     * transaction of its own (which then nests in it): until then the page reads outside any transaction, as it does in production, so code
     * that behaves otherwise inside one (the kit's savepoints, and the request memo they empty on a rollback) is measured as it runs.
     */
    private function watch(Connection $connection): void
    {
        if (isset($this->watched[$connection])) {
            return;
        }
        $this->watched[$connection] = true;
        $open = function (Connection $connection, ?string $sql = null): void {
            if ($this->opened === null || isset($this->opened[$connection->getName()])) {
                return;
            }
            if ($sql !== null && preg_match('/^\s*(?:select|with)\b/i', $sql) === 1 && ! RequestMemo::writesOrLocks($sql)) {
                return;
            }
            $this->opened[$connection->getName()] = $connection;
            $connection->beginTransaction();
        };
        $connection->beforeStartingTransaction(fn (Connection $connection) => $open($connection));
        $connection->beforeExecuting(fn (string $sql, array $bindings, Connection $connection) => $open($connection, $sql));
    }

    /**
     * A page's counts over its runs: the median of each, and the statements the median run repeats. An exact duplicate is a query run again with the
     * same SQL and bindings on the same connection: a statement run three times with the same bindings is two duplicates, with three ids none.
     *
     * @param  non-empty-list<array{status: int, queries: list<array{0: string, 1: string, 2: string, 3: float}>, time: float}>  $runs
     * @return array{status: int, queries: int|float, duplicates: int|float, time_ms: float, query_ms: float, repeated: list<array{sql: string, connection: string, count: int, duplicates: int}>}
     */
    private function measure(array $runs): array
    {
        $counted = [];
        foreach ($runs as $run) {
            $statements = [];
            foreach ($run['queries'] as [$connection, $sql, $bindings]) {
                $statements[$connection."\0".$sql] ??= ['sql' => $sql, 'connection' => $connection, 'count' => 0, 'bindings' => []];
                $statements[$connection."\0".$sql]['count']++;
                $statements[$connection."\0".$sql]['bindings'][$bindings] = true;
            }
            $repeated = [];
            $duplicates = 0;
            foreach ($statements as $statement) {
                $same = $statement['count'] - count($statement['bindings']);
                $duplicates += $same;
                if ($statement['count'] > 1) {
                    $repeated[] = ['sql' => $statement['sql'], 'connection' => $statement['connection'], 'count' => $statement['count'], 'duplicates' => $same];
                }
            }
            usort($repeated, fn (array $a, array $b): int => [$b['count'], $b['duplicates'], $a['sql']] <=> [$a['count'], $a['duplicates'], $b['sql']]);
            $counted[] = ['status' => $run['status'], 'queries' => count($run['queries']), 'duplicates' => $duplicates, 'time_ms' => $run['time'], 'query_ms' => array_sum(array_column($run['queries'], 3)), 'repeated' => $repeated];
        }
        $byQueries = $counted;
        usort($byQueries, fn (array $a, array $b): int => $a['queries'] <=> $b['queries']);
        $middle = $byQueries[intdiv(count($byQueries) - 1, 2)];

        return [
            'status' => $middle['status'],
            'queries' => self::median(array_column($counted, 'queries')),
            'duplicates' => self::median(array_column($counted, 'duplicates')),
            'time_ms' => round(self::median(array_column($counted, 'time_ms')), 1),
            'query_ms' => round(self::median(array_column($counted, 'query_ms')), 1),
            'repeated' => $middle['repeated'],
        ];
    }

    /**
     * Over the pages that answered 200: the median and worst query counts, the worst page, and the duplicate floor (the fewest exact duplicates any
     * page runs, which every page pays: the shell's and the middleware's).
     *
     * @param  list<array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function summary(array $pages, int $skipped): array
    {
        $ok = array_values(array_filter($pages, fn (array $page): bool => $page['status'] === 200));
        $worst = null;
        foreach ($ok as $page) {
            if ($worst === null || $page['queries'] > $worst['queries']) {
                $worst = $page;
            }
        }

        return [
            'pages' => count($pages),
            'ok' => count($ok),
            'skipped' => $skipped,
            'median_queries' => $ok === [] ? null : self::median(array_column($ok, 'queries')),
            'worst_queries' => $worst['queries'] ?? null,
            'worst_page' => $worst === null ? null : ['route' => $worst['route'], 'path' => $worst['path']],
            'duplicate_floor' => $ok === [] ? null : min(array_column($ok, 'duplicates')),
        ];
    }

    /** @param  array<string, mixed>  $report */
    private function text(array $report): void
    {
        $runs = $report['repeat'] === 1 ? 'once' : $report['repeat'].' times (the median shown)';
        $this->line('As user '.$report['user'].($report['workspace'] === null ? ', in no workspace' : ' in workspace '.$report['workspace']).': each page requested once to warm up, then '.$runs.', every request rolled back.');
        foreach ($report['pages'] as $page) {
            $this->newLine();
            $this->line('<info>'.$page['method'].' '.OutputFormatter::escape($page['path']).'</info>'.($page['route'] === null ? '' : '  '.OutputFormatter::escape($page['route'])));
            $status = $page['status'] === 200 ? '200' : '<fg=yellow>'.$page['status'].'</>';
            $this->line('  '.$status.' · '.$page['queries'].' '.Str::plural('query', (int) ceil($page['queries'])).' · '.$page['duplicates'].' '.Str::plural('duplicate', (int) ceil($page['duplicates'])).' · '.$page['time_ms'].' ms ('.$page['query_ms'].' ms in queries)');
            foreach (array_slice($page['repeated'], 0, self::TOP) as $statement) {
                $this->line('  '.mb_str_pad($statement['count'].'×', 5).OutputFormatter::escape(Str::limit($statement['sql'], 160)).($statement['duplicates'] > 0 ? '  <comment>('.$statement['duplicates'].' exact)</comment>' : ''));
            }
        }
        if ($report['skipped'] !== []) {
            $this->newLine();
            $this->line('<comment>Skipped</comment>');
            foreach ($report['skipped'] as $page) {
                $this->line('  '.$page['method'].' '.OutputFormatter::escape($page['path']).($page['route'] === null ? '' : '  '.OutputFormatter::escape($page['route'])).': '.OutputFormatter::escape($page['reason']));
            }
        }
        $summary = $report['summary'];
        $this->newLine();
        $line = $summary['pages'].' '.Str::plural('page', $summary['pages']).' ('.$summary['ok'].' answered 200), '.$summary['skipped'].' skipped.';
        if ($summary['ok'] > 0) {
            $worst = $summary['worst_page']['path'].($summary['worst_page']['route'] === null ? '' : ', '.$summary['worst_page']['route']);
            $line .= ' Over the 200s: median '.$summary['median_queries'].' queries, worst '.$summary['worst_queries'].' ('.OutputFormatter::escape($worst).'), duplicate floor '.$summary['duplicate_floor'].' per page.';
        }
        $this->line($line);
    }

    /** @param  list<int|float>  $values */
    private static function median(array $values): int|float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
