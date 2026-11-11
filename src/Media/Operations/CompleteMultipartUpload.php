<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\UploadSigning;
use Tey\Kit\Media\VerifiedObjects;
use Throwable;

/**
 * Step three for an S3 multipart upload (docs/media.md › Large files): the client's parts are checked (one per part number, each with an ETag, and
 * its SHA-256 when part checksums are on), S3 joins them, and then the whole object is re-hashed as a stream and compared with the declared size and
 * SHA-256 before ConfirmUpload marks the record ready. Part checksums only cover transit; the re-hash is what proves the object is the declared
 * content, so content-addressed keys and dedupe stay sound. It reads the whole object once, which costs time and egress for a large file.
 *
 * Fails closed: bad parts are refused with nothing sent to S3 (`media.upload.bad_parts`); a refused complete aborts the upload
 * (`media.upload.incomplete`); a re-hash mismatch deletes the object, unless a ready record shares it, and refuses (`media.upload.mismatch`). The
 * record then stays pending with no multipart id, so the client starts again. A ready record is returned unchanged.
 *
 * The multipart id stays set until the confirm run here clears it with the ready write, so no other confirm can mark the record ready meanwhile.
 * For a content-addressed key, the object's checksum (the ETag on S3, one HEAD) is read before the re-hash and that confirm compares it again, so an
 * object replaced at the key after the re-hash is refused (`media.upload.mismatch`) without a second full read. Any failure after the complete
 * (checksum, re-hash or confirm) deletes the object unless a ready record shares it.
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
        try {
            $store->complete($upload->key, $uploadId, $parts);
        } catch (Throwable) {
            AbortMultipartUpload::run($upload);

            throw new RuntimeException('media.upload.incomplete');
        }
        // The id is kept until the confirm has run, so every other confirm refuses the record meanwhile (`media.upload.multipart_pending`) and can't
        // mark the joined object ready unverified; the confirm run here clears it as it marks the record ready, in one conditional write. A
        // content-addressed key is trusted by ConfirmUpload, so the object's checksum (its ETag on S3: a HEAD) is read before the re-hash and handed
        // to it; it reads it again and refuses the record if it changed. A random key (`content_type`) is re-hashed by ConfirmUpload itself. Any
        // failure from here deletes the object (unless a ready record shares it) and clears the id, so the client starts again.
        $disk = Storage::disk($upload->disk);
        try {
            $verified = UploadSigning::contentAddressed($upload) && $disk->exists($upload->key) ? $this->checksum($disk, $upload->key) : null;
            if (! ($disk->exists($upload->key) && $disk->size($upload->key) === $upload->bytes && $this->hash($disk, $upload->key) === $upload->sha256)) {
                throw new RuntimeException('media.upload.mismatch');
            }

            return VerifiedObjects::during($upload->id, $uploadId, $verified, fn (): MediaUpload => ConfirmUpload::run($upload));
        } catch (Throwable $error) {
            $upload->refresh()->ready() || $this->discard($disk, $upload);

            throw $error;
        } finally {
            MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $uploadId)->update(['multipart_upload_id' => null]);
            $upload->refresh();
        }
    }

    private function discard(Filesystem $disk, MediaUpload $upload): void
    {
        if (! MediaUpload::query()->where('disk', $upload->disk)->where('key', $upload->key)->where('status', 'ready')->exists()) {
            $disk->delete($upload->key);
        }
    }

    private function checksum(Filesystem $disk, string $key): string
    {
        $checksum = $disk->checksum($key);

        return is_string($checksum) && $checksum !== '' ? $checksum : throw new RuntimeException('media.upload.missing');
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
