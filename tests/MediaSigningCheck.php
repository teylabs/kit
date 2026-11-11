<?php

// Opt-in `kit.media.signing`: `full` (the default) is byte-identical to before; `content_type` signs only Content-Type, uses a random key, keeps the
// declared SHA-256 as advisory metadata, never dedupes by it, and ConfirmUpload re-hashes the object (streamed) so a mismatch fails closed; it composes
// with S3 multipart (one re-hash, part checksums independent). Scopes stay apart. SQLite memory, a "bucket" disk that is NOT a local adapter (so
// the local-disk hash never applies), a stubbed presigner and a fake multipart store; no AWS, no network.
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\ValidationServiceProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tey\Kit\Media\KeptFileRemover;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartStore;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\Operations\AttachUpload;
use Tey\Kit\Media\Operations\CompleteMultipartUpload;
use Tey\Kit\Media\Operations\ConfirmUpload;
use Tey\Kit\Media\Operations\RequestMultipartUpload;
use Tey\Kit\Media\Operations\RequestUpload;
use Tey\Kit\Media\UploadSigning;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Slide extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];
}

/** A bucket stand-in: a local directory behind an adapter that is not LocalFilesystemAdapter, counting whole-object reads. */
final class BucketAdapter implements FlysystemAdapter
{
    public int $reads = 0;

    public function __construct(private LocalFilesystemAdapter $inner) {}

    public function fileExists(string $path): bool
    {
        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->inner->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        $this->reads++;

        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}

/** Stands in for S3 multipart: parts in memory, joined onto the disk on complete. */
final class FakeMultipartStore implements MultipartStore
{
    /** @var list<array<int, mixed>> */
    public array $calls = [];

    /** @var array<string, array<int, string>> */
    public array $received = [];

    private int $next = 0;

    public function __construct(private string $disk) {}

    public function create(string $key, string $mime, string $cacheControl, bool $checksums): string
    {
        $this->calls[] = ['create', $key, $mime, $cacheControl, $checksums];

        return 'mpu-'.(++$this->next);
    }

    public function presignPart(string $key, string $uploadId, int $part, int $bytes, ?string $sha256, DateTimeInterface $expiresAt): array
    {
        $this->calls[] = ['presign', $uploadId, $part, $bytes, $sha256];

        return ['url' => "https://bucket.test/{$key}?uploadId={$uploadId}&partNumber={$part}", 'headers' => $sha256 === null ? [] : ['x-amz-checksum-sha256' => $sha256]];
    }

    public function put(string $uploadId, int $part, string $body): string
    {
        $this->received[$uploadId][$part] = $body;

        return '"'.md5($body).'"';
    }

    public function complete(string $key, string $uploadId, array $parts): void
    {
        $this->calls[] = ['complete', $uploadId];
        $joined = '';
        foreach ($parts as $part) {
            $joined .= $this->received[$uploadId][$part['part']] ?? throw new RuntimeException('InvalidPart');
        }
        Storage::disk($this->disk)->put($key, $joined);
        unset($this->received[$uploadId]);
    }

    public function abort(string $key, string $uploadId): void
    {
        $this->calls[] = ['abort', $uploadId];
        unset($this->received[$uploadId]);
    }
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
    } catch (RuntimeException|InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return null;
}

function png(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

try {
    $base = sys_get_temp_dir().'/kit-media-signing-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->useStoragePath($base.'/storage');
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => []],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, FilesystemServiceProvider::class, CacheServiceProvider::class, DatabaseServiceProvider::class, ValidationServiceProvider::class, TranslationServiceProvider::class, BusServiceProvider::class, QueueServiceProvider::class, MediaLibraryServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    config(['media-library.path_generator' => KeyPathGenerator::class, 'media-library.file_remover_class' => KeptFileRemover::class, 'media-library.queue_conversions_by_default' => false]);
    Schema::create('slides', fn ($table) => $table->id());
    Schema::create(MediaUploads::table(), MediaUploads::schema(...));
    (require dirname(__DIR__).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();

    $adapter = new BucketAdapter(new LocalFilesystemAdapter($base.'/bucket'));
    $disk = new FilesystemAdapter(new Filesystem($adapter), $adapter, ['root' => $base.'/bucket']);
    Storage::set('bucket', $disk);
    $signed = [];
    $disk->buildTemporaryUploadUrlsUsing(function (string $path, $expiration, array $options) use (&$signed): array {
        $signed[] = [$path, $options];

        return ['url' => 'https://bucket.test/'.$path, 'headers' => ['Host' => ['bucket.test']]];
    });
    $rules = new MediaRules('bucket', 'app/slides', ['image/png' => 'png'], 50 * 1024 * 1024);
    $body = png(40, 30);
    $sha = hash('sha256', $body);
    $bytes = strlen($body);
    $tampered = str_pad(substr($body, 0, -4), $bytes, "\1");
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $selectsBySha = function () use (&$queries): int {
        return count(array_filter($queries, fn (string $sql): bool => str_starts_with(strtolower($sql), 'select') && str_contains($sql, '"sha256" = ?')));
    };

    // Default: unset and an explicit `full` are the same as before, the signature binds everything, and dedupe looks up the SHA-256.
    check(UploadSigning::mode() === 'full', 'the default signing mode is full');
    $unset = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'd-1');
    config(['kit.media.signing' => 'full']);
    $explicit = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'd-1');
    [$a, $b] = [$unset->toArray(), $explicit->toArray()];
    unset($a['expires_at'], $b['expires_at']);
    check($a === $b && $signed[0] === $signed[1], 'an explicit full is identical to the default');
    check($unset->upload->key === 'app/slides/'.substr($sha, 0, 2).'/'.$sha.'.png' && array_keys($signed[0][1]) === ['ContentType', 'ContentLength', 'CacheControl', 'ChecksumAlgorithm', 'ChecksumSHA256'] && array_keys($unset->headers) === ['Content-Type', 'Cache-Control', 'x-amz-checksum-sha256'], 'full keeps the content-addressed key and signs type, length, Cache-Control and the SHA-256');
    check($selectsBySha() === 1, 'full looks up ready content by SHA-256 for dedupe');
    $disk->put($unset->upload->key, $tampered);
    check(ConfirmUpload::run($unset->upload->fresh())->ready() && $adapter->reads === 1, 'full confirms a bucket object by size and header read only (the bucket proved the checksum), never a whole-object re-hash');
    $hit = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'd-2');
    check($hit->method === null && $hit->upload->key === $unset->upload->key, 'full still dedupes the same SHA-256 in a scope');

    // content_type: only Content-Type, a random key, an advisory SHA-256 and no dedupe.
    config(['kit.media.signing' => 'content_type']);
    $queries = [];
    $before = count($signed);
    $ticket = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, strtoupper($sha), 'c-1');
    [$path, $options] = $signed[$before];
    check($options === ['ContentType' => 'image/png'] && $ticket->headers === ['Content-Type' => 'image/png'] && $ticket->method === 'PUT', 'content_type signs only Content-Type and tells the client only that header');
    check(preg_match('#^app/slides/[0-9a-z]{26}\.png$#', $ticket->upload->key) === 1 && $path === $ticket->upload->key && ! str_contains($ticket->upload->key, $sha), 'content_type uses a random ULID key with the server-chosen extension, not the SHA-256');
    check($ticket->upload->status === 'pending' && $ticket->upload->sha256 === $sha && $selectsBySha() === 0, 'the same SHA-256 already ready in the scope is not reused or even looked up; the declared SHA-256 is stored as advisory metadata');
    $again = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'c-2');
    check($again->upload->key !== $ticket->upload->key && $again->method === 'PUT', 'a second upload of the same bytes gets its own key and its own PUT');
    $replay = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'c-1');
    check($replay->upload->id === $ticket->upload->id && $replay->headers === ['Content-Type' => 'image/png'], 'a replay returns the same record and the same signing');
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'image/png', $bytes, hash('sha256', 'x'), 'c-1')) === 'media.upload.mutation_conflict', 'a replay with a different SHA-256 is still refused');

    // Integrity rests on the re-hash: the object is read whole, and a mismatch fails closed.
    $disk->put($ticket->upload->key, $body);
    $reads = $adapter->reads;
    $ready = ConfirmUpload::run($ticket->upload->fresh());
    check($ready->ready() && $ready->width === 40 && $adapter->reads === $reads + 2, 'confirm re-hashes the object as a stream before marking it ready');
    $disk->put($again->upload->key, $tampered);
    check(refusal(fn () => ConfirmUpload::run($again->upload->fresh())) === 'media.upload.mismatch' && $again->upload->fresh()->status === 'pending' && ! $disk->exists($again->upload->key), 'an object that is not the declared SHA-256 (same size and type) fails closed: deleted and left pending');
    $media = AttachUpload::run($ready, Slide::query()->create(), 'slides');
    check($media->getCustomProperty(KeyPathGenerator::KEY) === $ready->key && $media->getCustomProperty('sha256') === $sha, 'a verified upload attaches over its random key like any other');

    // The record's key decides, not the setting: a random-key record confirmed after switching back to full is still re-hashed.
    $late = RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'c-3');
    config(['kit.media.signing' => 'full']);
    $disk->put($late->upload->key, $tampered);
    check(refusal(fn () => ConfirmUpload::run($late->upload->fresh())) === 'media.upload.mismatch', 'a content_type record confirmed after the setting changed is still re-hashed and refused');
    config(['kit.media.signing' => 'nope']);
    check(refusal(fn () => RequestUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'c-4')) === "kit.media.signing must be 'full' or 'content_type'." && MediaUpload::query()->where('mutation_id', 'c-4')->doesntExist(), 'an unknown signing mode is refused, reserving nothing');

    // Scopes stay apart: another scope's ready upload of the same bytes is never reused, in either mode.
    config(['kit.media.signing' => 'content_type']);
    $other = RequestUpload::run('ws:2', $rules, 'image/png', $bytes, $sha, 'c-1');
    check($other->upload->id !== $ticket->upload->id && $other->upload->scope === 'ws:2' && $other->method === 'PUT', 'the same mutation id in another scope is its own record');

    // With S3 multipart: a random key, part checksums as configured, one re-hash (in complete, not again in confirm), and a mismatch refused.
    $mib = 1024 * 1024;
    config(['kit.media.multipart' => ['enabled' => true, 'threshold_bytes' => 5 * $mib, 'part_bytes' => 5 * $mib, 'part_checksums' => true]]);
    $store = new FakeMultipartStore('bucket');
    MultipartUploads::fake('bucket', $store);
    $big = str_pad(png(40, 30), 11 * $mib + 123, "\0");
    $bigSha = hash('sha256', $big);
    $pieces = [substr($big, 0, MultipartUploads::partLength(strlen($big), 1)), substr($big, MultipartUploads::partLength(strlen($big), 1))];
    $hashes = array_map(fn (string $piece): string => hash('sha256', $piece), $pieces);
    $first = RequestMultipartUpload::run('ws:3', $rules, 'image/png', strlen($big), $bigSha, 'm-1', true);
    $second = RequestMultipartUpload::run('ws:3', $rules, 'image/png', strlen($big), $bigSha, 'm-1', true, $hashes);
    check($first->method === 'MULTIPART' && ! UploadSigning::contentAddressed($first->upload) && $store->calls[0][1] === $first->upload->key && $second->parts[0]['headers'] === ['x-amz-checksum-sha256' => base64_encode(hex2bin($hashes[0]))], 'content_type composes with multipart: a random key, and part checksums still signed while part_checksums is on');
    $tags = [$store->put('mpu-1', 1, $pieces[0]), $store->put('mpu-1', 2, $pieces[1])];
    $reads = $adapter->reads;
    $done = CompleteMultipartUpload::run($first->upload->fresh(), [['part' => 1, 'etag' => $tags[0], 'checksum' => base64_encode(hex2bin($hashes[0]))], ['part' => 2, 'etag' => $tags[1], 'checksum' => base64_encode(hex2bin($hashes[1]))]]);
    check($done->ready() && $adapter->reads === $reads + 2, 'complete re-hashes once and confirm does not read the whole object again');
    check(UploadSigning::needsRehash($first->upload->fresh()), 'the after-rehash mark is cleared once complete returns');
    $dupe = RequestMultipartUpload::run('ws:3', $rules, 'image/png', strlen($big), $bigSha, 'm-2', true);
    check($dupe->method === 'MULTIPART' && $dupe->upload->key !== $done->key, 'the same large content is not deduped in content_type mode');
    config(['kit.media.multipart.part_checksums' => false]);
    $unsigned = RequestMultipartUpload::run('ws:3', $rules, 'image/png', strlen($big), $bigSha, 'm-3', true);
    $bad = str_pad(png(40, 30), strlen($big), "\1");
    $badTags = [$store->put($unsigned->uploadId, 1, substr($bad, 0, strlen($pieces[0]))), $store->put($unsigned->uploadId, 2, substr($bad, strlen($pieces[0])))];
    check($unsigned->parts[0]['headers'] === [] && refusal(fn () => CompleteMultipartUpload::run($unsigned->upload->fresh(), [['part' => 1, 'etag' => $badTags[0]], ['part' => 2, 'etag' => $badTags[1]]])) === 'media.upload.mismatch' && ! $disk->exists($unsigned->upload->key), 'with both off (no signed checksum anywhere) the re-hash still refuses content that is not the declared SHA-256');

    echo "OK media signing\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
