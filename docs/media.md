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

BulkDrop shows the size limit as its hint ("Up to 10 MiB each") when no `hint` is given. Sizes are binary: MiB is 1024² bytes, rounded down to one decimal place where needed ("Up to 1.5 MiB each"), so the hint never promises more than is accepted. Pass `:show-limit="false"` to leave it out; with no `hint` either, no hint line is rendered.

On the server, `UploadLimit::message($kilobytes)` gives the same limit as a sentence: "Files can be up to 10 MiB" for 10240 KB, "Files can be up to 1.5 MiB" for 1536 KB. `RequestUpload`'s size refusal uses it. The wording is the `upload.limit` key in the kit's `ui` lang file (`:size` is the number of MiB); override it for the app in `lang/vendor/kit/{locale}/ui.php`:

```php
// lang/vendor/kit/en/ui.php
return ['upload' => ['limit' => 'Each file can be up to :size MiB']];
```

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
// POST request: { mime, bytes, sha256, mutation_id, file_name }
public function store(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $data = $request->validate(['mime' => 'required|string', 'bytes' => 'required|integer', 'sha256' => 'required|string', 'mutation_id' => 'nullable|string|max:64']);

    return RequestUpload::run('deck:'.$deck->id, Deck::slideRules(), $data['mime'], $data['bytes'], $data['sha256'], $data['mutation_id'] ?? null)->toArray();
}

// POST confirm: { id, file_name } as JSON, or { id, file_name, file } as multipart for a POST ticket
public function confirm(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $upload = MediaUpload::query()->where('scope', 'deck:'.$deck->id)->findOrFail($request->input('id'));
    $name = $request->input('file_name');
    $media = ClientFileName::attach(ConfirmUpload::run($upload, $request->file('file')), $deck, 'slides', is_string($name) ? $name : null);

    return ['id' => $media->uuid];
}
```

Scope the confirm lookup to the same scope the request used, so one owner can't confirm another's upload.

### The original file name

`directUpload` sends the File's own name as `file_name` in both bodies. Pass the confirm's `file_name` to `ClientFileName::attach($upload, $owner, $collection, $clientName, $name = null)` (`Tey\Kit\Media\ClientFileName`) instead of `AttachUpload::run(...)`:

- The name is never trusted. Only its last path segment is kept (`../../x.pdf` is `x.pdf`), control and format characters are removed, it is capped at 200 bytes, and its own extension is replaced with the storage key's, which the server's type check chose. A missing or unusable name falls back to the generated one.
- The storage key, the MIME type and Spatie's `file_name` stay the server's: medialibrary finds the object by `file_name`, so it remains the key's last part.
- The cleaned name is stored in the `client_file_name` custom property (`$media->getCustomProperty('client_file_name')`), and its stem is the item's `name` unless you pass one.
- `MediaDelivery` serves the original under it: `Content-Disposition` with an RFC 6266 `filename*` and an ASCII fallback, proxied or redirected. Conversions keep their own names. A route of your own that serves the file can use `ClientFileName::disposition($media, 'attachment')` (null when there is no client name).
- The name is set for that one attach only and cleared in a `finally`, so it never carries into another attach, a queued job or another request. Map `media.upload.*` refusals to a 422.

## Signing

Opt-in. By default (`full`) the presigned PUT signs Content-Type, Content-Length, the immutable Cache-Control and the SHA-256 checksum, under a key named after the SHA-256. The bucket refuses any other body, so confirm on a bucket only checks the size and reads the first bytes, and the same bytes in a scope dedupe to one object. Some buckets' CORS rules, and some clients, reject the extra signed headers. For those, sign only the type:

```php
// config/kit.php (read with a default, so the key is optional)
'media' => ['signing' => env('KIT_UPLOADS_SIGNING', 'full')], // or 'content_type'
```

With `content_type`:

- the PUT signs only Content-Type, and the ticket's `headers` is only `Content-Type`. Clients need no change: they send the ticket's headers as before;
- the key is random, `<prefix>/<ulid>.<ext>`, with the extension the collection's rules chose for the type;
- the client's SHA-256 is still required and stored on the record (and on the attached item's `sha256` property), but only as advisory metadata. It is never the key, and nothing is reused by it: the same bytes uploaded twice are two records and two objects;
- **integrity rests on the re-hash.** Nothing signed the checksum, so `ConfirmUpload` reads the whole object as a stream and compares its SHA-256 (and size and type) with the declaration before the record is ready. A mismatch fails closed like any other: the object is deleted and the record stays pending (`media.upload.mismatch`). The cost is one full read of every object on confirm: time on the request, and egress if the app runs outside the bucket's region.

The record's key decides, not the setting: a key not named after its SHA-256 is always re-hashed, so a record requested in one mode and confirmed after the setting changed is still verified, and a replayed mutation keeps the signing it was made with. An unknown value is refused (`InvalidArgumentException`) rather than read as either mode.

**With multipart.** The two settings are independent. `signing` decides the key, dedupe and the single PUT's headers; `multipart.part_checksums` alone decides whether each part URL signs its SHA-256. A multipart upload under `content_type` gets a random key and no dedupe, and `CompleteMultipartUpload` re-hashes it, as it always does, and `ConfirmUpload` re-hashes it again at the ready transition, so an object replaced between the two is still caught. That is two full reads of a large file in this mode. For a store that rejects both kinds of checksum header (R2, MinIO), set `signing` to `content_type` and `part_checksums` to false: integrity then rests entirely on the server's re-hash, which every path runs.

## Large files: S3 multipart

Off by default. With it on, a file over the threshold on an S3 disk goes up as an S3 multipart upload: parts in parallel, each retried on its own, so a dropped connection doesn't restart a large file. Anything else, including every upload while it is off, is the single presigned PUT above, unchanged.

```php
// config/kit.php (every key has this default, so the block is optional)
'media' => ['multipart' => [
    'enabled' => (bool) env('KIT_UPLOADS_MULTIPART', false),
    'threshold_bytes' => 100 * 1024 * 1024, // larger files use multipart (any value; only parts have a minimum)
    'part_bytes' => 16 * 1024 * 1024,       // raised to 5 MiB, the S3 minimum, if set lower
    'part_checksums' => true,               // false for a store that rejects per-part checksum headers (R2, MinIO)
]],
```

Multipart is used only when all of these hold. If any one doesn't, the upload is a single PUT:

- the flag is on and the file is larger than `threshold_bytes`;
- the disk is S3 (`Illuminate\Filesystem\AwsS3V3Adapter`, so `league/flysystem-aws-s3-v3` is installed). Other disks ignore the flag;
- the uploads table has the `multipart_upload_id` column. New tables get it from `schema()`. An existing table needs an additive migration:

  ```php
  Schema::table(MediaUploads::table(), MediaUploads::addMultipart(...));
  ```

- the client offered it. BulkDrop and `directUpload` send `multipart: true` only when `direct` has a `complete` route.

Add `complete` and `abort` to `direct`, and give them routes next to `store` and `confirm`. The store route switches to `RequestMultipartUpload`, which runs `RequestUpload` unchanged and only then turns an eligible PUT ticket into a `MULTIPART` one:

```vue
:direct="{ request: route('slides.uploads.store'), confirm: route('slides.uploads.confirm'),
    complete: route('slides.uploads.complete'), abort: route('slides.uploads.abort') }"
```

```php
// POST request: { mime, bytes, sha256, mutation_id, file_name, multipart?, part_sha256? }
public function store(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $data = $request->validate(['mime' => 'required|string', 'bytes' => 'required|integer', 'sha256' => 'required|string', 'mutation_id' => 'nullable|string|max:64',
        'multipart' => 'boolean', 'part_sha256' => 'array|max:10000', 'part_sha256.*' => 'string']);

    return RequestMultipartUpload::run('deck:'.$deck->id, Deck::slideRules(), $data['mime'], $data['bytes'], $data['sha256'], $data['mutation_id'] ?? null,
        $request->boolean('multipart'), $data['part_sha256'] ?? [])->toArray();
}

// POST complete: { id, file_name, parts: [{ part, etag, checksum }] }
public function complete(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    $upload = MediaUpload::query()->where('scope', 'deck:'.$deck->id)->findOrFail($request->input('id'));
    $name = $request->input('file_name');
    $media = ClientFileName::attach(CompleteMultipartUpload::run($upload, (array) $request->input('parts')), $deck, 'slides', is_string($name) ? $name : null);

    return ['id' => $media->uuid];
}

// POST abort: { id }
public function abort(Request $request, Deck $deck): array
{
    Gate::authorize('update', $deck);
    AbortMultipartUpload::run(MediaUpload::query()->where('scope', 'deck:'.$deck->id)->findOrFail($request->input('id')));

    return ['ok' => true];
}
```

Scope both lookups to the request's scope, as for confirm, and map `media.upload.*` refusals to a 422.

**The flow.** A `MULTIPART` ticket carries `upload_id` and one `{ part, url, headers }` per part. Every part is `ceil(bytes / parts)` bytes, and the last takes the rest, so the client derives the layout from the part count alone. With part checksums on, the first ticket has no URLs. The client hashes each part and asks again with the same mutation id and `part_sha256`, and each part URL then signs its part's length and SHA-256. The client uploads four parts at a time, tries each part up to three times, and sends every part's ETag (and checksum) to `complete`. The bucket's CORS rule must expose the `ETag` response header. A part that keeps failing, a part answered without an ETag, or a cancel (`directUpload`'s optional `signal`) sends the upload to `abort`, and no complete is sent.

**Integrity.** S3 has no whole-object SHA-256 for a multipart upload, only a composite of the part hashes. That would prove the parts, but not that they make up the declared SHA-256, which is the content-addressed key that dedupe trusts. So `CompleteMultipartUpload` always re-hashes the joined object as a stream, never in memory, and compares its size and SHA-256 with the declaration before `ConfirmUpload` marks it ready. The multipart id stays on the record until the re-hash has run, and `ConfirmUpload` refuses a record that has one (`media.upload.multipart_pending`), so a concurrent confirm can't mark the object ready unverified. Per-part checksums are a transit check on top of that. Dedupe stays on. The cost is one full read of the object per completed upload: time on the request, and egress if the app runs outside the bucket's region. Set `part_checksums` to false on R2 or MinIO if the store rejects the per-part checksum headers. The re-hash still guarantees integrity, and a store that rejects the headers fails the part, and so the upload, rather than accept it unchecked.

**Staging.** The parts are joined at a per-record staging key beside the final one (`<dir>/.staging/<record id>`, `MultipartUploads::stagingKey()`), never at the final key, so nothing a client uploads lands on a content-addressed key and another upload can't swap or overwrite an object a ready record points at. `CompleteMultipartUpload` re-hashes the staged object, and only a match is placed. If the final key already holds an object, it is re-hashed (an upload from before this release may have left unverified bytes there) and used as it is when it matches; one that doesn't is refused, and deleted unless a ready record shares it. Otherwise the staged object is copied server-side with `CopyObject` and `If-None-Match: *` (`ConditionalCopyStore`, which `S3MultipartStore` implements), so an object that appears first is never overwritten. A store without a conditional copy, and S3 above the 5 GiB `CopyObject` limit (the SDK's multipart copy), get HEAD-then-copy, which can't rule out a write landing between the two. The staged object is then deleted. A mismatch, or a final key holding an object of another size, deletes the staged object and refuses (`media.upload.mismatch`) with the final key untouched. Under `content_type` the final key is the record's random key and the same path is taken, and `ConfirmUpload` re-hashes it as for any random key. `kit:uploads:prune` deletes a stale row's staging object after aborting its upload. A multipart upload started before this release joined at the final key, so its complete is refused (`media.upload.incomplete`) and the client starts again; an abort falls back to the final key, so the pruner still cleans it up.

**Failure.** Every failure path fails closed:

- Parts that don't cover every part number exactly once, or that lack an ETag (or a checksum, while part checksums are on), are refused (`media.upload.bad_parts`). Nothing is sent to S3, so the client can send them again.
- If S3 refuses the complete, the upload is aborted (`media.upload.incomplete`).
- If the re-hash doesn't match, the object is deleted unless a ready record shares the key (`media.upload.mismatch`).

After an abort or a mismatch the record stays pending with no multipart id, so the same mutation starts a fresh upload. `AbortMultipartUpload` drops the parts at S3 and clears the id. It is the client's abort route, and it runs after a refused complete. `kit:uploads:prune` runs it for each stale pending record that still has a `multipart_upload_id`, before deleting the record, since S3 keeps and bills an unfinished upload's parts ([Pruning abandoned uploads](#pruning-abandoned-uploads)). A bucket lifecycle rule that aborts incomplete multipart uploads after a day is a good backstop.

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

The same content can back several Media items, across owners. **Deleting a Media item over a key never deletes the object.** `KeptFileRemover` removes only that item's own conversions. Only a reference-counted pruner deletes objects, and only once no upload row or Media item refers to them.

## Pruning abandoned uploads

A client that requests an upload and never confirms it leaves a pending row, and perhaps an object, behind. `php artisan kit:uploads:prune` clears them:

- It prunes rows still `pending` and untouched for more than `--older-than` hours (default `kit.media.prune_older_than`, 24). Ready rows are never pruned, however old.
- `--dry-run` prints the rows and objects it would delete, their bytes, the objects it would keep and a few sample keys, and changes nothing.
- It is reference-counted. The object goes only when no other upload row (any status, any scope) and no Media item over the key remain; otherwise only the stale rows go and the object stays. A Media item counts when it is on the same disk with the key's file name, which is how `AttachUpload` and `AdoptObject` record it.
- **Across tenants.** Keys are content-addressed under the app's prefix, not under a tenant or scope, so the same object can back rows in several scopes (the same bytes uploaded in two workspaces get the same key). References are therefore matched on the disk and key across the whole ledger and media table, never within one scope: that only ever keeps more, and nothing outside the stale row's scope is changed or printed. With separate tenant databases (`multitenancy.tenant_database_connection_name` set), another tenant's rows can't be seen, so the command removes stale rows but always keeps their objects.
- **Fail closed.** When a key's references can't be checked (a missing ledger or media table, a disk that errors), nothing for that key is deleted, not even its rows. It is listed as `not pruned` and the command exits nonzero. Other keys still prune.
- The object is deleted before its rows, after a second reference check, so a refused delete leaves the rows for the next run.
- **S3 multipart.** A stale row with an open S3 multipart upload (`multipart_upload_id`) has it aborted first, so the bucket drops its parts. An abort that fails (S3 refuses, or the disk has no multipart store) keeps that key's rows and object, is listed as `not pruned` and fails the run. `--dry-run` reports how many it would abort. A run with none prints exactly as before.
- Rows are read in id chunks (`--chunk`, default 500).

It isn't scheduled unless the app asks. Set `kit.media.prune_schedule` to `true` (daily at 03:00) or a cron expression, and the kit adds it to the schedule with `withoutOverlapping()` and `onOneServer()`:

```php
// config/kit.php
'media' => ['prune_schedule' => '0 3 * * *', 'prune_older_than' => 24],
```

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

`composer test:media-collection` covers a record's collection (listing, reorder, move and remove, owner-scoped, with the version). `composer test:media-uploads` covers the operations against a temporary local disk and a stubbed presigner. It runs with no network and no real S3. `composer test:media-conversions` runs real GD conversions over a faked queue, including a PNG with a malformed iCCP through TolerantImage. `composer test:media-delivery` covers both delivery modes over a stubbed URL signer. `composer test:media-prune` covers `kit:uploads:prune` over SQLite memory and a temporary local disk (dry run, age, ready rows, keys shared by another row or a Media item, fail-closed reference checks, aborting stale S3 multipart uploads, deleting stale staging objects and the opt-in schedule). `composer test:media-adopt` covers adoption and the command pattern, including no tenant (a loud failure) and two tenants (each adopts only its own). `composer test:media-multipart` covers S3 multipart over a fake multipart store, plus the S3 store's calls over a mocked S3 client: off by default and identical when off, every single-PUT fallback, create, parts, complete, the re-hash, staging (other bytes for a ready key refused at staging with the ready object untouched, the same bytes reusing the existing object, an object that appears first never overwritten, same-size wrong bytes at the key refused by the re-hash, the conditional copy and the HEAD-then-copy fallback, a confirm before complete refused), attach, refused parts and ETags, abort, and scopes. `composer test:media-signing` covers `kit.media.signing` over a bucket disk that is not a local adapter and a stubbed presigner: the default identical to an explicit `full`, `content_type` signing only Content-Type with a random key, no dedupe lookup, the advisory SHA-256, the streamed re-hash and its fail-closed mismatch, a setting changed between request and confirm, an unknown value, scopes, and multipart under both part-checksum settings with a single re-hash. `composer test:js` (`direct-upload.test.mjs`, `direct-upload-multipart.test.mjs`) covers the client flow over a fake transport, including part layout, concurrency, a retried part, a missing ETag and cancel. What S3 itself enforces (checksum and length refusal, part ETags, and signed-URL expiry) and a real multipart upload against S3, R2 or MinIO are not exercised, and nor is a real queue worker or the dropzone's rendering in a browser.
