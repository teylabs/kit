# Media

The kit's media pattern is a library (`Tey\Kit\Media`) that a client app adopts instead of writing its own. It sits on Media Library and adds direct-to-bucket uploads, responsive conversions, delivery and adopting existing objects.

## Setup

Create the upload ledger in one of the app's own migrations:

```php
use Tey\Kit\Media\MediaUploads;

Schema::create(MediaUploads::table(), MediaUploads::schema(...)); // `kit.media.uploads_table`, default kit_media_uploads
```

Point Media Library at the kit's path generator and file remover (`config/media-library.php`):

```php
'path_generator' => Tey\Kit\Media\KeyPathGenerator::class,
'file_remover_class' => Tey\Kit\Media\KeptFileRemover::class,
```

Both leave ordinary Media items alone. They only change how items over an existing key behave.

## Rules

Each collection declares a `MediaRules`: the disk, a key prefix under the app's own, the accepted types (MIME => extension), the largest size, and the presign TTL.

```php
$rules = new MediaRules(disk: 's3', prefix: 'app/slides', types: ['image/png' => 'png', 'image/webp' => 'webp'], maxBytes: 20_000_000);
```

Keys are content-addressed: `<prefix>/<sha[0..2]>/<sha256>.<ext>`.

## Uploading

An upload takes three steps. Each step is a plain operation, so a controller, an MCP tool or a job can call it. **Authorization is the caller's job:** run the policy check before step one.

1. **Request:** `RequestUpload::run($scope, $rules, $mime, $bytes, $sha256, $mutationId)`.
   - It checks the declaration against the rules (a `ValidationException` for `mime`, `bytes` or `sha256`) and reserves a pending `MediaUpload`. It returns an `UploadTicket` (`toArray()` for JSON or MCP), whose method tells the client what to do next:
     - `PUT`: a presigned URL. The signature binds `Content-Type`, `Content-Length`, `x-amz-checksum-sha256` and `Cache-Control: public, max-age=31536000, immutable`, so the bucket refuses any other body. Send exactly `headers`.
     - `POST`: the disk can't presign (a local disk). Send the file as multipart to the app's own route, which calls step 3 with the file.
     - `null`: the same content is already ready in this scope (dedupe). Go straight to step 3.
   - Replaying the same `mutationId` with the same declaration returns the same record with a fresh URL. A different declaration is refused (`media.upload.mutation_conflict`).
2. **PUT:** the client sends the body straight to the bucket.
3. **Confirm:** `ConfirmUpload::run($upload, $file = null)`.
   - On a bucket it checks the size, then reads only the first 256 KB to check the type and record the width and height. On a local disk it hashes the file. A multipart body is hashed and checked before it is written.
   - A mismatch deletes the object (unless a ready record shares it), leaves the record pending and throws `media.upload.mismatch`. An absent object throws `media.upload.missing`.
   - Confirming a ready record returns it unchanged.

Then `AttachUpload::run($upload, $owner, 'slides')` makes the upload a Media item over the existing key. It writes a metadata row only, with no byte copy. Attaching the same upload to the same owner and collection again returns the first item.

## From the browser: BulkDrop `direct`

Give BulkDrop the two routes and it uploads by itself. Each file goes straight to the bucket with progress in the prompt, then `uploaded` carries the confirm route's results. The input is cleared afterwards, so the form submits no bytes.

```vue
<BulkDrop name="slides" label="Slides" :accept="['image/png', 'image/webp']" :max-bytes="20_000_000" :has-files="false" multiple
    :direct="{ request: route('slides.uploads.store'), confirm: route('slides.uploads.confirm') }" @uploaded="attach" />
```

`directUpload(file, routes, progress)` (`Kit/Form/directUpload.ts`) is the same flow without the dropzone. Each File keeps one mutation id, so retrying the same file replays its upload. The app owns both routes, its own authorization and the owner to attach to:

```php
// POST request: { mime, bytes, sha256, mutation_id }
public function store(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $data = $request->validate(['mime' => 'required|string', 'bytes' => 'required|integer', 'sha256' => 'required|string', 'mutation_id' => 'nullable|string|max:64']);

    return RequestUpload::run('deck:'.$deck->id, Deck::slideRules(), $data['mime'], $data['bytes'], $data['sha256'], $data['mutation_id'] ?? null)->toArray();
}

// POST confirm: { id } as JSON, or { id, file } as multipart for a POST ticket
public function confirm(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $upload = MediaUpload::query()->where('scope', 'deck:'.$deck->id)->findOrFail($request->input('id'));
    $media = AttachUpload::run(ConfirmUpload::run($upload, $request->file('file')), $deck, 'slides');

    return ['id' => $media->uuid];
}
```

Scope the confirm lookup to the same scope the request used, so one owner can't confirm another's upload. Map `media.upload.*` refusals to a 422.

## Conversions

Declare responsive WebP widths in the model and set the kit's job (`config/media-library.php`):

```php
use Tey\Kit\Media\Concerns\HasResponsiveImages;

class Slide extends Model implements HasMedia
{
    use HasResponsiveImages, InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->responsiveWebp('slides', [828, 1920]);
    }
}

'jobs' => ['perform_conversions' => Tey\Kit\Media\Jobs\PerformConversions::class, /* ... */],
```

- Each width is a conversion named `w<width>`, fit within that width (`Fit::Max`), so it never upscales. Conversions sit beside the key under `conversions/<uuid>/`.
- They are always queued: one job per image, on `kit.media.queue` (default `media`) whatever `media-library.queue_name` says. Publishing never waits for them, and `AttachUpload` queues them as the item is created.
- `kit.media.tries` (3), `kit.media.backoff` ([30, 120, 600]), `kit.media.timeout` (300 s) and `kit.media.memory_limit` (1024M, raised for the job only) are sized for a 2000 px PNG. Run a worker for the queue with a matching `--timeout`.
- `Conversions::regenerate($media)` queues only the missing ones, and a retried job does the same, so both are idempotent.
- The job fails closed instead of finishing silently with nothing made:
  - an original it can't read (a refused read on a disk that doesn't throw, or an empty file) throws `media.conversions.unreadable_original`;
  - an image a generator can convert that ends with none of its requested conversions throws `media.conversions.none_generated`.

  Both are retried with the job's tries and backoff. A media row deleted before the job runs still drops the job, and logs a `media.conversions.missing_model` warning with the media id.
- The job is tenant-aware: it implements spatie/laravel-multitenancy's `TenantAware` explicitly, so it holds even if the host turns `queues_are_tenant_aware_by_default` off.
  - It carries the tenant it was queued in and runs in it. Spatie restores the previous tenant afterwards.
  - Queued with no current tenant, it fails loudly instead of converting, and its `handle()` throws `media.conversions.tenant_required`.
  - So attach, upload or regenerate tenant media inside the tenant (a request in the workspace, or a TenantAware command).
  - The kit's own avatar and note thumbnails are `nonQueued`, so this job never runs for them.
  - Media that belongs to no tenant can't use this job; give its conversions `nonQueued()`.

`Srcset::for($media, $url)` gives an `<img>` its attributes:

```php
['src' => ..., 'srcset' => '…/w828 828w, …/original 1600w', 'width' => 1600, 'height' => 900]
```

- It lists only the conversions already generated, narrower than the original, then the original at its own width. Before any conversion exists, the original is the only candidate.
- `$url($media, $conversion)` (`''` for the original) should return the app's delivery route, so the page never sees a bucket key. Without it, Media Library's own URL is used.
- The width and height come from the upload's header read. An item added another way has none, so its srcset lists only conversions.

## Delivery

Serve media from the app's own route, either an unguessable token or an authorized path. The page then only ever holds that route, never a bucket key. The app keeps its own 404 rules (status, hash or manifest checks) in the resolver:

```php
public function show(Request $request, string $token, ?string $conversion = null): Response
{
    return MediaDelivery::respond($request, fn () => Slide::published()->firstWhere('token', $token)?->getFirstMedia('slides'), $conversion ?? '', public: true);
}
```

- A conversion that is not generated yet is a 404.
- `proxy` (the default; `kit.media.delivery` sets it, and `$mode` overrides it per call) streams the bytes through PHP:
  - a public item is `public, max-age=31536000, immutable`, with an ETag and a 304 for a match;
  - a private item, such as a tenant file, is `private, no-store`;
  - every response is `nosniff`;
  - anything that isn't an image downloads under `Content-Security-Policy: default-src 'none'; sandbox`, so it never renders under the app's origin.
- `redirect` answers a 302 to a signed bucket URL valid for `kit.media.redirect_ttl` (default 3600 s).
  - The redirect itself is cached (public or private) for at most **half** that TTL, so a cached redirect never outlives its signature during a long session.
  - Non-images are signed as downloads.
  - A disk that can't sign URLs is proxied instead.
- Objects should be stored immutable, so the bucket's responses are cacheable too. Uploads already are, because the presigned PUT binds `Cache-Control`. For conversions, set `media-library.remote.extra_headers` to `['CacheControl' => 'public, max-age=31536000, immutable']`.

## Sharing and deletion

The same content can back several Media items, across owners. **Deleting a Media item over a key never deletes the object.** `KeptFileRemover` removes only that item's own conversions. Only the app's own reference-counted pruner deletes objects, and only once no ready upload or Media item refers to them. The pruner is also the place to clear pending records past their TTL.

## Adopting existing objects

An app that already has objects in a bucket (under its own keys, outside Media Library) adopts them in place. Nothing is copied or moved, and the objects stay externally owned.

`AdoptObject::run($owner, 'slides', 's3', $key)` probes the object (its size and first bytes, for the type and dimensions) and makes it a Media item over the key. `KeyPathGenerator` is the helper that maps that item's path to the existing key. The item never owns the object, so deleting it leaves the object alone. Adopting the same key into the same owner and collection again returns the first item. An absent object is refused (`media.adopt.missing`).

For a whole table, subclass `AdoptMediaCommand` once per collection and register it as usual:

```php
final class AdoptSlides extends AdoptMediaCommand
{
    protected $name = 'slides:adopt-media';

    protected string $collection = 'slides';

    protected function owners(): Builder
    {
        return Slide::query()->whereNotNull('image_path');
    }

    protected function source(Model $owner): ?array
    {
        return ['s3', $owner->image_path]; // [disk, key], or null when the owner has nothing to adopt
    }
}
```

It is tenant-aware, using spatie/laravel-multitenancy's own `TenantAware` command trait:

- It runs once per tenant: every tenant by default, or the ones `--tenant=` names (by `multitenancy.tenant_artisan_search_fields`). Its body refuses to run with no current tenant (`media.adopt.tenant_required`).
- `--dry-run` writes nothing and prints one line of counts per tenant, never a key: `tenant 7: owners 412, adopted 5, would_adopt 400, missing 7, no_source 0`.
- Override `sourceRows()` to count the current tenant's raw source rows, ignoring `owners()`'s filters. A tenant where the source has rows but `owners()` finds none is a warning and a failing exit, never a quiet "adopted 0".
- Without `--dry-run`, each tenant's owners are read in id chunks (`--chunk`, default 500). Each chunk is one `AdoptChunk` job on `kit.media.queue`, and `--sync` runs the chunks inline.
  - The job implements spatie's `TenantAware` explicitly, so it carries the tenant it was dispatched in even if the host turns `queues_are_tenant_aware_by_default` off.
  - Dispatched with no tenant, it fails loudly. Its `handle()` also throws when no tenant is current.
  - Spatie restores the previous tenant after the job.
- Every chunk is idempotent. Owners whose object is already adopted are skipped, and a missing object is counted, not fatal. Re-run the command, or retry a job, freely.

## Coverage

`composer test:media-uploads` covers the operations against a temporary local disk and a stubbed presigner. It runs with no network and no real S3. `composer test:media-conversions` runs real GD conversions over a faked queue. `composer test:media-delivery` covers both delivery modes over a stubbed URL signer. `composer test:media-adopt` covers adoption and the command pattern, including no tenant (a loud failure) and two tenants (each adopts only its own). `composer test:js` (`direct-upload.test.mjs`) covers the client flow over a fake transport. What S3 itself enforces (checksum and length refusal, and signed-URL expiry) is not exercised, and nor is a real queue worker or the dropzone's rendering in a browser.
