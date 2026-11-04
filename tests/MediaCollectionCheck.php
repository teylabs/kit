<?php

// A record's collection (docs/media.md): MediaCollection lists the owner's items in order with a version; ReorderMedia, MoveMedia and RemoveMedia act only on
// the owner's own items, fail closed on anything else, and check the version. SQLite memory and a temporary local disk; no network.
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\KeptFileRemover;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\MediaCollection;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\Operations\MoveMedia;
use Tey\Kit\Media\Operations\RemoveMedia;
use Tey\Kit\Media\Operations\ReorderMedia;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Listing extends Model implements HasMedia
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

function refusal(callable $operation): ?string
{
    try {
        $operation();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
}

/** A Media item over an object in the bucket, as AttachUpload writes one. */
function item(Listing $owner, string $collection, string $name, string $mime = 'image/png'): Media
{
    $key = 'app/listings/'.$name.'.png';
    Storage::disk('bucket')->put($key, 'bytes');
    $media = new Media;
    $media->forceFill([
        'model_type' => $owner->getMorphClass(), 'model_id' => $owner->getKey(), 'uuid' => (string) Str::uuid(), 'collection_name' => $collection, 'name' => $name,
        'file_name' => basename($key), 'mime_type' => $mime, 'disk' => 'bucket', 'conversions_disk' => 'bucket', 'size' => 5, 'manipulations' => [],
        'generated_conversions' => [], 'responsive_images' => [], 'custom_properties' => [KeyPathGenerator::KEY => $key, 'width' => 40, 'height' => 30],
        'order_column' => count(MediaCollection::items($owner, $collection)) + 1,
    ])->save();

    return $media;
}

try {
    $base = sys_get_temp_dir().'/kit-media-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->instance('env', 'testing');
    $app->instance('config', new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => [
            'bucket' => ['driver' => 'local', 'root' => $base.'/bucket', 'throw' => false],
            'plain' => ['driver' => 'local', 'root' => $base.'/plain', 'throw' => false],
        ]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Bus\BusServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    config(['media-library.path_generator' => KeyPathGenerator::class, 'media-library.file_remover_class' => KeptFileRemover::class, 'media-library.queue_conversions_by_default' => false]);
    Schema::create('listings', fn ($table) => $table->id());
    Schema::create(MediaUploads::table(), MediaUploads::schema(...));
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();

    $a = Listing::query()->create();
    $b = Listing::query()->create();
    [$one, $two, $three] = [item($a, 'photos', 'one'), item($a, 'photos', 'two'), item($a, 'photos', 'three')];
    $theirs = item($b, 'photos', 'theirs');
    $url = fn (Media $media, string $conversion): string => '/media/'.$media->getKey().($conversion === '' ? '' : '/'.$conversion);

    // Listing.
    $listed = MediaCollection::for($a, 'photos', $url);
    check(array_column($listed['items'], 'name') === ['one', 'two', 'three'] && $listed['collection'] === 'photos', 'the collection lists only the owner\'s items, in order');
    check($listed['items'][0]['src'] === '/media/'.$one->getKey() && ! str_contains(json_encode($listed), 'app/listings'), 'items carry the app\'s delivery URL, never a bucket key');
    check($listed['version'] === MediaCollection::version($a, 'photos') && $listed['version'] !== MediaCollection::version($b, 'photos'), 'the version is the owner\'s collection\'s own');

    // Reorder.
    $v = $listed['version'];
    check(refusal(fn () => ReorderMedia::run($a, 'photos', [$three->id, $one->id, $theirs->id], $v)) === 'media.order.mismatch', 'an id from another record is refused, never reordered');
    check(refusal(fn () => ReorderMedia::run($a, 'photos', [$three->id, $one->id], $v)) === 'media.order.mismatch', 'an order missing an item is refused');
    check(refusal(fn () => ReorderMedia::run($a, 'photos', [$three->id, $one->id, $one->id], $v)) === 'media.order.mismatch', 'an order naming an item twice is refused');
    check((int) $theirs->fresh()->order_column === 1, 'a refused reorder changes nothing on the other record');
    $v2 = ReorderMedia::run($a, 'photos', [$three->id, $one->id, $two->id], $v);
    check(array_column(MediaCollection::for($a, 'photos')['items'], 'name') === ['three', 'one', 'two'] && $v2 !== $v, 'a reorder applies and returns a new version');
    check(refusal(fn () => ReorderMedia::run($a, 'photos', [$one->id, $two->id, $three->id], $v)) === 'media.order.stale', 'a reorder with the old version is stale (409)');

    // Move.
    check(refusal(fn () => MoveMedia::run($a, $theirs->id, 'cover', $v2)) === 'media.move.not_found', 'moving another record\'s item is refused');
    check(refusal(fn () => MoveMedia::run($a, $one->id, 'cover', $v)) === 'media.move.stale', 'a move with a stale source version is refused');
    $pdfOnly = new MediaRules('bucket', 'app/docs', ['application/pdf' => 'pdf'], 1_000_000);
    check(refusal(fn () => MoveMedia::run($a, $one->id, 'documents', $v2, $pdfOnly)) === 'media.move.type' && $one->fresh()->collection_name === 'photos', 'a type the target does not accept stays put');
    $moved = MoveMedia::run($a, $one->id, 'cover', $v2, single: true);
    check($moved->collection_name === 'cover' && Storage::disk('bucket')->exists('app/listings/one.png'), 'a move changes the collection over the same object, with no byte copy');
    check(array_column(MediaCollection::for($a, 'photos')['items'], 'name') === ['three', 'two'], 'the source keeps its other items');
    $v3 = MediaCollection::version($a, 'photos');
    check(refusal(fn () => MoveMedia::run($a, $two->id, 'cover', $v3, single: true)) === 'media.move.full', 'a full single-file target refuses a second item');
    MoveMedia::run($a, $two->id, 'cover', $v3, single: true, replace: true);
    check(array_column(MediaCollection::for($a, 'cover')['items'], 'name') === ['two'] && Media::query()->find($one->id) === null, 'replace removes the occupant first');
    check(Storage::disk('bucket')->exists('app/listings/one.png'), 'the replaced item\'s object stays in the bucket');

    // Remove.
    check(refusal(fn () => RemoveMedia::run($a, $theirs->id)) === 'media.remove.not_found' && Media::query()->find($theirs->id) !== null, 'removing another record\'s item is refused');
    RemoveMedia::run($a, $three->id);
    check(MediaCollection::for($a, 'photos')['items'] === [] && Storage::disk('bucket')->exists('app/listings/three.png'), 'remove deletes the item, never the object');
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
    exit(1);
}
