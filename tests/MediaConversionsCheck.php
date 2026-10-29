<?php

// Responsive conversions: declared WebP widths, fit within with no upscaling, queued one image per job on kit.media.queue with retries and a
// timeout, an attached upload queues its own; srcset lists only generated conversions with the original as fallback; regeneration is idempotent.
// The job is tenant-aware (spatie multitenancy): it fails loudly with no tenant and runs in the tenant it was dispatched in. SQLite memory, a
// temporary local disk, GD, a faked then a sync queue; no network.
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Queue;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
use Tey\Kit\Media\Concerns\HasResponsiveImages;
use Tey\Kit\Media\Jobs\Conversions;
use Tey\Kit\Media\Jobs\PerformConversions;
use Tey\Kit\Media\Srcset;
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

final class Team extends Model implements IsTenant
{
    use ImplementsTenant, Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;

    public $timestamps = false;

    protected $guarded = [];
}

final class Slide extends Model implements HasMedia
{
    use HasResponsiveImages, InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->responsiveWebp('slides', [20, 60]);
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
    $app->instance('config', new Illuminate\Config\Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'key' => 'base64:'.base64_encode(random_bytes(32)), 'cipher' => 'AES-256-CBC', 'env' => 'testing'],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']], 'prefix' => ''],
        'logging' => ['default' => 'test', 'channels' => ['test' => ['driver' => 'monolog', 'handler' => Monolog\Handler\TestHandler::class]]],
        'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => [
            'bucket' => ['driver' => 'local', 'root' => $base.'/bucket', 'throw' => false],
            'plain' => ['driver' => 'local', 'root' => $base.'/plain', 'throw' => false],
        ]],
        'multitenancy' => ['tenant_finder' => null, 'tenant_artisan_search_fields' => ['id'], 'switch_tenant_tasks' => [], 'tenant_model' => Team::class, 'queues_are_tenant_aware_by_default' => true, 'current_tenant_context_key' => 'tenantId', 'current_tenant_container_key' => 'currentTenant', 'tenant_database_connection_name' => null, 'landlord_database_connection_name' => null, 'shared_routes_cache' => false, 'actions' => [], 'queueable_to_job' => [], 'tenant_aware_interface' => Spatie\Multitenancy\Jobs\TenantAware::class, 'not_tenant_aware_interface' => Spatie\Multitenancy\Jobs\NotTenantAware::class, 'tenant_aware_jobs' => [], 'not_tenant_aware_jobs' => []],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Log\LogServiceProvider::class, Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Bus\BusServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    app(Spatie\Multitenancy\Multitenancy::class)->start();
    config(['media-library.path_generator' => KeyPathGenerator::class, 'media-library.file_remover_class' => KeptFileRemover::class, 'media-library.jobs.perform_conversions' => PerformConversions::class, 'media-library.queue_conversions_after_database_commit' => false]);
    Schema::create('teams', fn ($table) => $table->id());
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

    $rules = new MediaRules('bucket', 'app/slides', ['image/png' => 'png'], 1_000_000);
    $body = png(40, 30);
    $sha = hash('sha256', $body);
    $ticket = RequestUpload::run('ws:1', $rules, 'image/png', strlen($body), $sha);
    Storage::disk('bucket')->put($ticket->upload->key, $body);
    $upload = ConfirmUpload::run($ticket->upload);
    $slide = Slide::query()->create();

    // Queued, one image per job, on the media queue.
    $realQueue = app('queue');
    Queue::fake();
    $media = AttachUpload::run($upload, $slide, 'slides');
    $job = Queue::pushed(PerformConversions::class)->first();
    check(Queue::pushed(PerformConversions::class)->count() === 1 && $job->queue === 'media' && $job->tries === 3 && $job->timeout === 300 && $job->backoff === [30, 120, 600], 'attaching queues one conversion job for the image on kit.media.queue, with retries, backoff and a timeout');
    check(Srcset::for($media->fresh(), fn (Media $item, string $conversion) => '/m/'.$item->uuid.($conversion ? '/'.$conversion : ''))['srcset'] === '/m/'.$media->uuid.' 40w', 'before any conversion exists, srcset offers only the original');
    check(! $media->fresh()->hasGeneratedConversion('w20'), 'publishing does not wait for conversions');
    config(['kit.media.queue' => 'images']);
    Conversions::regenerate($media->fresh());
    check(Queue::pushed(PerformConversions::class)->last()->queue === 'images', 'the queue is kit.media.queue, whatever Media Library asks for');

    // Tenancy: loud with no tenant, and on the queue the tenant travels with the job.
    check(in_array(Spatie\Multitenancy\Jobs\TenantAware::class, class_implements(PerformConversions::class), true), 'the job is explicitly TenantAware, whatever the host default');
    check(refusal(fn () => $job->handle(app(Spatie\MediaLibrary\Conversions\FileManipulator::class))) === 'media.conversions.tenant_required' && ! $media->fresh()->hasGeneratedConversion('w20'), 'run with no current tenant, the job throws instead of converting');
    [$teamA, $teamB] = [Team::query()->create(), Team::query()->create()];
    try {
        Queue::swap($realQueue);
        dispatch(new PerformConversions(new Spatie\MediaLibrary\Conversions\ConversionCollection, $media->fresh()));
        $loud = false;
    } catch (Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob) {
        $loud = true;
    }
    check($loud, 'dispatched with no current tenant, the queued job fails loudly');
    $seen = null;
    Illuminate\Support\Facades\Event::listen(Illuminate\Queue\Events\JobProcessed::class, function () use (&$seen): void {
        $seen ??= 'after';
    });
    Illuminate\Support\Facades\Event::listen(Illuminate\Queue\Events\JobProcessing::class, function () use (&$seen): void {
        $seen = 'tenant '.Team::current()?->getKey();
    });
    // A block, not an arrow: dispatch() queues when its PendingDispatch is destroyed, which must happen inside the tenant.
    $teamB->execute(function () use ($media): void {
        dispatch(new PerformConversions(new Spatie\MediaLibrary\Conversions\ConversionCollection, $media->fresh()));
    });
    check($seen === 'tenant '.$teamB->id && Team::current() === null, 'queued in a tenant, the job runs in that tenant, and the previous context is restored');
    Queue::fake();

    // Run them, in a tenant.
    $teamA->makeCurrent();
    $job->handle(app(Spatie\MediaLibrary\Conversions\FileManipulator::class));
    $media = $media->fresh();
    $w20 = $media->getPath('w20');
    [$w, $h, $type] = getimagesize($w20);
    check($media->hasGeneratedConversion('w20') && $type === IMAGETYPE_WEBP && $w === 20 && $h === 15, 'a declared width is a WebP fit within that width');
    [$w] = getimagesize($media->getPath('w60'));
    check($w === 40, 'a width over the original is never upscaled');
    check(str_starts_with($w20, Storage::disk('bucket')->path('app/slides/'.substr($sha, 0, 2).'/conversions/'.$media->uuid.'/')), 'the conversions sit beside the key, under the item');
    $set = Srcset::for($media, fn (Media $item, string $conversion) => '/m/'.$item->uuid.($conversion ? '/'.$conversion : ''));
    check($set['srcset'] === '/m/'.$media->uuid.'/w20 20w, /m/'.$media->uuid.' 40w' && $set['src'] === '/m/'.$media->uuid && $set['width'] === 40 && $set['height'] === 30, 'srcset lists the generated widths below the original, then the original; the original is the src');
    check(! str_contains(json_encode($set), 'app/slides'), 'srcset carries the app route, never a bucket key');

    // Idempotent regeneration.
    Queue::fake();
    // A marker in place of the generated file shows whether it was rewritten.
    file_put_contents($w20, 'marker');
    Conversions::regenerate($media);
    Queue::pushed(PerformConversions::class)->first()->handle(app(Spatie\MediaLibrary\Conversions\FileManipulator::class));
    check(file_get_contents($w20) === 'marker', 'regenerating does only what is missing');
    unlink($w20);
    Conversions::regenerate($media->fresh());
    Queue::pushed(PerformConversions::class)->last()->handle(app(Spatie\MediaLibrary\Conversions\FileManipulator::class));
    check(file_exists($w20), 'a missing conversion is regenerated');

    $media->delete();
    check(! is_dir(dirname($w20)) && Storage::disk('bucket')->exists($upload->key), 'deleting the item removes its conversions and keeps the object');


    // Fails closed: never a silent finish with nothing made (a production finding: an unreadable original, a generator that declines).
    $loud = function (string $name) use ($rules, $teamA): array {
        $body = png(30, 20);
        $ticket = RequestUpload::run('ws:loud-'.$name, new MediaRules('bucket', 'app/loud-'.$name, ['image/png' => 'png'], 1_000_000), 'image/png', strlen($body), hash('sha256', $body));
        Storage::disk('bucket')->put($ticket->upload->key, $body);
        Queue::fake();
        $media = AttachUpload::run(ConfirmUpload::run($ticket->upload), Slide::query()->create(), 'slides');

        return [$media, Queue::pushed(PerformConversions::class)->first()];
    };
    $manipulator = app(Spatie\MediaLibrary\Conversions\FileManipulator::class);

    [$gone, $job] = $loud('gone');
    Storage::disk('bucket')->delete($gone->getCustomProperty('kit_key'));
    check(refusal(fn () => $job->handle($manipulator)) === 'media.conversions.unreadable_original', 'an original that cannot be read (a null stream on a disk that does not throw) fails the job instead of finishing silently');
    [$empty, $job] = $loud('empty');
    Storage::disk('bucket')->put($empty->getCustomProperty('kit_key'), '');
    check(refusal(fn () => $job->handle($manipulator)) === 'media.conversions.unreadable_original' && ! $empty->fresh()->hasGeneratedConversion('w20'), 'an empty original fails too, and nothing is marked generated');

    [$declined, $job] = $loud('declined');
    // A manipulator that returns without converting: spatie's own early returns (a generator declining) look like this.
    $declines = new class extends Spatie\MediaLibrary\Conversions\FileManipulator {
        public function __construct() {}

        public function performConversions(Spatie\MediaLibrary\Conversions\ConversionCollection $conversions, Media $media, bool $onlyMissing = false): Spatie\MediaLibrary\Conversions\FileManipulator
        {
            return $this;
        }
    };
    check(refusal(fn () => $job->handle($declines)) === 'media.conversions.none_generated', 'an image that ends with none of its conversions fails the job (so tries and backoff apply)');
    check(refusal(fn () => $job->handle($manipulator)) === null && $declined->fresh()->hasGeneratedConversion('w20'), 'the same job succeeds once the conversions are made');

    [$deleted, $job] = $loud('deleted');
    $serialized = serialize($job);
    $deleted->forceDelete();
    try {
        unserialize($serialized);
        $dropped = false;
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
        $dropped = true;
    }
    $records = app('log')->getLogger()->getHandlers()[0]->getRecords();
    $warning = array_values(array_filter($records, fn ($record) => $record->message === 'media.conversions.missing_model'))[0] ?? null;
    check($dropped && (new ReflectionProperty(PerformConversions::class, 'deleteWhenMissingModels'))->getValue($job) === true, 'a media row deleted before the job runs still drops the job (deleteWhenMissingModels)');
    check($warning !== null && $warning->level->name === 'Warning' && $warning->context['media_id'] === $deleted->id, 'and logs a warning naming the media id');
    Queue::swap($realQueue);


    // A PNG with a malformed iCCP (the production cause): libpng warns, Laravel's error handler makes that an exception, GD refuses it. TolerantImage strips the
    // metadata chunks from a temporary copy first; a PNG corrupt beyond its metadata still fails loudly.
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $clean = png(40, 30);
    $badIccp = substr($clean, 0, 33).$chunk('iCCP', "Broken\0\0".str_repeat("\xff", 40)).$chunk('tEXt', "Comment\0hello").substr($clean, 33);
    $stripped = Tey\Kit\Media\Generators\PngChunks::strip($badIccp);
    check($stripped === $clean, 'PngChunks removes the iCCP and tEXt chunks and nothing else (the result is the clean PNG, byte for byte)');
    check(Tey\Kit\Media\Generators\PngChunks::strip($clean) === $clean, 'a PNG without metadata chunks is returned unchanged');
    $corruptIhdr = $clean;
    $corruptIhdr[29] = chr(ord($corruptIhdr[29]) ^ 0xFF);
    check(Tey\Kit\Media\Generators\PngChunks::strip($corruptIhdr) === null, 'a critical chunk with a bad CRC (IHDR) is not "repaired": null, so it fails loudly');
    check(Tey\Kit\Media\Generators\PngChunks::strip(substr($clean, 0, 40)) === null && Tey\Kit\Media\Generators\PngChunks::strip('GIF89a') === null, 'a truncated file or a non-PNG is left alone');

    $convert = function (string $body, string $name, string $generator) use ($teamA): array {
        config(['media-library.image_generators' => [$generator]]);
        $ticket = RequestUpload::run('ws:png-'.$name, new MediaRules('bucket', 'app/png-'.$name, ['image/png' => 'png'], 1_000_000), 'image/png', strlen($body), hash('sha256', $body));
        Storage::disk('bucket')->put($ticket->upload->key, $body);
        Queue::fake();
        $media = AttachUpload::run(ConfirmUpload::run($ticket->upload), Slide::query()->create(), 'slides');
        $job = Queue::pushed(PerformConversions::class)->first();
        // As Laravel's HandleExceptions does: a PHP warning is an exception.
        set_error_handler(function (int $level, string $message, string $file = '', int $line = 0): bool {
            throw new ErrorException($message, 0, $level, $file, $line);
        });
        try {
            $outcome = refusal(fn () => $job->handle(app(Spatie\MediaLibrary\Conversions\FileManipulator::class)));
        } catch (Throwable $error) {
            $outcome = $error::class.': '.$error->getMessage();
        } finally {
            restore_error_handler();
        }

        return [$media->fresh(), $outcome, $ticket->upload->key];
    };
    // How libpng reports a malformed iCCP depends on the build (a PHP warning with one GD, straight to stderr with another; an error with a stricter one), so the
    // check is structural and holds on any of them: the cleaned copy has no metadata chunk for libpng to complain about, and it loads.
    $types = function (string $png): array {
        $found = [];
        for ($offset = 8; $offset + 8 <= strlen($png); $offset += 12 + unpack('N', substr($png, $offset, 4))[1]) {
            $found[] = substr($png, $offset + 4, 4);
        }

        return $found;
    };
    check(array_intersect(['iCCP', 'tEXt'], $types($badIccp)) === ['iCCP', 'tEXt'] && array_intersect(Tey\Kit\Media\Generators\PngChunks::DROPPED, $types((string) $stripped)) === [] && $types((string) $stripped) === $types($clean), 'the cleaned copy keeps every chunk but the metadata ones (IHDR, IDAT, IEND… as the clean PNG)');
    check(@imagecreatefromstring((string) $stripped) !== false, 'the cleaned copy loads');
    [$tolerant, $ok, $key] = $convert($badIccp, 'tolerant', Tey\Kit\Media\Generators\TolerantImage::class);
    check($ok === null && $tolerant->hasGeneratedConversion('w20') && getimagesize($tolerant->getPath('w20'))[2] === IMAGETYPE_WEBP, 'TolerantImage converts it: the WebP conversions are made');
    check(Storage::disk('bucket')->get($key) === $badIccp, 'the original object is untouched (only a temporary copy is cleaned)');
    // The client's production repro: a booted app, Laravel's HandleExceptions turning libpng's E_WARNING into an ErrorException mid-load. spatie/image before 3.9.9
    // (the client ran 3.9.6) loads with a bare imagecreatefromstring() and so reports CouldNotLoadImage; 3.9.9 (this lock) collects the warning itself. So the
    // "fails without" half is checked at the exact call older spatie/image makes, under Laravel's real handler; the "converts with" half runs the full conversion.
    $iccData = "icc\0\0".gzcompress(str_repeat('profile', 50)).'EXTRA-GARBAGE';
    $clientPng = substr($clean, 0, 33).pack('N', strlen($iccData)).'iCCP'.$iccData.pack('N', crc32('iCCP'.$iccData)).substr($clean, 33);
    $handled = new Illuminate\Foundation\Bootstrap\HandleExceptions;
    try {
        $handled->handleError(E_WARNING, 'probe', __FILE__, __LINE__);
        $laravelThrows = false;
    } catch (ErrorException) {
        $laravelThrows = true;
    }
    set_error_handler([$handled, 'handleError']);
    try {
        try {
            imagecreatefromstring($clientPng);
            $raw = 'loaded';
        } catch (ErrorException $error) {
            $raw = $error->getMessage();
        }
        try {
            $cleanLoad = imagecreatefromstring((string) Tey\Kit\Media\Generators\PngChunks::strip($clientPng)) !== false ? 'loaded' : 'false';
        } catch (ErrorException $error) {
            $cleanLoad = $error->getMessage();
        }
    } finally {
        restore_error_handler();
    }
    // Some GD builds print libpng's warnings to stderr instead of raising them in PHP (the PHP 8.5 CI runner's): there no PHP handler, Laravel's included, ever sees
    // them, so the PNG loads either way and there is nothing to fail. That half is skipped, out loud, on such a build.
    $phpWarnings = 0;
    set_error_handler(function () use (&$phpWarnings): bool {
        $phpWarnings++;

        return true;
    }, E_WARNING);
    imagecreatefromstring($clientPng);
    restore_error_handler();
    if ($phpWarnings > 0) {
        check($laravelThrows && str_contains($raw, 'iCCP'), 'without the tolerant generator: under Laravel\'s error handler the client\'s PNG throws mid-load, as spatie/image < 3.9.9 loads it ('.$raw.')');
    } else {
        check($raw === 'loaded', 'this GD build sends libpng warnings to stderr, not PHP: the client\'s PNG loads even under Laravel\'s handler (nothing to fail here)');
        echo "SKIP the \"fails without\" half: this GD build never raises libpng's warning in PHP\n";
    }
    check($cleanLoad === 'loaded', 'with it: the same PNG, cleaned, loads under Laravel\'s error handler');
    set_error_handler([$handled, 'handleError']);
    try {
        [$client, $clientOutcome] = $convert($clientPng, 'client', Tey\Kit\Media\Generators\TolerantImage::class);
    } finally {
        restore_error_handler();
    }
    check($clientOutcome === null && $client->hasGeneratedConversion('w20'), 'and the full conversion of the client\'s PNG succeeds through TolerantImage inside the booted app');
    [$corrupt, $loud] = $convert($corruptIhdr, 'corrupt', Tey\Kit\Media\Generators\TolerantImage::class);
    check($loud !== null && ! $corrupt->hasGeneratedConversion('w20'), 'a corrupt IHDR still fails loudly with TolerantImage: '.substr((string) $loud, 0, 80));
    $source = sys_get_temp_dir().'/kit-png-'.bin2hex(random_bytes(4)).'.png';
    file_put_contents($source, $badIccp);
    $cleanedPath = (new Tey\Kit\Media\Generators\TolerantImage)->convert($source);
    check($cleanedPath !== $source && file_get_contents($cleanedPath) === $stripped && file_get_contents($source) === $badIccp, 'convert() writes the cleaned copy beside the temporary file and leaves that file as it was');
    config(['kit.media.tolerant_png' => false]);
    check((new Tey\Kit\Media\Generators\TolerantImage)->convert($source) === $source, 'kit.media.tolerant_png false opts out: the PNG is passed on as it is');
    @unlink($source);
    @unlink($cleanedPath);
    config(['kit.media.tolerant_png' => true, 'media-library.image_generators' => [Spatie\MediaLibrary\Conversions\ImageGenerators\Image::class]]);
    Queue::swap($realQueue);

    echo "OK media conversions\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
