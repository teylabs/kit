<?php

// Concerns\Touches and Touch on synthetic models (Client → Project → Task, a Comment morphing to a Task, a Note whose parent has no touched_at): create,
// update and delete touch the parents and grandparents with a quiet update (no events, updated_at unchanged), each record once per request, a cycle
// stops, Touch::without() and the console switch turn it off, touchTree() touches without saving, alsoTouch() runs, the `touchedAt()` macro, and the
// TouchesAssertions helpers. SQLite memory.
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Core\Concerns\Touches;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Testing\TouchesAssertions;
use Tey\Kit\Features\Core\Touch;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Client extends Model
{
    use Touches;

    protected $guarded = [];

    /** @var list<string> */
    public static array $also = [];

    public function alsoTouch(DateTimeInterface $at): void
    {
        self::$also[] = $this->name;
    }
}

final class Project extends Model
{
    use Touches;

    protected array $touchesParents = ['client'];

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

final class Task extends Model
{
    use Touches;

    protected array $touchesParents = ['project'];

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

final class Comment extends Model
{
    use Touches;

    protected array $touchesParents = ['subject'];

    protected $guarded = [];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

/** Forgets its parent: the coverage helper must list it. */
final class Estimate extends Model
{
    use Touches;

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

/** A parent table with no touched_at column: skipped, never written. */
final class Folder extends Model
{
    protected $guarded = [];
}

final class Note extends Model
{
    use Touches;

    protected array $touchesParents = ['folder'];

    protected $guarded = [];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}

/** Two records that name each other: the cascade must stop. */
final class Node extends Model
{
    use Touches;

    protected array $touchesParents = ['partner'];

    protected $guarded = [];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'partner_id');
    }
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $base = sys_get_temp_dir().'/kit-touched-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->singleton(ExceptionHandler::class, Handler::class);
    $app->instance('config', $config = new Repository([
        'app' => ['env' => 'testing'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'kit' => ['touch' => ['console' => true]],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, DatabaseServiceProvider::class, CoreProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();

    check(Blueprint::hasMacro('touchedAt'), 'the touchedAt() migration macro is registered');
    foreach (['clients', 'projects', 'tasks', 'comments', 'estimates', 'notes', 'nodes'] as $table) {
        Schema::create($table, function (Blueprint $t) use ($table): void {
            $t->id();
            $t->string('name')->nullable();
            match ($table) {
                'projects' => $t->foreignId('client_id')->nullable(),
                'tasks' => $t->foreignId('project_id')->nullable(),
                'estimates' => $t->foreignId('project_id')->nullable(),
                'comments' => $t->nullableMorphs('subject'),
                'notes' => $t->foreignId('folder_id')->nullable(),
                'nodes' => $t->foreignId('partner_id')->nullable(),
                default => null,
            };
            if ($table !== 'notes') {
                $t->touchedAt();
            }
            $t->timestamps();
        });
    }
    Schema::create('folders', function (Blueprint $t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->timestamps();
    });
    $column = collect(Schema::getColumns('clients'))->firstWhere('name', 'touched_at');
    $indexed = collect(Schema::getIndexes('clients'))->contains(fn ($index) => $index['columns'] === ['touched_at']);
    check($column !== null && $column['nullable'] && $indexed, 'touchedAt() adds a nullable, indexed touched_at timestamp');

    $at = fn (string $time) => Carbon::setTestNow(Carbon::parse('2026-01-01 '.$time));
    $fresh = fn (Model $model) => $model->newQueryWithoutScopes()->toBase()->find($model->getKey());
    // A new request: the records touched before are forgotten.
    $request = function (string $time) use ($at): void {
        Touch::flush();
        $at($time);
    };

    $request('09:00:00');
    $client = Client::create(['name' => 'Acme']);
    $project = Project::create(['name' => 'Site', 'client_id' => $client->id]);
    check((string) $fresh($client)->touched_at === '2026-01-01 09:00:00', 'a new record sets its own touched_at on create');

    // Fire-counter on the parents: a quiet touch fires none of their model events.
    $events = [];
    foreach (['saving', 'saved', 'updating', 'updated'] as $name) {
        Event::listen("eloquent.{$name}: ".Client::class, function () use (&$events, $name) {
            $events[] = 'client.'.$name;
        });
        Event::listen("eloquent.{$name}: ".Project::class, function () use (&$events, $name) {
            $events[] = 'project.'.$name;
        });
    }

    $request('10:00:00');
    $task = Task::create(['name' => 'Wire', 'project_id' => $project->id]);
    check((string) $fresh($task)->touched_at === '2026-01-01 10:00:00', 'the child stamps its own touched_at on create');
    check((string) $fresh($project)->touched_at === '2026-01-01 10:00:00', 'creating a child touches its parent');
    check((string) $fresh($client)->touched_at === '2026-01-01 10:00:00', 'the cascade reaches the grandparent');
    check((string) $fresh($project)->updated_at === '2026-01-01 09:00:00' && (string) $fresh($client)->updated_at === '2026-01-01 09:00:00', 'the parents\' updated_at is unchanged');
    check($events === [], 'no model events fire on the touched parents');
    check(Client::$also === ['Acme', 'Acme'], 'alsoTouch() runs with each touch of the record (its create, then the cascade) '.json_encode(Client::$also));

    $request('11:00:00');
    $task->update(['name' => 'Wire again']);
    check((string) $fresh($project)->touched_at === '2026-01-01 11:00:00' && (string) $fresh($client)->touched_at === '2026-01-01 11:00:00', 'updating a child touches its parent and grandparent');

    $request('11:30:00');
    $task->save();
    check((string) $fresh($project)->touched_at === '2026-01-01 11:00:00', 'a save with no changes touches nothing');

    $request('12:00:00');
    $task->delete();
    check((string) $fresh($project)->touched_at === '2026-01-01 12:00:00' && (string) $fresh($client)->touched_at === '2026-01-01 12:00:00', 'deleting a child touches its parent and grandparent');

    // Dedupe: within one request each record is touched once, by its first touch; three child writes make one update per ancestor.
    $request('13:00:00');
    $updates = [];
    DB::listen(function ($query) use (&$updates) {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updates[] = $query->sql;
        }
    });
    $one = Task::create(['name' => 'One', 'project_id' => $project->id]);
    $at('13:05:00');
    Task::create(['name' => 'Two', 'project_id' => $project->id]);
    $one->update(['name' => 'One more']);
    $parentUpdates = array_values(array_filter($updates, fn ($sql) => str_contains($sql, '"projects"') || str_contains($sql, '"clients"')));
    check(count($parentUpdates) === 2, 'each ancestor is touched once per request (dedupe by class and key), not once per child write');
    check((string) $fresh($project)->touched_at === '2026-01-01 13:00:00', 'the deduped ancestor keeps its first touch of the request');

    // MorphTo: a comment on a task touches the task, its project and its client.
    $request('14:00:00');
    $task2 = Task::withoutEvents(fn () => Task::create(['name' => 'Morph', 'project_id' => $project->id]));
    Comment::create(['name' => 'Hi', 'subject_type' => Task::class, 'subject_id' => $task2->id]);
    check((string) $fresh($task2)->touched_at === '2026-01-01 14:00:00' && (string) $fresh($client)->touched_at === '2026-01-01 14:00:00', 'a MorphTo parent is touched, and the cascade goes on from it');

    // A cycle: two nodes name each other; the cascade stops at the first record seen twice.
    $request('15:00:00');
    $a = Node::create(['name' => 'A']);
    $b = Node::create(['name' => 'B', 'partner_id' => $a->id]);
    $a->update(['partner_id' => $b->id]);
    $request('15:30:00');
    $b->update(['name' => 'B2']);
    check((string) $fresh($a)->touched_at === '2026-01-01 15:30:00', 'a cycle touches the other record and stops (no endless loop)');

    // touchTree(): touches a record and its parents without saving it.
    $request('16:00:00');
    $before = (string) $fresh($task2)->updated_at;
    $task2->touchTree();
    check((string) $fresh($task2)->touched_at === '2026-01-01 16:00:00' && (string) $fresh($task2)->updated_at === $before, 'touchTree() touches the record quietly, updated_at unchanged');
    check((string) $fresh($client)->touched_at === '2026-01-01 16:00:00', 'touchTree() touches the record\'s ancestors');

    // without(): nothing is stamped or cascaded inside it.
    $request('17:00:00');
    $quiet = Touch::without(fn () => Task::create(['name' => 'Import', 'project_id' => $project->id]));
    check($fresh($quiet)->touched_at === null && (string) $fresh($project)->touched_at === '2026-01-01 16:00:00', 'Touch::without() stamps and cascades nothing');
    $task2->update(['name' => 'After']);
    check((string) $fresh($project)->touched_at === '2026-01-01 17:00:00', 'touching resumes after without()');

    // The console switch: off, a command or queue worker touches nothing.
    $request('18:00:00');
    $config->set('kit.touch.console', false);
    $task2->update(['name' => 'Console']);
    check((string) $fresh($project)->touched_at === '2026-01-01 17:00:00', 'kit.touch.console false turns touching off under the console');
    $config->set('kit.touch.console', true);

    // A parent without a touched_at column is skipped.
    $request('19:00:00');
    $folder = Folder::create(['name' => 'Inbox']);
    $updates = [];
    Note::create(['name' => 'N', 'folder_id' => $folder->id]);
    check(! collect($updates)->contains(fn ($sql) => str_contains($sql, '"folders"')) && (string) $fresh($folder)->updated_at === '2026-01-01 19:00:00', 'a parent without a touched_at column is skipped');

    // A job ending forgets the touched records, so the next job touches again.
    $request('20:00:00');
    $task2->update(['name' => 'Job 1']);
    $at('20:10:00');
    $task2->update(['name' => 'Job 1 again']);
    check((string) $fresh($project)->touched_at === '2026-01-01 20:00:00', 'within one job the parent is touched once');
    event(new JobProcessed('sync', new SyncJob($app, '{}', 'sync', 'default')));
    $task2->update(['name' => 'Job 2']);
    check((string) $fresh($project)->touched_at === '2026-01-01 20:10:00', 'a job ending flushes the dedupe set');

    // The test helpers.
    TouchesAssertions::assertTouchesParent(Task::class, 'project');
    check(true, 'assertTouchesParent passes for a declared relation');
    $refused = null;
    try {
        TouchesAssertions::assertTouchesParent(Estimate::class, 'project');
    } catch (Throwable $e) {
        $refused = $e->getMessage();
    }
    check($refused !== null && str_contains($refused, "'project'"), 'assertTouchesParent fails for an undeclared relation');
    $missing = TouchesAssertions::uncovered([Project::class, Task::class, Comment::class, Estimate::class, Note::class]);
    check($missing === [Estimate::class.'::project'], 'uncovered() lists only the BelongsTo to a touched parent left out of $touchesParents (got '.implode(', ', $missing).')');
    check(TouchesAssertions::uncovered([Estimate::class], [Estimate::class.'::project']) === [], 'uncovered() honours the ignore list');

    echo "touched_at check passed\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
