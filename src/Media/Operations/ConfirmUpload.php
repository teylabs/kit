<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * Step three: the object is checked against its declaration and the record marked ready. On a bucket that is a HEAD for the size and a read of only
 * the first bytes for the type and dimensions, never the whole object (the bucket already verified the checksum). On a local disk the file is hashed.
 * With `$file` (the multipart fallback) the body is checked first and written to the key. A mismatch deletes the object, unless a ready record shares
 * it, and leaves the record pending for the app's pruner. Confirming a ready record returns it unchanged.
 */
class ConfirmUpload
{
    use AsAction;

    private const HEAD_BYTES = 262_144;

    public function handle(MediaUpload $upload, ?UploadedFile $file = null): MediaUpload
    {
        if ($upload->ready()) {
            return $upload;
        }
        $disk = Storage::disk($upload->disk);
        if ($file !== null) {
            if ($file->getSize() !== $upload->bytes || hash_file('sha256', $file->getRealPath()) !== $upload->sha256) {
                throw new RuntimeException('media.upload.mismatch');
            }
            $stream = fopen($file->getRealPath(), 'rb');
            try {
                $disk->writeStream($upload->key, $stream, ['CacheControl' => RequestUpload::CACHE_CONTROL, 'ContentType' => $upload->mime]);
            } finally {
                is_resource($stream) && fclose($stream);
            }
        }
        if (! $disk->exists($upload->key)) {
            throw new RuntimeException('media.upload.missing');
        }
        [$mime, $width, $height] = $this->sniff($disk, $upload);
        $local = method_exists($disk, 'getAdapter') && $disk->getAdapter() instanceof LocalFilesystemAdapter;
        if ($disk->size($upload->key) !== $upload->bytes || $mime !== $upload->mime || ($local && $this->hash($disk, $upload->key) !== $upload->sha256)) {
            $this->discard($disk, $upload);
            throw new RuntimeException('media.upload.mismatch');
        }
        $upload->forceFill(['status' => 'ready', 'width' => $width, 'height' => $height])->save();

        return $upload;
    }

    /** @return array{0: string, 1: ?int, 2: ?int} */
    private function sniff(Filesystem $disk, MediaUpload $upload): array
    {
        $stream = $disk->readStream($upload->key);
        try {
            $head = (string) stream_get_contents($stream, self::HEAD_BYTES);
        } finally {
            is_resource($stream) && fclose($stream);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
        $size = str_starts_with($mime, 'image/') ? @getimagesizefromstring($head) : false;

        return [$mime, $size === false ? null : $size[0], $size === false ? null : $size[1]];
    }

    private function hash(Filesystem $disk, string $key): string
    {
        $stream = $disk->readStream($key);
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            is_resource($stream) && fclose($stream);
        }
    }

    private function discard(Filesystem $disk, MediaUpload $upload): void
    {
        $shared = MediaUpload::query()->where('disk', $upload->disk)->where('key', $upload->key)->where('status', 'ready')->exists();
        if (! $shared) {
            $disk->delete($upload->key);
        }
    }
}
