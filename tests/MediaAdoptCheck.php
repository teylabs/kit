<?php

// Adopting existing objects: an item over the existing key with no copy, idempotent, refusing a missing object, never deleting it; the adopt command
// pattern's counts-only dry run, its id chunks as queued jobs on kit.media.queue, and a re-run that adopts nothing twice. SQLite memory, a temporary
// local disk, a faked then a sync queue; no network.
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Kit\Media\Console\AdoptMediaCommand;
use Tey\Kit\Media\Jobs\AdoptChunk;
use Tey\Kit\Media\Operations\AdoptObject;
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

final class AdoptSlides extends AdoptMediaCommand
{
    protected $name = 'slides:adopt';

    protected string $collection = 'slides';

    protected function owners(): Builder
    {
        return Slide::query();
    }

    protected function source(Model $owner): ?array
    {
        return $owner->legacy_key === null ? null : ['bucket', $owner->legacy_key];
    }
}

/** Runs the command and returns its output. */
function adopt(Illuminate\Foundation\Application $app, array $options = []): string
{
    $command = new AdoptSlides;
    $command->setLaravel($app);
    $command->run(new ArrayInput($options), $output = new BufferedOutput);

    return $output->fetch();
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
    Schema::create('slides', function ($table) {
        $table->id();
        $table->string('legacy_key')->nullable();
    });
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

    $body = png(40, 30);
    Storage::disk('bucket')->put('legacy/decks/1/slide-1.png', $body);
    Storage::disk('bucket')->put('legacy/decks/1/slide-2.png', $body);
    $one = Slide::query()->create(['legacy_key' => 'legacy/decks/1/slide-1.png']);

    // The operation.
    $media = AdoptObject::run($one, 'slides', 'bucket', 'legacy/decks/1/slide-1.png');
    check($media->getPath() === Storage::disk('bucket')->path('legacy/decks/1/slide-1.png') && count(Storage::disk('bucket')->allFiles()) === 2, 'an object is adopted over its existing key, with no copy');
    check($media->mime_type === 'image/png' && $media->size === strlen($body) && $media->getCustomProperty('width') === 40, 'its type, size and dimensions come from a probe');
    check(AdoptObject::run($one, 'slides', 'bucket', 'legacy/decks/1/slide-1.png')->is($media) && $one->media()->count() === 1, 'adopting the same key again returns the first item');
    check(refusal(fn () => AdoptObject::run($one, 'slides', 'bucket', 'legacy/nope.png')) === 'media.adopt.missing', 'an absent object is refused');
    $media->delete();
    check(Storage::disk('bucket')->exists('legacy/decks/1/slide-1.png'), 'deleting an adopted item never deletes the object');

    // The command.
    foreach (['legacy/decks/1/slide-2.png', 'legacy/decks/1/slide-1.png', 'legacy/gone.png', null, 'legacy/decks/1/slide-2.png'] as $key) {
        Slide::query()->create(['legacy_key' => $key]);
    }
    AdoptObject::run(Slide::query()->find(2), 'slides', 'bucket', 'legacy/decks/1/slide-2.png');
    $before = Media::query()->count();
    $dry = adopt($app, ['--dry-run' => true, '--chunk' => '2']);
    check($dry === "owners: 6\nadopted: 1\nwould_adopt: 3\nmissing: 1\nno_source: 1\n" && Media::query()->count() === $before, 'a dry run writes nothing and prints counts only');
    check(! str_contains($dry, 'legacy/'), 'a dry run prints no key');

    Queue::fake();
    $queued = adopt($app, ['--chunk' => '2']);
    $jobs = Queue::pushed(AdoptChunk::class);
    check($queued === "chunks queued: 3\n" && $jobs->count() === 3 && $jobs->every(fn ($job) => $job->queue === 'media') && $jobs->first()->ids === [1, 2], 'owners are read in id chunks, one queued job per chunk on kit.media.queue');
    check(Media::query()->count() === $before, 'queueing adopts nothing yet');
    $counts = array_map(fn ($job) => $job->handle(), $jobs->all());
    check(array_sum(array_column($counts, 'adopted')) === 3 && array_sum(array_column($counts, 'missing')) === 1 && array_sum(array_column($counts, 'skipped')) === 2, 'the chunks adopt each object, skip the adopted and sourceless, and count the missing');
    check(Media::query()->count() === $before + 3 && Slide::query()->find(6)->getFirstMedia('slides')->getCustomProperty('kit_key') === 'legacy/decks/1/slide-2.png', 'two owners of the same object each get an item over it');
    $counts = array_map(fn ($job) => $job->handle(), $jobs->all());
    check(array_sum(array_column($counts, 'adopted')) === 0 && Media::query()->count() === $before + 3, 'running the chunks again adopts nothing twice');
    check(adopt($app, ['--sync' => true]) === "chunks run: 1\n" && Media::query()->count() === $before + 3, '--sync runs the chunks inline, and a re-run is a no-op');
    check(count(Storage::disk('bucket')->allFiles()) === 2, 'no object was copied, moved or deleted');

    echo "OK media adopt\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
