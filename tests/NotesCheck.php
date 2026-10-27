<?php

// The notes feature: tenant isolation, ownership and moderation, stale writes, intent replay, subjects, attachments and their gated media, the save
// toast, the dashboard widget, the MCP tools over the same operations, the legacy-table attention step and the import. SQLite memory, real Concierge,
// spatie teams and Pennant, medialibrary on a temporary local disk, synthetic users.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;
use Laravel\Pennant\Feature;
use Tey\Concierge\Actions\SyncRoleDefinitions;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Enums\MembershipStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Blueprint;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Notes\Integrations\Mcp\DeleteNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\ListNotes;
use Tey\Kit\Features\Notes\Integrations\Mcp\ReadNote;
use Tey\Kit\Features\Notes\Integrations\Mcp\WriteNote;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Workspaces\Concerns\HasWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;
use Tey\Kit\HostRequirements;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class HttpUser extends Authenticatable implements Member
{
    use HasApiTokens, HasWorkspaces;

    protected $table = 'users';

    protected $guarded = [];
}

class_alias(HttpUser::class, 'App\Models\User');

final class Project extends Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['secret' => 'boolean'];
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** The refusal code an operation throws, or null when it ran. */
function refusal(callable $operation): ?string
{
    try {
        $operation();
    } catch (Illuminate\Validation\ValidationException) {
        return 'validation';
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
}

try {
    $base = sys_get_temp_dir().'/kit-notes-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework/views', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
    $app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
    $app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);
    $app->instance('env', 'testing');
    $app->instance('config', $config = new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'locale' => 'en', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'debug' => false, 'env' => 'testing'],
        'auth' => ['defaults' => ['guard' => 'web', 'passwords' => 'users'], 'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']], 'providers' => ['users' => ['driver' => 'eloquent', 'model' => HttpUser::class]]],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'session' => ['driver' => 'array', 'lifetime' => 120, 'cookie' => 's', 'path' => '/', 'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false, 'lottery' => [0, 100], 'expire_on_close' => false, 'encrypt' => false, 'files' => $base.'/storage'],
        'view' => ['paths' => [], 'compiled' => $base.'/storage/framework/views'],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]],
        'kit' => ['app_id' => null, 'features' => ['core', 'workspaces', 'access', 'settings', 'session-ui', 'mcp', 'notes'], 'models' => ['user' => HttpUser::class], 'capabilities' => ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true], 'mcp' => ['ui' => true]],
        'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $base.'/storage/app/private', 'throw' => false]]],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => Monolog\Handler\TestHandler::class]]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([
        Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Encryption\EncryptionServiceProvider::class, Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class, Illuminate\Hashing\HashServiceProvider::class, Illuminate\Pipeline\PipelineServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class,
        Tey\Mod\ModServiceProvider::class, Spatie\Permission\PermissionServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\LaravelSettings\LaravelSettingsServiceProvider::class, Spatie\LaravelData\LaravelDataServiceProvider::class,
        Laravel\Pennant\PennantServiceProvider::class, Tey\Kit\Features\FeaturesServiceProvider::class, Tey\Concierge\ConciergeServiceProvider::class, Laravel\Passport\PassportServiceProvider::class, Laravel\Fortify\FortifyServiceProvider::class, Inertia\ServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class,
    ] as $provider) {
        $app->register($provider);
    }
    // A subject the app registers: a "project" the viewer may see only when it is not secret.
    $app->make(Registry::class)->noteSubject('project', Project::class, fn (Principal $principal, Project $project): bool => ! $project->secret, fn (Project $project): string => $project->name, fn (Project $project): string => '/projects/'.$project->id);
    // The shell's middleware joins the web group through both the router and the HTTP kernel (which rebuilds the router's groups), so the kernel's holds it too.
    $app->make(Illuminate\Contracts\Http\Kernel::class)->setMiddlewareGroups(['web' => [Illuminate\Session\Middleware\StartSession::class, Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests::class]]);
    $app->boot();
    $app['router']->aliasMiddleware('auth', Illuminate\Auth\Middleware\Authenticate::class);

    // Real tables: the host's users, Concierge's, spatie's, and the kit's own migration stubs.
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });
    foreach (glob(dirname(__DIR__).'/resources/features/*/migrations/*.stub') as $file) {
        $stubs[basename($file)] = $file;
    }
    ksort($stubs);
    foreach (glob(dirname(__DIR__).'/vendor/tey/concierge/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    foreach (glob(dirname(__DIR__).'/vendor/laravel/passport/database/migrations/*.php') as $file) {
        (require $file)->up();
    }
    // The shell reads avatars through medialibrary.
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    foreach ($stubs as $file) {
        (require $file)->up();
    }
    $app['router']->getRoutes()->refreshNameLookups();

    $config->set('concierge.roles', ['owner' => ['label' => 'Owner', 'grantable_by' => []], 'admin' => ['label' => 'Admin', 'grantable_by' => ['owner']], 'member' => ['label' => 'Member', 'grantable_by' => ['owner', 'admin']], 'viewer' => ['label' => 'Viewer', 'grantable_by' => ['owner', 'admin']]]);
    SyncRoleDefinitions::run();
    // The console kernel would bootstrap the app again and reload its config, so the command runs on its own.
    $sync = new Tey\Kit\Features\Access\Console\SyncPermissionsCommand;
    $sync->setLaravel($app);
    $registry = $app->make(Registry::class);

    $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
    $beta = Workspace::create(['name' => 'Beta', 'slug' => 'beta']);
    foreach ([$workspace, $beta] as $each) {
        $each->execute(function () use ($registry, $each): void {
            foreach ($registry->provisioners() as $provision) {
                $provision($each);
            }
        });
    }
    $people = [];
    // Two members, so a member can meet someone else's note; `outsider` owns Beta and belongs to no Acme.
    foreach (['owner' => 'owner', 'admin' => 'admin', 'member' => 'member', 'other' => 'member', 'viewer' => 'viewer', 'outsider' => 'owner'] as $name => $role) {
        $people[$name] = HttpUser::create(['name' => ucfirst($name), 'email' => $name.'@example.test', 'email_verified_at' => now()]);
        $in = $name === 'outsider' ? $beta : $workspace;
        ConciergeModels::membership()::query()->create(['workspace_id' => $in->id, 'user_id' => $people[$name]->id, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(Tey\Concierge\Concierge::class)->context()->run($in, fn () => $people[$name]->assignRole($role), [$people[$name]]);
    }
    Schema::create('projects', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('secret')->default(false);
    });

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    // Provisioning admitted the workspace on its own copy.
    $workspace->refresh();

    $sync->run(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput);
    $workspace->refresh();
    $beta->refresh();
    $notes = app(WorkspaceNotes::class);
    $in = fn (Workspace $at, callable $operation) => app(TenantBoundary::class)->run($at->id, $operation);
    $as = fn (string $who): Principal => new Principal((int) $people[$who]->id);
    $key = fn (): string => bin2hex(random_bytes(8));
    $create = fn (string $who, array $input = [], ?Workspace $at = null) => $in($at ?? $workspace, fn () => $notes->mutate($as($who), 'create', ['body' => 'From '.$who, 'intent_key' => $key(), ...$input]))->note;
    $change = fn (string $who, int $id, int $version, array $input = []) => refusal(fn () => $in($workspace, fn () => $notes->mutate($as($who), 'update', ['id' => $id, 'expected_version' => $version, 'intent_key' => $key(), 'body' => 'Changed by '.$who, ...$input])));
    $http = function (string $who, string $method, string $path, array $data = [], array $files = [], bool $inertia = false) use ($app, $people): array {
        Auth::guard('web')->setUser($people[$who]);
        $server = $inertia ? ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : ['HTTP_ACCEPT' => 'application/json'];
        $request = Request::create('https://kit.test'.$path, $method, $data, files: $files, server: $server);
        $request->setUserResolver(fn () => $people[$who]);
        $request->setLaravelSession($app['session']->driver());
        $app->instance('request', $request);
        try {
            $response = $app['router']->dispatch($request);
        } catch (Throwable $error) {
            $response = $app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $error);
        }
        Feature::flushCache();

        return [$response->getStatusCode(), $response, (string) $response->getContent()];
    };

    // Writing and reading, and the workspace boundary.
    $first = $create('member', ['colour' => 'pink', 'pinned' => true]);
    check($first->author_id === (int) $people['member']->id && $first->colour === 'pink' && $first->pinned && $first->version === 1, 'a member writes a note: author, colour, pin and version 1');
    check($create('owner')->colour === 'yellow', 'a note with no colour gets the first configured colour');
    check(refusal(fn () => $create('member', ['colour' => 'tartan'])) === 'validation', 'a colour outside the palette is refused');
    check(refusal(fn () => $create('viewer')) === 'permission_denied', 'a viewer cannot write: notes.write is refused');
    check(count($in($workspace, fn () => $notes->listing($as('viewer')))) === 2 && $in($workspace, fn () => $notes->listing($as('viewer')))[0]->pinned, 'a viewer reads the workspace notes, pinned first');
    $betaNote = $create('outsider', [], $beta);
    check(refusal(fn () => $in($workspace, fn () => $notes->read($as('owner'), ['id' => $betaNote->id]))) === 'note_not_found', 'another workspace\'s note is not found, even for an owner');
    check(refusal(fn () => $in($workspace, fn () => $notes->read($as('outsider'), ['id' => $first->id]))) === 'workspace_not_found', 'someone outside the workspace reads nothing');
    check(refusal(fn () => $in($workspace, fn () => $notes->listing($as('member'), ['actor_id' => 1]))) === 'unknown_arguments', 'an argument the operation does not name is refused');

    // Moderation: your own note, the owner, someone else's without notes.moderate, and with it.
    $mine = $create('member');
    check($change('member', $mine->id, 1) === null, 'the author changes their own note');
    check($change('other', $mine->id, 2) === 'note_not_yours', 'another member with notes.write cannot change someone else\'s note');
    check(refusal(fn () => $in($workspace, fn () => $notes->mutate($as('other'), 'delete', ['id' => $mine->id, 'expected_version' => 2, 'intent_key' => $key()]))) === 'note_not_yours', 'nor delete it');
    check($change('owner', $mine->id, 2) === null, 'the workspace owner changes anyone\'s note');
    check($change('admin', $mine->id, 3) === null, 'an admin, who holds notes.moderate by default, changes anyone\'s note');
    $memberRole = app(Spatie\Permission\PermissionRegistrar::class)->getRoleClass()::query()->where('name', 'member')->where('team_id', $workspace->id)->first() ?? app(Spatie\Permission\PermissionRegistrar::class)->getRoleClass()::query()->where('name', 'member')->first();
    $workspace->execute(fn () => $memberRole->givePermissionTo('workspace.notes.moderate'));
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    app(Tey\Kit\Features\Core\RequestMemo::class)->flush();
    check($change('other', $mine->id, 4) === null, 'granted notes.moderate (the client app\'s member role), a member changes someone else\'s note');
    $workspace->execute(fn () => $memberRole->revokePermissionTo('workspace.notes.moderate'));
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    app(Tey\Kit\Features\Core\RequestMemo::class)->flush();
    check($change('other', $mine->id, 5) === 'note_not_yours', 'and without it again, cannot');

    // Stale writes and intent replay.
    check($change('member', $mine->id, 1) === 'stale_note', 'an update at an old version is refused as stale_note');
    $intent = $key();
    $once = $in($workspace, fn () => $notes->mutate($as('member'), 'update', ['id' => $mine->id, 'expected_version' => 5, 'intent_key' => $intent, 'body' => 'Once']));
    $twice = $in($workspace, fn () => $notes->mutate($as('member'), 'update', ['id' => $mine->id, 'expected_version' => 5, 'intent_key' => $intent, 'body' => 'Once']));
    check($once->note->version === 6 && $twice->toArray() === $once->toArray() && DB::table('audit_events')->where('event', 'workspace.note.updated')->where('details', 'like', '%"version":6%')->count() === 1, 'a retry with the same intent key replays the first result and writes once');
    check(refusal(fn () => $in($workspace, fn () => $notes->mutate($as('member'), 'update', ['id' => $mine->id, 'expected_version' => 6, 'intent_key' => $intent, 'body' => 'Other']))) === 'note_intent_conflict', 'the same intent key with other input is note_intent_conflict');

    // Subjects: visibility follows the record, and an unknown alias fails closed.
    $open = Project::create(['name' => 'Open project']);
    $secret = Project::create(['name' => 'Secret project', 'secret' => true]);
    $about = $create('member', ['subject_type' => 'project', 'subject_id' => $open->id]);
    check($about->subject === ['type' => 'project', 'id' => $open->id, 'label' => 'Open project', 'url' => '/projects/'.$open->id], 'a note about a registered subject carries its label and link');
    check(count($in($workspace, fn () => $notes->listing($as('member'), ['subject_type' => 'project', 'subject_id' => $open->id]))) === 1 && ! in_array($about->id, array_column(array_map(fn ($n) => $n->toArray(), $in($workspace, fn () => $notes->listing($as('member')))), 'id'), true), 'a subject\'s notes list on their own, not on the workspace wall');
    check(refusal(fn () => $create('member', ['subject_type' => 'project', 'subject_id' => $secret->id])) === 'note_subject_unavailable', 'a note about a record the viewer may not see is refused');
    check(refusal(fn () => $create('member', ['subject_type' => 'invoice', 'subject_id' => 1])) === 'note_subject_unavailable', 'an unregistered subject alias is refused');
    $hidden = $in($workspace, fn () => Tey\Kit\Features\Notes\Models\Note::query()->create(['author_id' => $people['member']->id, 'subject_type' => 'project', 'subject_id' => $secret->id, 'body' => 'Hidden', 'colour' => 'yellow', 'version' => 1]));
    check(refusal(fn () => $in($workspace, fn () => $notes->read($as('owner'), ['id' => $hidden->id]))) === 'note_not_found', 'a note about a record the viewer may not see looks absent');

    // The page, the save toast, attachments and their gated media.
    [$status, , $page] = $http('member', 'GET', '/@acme/notes', inertia: true);
    $props = json_decode($page, true)['props'] ?? [];
    check($status === 200 && json_decode($page, true)['component'] === 'Workspace/Notes/Index/Page' && $props['composer']['submit']['url'] !== '' && in_array('notes', json_decode($page, true)['deferredProps']['lists'] ?? [], true), 'the wall renders with the composer, and the notes load deferred');
    [, , $viewerPage] = $http('viewer', 'GET', '/@acme/notes', inertia: true);
    check(json_decode($viewerPage, true)['props']['composer'] === null, 'a viewer gets no composer');
    $png = UploadedFile::fake()->image('board.png', 40, 30);
    [$status, $response] = $http('member', 'POST', '/@acme/notes', ['body' => 'With a picture', 'colour' => 'blue', 'pinned' => '0', 'intent_key' => $key()], ['files' => [$png]], inertia: true);
    check($status === 302 && str_contains(json_encode($app['session']->driver()->all()), 'Note added'), 'saving from the page redirects back and flashes the save toast');
    $withFile = $in($workspace, fn () => Tey\Kit\Features\Notes\Models\Note::query()->where('body', 'With a picture')->firstOrFail());
    $record = $in($workspace, fn () => $notes->read($as('member'), ['id' => $withFile->id]));
    check(count($record->attachments) === 1 && $record->attachments[0]['mime'] === 'image/png' && $record->attachments[0]['thumb_url'] !== null, 'an attachment is stored, with a thumbnail');
    $path = parse_url($record->attachments[0]['url'], PHP_URL_PATH);
    [$status, $media] = $http('viewer', 'GET', $path);
    check($status === 200 && $media->headers->get('Cache-Control') === 'no-store, private' && $media->headers->get('X-Content-Type-Options') === 'nosniff', 'a reader opens the attachment, never cached');
    [$status] = $http('outsider', 'GET', $path);
    check($status === 404 || $status === 403, 'someone outside the workspace cannot open it');
    [$status] = $http('viewer', 'GET', str_replace((string) $withFile->id, (string) $first->id, $path));
    check($status === 404, 'an attachment asked for under another note is a 404');
    [$status] = $http('member', 'POST', '/@acme/notes', ['body' => 'Script', 'intent_key' => $key()], ['files' => [UploadedFile::fake()->create('run.sh', 1, 'text/x-shellscript')]]);
    check($status === 422, 'a file type outside kit.notes.media.accept is refused');
    [$status, , $json] = $http('other', 'PUT', '/@acme/notes/'.$withFile->id, ['body' => 'Mine now', 'expected_version' => 1, 'intent_key' => $key()]);
    check($status === 403 && str_contains($json, 'note_not_yours'), 'over HTTP, changing someone else\'s note is a 403 note_not_yours');
    [$status, , $json] = $http('member', 'DELETE', '/@acme/notes/'.$withFile->id, ['expected_version' => 1, 'intent_key' => $key()]);
    check($status === 200 && json_decode($json, true)['deleted'] === true, 'the author deletes their note');
    [$status] = $http('viewer', 'GET', $path);
    check($status === 404, 'a deleted note\'s attachment is a 404');

    // The dashboard widget.
    [, , $dash] = $http('member', 'GET', '/@acme', inertia: true);
    $widget = array_column(json_decode($dash, true)['props']['widgets'], null, 'key')['notes.recent'] ?? null;
    check($widget !== null && $widget['component'] === 'notes' && $widget['composer'] !== null && count($widget['notes']) <= 6 && $widget['notes'][0]['pinned'], 'the dashboard shows the notes widget with the composer and the latest notes, pinned first');
    [, , $dash] = $http('viewer', 'GET', '/@acme', inertia: true);
    $widget = array_column(json_decode($dash, true)['props']['widgets'], null, 'key')['notes.recent'] ?? null;
    check($widget !== null && $widget['composer'] === null, 'a viewer sees the notes widget without the composer');
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => false]);
    Feature::flushCache();
    [, , $dash] = $http('owner', 'GET', '/@acme', inertia: true);
    [$status] = $http('owner', 'GET', '/@acme/notes', inertia: true);
    check(! in_array('notes.recent', array_column(json_decode($dash, true)['props']['widgets'], 'key'), true) && $status !== 200, 'with the notes capability off, the widget and the wall are gone');
    $config->set('kit.capabilities', ['workspace.settings' => true, 'workspace.dashboard' => true, 'workspace.notes' => true]);
    Feature::flushCache();

    // MCP: the tools are the same operations.
    check(array_values(array_intersect(array_map('class_basename', $registry->mcpTools()), ['ListNotes', 'ReadNote', 'WriteNote', 'DeleteNote'])) === ['ListNotes', 'ReadNote', 'WriteNote', 'DeleteNote'] && (new DeleteNote)->name() === 'notes_delete', 'the four notes tools are registered as capability tools');
    $call = fn (string $tool, string $who, array $validated) => $in($workspace, fn () => (new ReflectionMethod($tool, 'call'))->invoke(new $tool, $as($who), $workspace->id, $validated));
    $written = $call(WriteNote::class, 'member', ['body' => 'From the assistant']);
    check($written['note']['author_id'] === (int) $people['member']->id && $written['note']['version'] === 1, 'notes_write creates through the same operation');
    check(refusal(fn () => $call(WriteNote::class, 'other', ['id' => $written['note_id'], 'expected_version' => 1, 'body' => 'Hijack'])) === 'note_not_yours', 'notes_write refuses someone else\'s note the same way');
    check($call(ReadNote::class, 'viewer', ['id' => $written['note_id']])['body'] === 'From the assistant' && count($call(ListNotes::class, 'viewer', [])['notes']) >= 1, 'notes_read and notes_list read the same notes');
    check($call(DeleteNote::class, 'member', ['id' => $written['note_id'], 'expected_version' => 1])['deleted'] === true, 'notes_delete deletes through the same operation');

    // The legacy-table attention step, and the import.
    $manifest = Blueprint::lineage('notes')['feature'];
    $steps = fn (): array => array_values(array_filter(HostRequirements::of(sys_get_temp_dir(), ['notes' => $manifest])['steps'], fn (array $step): bool => $step['kind'] === 'legacy_table'));
    check(array_column($steps(), 'status') === ['met', 'met'], 'with no legacy table, the legacy steps are met');
    Schema::create('notes', function (Illuminate\Database\Schema\Blueprint $t): void {
        $t->id();
        $t->foreignId('workspace_id');
        $t->string('body', 2000);
        $t->unsignedInteger('version')->default(1);
    });
    DB::table('notes')->insert([['workspace_id' => $workspace->id, 'body' => 'Legacy one'], ['workspace_id' => $workspace->id, 'body' => '  '], ['workspace_id' => 999, 'body' => 'Orphan'], ['workspace_id' => $beta->id, 'body' => 'Legacy beta']]);
    $legacy = $steps()[0];
    check($legacy['status'] === 'unmet' && $legacy['blocking'] === false && str_contains($legacy['message'], 'kit:notes:import --from=notes'), 'a legacy notes table is pointed out, non-blocking, with the import command');
    $before = [Schema::getColumnListing('notes'), DB::table('notes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    $import = function (array $options) use ($app): array {
        $command = new Tey\Kit\Features\Notes\Console\ImportNotesCommand;
        $command->setLaravel($app);
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $code = $command->run(new Symfony\Component\Console\Input\ArrayInput($options), $output);

        return [$code, json_decode(trim(collect(explode("\n", trim($output->fetch())))->last()), true)];
    };
    [$code, $dry] = $import(['--from' => 'notes', '--dry-run' => true]);
    check($code === 0 && $dry['copied'] === 2 && DB::table('kit_notes')->whereNotNull('imported_from')->count() === 0, 'a dry run reports and copies nothing');
    [$code, $first] = $import(['--from' => 'notes']);
    check($code === 0 && $first['copied'] === 2 && $first['empty'] === 1 && $first['no_workspace'] === 1 && $first['source_changed'] === false, 'the import copies the notes it can, and reports each skip');
    [$code, $again] = $import(['--from' => 'notes']);
    check($code === 0 && $again['copied'] === 0 && $again['already_imported'] === 2, 'a second import copies nothing twice');
    check([Schema::getColumnListing('notes'), DB::table('notes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()] === $before, 'the source table is never altered');
    $copied = $in($beta, fn () => Tey\Kit\Features\Notes\Models\Note::query()->where('imported_from', 'like', 'notes:%')->pluck('body')->all());
    check($copied === ['Legacy beta'], 'imported notes land in their own workspace');
    [$code] = $import(['--from' => 'kit_notes']);
    [$missing] = $import(['--from' => 'nope']);
    check($code === 1 && $missing === 1, 'importing from kit_notes itself, or a table that is not there, is refused');

    echo "Notes checks on SQLite memory with real Concierge, spatie teams, Pennant and medialibrary (local disk); HTTP through router dispatch without the global middleware stack. No browser: the composer, cards and widget are not rendered here, and the MCP tools' operations are called directly, not over the transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
