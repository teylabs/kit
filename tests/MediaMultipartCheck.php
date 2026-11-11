<?php

// Opt-in S3 multipart for direct uploads: off by default and byte-identical when off; below the threshold, without a `complete` route, on a non-S3
// disk or without the column it is the single PUT; above it, create, parts presigned (with per-part SHA-256 or without), complete, a streamed re-hash,
// confirm and attach; bad or missing ETags and checksums fail closed; a refused complete and a mismatch abort or delete; abort cleans up; scopes stay
// apart. SQLite memory, a temporary local disk and a fake multipart store that records every call; no AWS, no network.
use Aws\S3\S3Client;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\ValidationServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tey\Kit\Media\ConditionalCopyStore;
use Tey\Kit\Media\KeptFileRemover;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\MultipartStore;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\Operations\AbortMultipartUpload;
use Tey\Kit\Media\Operations\AttachUpload;
use Tey\Kit\Media\Operations\CompleteMultipartUpload;
use Tey\Kit\Media\Operations\ConfirmUpload;
use Tey\Kit\Media\Operations\RequestMultipartUpload;
use Tey\Kit\Media\Operations\RequestUpload;
use Tey\Kit\Media\S3MultipartStore;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

final class Slide extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];
}

/** Stands in for S3: keeps parts in memory, checks ETags and part checksums on complete, and writes the joined object to the local disk. */
final class FakeMultipartStore implements MultipartStore
{
    /** @var list<array<int, mixed>> */
    public array $calls = [];

    /** @var array<string, array<int, string>> */
    public array $received = [];

    public bool $refuseComplete = false;

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

    /** The client's PUT of one part; returns the ETag S3 would. */
    public function put(string $uploadId, int $part, string $body): string
    {
        $this->received[$uploadId][$part] = $body;

        return '"'.md5($body).'"';
    }

    public function complete(string $key, string $uploadId, array $parts): void
    {
        $this->calls[] = ['complete', $uploadId, $parts];
        $joined = '';
        foreach ($parts as $part) {
            $body = $this->received[$uploadId][$part['part']] ?? null;
            if ($this->refuseComplete || $body === null || $part['etag'] !== '"'.md5($body).'"' || ($part['checksum'] !== null && $part['checksum'] !== base64_encode(hash('sha256', $body, true)))) {
                throw new RuntimeException('InvalidPart');
            }
            $joined .= $body;
        }
        Storage::disk($this->disk)->put($key, $joined);
        unset($this->received[$uploadId]);
    }

    public function abort(string $key, string $uploadId): void
    {
        $this->calls[] = ['abort', $uploadId];
        unset($this->received[$uploadId]);
    }

    public function count(string $call): int
    {
        return count(array_filter($this->calls, fn (array $entry): bool => $entry[0] === $call));
    }
}

/** A store that also copies conditionally, recording each copy; `$plant` puts an object at the target first, as a racing writer would. */
final class ConditionalFakeStore implements ConditionalCopyStore, MultipartStore
{
    /** @var list<array{string, string}> */
    public array $copies = [];

    public ?string $plant = null;

    public function __construct(public FakeMultipartStore $inner, private string $disk) {}

    public function create(string $key, string $mime, string $cacheControl, bool $checksums): string
    {
        return $this->inner->create($key, $mime, $cacheControl, $checksums);
    }

    public function presignPart(string $key, string $uploadId, int $part, int $bytes, ?string $sha256, DateTimeInterface $expiresAt): array
    {
        return $this->inner->presignPart($key, $uploadId, $part, $bytes, $sha256, $expiresAt);
    }

    public function complete(string $key, string $uploadId, array $parts): void
    {
        $this->inner->complete($key, $uploadId, $parts);
    }

    public function abort(string $key, string $uploadId): void
    {
        $this->inner->abort($key, $uploadId);
    }

    public function copyIfAbsent(string $from, string $to, int $bytes): bool
    {
        $this->copies[] = [$from, $to];
        $this->plant !== null && Storage::disk($this->disk)->put($to, $this->plant);
        if (Storage::disk($this->disk)->exists($to)) {
            return false;
        }
        Storage::disk($this->disk)->copy($from, $to);

        return true;
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
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => [
            'bucket' => ['driver' => 'local', 'root' => $base.'/bucket', 'throw' => false],
            'plain' => ['driver' => 'local', 'root' => $base.'/plain', 'throw' => false],
        ]],
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

    Storage::disk('bucket')->buildTemporaryUploadUrlsUsing(fn (string $path): array => ['url' => 'https://bucket.test/'.$path, 'headers' => []]);
    $mib = 1024 * 1024;
    $rules = new MediaRules('bucket', 'app/videos', ['image/png' => 'png'], 50 * $mib);
    // A real PNG padded to 11 MiB and a bit: two parts of 5 MiB minimum.
    $body = str_pad(png(40, 30), 11 * $mib + 123, "\0");
    $sha = hash('sha256', $body);
    $bytes = strlen($body);
    $small = png(10, 10);

    // Off by default: byte-identical to RequestUpload.
    $store = new FakeMultipartStore('bucket');
    MultipartUploads::fake('bucket', $store);
    $plainTicket = RequestUpload::run('ws:0', $rules, 'image/png', $bytes, $sha, 'off-1')->toArray();
    $offTicket = RequestMultipartUpload::run('ws:0', $rules, 'image/png', $bytes, $sha, 'off-1', true)->toArray();
    unset($plainTicket['expires_at'], $offTicket['expires_at']);
    check($offTicket === $plainTicket && array_keys($offTicket) === ['id', 'status', 'method', 'url', 'headers'] && $store->calls === [], 'with the flag off a large file gets the same single PUT ticket, with the same keys, and S3 multipart is never called');

    config(['kit.media.multipart' => ['enabled' => true, 'threshold_bytes' => 5 * $mib, 'part_bytes' => 5 * $mib, 'part_checksums' => true]]);
    check(MultipartUploads::partCount($bytes) === 2 && MultipartUploads::partLength($bytes, 1) + MultipartUploads::partLength($bytes, 2) === $bytes && MultipartUploads::partLength($bytes, 1) >= 5 * $mib, 'the part layout covers the file in parts of at least the part size');
    config(['kit.media.multipart.part_bytes' => 1]);
    check(MultipartUploads::partBytes() === 5 * $mib, 'a part size under 5 MiB is raised to the S3 minimum');
    config(['kit.media.multipart.part_bytes' => 5 * $mib]);
    config(['kit.media.multipart.threshold_bytes' => 1024]);
    check(MultipartUploads::thresholdBytes() === 1024, 'a threshold under 5 MiB is kept as configured; only the part size has the S3 minimum');
    config(['kit.media.multipart.threshold_bytes' => 5 * $mib]);

    // The single PUT stays for everything that is not eligible.
    check(RequestMultipartUpload::run('ws:1', $rules, 'image/png', strlen($small), hash('sha256', $small), 'small', true)->method === 'PUT', 'a file under the threshold is a single PUT');
    check(RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'no-complete', false)->method === 'PUT' && $store->calls === [], 'without the client offering a complete route it is a single PUT');
    $other = new MediaRules('plain', 'app/videos', ['image/png' => 'png'], 50 * $mib);
    Storage::disk('plain')->buildTemporaryUploadUrlsUsing(fn (string $path): array => ['url' => 'https://plain.test/'.$path, 'headers' => []]);
    check(RequestMultipartUpload::run('ws:1', $other, 'image/png', $bytes, $sha, 'non-s3', true)->method === 'PUT' && MultipartUploads::store('plain') === null, 'a disk that is not S3 ignores the flag and gets the single PUT');
    config(['kit.media.uploads_table' => 'legacy_uploads']);
    // A table made before this release: every column but multipart_upload_id.
    Schema::create('legacy_uploads', function ($table): void {
        $table->ulid('id')->primary();
        foreach (['scope', 'disk', 'key', 'mime', 'status'] as $column) {
            $table->string($column);
        }
        $table->string('mutation_id')->nullable();
        $table->unsignedBigInteger('bytes');
        $table->char('sha256', 64);
        $table->unsignedInteger('width')->nullable();
        $table->unsignedInteger('height')->nullable();
        $table->timestamps();
    });
    MultipartUploads::fake('bucket', $store);
    check(RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'legacy', true)->method === 'PUT' && $store->calls === [], 'an uploads table without the multipart column keeps multipart off');
    config(['kit.media.uploads_table' => 'kit_media_uploads']);
    MultipartUploads::fake('bucket', $store);

    // The column check is remembered per connection and database, so switching to a tenant database without the column turns multipart off there.
    check(MultipartUploads::hasColumn(), 'the current database has the column');
    config(['database.connections.tenant_b' => ['driver' => 'sqlite', 'database' => $base.'/tenant-b.sqlite', 'prefix' => '']]);
    touch($base.'/tenant-b.sqlite');
    Schema::connection('tenant_b')->create('kit_media_uploads', fn ($table) => $table->ulid('id')->primary());
    config(['database.default' => 'tenant_b']);
    check(! MultipartUploads::hasColumn(), 'another database without the column is checked afresh, not read from the first one');
    config(['database.default' => 'testing']);
    check(MultipartUploads::hasColumn(), 'and back again');

    // Above the threshold: create, then parts (hashed by the client), then complete, a re-hash, confirm and attach.
    $first = RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, strtoupper($sha), 'big-1', true);
    $array = $first->toArray();
    check($first->method === 'MULTIPART' && $array['upload_id'] === 'mpu-1' && count($array['parts']) === 2 && $array['parts'][0]['url'] === null && $store->count('presign') === 0, 'the first request starts the upload and lists the parts with no URLs until their SHA-256s are sent');
    check($store->calls[0] === ['create', MultipartUploads::stagingKey($first->upload->key, $first->upload->id), 'image/png', RequestUpload::CACHE_CONTROL, true] && $first->upload->fresh()->multipart_upload_id === 'mpu-1', 'the upload is created with the type, immutable Cache-Control and SHA-256 part checksums, and its id recorded');
    $pieces = [substr($body, 0, MultipartUploads::partLength($bytes, 1)), substr($body, MultipartUploads::partLength($bytes, 1))];
    $hashes = array_map(fn (string $piece): string => hash('sha256', $piece), $pieces);
    check(refusal(fn () => RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'big-1', true, [$hashes[0]])) === 'validation:part_sha256', 'part hashes that do not cover every part are refused');
    $second = RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'big-1', true, $hashes);
    $parts = $second->toArray()['parts'];
    check($second->upload->id === $first->upload->id && $second->uploadId === 'mpu-1' && $store->count('create') === 1, 'asking again with the same mutation reuses the started upload');
    check($parts[1]['url'] !== null && $parts[0]['headers']['x-amz-checksum-sha256'] === base64_encode(hex2bin($hashes[0])) && $store->calls[1] === ['presign', 'mpu-1', 1, strlen($pieces[0]), base64_encode(hex2bin($hashes[0]))], 'each part URL signs its length and SHA-256');

    $etags = [$store->put('mpu-1', 1, $pieces[0]), $store->put('mpu-1', 2, $pieces[1])];
    Storage::disk('bucket')->put($first->upload->key, $body);
    check(refusal(fn () => ConfirmUpload::run($first->upload->fresh())) === 'media.upload.multipart_pending' && $first->upload->fresh()->status === 'pending', 'the ordinary confirm refuses an open multipart upload, so nothing is ready before the re-hash');
    Storage::disk('bucket')->delete($first->upload->key);
    $payload = [['part' => 1, 'etag' => $etags[0], 'checksum' => base64_encode(hex2bin($hashes[0]))], ['part' => 2, 'etag' => $etags[1], 'checksum' => base64_encode(hex2bin($hashes[1]))]];
    $before = count($store->calls);
    check(refusal(fn () => CompleteMultipartUpload::run($first->upload->fresh(), [$payload[0]])) === 'media.upload.bad_parts', 'a missing part is refused');
    check(refusal(fn () => CompleteMultipartUpload::run($first->upload->fresh(), [$payload[0], ['part' => 2, 'checksum' => $payload[1]['checksum']]])) === 'media.upload.bad_parts', 'a missing ETag is refused');
    check(refusal(fn () => CompleteMultipartUpload::run($first->upload->fresh(), [$payload[0], [...$payload[1], 'etag' => "bad\netag"]])) === 'media.upload.bad_parts', 'a malformed ETag is refused');
    check(refusal(fn () => CompleteMultipartUpload::run($first->upload->fresh(), [$payload[0], $payload[0]])) === 'media.upload.bad_parts', 'a repeated part is refused');
    check(refusal(fn () => CompleteMultipartUpload::run($first->upload->fresh(), [$payload[0], [...$payload[1], 'checksum' => null]])) === 'media.upload.bad_parts', 'a part without its checksum is refused while part checksums are on');
    check(count($store->calls) === $before && $first->upload->fresh()->multipart_upload_id === 'mpu-1', 'refused parts send nothing to S3 and keep the upload, so the client can send them again');
    check(refusal(fn () => CompleteMultipartUpload::run(RequestUpload::run('ws:1', $rules, 'image/png', strlen($small), hash('sha256', $small))->upload, $payload)) === 'media.upload.not_multipart', 'a single-PUT upload cannot be completed as multipart');

    $ready = CompleteMultipartUpload::run($first->upload->fresh(), array_reverse($payload));
    check($ready->ready() && $ready->width === 40 && $ready->multipart_upload_id === null && Storage::disk('bucket')->get($ready->key) === $body, 'complete joins the parts in order, re-hashes the object, and confirms it ready');
    $slide = Slide::query()->create();
    $media = AttachUpload::run($ready, $slide, 'videos');
    check($media->getPath() === Storage::disk('bucket')->path($ready->key), 'the completed upload attaches over its key like any other');
    check(CompleteMultipartUpload::run($ready, [])->is($ready), 'completing a ready upload again returns it unchanged');
    $dupe = RequestMultipartUpload::run('ws:1', $rules, 'image/png', $bytes, $sha, 'big-dupe', true);
    check($dupe->method === null && $dupe->upload->key === $ready->key && $store->count('create') === 1, 'the same content in the same scope is a dedupe hit with no new multipart upload');

    // A wrong ETag (S3 refuses the complete): abort, clear, refuse.
    $tampered = str_pad(png(40, 30), $bytes, "\1");
    $wrong = RequestMultipartUpload::run('ws:2', $rules, 'image/png', $bytes, $sha, 'big-2', true, $hashes);
    $id = $wrong->uploadId;
    $store->put($id, 1, $pieces[0]);
    $store->put($id, 2, $pieces[1]);
    check(refusal(fn () => CompleteMultipartUpload::run($wrong->upload->fresh(), [$payload[0], [...$payload[1], 'etag' => '"'.md5('other').'"']])) === 'media.upload.incomplete' && end($store->calls) === ['abort', $id] && $wrong->upload->fresh()->multipart_upload_id === null && $wrong->upload->fresh()->status === 'pending', 'an ETag S3 refuses fails closed: the upload is aborted, its id cleared and the record left pending');
    $restart = RequestMultipartUpload::run('ws:2', $rules, 'image/png', $bytes, $sha, 'big-2', true, $hashes);
    check($restart->uploadId !== $id && $restart->upload->id === $wrong->upload->id, 'the same mutation then starts a fresh multipart upload');

    // Content that is not the declared SHA-256 (part checksums off, as on R2 or MinIO): the re-hash refuses it and deletes the object.
    config(['kit.media.multipart.part_checksums' => false]);
    AbortMultipartUpload::run($restart->upload->fresh());
    $unsigned = RequestMultipartUpload::run('ws:3', $rules, 'image/png', $bytes, $sha, 'big-3', true);
    check($unsigned->parts[0]['url'] !== null && end($store->calls)[4] === null && $store->calls[array_key_last($store->calls) - 2][4] === false, 'with part checksums off the parts are signed at once, without a checksum');
    $bad = [substr($tampered, 0, strlen($pieces[0])), substr($tampered, strlen($pieces[0]))];
    $badTags = [$store->put($unsigned->uploadId, 1, $bad[0]), $store->put($unsigned->uploadId, 2, $bad[1])];
    check(refusal(fn () => CompleteMultipartUpload::run($unsigned->upload->fresh(), [['part' => 1, 'etag' => $badTags[0]], ['part' => 2, 'etag' => $badTags[1]]])) === 'media.upload.mismatch' && Storage::disk('bucket')->exists($ready->key) && $unsigned->upload->fresh()->status === 'pending', 'joined content that is not the declared SHA-256 is refused by the re-hash and the shared ready object is kept');
    $lonely = new MediaRules('bucket', 'app/other', ['image/png' => 'png'], 50 * $mib);
    $solo = RequestMultipartUpload::run('ws:3', $lonely, 'image/png', $bytes, $sha, 'big-4', true);
    $soloTags = [$store->put($solo->uploadId, 1, $bad[0]), $store->put($solo->uploadId, 2, $bad[1])];
    check(refusal(fn () => CompleteMultipartUpload::run($solo->upload->fresh(), [['part' => 1, 'etag' => $soloTags[0]], ['part' => 2, 'etag' => $soloTags[1]]])) === 'media.upload.mismatch' && ! Storage::disk('bucket')->exists($solo->upload->key), 'a mismatched object that no ready record shares is deleted');
    config(['kit.media.multipart.part_checksums' => true]);

    // Abort.
    $cancel = RequestMultipartUpload::run('ws:4', $lonely, 'image/png', $bytes, $sha, 'big-5', true);
    $store->put($cancel->uploadId, 1, $pieces[0]);
    $aborted = AbortMultipartUpload::run($cancel->upload->fresh());
    check(end($store->calls) === ['abort', $cancel->uploadId] && ! isset($store->received[$cancel->uploadId]) && $aborted->multipart_upload_id === null && $aborted->status === 'pending', 'abort drops the parts at S3 and clears the id, leaving the record for the pruner');
    $calls = count($store->calls);
    AbortMultipartUpload::run($aborted);
    check(count($store->calls) === $calls, 'aborting again does nothing');

    // Scopes stay apart: the same mutation id in another scope is its own record and its own multipart upload.
    $mine = RequestMultipartUpload::run('ws:5', $lonely, 'image/png', $bytes, $sha, 'shared-id', true);
    $theirs = RequestMultipartUpload::run('ws:6', $lonely, 'image/png', $bytes, $sha, 'shared-id', true);
    check($mine->upload->id !== $theirs->upload->id && $mine->uploadId !== $theirs->uploadId, 'another scope with the same mutation id gets its own record and multipart upload');

    // Staging: the parts are joined at a per-record staging key, never at the content key, so nothing a client sends can swap or overwrite a
    // content-addressed object; only a verified object is copied there, never over an existing one.
    $stagedRules = new MediaRules('bucket', 'app/staged', ['image/png' => 'png'], 50 * $mib);
    $begin = function (string $scope, string $tag, string $bytesIn) use ($stagedRules, $store, $hashes, $sha, $bytes): array {
        $ticket = RequestMultipartUpload::run($scope, $stagedRules, 'image/png', $bytes, $sha, $tag, true, $hashes);
        $split = [substr($bytesIn, 0, MultipartUploads::partLength($bytes, 1)), substr($bytesIn, MultipartUploads::partLength($bytes, 1))];
        $tags = [$store->put($ticket->uploadId, 1, $split[0]), $store->put($ticket->uploadId, 2, $split[1])];

        return [$ticket->upload->fresh(), [['part' => 1, 'etag' => $tags[0]], ['part' => 2, 'etag' => $tags[1]]]];
    };
    config(['kit.media.multipart.part_checksums' => false]);
    $conditional = new ConditionalFakeStore($store, 'bucket');
    MultipartUploads::fake('bucket', $conditional);
    [$a, $aParts] = $begin('ws:9', 'stage-a', $body);
    $staging = MultipartUploads::stagingKey($a->key, $a->id);
    check($staging === dirname($a->key).'/.staging/'.$a->id && end($store->calls)[0] === 'presign', 'the staging key is per record, beside the content key');
    $racer = null;
    $conditional->plant = null;
    $aReady = (function () use ($a, $aParts, &$racer) {
        // An ordinary confirm while complete runs is refused: the multipart id stays set until the object is in place.
        $racer = refusal(fn () => ConfirmUpload::run($a->fresh()));

        return CompleteMultipartUpload::run($a, $aParts);
    })();
    check($racer === 'media.upload.multipart_pending' && $aReady->ready() && $aReady->multipart_upload_id === null, 'a confirm before complete is refused; complete then confirms the record and clears its id');
    check($conditional->copies === [[$staging, $a->key]] && Storage::disk('bucket')->get($a->key) === $body && ! Storage::disk('bucket')->exists($staging), 'the verified staged object is copied to the content key with a conditional copy, and the staging object deleted');

    // Overwrite: another scope declares the same SHA-256 but sends other bytes of the same size and PNG header. They are refused at staging; the
    // ready object at the content key is never written to.
    [$b, $bParts] = $begin('ws:10', 'stage-b', $tampered);
    $copies = count($conditional->copies);
    check(refusal(fn () => CompleteMultipartUpload::run($b, $bParts)) === 'media.upload.mismatch' && Storage::disk('bucket')->get($a->key) === $body && count($conditional->copies) === $copies, 'other bytes for the same key are refused at staging, and the ready object is untouched');
    check(! Storage::disk('bucket')->exists(MultipartUploads::stagingKey($b->key, $b->id)) && $b->fresh()->status === 'pending' && $b->fresh()->multipart_upload_id === null, 'the refused staging object is deleted and the record left pending with no id');

    // The same bytes again while the key already holds them: the existing object is used (a HEAD for its size), not overwritten.
    [$c, $cParts] = $begin('ws:11', 'stage-c', $body);
    $cReady = CompleteMultipartUpload::run($c, $cParts);
    check($cReady->ready() && $cReady->key === $a->key && Storage::disk('bucket')->get($a->key) === $body && ! Storage::disk('bucket')->exists(MultipartUploads::stagingKey($c->key, $c->id)), 'verified bytes for a key that already holds them reuse the object and drop the staging copy');

    // An object that appears at the key just before the copy (here of another size) is never overwritten: it is re-hashed, refused, and, with no
    // ready record over it, deleted so a retry can place the verified bytes.
    $lonelyRules = new MediaRules('bucket', 'app/raced', ['image/png' => 'png'], 50 * $mib);
    $ticket = RequestMultipartUpload::run('ws:12', $lonelyRules, 'image/png', $bytes, $sha, 'stage-d', true);
    $dTags = [$store->put($ticket->uploadId, 1, $pieces[0]), $store->put($ticket->uploadId, 2, $pieces[1])];
    $conditional->plant = 'planted';
    check(refusal(fn () => CompleteMultipartUpload::run($ticket->upload->fresh(), [['part' => 1, 'etag' => $dTags[0]], ['part' => 2, 'etag' => $dTags[1]]])) === 'media.upload.mismatch' && count($conditional->copies) > 0 && ! Storage::disk('bucket')->exists($ticket->upload->key) && $ticket->upload->fresh()->status === 'pending', 'an object that appears at the key first is not overwritten but refused, and deleted when no ready record shares it');
    $conditional->plant = null;

    // Same-size wrong bytes already at the key (a multipart upload from before staging keys that died before its re-hash): re-hashed and refused.
    $legacyRules = new MediaRules('bucket', 'app/legacy', ['image/png' => 'png'], 50 * $mib);
    $ticket = RequestMultipartUpload::run('ws:14', $legacyRules, 'image/png', $bytes, $sha, 'stage-f', true);
    Storage::disk('bucket')->put($ticket->upload->key, $tampered);
    $fTags = [$store->put($ticket->uploadId, 1, $pieces[0]), $store->put($ticket->uploadId, 2, $pieces[1])];
    check(refusal(fn () => CompleteMultipartUpload::run($ticket->upload->fresh(), [['part' => 1, 'etag' => $fTags[0]], ['part' => 2, 'etag' => $fTags[1]]])) === 'media.upload.mismatch' && ! Storage::disk('bucket')->exists($ticket->upload->key) && $ticket->upload->fresh()->status === 'pending', 'same-size wrong bytes already at the key are re-hashed and refused, never trusted on size');

    // A store without a conditional copy: HEAD-then-copy through the disk.
    MultipartUploads::fake('bucket', $store);
    $plainRules = new MediaRules('bucket', 'app/plain-copy', ['image/png' => 'png'], 50 * $mib);
    $ticket = RequestMultipartUpload::run('ws:13', $plainRules, 'image/png', $bytes, $sha, 'stage-e', true);
    $eTags = [$store->put($ticket->uploadId, 1, $pieces[0]), $store->put($ticket->uploadId, 2, $pieces[1])];
    $eReady = CompleteMultipartUpload::run($ticket->upload->fresh(), [['part' => 1, 'etag' => $eTags[0]], ['part' => 2, 'etag' => $eTags[1]]]);
    check($eReady->ready() && Storage::disk('bucket')->get($eReady->key) === $body && ! Storage::disk('bucket')->exists(MultipartUploads::stagingKey($eReady->key, $eReady->id)), 'a store without a conditional copy copies after a HEAD, and drops the staging object');
    config(['kit.media.multipart.part_checksums' => true]);

    // The real S3 store, over a mocked S3 client (the SDK is optional, so a recording stand-in is declared when it is absent): the eligible disk,
    // the bucket and prefixed key, the signed part checksum, and what complete and abort send.
    if (! class_exists(S3Client::class)) {
        eval('namespace Aws\\S3; class S3Client { public array $calls = []; public function __call($name, $args) { $this->calls[] = [$name, $args[0] ?? null]; return ["UploadId" => "s3-1"]; } public function getCommand($name, $args) { $this->calls[] = ["getCommand", $name, $args]; return [$name, $args]; } public function createPresignedRequest($command, $expires) { $this->calls[] = ["presign", $command]; return new \\GuzzleHttp\\Psr7\\Request("PUT", "https://s3.test/".$command[1]["Key"]."?partNumber=".$command[1]["PartNumber"], ["Host" => "s3.test", "Content-Length" => "9", "x-amz-checksum-sha256" => $command[1]["ChecksumSHA256"] ?? "none"]); } }');
    }
    $client = new S3Client;
    $local = new LocalFilesystemAdapter($base.'/s3');
    $s3 = new AwsS3V3Adapter(new Filesystem($local), $local, ['bucket' => 'media-bucket', 'root' => 'tenant-a'], $client);
    Storage::set('s3', $s3);
    MultipartUploads::fake('bucket', null);
    check(MultipartUploads::store('s3') instanceof S3MultipartStore && MultipartUploads::store('bucket') === null, 'an S3 disk gets the S3 store; a local disk gets none');
    $real = MultipartUploads::store('s3');
    check($real->create('k/a.png', 'image/png', 'immutable', true) === 's3-1' && $client->calls[0] === ['createMultipartUpload', ['Bucket' => 'media-bucket', 'Key' => 'tenant-a/k/a.png', 'ContentType' => 'image/png', 'CacheControl' => 'immutable', 'ChecksumAlgorithm' => 'SHA256']], 'create sends the bucket, the prefixed key, the type, Cache-Control and SHA-256 checksums');
    $signedPart = $real->presignPart('k/a.png', 's3-1', 2, 6 * $mib, 'c2hh', now()->addMinutes(10));
    check($client->calls[1][2] === ['Bucket' => 'media-bucket', 'Key' => 'tenant-a/k/a.png', 'UploadId' => 's3-1', 'PartNumber' => 2, 'ContentLength' => 6 * $mib, 'ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => 'c2hh'] && $signedPart['headers'] === ['x-amz-checksum-sha256' => 'c2hh'] && str_contains($signedPart['url'], 'partNumber=2'), 'a part URL signs its number, length and checksum, and tells the client every header but Host and Content-Length');
    $real->complete('k/a.png', 's3-1', [['part' => 1, 'etag' => '"e1"', 'checksum' => 'c2hh'], ['part' => 2, 'etag' => '"e2"', 'checksum' => null]]);
    check(end($client->calls) === ['completeMultipartUpload', ['Bucket' => 'media-bucket', 'Key' => 'tenant-a/k/a.png', 'UploadId' => 's3-1', 'MultipartUpload' => ['Parts' => [['PartNumber' => 1, 'ETag' => '"e1"', 'ChecksumSHA256' => 'c2hh'], ['PartNumber' => 2, 'ETag' => '"e2"']]]]], 'complete sends each part number with its ETag and checksum');
    $real->abort('k/a.png', 's3-1');
    check(end($client->calls) === ['abortMultipartUpload', ['Bucket' => 'media-bucket', 'Key' => 'tenant-a/k/a.png', 'UploadId' => 's3-1']], 'abort sends the bucket, key and upload id');
    check($real->copyIfAbsent('k/.staging/01', 'k/a.png', 10) && end($client->calls) === ['copyObject', ['Bucket' => 'media-bucket', 'Key' => 'tenant-a/k/a.png', 'CopySource' => 'media-bucket/tenant-a/k/.staging/01', 'IfNoneMatch' => '*']], 'the conditional copy is one CopyObject from the staging key with If-None-Match: *');

    echo "OK media multipart\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
