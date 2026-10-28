# Media

The kit's media pattern is a library (`Tey\Kit\Media`) that a client app adopts instead of writing its own. It sits on Media Library and adds direct-to-bucket uploads and adopting existing objects. Conversions and delivery are documented as they land.

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

- `php artisan slides:adopt-media --dry-run` writes nothing and prints counts only, never a key: owners, adopted, would_adopt, missing and no_source.
- Without `--dry-run`, owners are read in id chunks (`--chunk`, default 500), and each chunk is one queued job on `kit.media.queue`. `--sync` runs the chunks inline.
- Every chunk is idempotent. Owners whose object is already adopted are skipped, and a missing object is counted, not fatal. Re-run the command, or retry a job, freely.
- Run it per tenant where the owners are tenant-scoped, and restore the tenant context in `finally`.

## Coverage

`composer test:media-uploads` covers the operations against a temporary local disk and a stubbed presigner. It runs with no network and no real S3. `composer test:media-adopt` covers adoption and the command pattern. What S3 itself enforces (checksum and length refusal) is not exercised. The BulkDrop `direct` mode and the HTTP routes are not part of this slice.
