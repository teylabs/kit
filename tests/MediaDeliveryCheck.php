<?php

// Media delivery: proxy (ETag and 304, immutable for public, private no-store, nosniff, a sandbox CSP and a download for non-images) and redirect (a
// 302 to a signed bucket URL whose own max-age is at most half the signature's TTL); the app's resolver decides 404s; only generated conversions
// are served; a disk that cannot sign is proxied. SQLite memory, a temporary local disk and a stubbed URL signer; no network.
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tey\Kit\Media\MediaDelivery;
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

/** The status a delivery answered, with its response; an abort is its status code. */
function deliver(Closure $resolve, string $conversion = '', bool $public = false, ?string $mode = null, array $headers = []): array
{
    $request = Request::create('https://kit.test/m', 'GET', server: array_combine(array_map(fn ($name) => 'HTTP_'.strtoupper(str_replace('-', '_', $name)), array_keys($headers)), $headers));
    try {
        $response = MediaDelivery::respond($request, $resolve, $conversion, $public, $mode);

        return [$response->getStatusCode(), $response];
    } catch (HttpException $exception) {
        return [$exception->getStatusCode(), null];
    }
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
        'view' => ['paths' => [], 'compiled' => $base.'/views'], 'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
        'filesystems' => ['default' => 'bucket', 'disks' => [
            'bucket' => ['driver' => 'local', 'root' => $base.'/bucket', 'throw' => false],
            'plain' => ['driver' => 'local', 'root' => $base.'/plain', 'throw' => false],
        ]],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
    ]));
    $app->instance('request', Request::create('https://kit.test'));
    Illuminate\Support\Facades\Facade::clearResolvedInstances();
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Events\EventServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Cache\CacheServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Bus\BusServiceProvider::class, Illuminate\Routing\RoutingServiceProvider::class, Illuminate\View\ViewServiceProvider::class, Illuminate\Queue\QueueServiceProvider::class, Spatie\MediaLibrary\MediaLibraryServiceProvider::class] as $provider) {
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

    $signed = [];
    Storage::disk('bucket')->buildTemporaryUrlsUsing(function (string $path, $expiration, array $options) use (&$signed): string {
        $signed[] = [$path, $expiration, $options];

        return 'https://bucket.test/'.$path.'?X-Amz-Expires='.($expiration->getTimestamp() - time());
    });
    $rules = new MediaRules('bucket', 'app/slides', ['image/png' => 'png', 'application/pdf' => 'pdf'], 1_000_000);
    $attach = function (string $body, string $mime) use ($rules): Media {
        $ticket = RequestUpload::run('ws:1', $rules, $mime, strlen($body), hash('sha256', $body));
        Storage::disk('bucket')->put($ticket->upload->key, $body);

        return AttachUpload::run(ConfirmUpload::run($ticket->upload), Slide::query()->create(), 'slides');
    };
    $image = $attach(png(40, 30), 'image/png');
    $pdf = $attach("%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", 'application/pdf');

    // The app's rules.
    check(deliver(fn () => null)[0] === 404, "the app's resolver decides what is a 404");
    check(deliver(fn () => $image, 'w828')[0] === 404, 'a conversion that is not generated is a 404');

    // Proxy.
    [$status, $public] = deliver(fn () => $image, public: true);
    check($status === 200 && $public->headers->get('Cache-Control') === 'immutable, max-age=31536000, public' && $public->headers->get('Content-Type') === 'image/png' && $public->headers->get('X-Content-Type-Options') === 'nosniff' && ! $public->headers->has('Pragma'), 'a public image is proxied inline, immutable for a year and nosniff');
    ob_start();
    $public->sendContent();
    check(ob_get_clean() === Storage::disk('bucket')->get($image->getCustomProperty('kit_key')), 'the proxied bytes are the object');
    $etag = $public->headers->get('ETag');
    [$status, $cached] = deliver(fn () => $image, public: true, headers: ['If-None-Match' => 'W/"x", '.$etag]);
    check($status === 304 && $cached->headers->get('ETag') === $etag, 'a matching ETag is a 304');
    [, $private] = deliver(fn () => $image);
    check($private->headers->get('Cache-Control') === 'no-store, private' && ! $private->headers->has('ETag') && deliver(fn () => $image, headers: ['If-None-Match' => $etag])[0] === 200, 'a private image is private, no-store, with no ETag');
    [, $document] = deliver(fn () => $pdf);
    check(str_starts_with((string) $document->headers->get('Content-Disposition'), 'attachment') && str_contains((string) $document->headers->get('Content-Security-Policy'), 'sandbox') && $document->headers->get('X-Content-Type-Options') === 'nosniff', 'anything not an image downloads under a sandboxing CSP');

    // Redirect.
    config(['kit.media.redirect_ttl' => 3600]);
    [$status, $redirect] = deliver(fn () => $image, public: true, mode: MediaDelivery::REDIRECT);
    [$path, $expiration, $options] = end($signed);
    check($status === 302 && $redirect->headers->get('Location') === 'https://bucket.test/'.$image->getCustomProperty('kit_key').'?X-Amz-Expires=3600' && $path === $image->getCustomProperty('kit_key'), 'redirect answers a 302 to a signed bucket URL for the object');
    check($redirect->headers->get('Cache-Control') === 'max-age=1800, public' && $redirect->headers->get('Referrer-Policy') === 'no-referrer', "the redirect's own max-age is half the signature's TTL");
    config(['kit.media.redirect_ttl' => 301, 'kit.media.delivery' => MediaDelivery::REDIRECT]);
    [, $short] = deliver(fn () => $pdf);
    [, , $options] = end($signed);
    check($short->headers->get('Cache-Control') === 'max-age=150, private' && str_starts_with($options['ResponseContentDisposition'], 'attachment') && $options['ResponseContentType'] === 'application/pdf', 'a private document redirects as a download, cached privately for at most half the TTL (kit.media.delivery)');
    check(! str_contains((string) $short->getContent(), 'app/slides'), 'the redirect body carries no key');

    $plain = new MediaRules('plain', 'app/slides', ['image/png' => 'png'], 1_000_000);
    $ticket = RequestUpload::run('ws:9', $plain, 'image/png', strlen(png(10, 10)), hash('sha256', png(10, 10)));
    file_put_contents($tmp = $base.'/p.png', png(10, 10));
    $local = AttachUpload::run(ConfirmUpload::run($ticket->upload, new UploadedFile($tmp, 'p.png', 'image/png', null, true)), Slide::query()->create(), 'slides');
    check(deliver(fn () => $local, mode: MediaDelivery::REDIRECT)[0] === 200, 'a disk that cannot sign URLs is proxied');

    echo "OK media delivery\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
