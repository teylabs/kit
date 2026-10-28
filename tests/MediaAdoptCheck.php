<?php

// Adopting existing objects: an item over the existing key with no copy, idempotent, refusing a missing object, never deleting it; the adopt command
// pattern, tenant-aware through spatie/laravel-multitenancy: no current tenant is a loud failure, two tenants each adopt only their own, per-tenant
// counts-only dry runs, a tenant whose source has rows but no owners is a failing warning, id chunks as tenant-aware queued jobs on kit.media.queue,
// and a re-run that adopts nothing twice. SQLite memory, a temporary local disk, a faked then a sync queue; no network.
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
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

final class Team extends Model implements IsTenant
{
    use ImplementsTenant, Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;

    public $timestamps = false;

    protected $guarded = [];
}

/** Scoped to the current tenant the way a client app might scope it: with no tenant it silently finds nothing. */
final class Slide extends Model implements HasMedia
{
    use InteractsWithMedia;

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', fn ($query) => $query->where('team_id', Team::current()?->getKey() ?? 0));
    }
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

    protected function sourceRows(): ?int
    {
        return Illuminate\Support\Facades\DB::table('slides')->where('team_id', Team::current()->getKey())->count();
    }
}

/** An owners() that misses every row: the silent-zero case. */
final class AdoptNothing extends AdoptMediaCommand
{
    protected $name = 'slides:adopt-nothing';

    protected string $collection = 'slides';

    protected function owners(): Builder
    {
        return Slide::query()->whereRaw('1 = 0');
    }

    protected function source(Model $owner): ?array
    {
        return null;
    }

    protected function sourceRows(): ?int
    {
        return Illuminate\Support\Facades\DB::table('slides')->where('team_id', Team::current()->getKey())->count();
    }
}

/** Runs the command; returns its exit code and output. */
function adopt(Illuminate\Foundation\Application $app, array $options = [], string $class = AdoptSlides::class): array
{
    $command = new $class;
    $command->setLaravel($app);
    $code = $command->run(new ArrayInput($options), $output = new BufferedOutput);

    return [$code, $output->fetch()];
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
        'multitenancy' => ['tenant_finder' => null, 'tenant_artisan_search_fields' => ['id'], 'switch_tenant_tasks' => [], 'tenant_model' => Team::class, 'queues_are_tenant_aware_by_default' => true, 'current_tenant_context_key' => 'tenantId', 'current_tenant_container_key' => 'currentTenant', 'tenant_database_connection_name' => null, 'landlord_database_connection_name' => null, 'shared_routes_cache' => false, 'actions' => [], 'queueable_to_job' => [], 'tenant_aware_interface' => Spatie\Multitenancy\Jobs\TenantAware::class, 'not_tenant_aware_interface' => Spatie\Multitenancy\Jobs\NotTenantAware::class, 'tenant_aware_jobs' => [], 'not_tenant_aware_jobs' => []],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Bus\BusServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class, Illuminate\Log\Context\ContextServiceProvider::class, Spatie\Multitenancy\MultitenancyServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->boot();
    app(Spatie\Multitenancy\Multitenancy::class)->start();
    config(['media-library.path_generator' => KeyPathGenerator::class, 'media-library.file_remover_class' => KeptFileRemover::class, 'media-library.queue_conversions_by_default' => false]);
    Schema::create('teams', fn ($table) => $table->id());
    Schema::create('slides', function ($table) {
        $table->id();
        $table->unsignedBigInteger('team_id');
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
    foreach (['a/1.png', 'a/2.png', 'b/1.png'] as $key) {
        Storage::disk('bucket')->put('legacy/'.$key, $body);
    }
    [$teamA, $teamB, $teamC] = [Team::query()->create(), Team::query()->create(), Team::query()->create()];

    // The operation (tenant-neutral: it is handed its owner).
    $one = $teamA->execute(fn () => Slide::query()->create(['team_id' => $teamA->id, 'legacy_key' => 'legacy/a/1.png']));
    $media = AdoptObject::run($one, 'slides', 'bucket', 'legacy/a/1.png');
    check($media->getPath() === Storage::disk('bucket')->path('legacy/a/1.png') && count(Storage::disk('bucket')->allFiles()) === 3, 'an object is adopted over its existing key, with no copy');
    check($media->mime_type === 'image/png' && $media->size === strlen($body) && $media->getCustomProperty('width') === 40, 'its type, size and dimensions come from a probe');
    check(AdoptObject::run($one, 'slides', 'bucket', 'legacy/a/1.png')->is($media) && $one->media()->count() === 1, 'adopting the same key again returns the first item');
    check(refusal(fn () => AdoptObject::run($one, 'slides', 'bucket', 'legacy/nope.png')) === 'media.adopt.missing', 'an absent object is refused');
    $media->delete();
    check(Storage::disk('bucket')->exists('legacy/a/1.png'), 'deleting an adopted item never deletes the object');

    // Rows in two tenants: A has two objects, a missing one and a sourceless row; B has one object, also referenced by A's key list.
    $teamA->execute(function () use ($teamA): void {
        foreach (['legacy/a/2.png', 'legacy/gone.png', null] as $key) {
            Slide::query()->create(['team_id' => $teamA->id, 'legacy_key' => $key]);
        }
    });
    $teamB->execute(fn () => Slide::query()->create(['team_id' => $teamB->id, 'legacy_key' => 'legacy/b/1.png']));

    // No tenant is loud, never a silent zero.
    check(refusal(fn () => (new AdoptChunk(AdoptSlides::class, [1, 2]))->handle()) === 'media.adopt.tenant_required' && Media::query()->count() === 0, 'an adopt job with no current tenant throws instead of adopting nothing');
    check(refusal(fn () => (function () { $command = new AdoptSlides; $command->setLaravel(app()); $command->handle(); })()) === 'media.adopt.tenant_required', 'the command body refuses to run outside a tenant');
    check(in_array(Spatie\Multitenancy\Jobs\TenantAware::class, class_implements(AdoptChunk::class), true), 'the job is explicitly TenantAware, whatever the host default');
    config(['multitenancy.queues_are_tenant_aware_by_default' => false]);
    try {
        dispatch(new AdoptChunk(AdoptSlides::class, [1]));
        $loud = false;
    } catch (Spatie\Multitenancy\Exceptions\CurrentTenantCouldNotBeDeterminedInTenantAwareJob) {
        $loud = true;
    }
    check($loud && Media::query()->count() === 0, 'dispatched with no current tenant, the queued job fails loudly even with tenant-aware queues off by default');
    config(['multitenancy.queues_are_tenant_aware_by_default' => true]);

    // Dry run: per tenant, counts only.
    [$code, $dry] = adopt($app, ['--dry-run' => true, '--chunk' => '2', '--tenant' => [$teamA->id, $teamB->id]]);
    check($code === 0 && $dry === "tenant 1: owners 4, adopted 0, would_adopt 2, missing 1, no_source 1\ntenant 2: owners 1, adopted 0, would_adopt 1, missing 0, no_source 0\n" && Media::query()->count() === 0, 'a dry run prints each tenant its own counts and writes nothing');
    check(! str_contains($dry, 'legacy/'), 'a dry run prints no key');
    check(Team::current() === null, 'the command leaves no tenant current');

    // Silent zero is a failing warning.
    [$code, $warned] = adopt($app, ['--dry-run' => true, '--tenant' => [$teamA->id]], AdoptNothing::class);
    check($code !== 0 && str_contains($warned, 'tenant 1: 0 owners found, but the source has 4 rows'), 'a tenant whose source has rows but no owners is a warning and a failing exit');

    // Queued: chunks per tenant, on kit.media.queue.
    $realQueue = app('queue');
    Queue::fake();
    [$code, $queued] = adopt($app, ['--chunk' => '2', '--tenant' => [$teamA->id, $teamB->id]]);
    $jobs = Queue::pushed(AdoptChunk::class);
    check($code === 0 && $queued === "tenant 1: chunks queued 2\ntenant 2: chunks queued 1\n" && $jobs->count() === 3 && $jobs->every(fn ($job) => $job->queue === 'media'), 'each tenant\'s owners are read in id chunks, one queued job per chunk on kit.media.queue');
    Queue::swap($realQueue);

    // Real tenant-aware dispatch: each chunk runs in the tenant it was dispatched in, and each tenant adopts only its own.
    [$code, $ran] = adopt($app, ['--chunk' => '2']);
    check($code === 0 && $ran === "tenant 1: chunks queued 2\ntenant 2: chunks queued 1\ntenant 3: chunks queued 0\n", 'by default the command runs for every tenant, an empty one included');
    $owned = fn (Team $team) => $team->execute(fn () => Slide::query()->get()->sum(fn (Slide $slide) => $slide->media()->count()));
    check($owned($teamA) === 2 && $owned($teamB) === 1 && Media::query()->count() === 3, 'two tenants: each adopts only its own owners\' objects');
    check($teamB->execute(fn () => Slide::query()->first()->getFirstMedia('slides')->getCustomProperty('kit_key')) === 'legacy/b/1.png', 'a tenant\'s item is over its own key');
    [$code] = adopt($app, ['--sync' => true]);
    check($code === 0 && Media::query()->count() === 3, '--sync runs inline per tenant, and a re-run adopts nothing twice');
    check(count(Storage::disk('bucket')->allFiles()) === 3 && Team::current() === null, 'no object was copied, moved or deleted, and no tenant is left current');

    echo "OK media adopt\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
