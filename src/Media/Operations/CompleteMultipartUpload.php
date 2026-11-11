<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\ConditionalCopyStore;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartStore;
use Tey\Kit\Media\MultipartUploads;
use Throwable;

/**
 * Step three for an S3 multipart upload (docs/media.md › Large files): the client's parts are checked (one per part number, each with an ETag, and
 * its SHA-256 when part checksums are on) and S3 joins them at the record's staging key (MultipartUploads::stagingKey), never at its final key.
 * The staged object is re-hashed as a stream and compared with the declared size and SHA-256; only then is it copied server-side to the final key
 * and ConfirmUpload marks the record ready. Nothing a client uploads ever lands on a content-addressed key, so another upload can't swap or
 * overwrite one. It reads the whole object once, which costs time and egress for a large file.
 *
 * Placing it: when the final key already holds an object, it is re-hashed (an upload from before staging keys may have left unverified bytes
 * there) and used as it is when it matches; otherwise the staged object is copied with a conditional write (ConditionalCopyStore: `If-None-Match: *`, so it never overwrites),
 * or, on a store without one, HEAD-then-copy through the disk. The staged object is then deleted either way. A random key (`content_type`) takes the
 * same path; ConfirmUpload re-hashes it again as it does for any random key.
 *
 * Fails closed: bad parts are refused with nothing sent to S3 (`media.upload.bad_parts`); a refused complete aborts the upload
 * (`media.upload.incomplete`); a re-hash mismatch, or a final key holding an object of another size, deletes the staged object and refuses
 * (`media.upload.mismatch`), leaving the final key untouched. The multipart id stays set until the object is in place, so no ordinary confirm can
 * mark the record ready meanwhile; it is cleared after either way, and the client starts again. A ready record is returned unchanged.
 */
final class CompleteMultipartUpload
{
    use AsAction;

    /** @param  array<mixed>  $parts  [{ part, etag, checksum }] from the client */
    public function handle(MediaUpload $upload, array $parts): MediaUpload
    {
        if ($upload->ready()) {
            return $upload;
        }
        $uploadId = $upload->multipart_upload_id ?? throw new RuntimeException('media.upload.not_multipart');
        $store = MultipartUploads::store($upload->disk) ?? throw new RuntimeException('media.upload.multipart_unavailable');
        $parts = $this->parts($parts, MultipartUploads::partCount($upload->bytes), MultipartUploads::partChecksums());
        $staging = MultipartUploads::stagingKey($upload->key, $upload->id);
        try {
            $store->complete($staging, $uploadId, $parts);
        } catch (Throwable) {
            AbortMultipartUpload::run($upload);

            throw new RuntimeException('media.upload.incomplete');
        }
        $disk = Storage::disk($upload->disk);
        $placed = false;
        try {
            if (! ($disk->exists($staging) && $disk->size($staging) === $upload->bytes && $this->hash($disk, $staging) === $upload->sha256)) {
                throw new RuntimeException('media.upload.mismatch');
            }
            $this->place($store, $disk, $staging, $upload);
            $placed = true;
        } finally {
            // The staged object goes whatever happened; then the id, so ConfirmUpload accepts the record. A staging delete that fails after the
            // object is placed fails the complete: the record stays pending, so the pruner deletes the staging object with its row.
            $cleaned = rescue(fn () => $disk->delete($staging), false, report: true) === true || ! rescue(fn () => $disk->exists($staging), true, report: true);
            MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $uploadId)->update(['multipart_upload_id' => null]);
            $upload->refresh();
        }
        if ($placed && ! $cleaned) {
            throw new RuntimeException('media.upload.incomplete');
        }
        $ready = ConfirmUpload::run($upload);
        // A replay of the mutation between the id being cleared and the ready write could have started another multipart upload: abort it.
        $fresh = MediaUpload::query()->find($upload->id);
        if ($fresh !== null && ($stray = $fresh->multipart_upload_id) !== null) {
            // The id is cleared only once the abort succeeds; otherwise it stays on the ready row and `kit:uploads:prune` retries the abort.
            if (rescue(fn (): bool => $store->abort($staging, $stray) === null, false, report: true)) {
                MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $stray)->update(['multipart_upload_id' => null]);
            }
            $ready->refresh();
        }

        return $ready;
    }

    /**
     * Copies the verified staged object to the final key without overwriting anything there. An object already there is re-hashed (an upload
     * from before staging keys may have joined unverified bytes there); one that doesn't match is refused, and deleted unless a ready record shares it.
     */
    private function place(MultipartStore $store, Filesystem $disk, string $staging, MediaUpload $upload): void
    {
        $copied = $store instanceof ConditionalCopyStore
            ? $store->copyIfAbsent($staging, $upload->key, $upload->bytes)
            : ! $disk->exists($upload->key) && $disk->copy($staging, $upload->key);
        if ($copied) {
            return;
        }
        if (! $disk->exists($upload->key)) {
            throw new RuntimeException('media.upload.incomplete');
        }
        if ($disk->size($upload->key) !== $upload->bytes || $this->hash($disk, $upload->key) !== $upload->sha256) {
            if (! MediaUpload::query()->where('disk', $upload->disk)->where('key', $upload->key)->where('status', 'ready')->exists()) {
                $disk->delete($upload->key);
            }

            throw new RuntimeException('media.upload.mismatch');
        }
    }

    /**
     * @param  array<mixed>  $parts
     * @return list<array{part: int, etag: string, checksum: ?string}>
     */
    private function parts(array $parts, int $count, bool $checksums): array
    {
        $clean = [];
        foreach ($parts as $part) {
            $number = is_array($part) && is_int($part['part'] ?? null) ? $part['part'] : 0;
            $etag = is_array($part) && is_string($part['etag'] ?? null) ? trim($part['etag']) : '';
            $checksum = is_array($part) && is_string($part['checksum'] ?? null) ? $part['checksum'] : null;
            $validChecksum = $checksum !== null && preg_match('/^[A-Za-z0-9+\/]{43}=$/', $checksum) === 1;
            if ($number < 1 || $number > $count || isset($clean[$number]) || $etag === '' || strlen($etag) > 128 || preg_match('/[^\x21-\x7e]/', $etag) === 1 || ($checksums && ! $validChecksum)) {
                throw new RuntimeException('media.upload.bad_parts');
            }
            $clean[$number] = ['part' => $number, 'etag' => $etag, 'checksum' => $checksums ? $checksum : null];
        }
        if (count($clean) !== $count) {
            throw new RuntimeException('media.upload.bad_parts');
        }
        ksort($clean);

        return array_values($clean);
    }

    private function hash(Filesystem $disk, string $key): string
    {
        $stream = $disk->readStream($key) ?: throw new RuntimeException('media.upload.missing');
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            is_resource($stream) && fclose($stream);
        }
    }
}
