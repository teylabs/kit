<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\UploadSigning;
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
        // The id is kept until the re-hash has run, so ConfirmUpload refuses the record meanwhile and a concurrent confirm can't mark the joined
        // object ready unverified. It is cleared after: S3 has no open upload left, either way.
        $disk = Storage::disk($upload->disk);
        try {
            $matches = $disk->exists($upload->key) && $disk->size($upload->key) === $upload->bytes && $this->hash($disk, $upload->key) === $upload->sha256;
        } finally {
            MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $uploadId)->update(['multipart_upload_id' => null]);
            $upload->refresh();
        }
        if (! $matches) {
            if (! MediaUpload::query()->where('disk', $upload->disk)->where('key', $upload->key)->where('status', 'ready')->exists()) {
                $disk->delete($upload->key);
            }

            throw new RuntimeException('media.upload.mismatch');
        }

        // Just re-hashed, so a random-key (`content_type` signing) record isn't read a second time by ConfirmUpload.
        return UploadSigning::afterRehash($upload, fn () => ConfirmUpload::run($upload));
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
