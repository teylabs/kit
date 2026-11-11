<?php

// kit:uploads:prune: a dry run changes nothing; stale pending rows go and recent ones stay; an object shared with another upload row (any status or
// scope) or an attached Media item is kept while the stale row goes; ready rows are never pruned; a reference check that can't complete deletes
// nothing for that key and fails the run; the schedule is off unless configured. SQLite memory and a temporary local disk; no network.
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Media\Console\PruneUploadsCommand;
use Tey\Kit\Media\KeptFileRemover;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\Operations\AttachUpload;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Deck extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];
}

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** A ledger row over `$key` on the bucket, last touched `$hours` ago, with its object written when `$object`. */
function upload(string $scope, string $key, string $status, int $hours, bool $object = true): MediaUpload
{
    $object && Storage::disk('bucket')->put($key, str_repeat('x', 10));
    $upload = MediaUpload::query()->create(['scope' => $scope, 'disk' => 'bucket', 'key' => $key, 'mime' => 'image/png', 'bytes' => 10, 'sha256' => hash('sha256', $key), 'status' => $status]);
    $at = now()->subHours($hours);
    DB::table(MediaUploads::table())->where('id', $upload->id)->update(['created_at' => $at, 'updated_at' => $at]);

    return $upload->fresh();
}

/** @return array{0: int, 1: string} */
function prune(array $options = []): array
{
    $command = new PruneUploadsCommand;
    $command->setLaravel(app());
    $output = new BufferedOutput;
    $code = $command->run(new ArrayInput($options), $output);

    return [$code, $output->fetch()];
}

function rows(): int
{
    return MediaUpload::query()->count();
}

try {
    $base = sys_get_temp_dir().'/kit-prune-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => ['bucket' => ['driver' => 'local', 'root' => $base.'/bucket', 'throw' => false]]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class, DatabaseServiceProvider::class, BusServiceProvider::class, QueueServiceProvider::class, MediaLibraryServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    config(['media-library.path_generator' => KeyPathGenerator::class, 'media-library.file_remover_class' => KeptFileRemover::class, 'media-library.queue_conversions_by_default' => false]);
    Schema::create('decks', fn ($table) => $table->id());
    Schema::create(MediaUploads::table(), MediaUploads::schema(...));
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    $disk = Storage::disk('bucket');

    $stale = upload('ws:1', 'app/aa/stale.png', 'pending', 48);
    $recent = upload('ws:1', 'app/bb/recent.png', 'pending', 2);
    $ready = upload('ws:1', 'app/cc/ready.png', 'ready', 100);
    $sharedUpload = upload('ws:1', 'app/dd/shared.png', 'pending', 48);
    $otherScope = upload('ws:2', 'app/dd/shared.png', 'ready', 1);
    $sharedMedia = upload('ws:1', 'app/ee/attached.png', 'pending', 48);
    $attached = upload('ws:3', 'app/ee/attached.png', 'ready', 1);
    AttachUpload::run($attached, Deck::query()->create(), 'slides');
    $attached->delete(); // the Media item alone references the key now
    $twins = [upload('ws:1', 'app/ff/twin.png', 'pending', 30), upload('ws:2', 'app/ff/twin.png', 'pending', 50)];
    $never = upload('ws:1', 'app/gg/never-put.png', 'pending', 48, object: false);
    $before = [rows(), $disk->allFiles()];

    // Dry run.
    [$code, $output] = prune(['--dry-run' => true]);
    check($code === 0 && rows() === $before[0] && $disk->allFiles() === $before[1], 'a dry run changes no row and no object');
    check(str_contains($output, 'would delete 6 rows and 2 objects (20 bytes); 2 objects kept') && str_contains($output, 'bucket:app/aa/stale.png'), 'a dry run reports the rows, objects, bytes, kept objects and sample keys');

    // Prune.
    [$code, $output] = prune(['--chunk' => 2]);
    check($code === 0 && str_contains($output, 'deleted 6 rows and 2 objects'), 'the run reports what it deleted, across chunks');
    check(MediaUpload::query()->find($stale->id) === null && ! $disk->exists('app/aa/stale.png'), 'an old pending row and its unreferenced object are pruned');
    check(MediaUpload::query()->find($recent->id) !== null && $disk->exists('app/bb/recent.png'), 'a recent pending row and its object are kept');
    check(MediaUpload::query()->find($ready->id) !== null && $disk->exists('app/cc/ready.png'), 'a ready row is never pruned, however old');
    check(MediaUpload::query()->find($sharedUpload->id) === null && MediaUpload::query()->find($otherScope->id) !== null && $disk->exists('app/dd/shared.png'), 'a key another upload row references (another scope) keeps its object while the stale row goes');
    check(MediaUpload::query()->find($sharedMedia->id) === null && $disk->exists('app/ee/attached.png'), 'a key an attached Media item references keeps its object while the stale row goes');
    check(MediaUpload::query()->whereIn('id', [$twins[0]->id, $twins[1]->id])->count() === 0 && ! $disk->exists('app/ff/twin.png'), 'stale rows sharing a key are pruned together and their object goes');
    check(MediaUpload::query()->find($never->id) === null, 'a stale row whose object never landed is pruned');

    // --older-than.
    $mid = upload('ws:1', 'app/hh/mid.png', 'pending', 10);
    prune();
    check(MediaUpload::query()->find($mid->id) !== null, 'the default keeps a row younger than 24 hours');
    prune(['--older-than' => 6]);
    check(MediaUpload::query()->find($mid->id) === null && ! $disk->exists('app/hh/mid.png'), '--older-than lowers the age');
    check(prune(['--older-than' => 'x'])[0] !== 0 && prune(['--older-than' => '1.5'])[0] !== 0, 'a malformed or fractional --older-than is refused');
    config(['kit.media.prune_older_than' => 1.5]);
    check(prune()[0] !== 0, 'a fractional kit.media.prune_older_than is refused');
    config(['kit.media.prune_older_than' => null]);

    // Fail closed.
    $blind = upload('ws:1', 'app/ii/blind.png', 'pending', 48);
    Schema::rename('media', 'media_moved');
    [$code, $output] = prune();
    check($code !== 0 && MediaUpload::query()->find($blind->id) !== null && $disk->exists('app/ii/blind.png') && str_contains($output, 'not pruned: bucket:app/ii/blind.png'), 'a missing media table deletes nothing for the key, reports it and fails the run');
    Schema::rename('media_moved', 'media');
    $broken = MediaUpload::query()->create(['scope' => 'ws:1', 'disk' => 'missing-disk', 'key' => 'app/jj/broken.png', 'mime' => 'image/png', 'bytes' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'pending']);
    DB::table(MediaUploads::table())->where('id', $broken->id)->update(['updated_at' => now()->subHours(48)]);
    [$code, $output] = prune();
    check($code !== 0 && MediaUpload::query()->find($broken->id) !== null && MediaUpload::query()->find($blind->id) === null, 'a disk error deletes nothing for that key while other keys still prune');
    $broken->delete();
    $split = upload('ws:1', 'app/kk/split.png', 'pending', 48);
    config(['multitenancy.tenant_database_connection_name' => 'tenant']);
    prune();
    config(['multitenancy.tenant_database_connection_name' => null]);
    check(MediaUpload::query()->find($split->id) === null && $disk->exists('app/kk/split.png'), 'with separate tenant databases the row goes but the object is always kept');
    $tenantBlind = upload('ws:1', 'app/ll/tenant-blind.png', 'pending', 48);
    Schema::rename('media', 'media_moved');
    config(['multitenancy.tenant_database_connection_name' => 'tenant']);
    [$code] = prune();
    config(['multitenancy.tenant_database_connection_name' => null]);
    Schema::rename('media_moved', 'media');
    check($code !== 0 && MediaUpload::query()->find($tenantBlind->id) !== null && $disk->exists('app/ll/tenant-blind.png'), 'with separate tenant databases a missing media table still deletes nothing');
    $tenantBlind->delete();

    // A row confirmed between the first and second reference check is not deleted, and keeps its object.
    $racing = upload('ws:1', 'app/mm/racing.png', 'pending', 48);
    $listener = function ($query) use ($racing): void {
        static $done = false;
        if (! $done && str_contains($query->sql, 'select exists') && str_contains($query->sql, 'media"')) {
            $done = true;
            DB::table(MediaUploads::table())->where('id', $racing->id)->update(['status' => 'ready', 'updated_at' => now()]);
        }
    };
    DB::listen($listener);
    prune();
    check(MediaUpload::query()->find($racing->id)?->status === 'ready' && $disk->exists('app/mm/racing.png'), 'a row confirmed after the first check is kept with its object');
    Schema::drop(MediaUploads::table());
    check(prune()[0] !== 0, 'a missing ledger fails the run');

    // Schedule.
    $schedule = new Schedule;
    $events = fn () => collect($schedule->events())->filter(fn ($event) => str_contains($event->command ?? '', 'kit:uploads:prune'));
    PruneUploadsCommand::schedule($schedule);
    check($events()->isEmpty(), 'nothing is scheduled while kit.media.prune_schedule is unset');
    config(['kit.media.prune_schedule' => '0 4 * * *']);
    PruneUploadsCommand::schedule($schedule);
    check($events()->first()?->expression === '0 4 * * *', 'kit.media.prune_schedule registers the command at its cron');

    echo "OK media prune\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
