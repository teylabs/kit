<?php

// Direct-to-bucket uploads: the declaration's checks, the presigned PUT and what it signs, mutation replay, dedupe within a scope, confirm by HEAD and
// a header read (or a hash on a local disk), the multipart fallback, attaching over the existing key with no copy, and that deleting a Media item never
// deletes the object. SQLite memory, a temporary local disk and a stubbed presigner; no network.
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\KeptFileRemover;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\Operations\AttachUpload;
use Tey\Kit\Media\Operations\ConfirmUpload;
use Tey\Kit\Media\Operations\RequestUpload;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Slide extends Model implements HasMedia
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
    } catch (ValidationException $exception) {
        return 'validation:'.implode(',', array_keys($exception->errors()));
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
}

/** A small real PNG, so the header read finds a type and dimensions. */
function png(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
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
    Schema::create('slides', fn ($table) => $table->id());
    Schema::create(MediaUploads::table(), MediaUploads::schema(...));
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();

    // The "bucket" presigns through a stub that records what it was asked to sign; "plain" cannot presign.
    $signed = [];
    Storage::disk('bucket')->buildTemporaryUploadUrlsUsing(function (string $path, $expiration, array $options) use (&$signed): array {
        $signed[] = [$path, $options];

        return ['url' => 'https://bucket.test/'.$path.'?X-Amz-Expires=600', 'headers' => ['Host' => ['bucket.test'], 'Content-Length' => ['9'], 'x-amz-sdk-checksum-algorithm' => ['SHA256']]];
    });
    $rules = new MediaRules('bucket', 'app/slides', ['image/png' => 'png', 'image/webp' => 'webp'], 1_000_000);
    $body = png(40, 30);
    $sha = hash('sha256', $body);
    $bytes = strlen($body);

    // The declaration.
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'application/pdf', $bytes, $sha)) === 'validation:mime', 'a type the collection does not accept is refused');
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'image/png', 2_000_000, $sha)) === 'validation:bytes', 'a size over the limit is refused');
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'image/png', $bytes, 'abc')) === 'validation:sha256', 'a malformed checksum is refused');
    check(MediaUpload::query()->count() === 0, 'a refused declaration reserves nothing');

    // The presigned PUT.
    $ticket = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, strtoupper($sha), 'm-1');
    [$path, $options] = $signed[0];
    check($ticket->method === 'PUT' && $ticket->upload->status === 'pending' && $path === 'app/slides/'.substr($sha, 0, 2).'/'.$sha.'.png', 'a declaration reserves a pending record and presigns a PUT for a content-addressed key under the prefix');
    check($options['ContentType'] === 'image/png' && $options['ContentLength'] === $bytes && $options['ChecksumSHA256'] === base64_encode(hex2bin($sha)) && $options['CacheControl'] === RequestUpload::CACHE_CONTROL, 'the signature binds the type, length, SHA-256 checksum and immutable Cache-Control');
    check(! isset($ticket->headers['Host']) && ! isset($ticket->headers['Content-Length']) && $ticket->headers['x-amz-checksum-sha256'] === base64_encode(hex2bin($sha)) && $ticket->headers['x-amz-sdk-checksum-algorithm'] === 'SHA256', 'the client is told every signed header it must send, and not the ones a browser sets itself');
    check($ticket->toArray()['url'] !== null && ! str_contains(json_encode($ticket->toArray()), 'secret'), 'the ticket serialises for JSON and MCP');

    // Replay.
    $again = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'm-1');
    check($again->upload->id === $ticket->upload->id && count($signed) === 2 && MediaUpload::query()->count() === 1, 'replaying the mutation with the same declaration returns the same record with a fresh URL');
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'image/png', $bytes - 1, $sha, 'm-1')) === 'media.upload.mutation_conflict', 'replaying the mutation with a different declaration is refused');

    // Confirm.
    check(refusal(fn () => ConfirmUpload::run($ticket->upload)) === 'media.upload.missing' && $ticket->upload->fresh()->status === 'pending', 'confirming before the PUT lands leaves the record pending');
    Storage::disk('bucket')->put($path, $body);
    $ready = ConfirmUpload::run($ticket->upload->fresh());
    check($ready->status === 'ready' && $ready->width === 40 && $ready->height === 30, 'confirm checks the object and records its dimensions from the header bytes');
    check(ConfirmUpload::run($ready)->is($ready), 'confirming a ready record again returns it unchanged');

    $wrong = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, hash('sha256', 'other'), 'm-2');
    Storage::disk('bucket')->put($wrong->upload->key, $body);
    check(refusal(fn () => ConfirmUpload::run($wrong->upload)) === 'media.upload.mismatch' && ! Storage::disk('bucket')->exists($wrong->upload->key) && $wrong->upload->fresh()->status === 'pending', 'a body that does not match its declaration is deleted and the record left pending for the pruner');

    // Dedupe.
    $dupe = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'm-3');
    check($dupe->method === null && $dupe->upload->ready() && $dupe->upload->key === $ready->key && $dupe->upload->width === 40, 'the same content in the same scope is ready at once over the same object');
    check(RequestUpload::run('ws:2', $rules, 'image/png', $bytes, $sha)->method === 'PUT', 'another scope does not share it');

    // Fallback.
    $plain = new MediaRules('plain', 'app/slides', ['image/png' => 'png'], 1_000_000);
    $post = RequestUpload::run('ws:1', $plain, 'image/png', $bytes, $sha);
    check($post->method === 'POST' && $post->url === null, 'a disk that cannot presign gets a multipart ticket');
    file_put_contents($tmp = $base.'/upload.png', $body);
    check(refusal(fn () => ConfirmUpload::run($post->upload, new UploadedFile($tmp, 'x.png', 'image/png', null, true))) === null && Storage::disk('plain')->get($post->upload->key) === $body, 'the multipart body is hashed, checked and written to the key');
    file_put_contents($tmp, $body.'x');
    $bad = RequestUpload::run('ws:3', $plain, 'image/png', $bytes, $sha);
    check(refusal(fn () => ConfirmUpload::run($bad->upload, new UploadedFile($tmp, 'x.png', 'image/png', null, true))) === 'media.upload.mismatch' && count(Storage::disk('plain')->allFiles()) === 1, 'a multipart body that does not match is refused before it is written');

    // Attach, share and delete.
    $one = Slide::query()->create();
    $two = Slide::query()->create();
    check(refusal(fn () => AttachUpload::run($wrong->upload->fresh(), $one, 'images')) === 'media.upload.pending', 'a pending upload cannot be attached');
    $first = AttachUpload::run($ready, $one, 'images');
    check($first->getPath() === Storage::disk('bucket')->path($ready->key) && count(Storage::disk('bucket')->allFiles()) === 1, 'the Media item points at the existing key, with no byte copy');
    check(AttachUpload::run($ready, $one, 'images')->is($first), 'attaching the same upload to the same owner again returns the first item');
    $second = AttachUpload::run($dupe->upload, $two, 'images');
    $first->delete();
    check(Storage::disk('bucket')->exists($ready->key) && $second->fresh() !== null, 'deleting a Media item never deletes the shared object');
    $own = $two->addMediaFromString($body)->usingFileName('own.png')->toMediaCollection('own', 'plain');
    $ownPath = $own->getPath();
    $own->delete();
    check(! file_exists($ownPath), 'an item that owns its file is still removed as usual');

    echo "OK media uploads\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
