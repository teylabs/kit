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

## Upload limits

A file uploaded through the app (a note's attachment, an avatar) passes three limits before it's kept: the kit's own cap for it (`kit.notes.media.max_kb`, `kit.avatar.max_kb`), PHP's `upload_max_filesize` and `post_max_size`, and medialibrary's `max_file_size`. `Tey\Kit\Features\Core\UploadLimit::cap($bytes)` (`capKb()` for Laravel's `max:` rule) is the lowest of them. The kit's validation and the size its pages show both use it, so a page never promises more than the stack accepts, and an oversized file is refused up front with a 422 instead of failing when it's kept.

To raise a limit, raise all three. The notes cap is `KIT_NOTES_MAX_KB` in `.env` (default 10240), so it can be raised without copying the whole `kit.notes` block. `php artisan kit:preflight` warns for each kit cap that PHP or medialibrary would cut down, naming the setting and both values ("notes attachments: 10.0 MB, but PHP's upload_max_filesize allows 2.0 MB"); the cap itself still drops to the lower limit.

BulkDrop shows the size limit as its hint ("Up to 10.0 MB each") when no `hint` is given. Pass `:show-limit="false"` to leave it out; with no `hint` either, no hint line is rendered.

Direct-to-bucket uploads (below) never pass through PHP: their limit is the `MediaRules` `maxBytes` alone.

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

Give BulkDrop the two routes and it uploads by itself. Each file goes straight to the bucket with progress in the prompt, then `uploaded` carries the confirm route's results. The input is cleared afterwards, so the form submits no bytes. While an upload is in flight it is pending work, so the form's leave guard asks before the page is left ([docs/submit-form.md › Pending work](submit-form.md#pending-work)).

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
  - The kit's avatar thumbnails are `nonQueued`, so this job never runs for them. Note thumbnails are queued (after the note's transaction commits), so they run on this job and need a queue worker unless the queue is `sync`.
  - Media that belongs to no tenant can't use this job; give its conversions `nonQueued()`.

**PNGs with malformed metadata.** A PNG with a broken colour profile (an `iCCP` chunk) or broken text chunks makes libpng complain. Where the host's libpng treats that as an error, GD refuses an image whose pixels are fine. The kit's tolerant generator removes the `iCCP`, `iTXt`, `zTXt` and `tEXt` chunks from a temporary copy before GD loads it:

```php
// config/media-library.php
'image_generators' => [Tey\Kit\Media\Generators\TolerantImage::class, /* Webp, Avif, Pdf, Svg, Video… as before */],
```

- The original object is never touched, and nothing is lost: the WebP conversions carry no ICC profile or text either way.
- The PNG's structure is checked. A truncated file, or a critical chunk (IHDR, IDAT…) with a bad CRC, is passed on unchanged, so a genuinely corrupt PNG still fails loudly.
- `kit.media.tolerant_png` false turns it off.
- The tolerant copy applies to the GD driver (the default). A host may instead set `media-library.image_driver` to `imagick`, which tolerates a malformed iCCP itself. The kit doesn't switch drivers, because that would change conversion output from host to host and depend on an extension.

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

## A record's collection

A record's photos or files, as the page shows and edits them: one file (a cover) or an ordered set (a listing's photos), with reordering, moving between the record's collections and removing.

The server, in the record's page view model and its routes (authorise the record first; these check only that the items are the record's own):

```php
use Tey\Kit\Media\MediaCollection;
use Tey\Kit\Media\Operations\{MoveMedia, RemoveMedia, ReorderMedia};

// the page prop: items in order, delivery URLs only, and a version over the order
'photos' => MediaCollection::for($listing, 'photos', fn ($media, $conversion) => route('listings.media.show', [$media, $conversion])),

// PATCH order: { order: [ids…], version } → { version }
ReorderMedia::run($listing, 'photos', $request->input('order'), $request->input('version'));
// POST move: { to, version }
MoveMedia::run($listing, $media, 'cover', $request->input('version'), Listing::coverRules(), single: true, replace: $request->boolean('replace'));
// DELETE
RemoveMedia::run($listing, $media);
```

- **Fail closed:** an id that isn't the record's own, an order that misses or repeats an item, a type the target's rules don't accept, or a full single-file target is refused (`media.order.mismatch`, `media.move.not_found`, `media.move.type`, `media.move.full`, `media.remove.not_found`). Map them to 422 (or 404 for `not_found`).
- **Stale writes:** a reorder or move with an old version is `media.order.stale` / `media.move.stale`; answer 409 with `{ error: 'stale_write' }` and the page rolls back.
- **No byte copy:** a move changes the item's collection over the same object and queues the target's conversions; a remove deletes the item and its own conversions, never the object.

The page:

```vue
<MediaCollection :collection="photos" label="Photos" :accept="['image/jpeg', 'image/png']" :max-bytes="20_000_000"
    :direct="{ request: uploadUrl, confirm: confirmUrl }" :reorder="{ url: orderUrl, method: 'patch' }"
    :move-targets="[{ collection: 'cover', label: 'cover' }]" @uploaded="reload" @move="(item, to) => router.post(moveUrl(item), { to, version: photos.version })"
    @remove="confirmRemove" />
```

`single` shows one file, and offers the dropzone only while it's empty. Reordering is SortableList's (drag, keyboard and Move up/down buttons, with an announcement). Move and remove are emitted for the host to run, so it keeps its own confirmation and route.

## Where failed jobs live

When conversions fail, look in both places a failed job can be:

- **The `failed_jobs` table** (`php artisan queue:failed`), for a database, Redis or SQS queue the app runs itself.
- **The managed queue's own store**, on Laravel Cloud's managed queue. A job that exhausts its tries is kept in the queue service's failed-jobs store (its dashboard and CLI), not in `failed_jobs`. A conversion failing every attempt can look silent if you only check the table.

`PerformConversions` names its failures (`media.conversions.unreadable_original`, `media.conversions.none_generated`, `media.conversions.tenant_required`), so search for those in either store.

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

`composer test:media-collection` covers a record's collection (listing, reorder, move and remove, owner-scoped, with the version). `composer test:media-uploads` covers the operations against a temporary local disk and a stubbed presigner. It runs with no network and no real S3. `composer test:media-conversions` runs real GD conversions over a faked queue, including a PNG with a malformed iCCP through TolerantImage. `composer test:media-delivery` covers both delivery modes over a stubbed URL signer. `composer test:media-adopt` covers adoption and the command pattern, including no tenant (a loud failure) and two tenants (each adopts only its own). `composer test:js` (`direct-upload.test.mjs`) covers the client flow over a fake transport. What S3 itself enforces (checksum and length refusal, and signed-URL expiry) is not exercised, and nor is a real queue worker or the dropzone's rendering in a browser.
