# Media

The kit's media pattern is a library (`Tey\Kit\Media`) that a client app adopts instead of writing its own. It sits on Media Library and adds direct-to-bucket uploads. Conversions, delivery and adopting existing objects come in later slices.

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

## Sharing and deletion

The same content can back several Media items, across owners. **Deleting a Media item over a key never deletes the object.** `KeptFileRemover` removes only that item's own conversions. Only the app's own reference-counted pruner deletes objects, and only once no ready upload or Media item refers to them. The pruner is also the place to clear pending records past their TTL.

## Coverage

`composer test:media-uploads` covers the operations against a temporary local disk and a stubbed presigner. It runs with no network and no real S3. What S3 itself enforces (checksum and length refusal) is not exercised. `composer test:js` (`direct-upload.test.mjs`) covers the client flow over a fake transport. The dropzone's rendering in a browser is not covered.
