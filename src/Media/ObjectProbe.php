<?php

namespace Tey\Kit\Media;

use finfo;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * What an object is, from its size (a HEAD on a bucket) and only its first bytes: never the whole object.
 */
final class ObjectProbe
{
    private const HEAD_BYTES = 262_144;

    /** @return array{bytes: int, mime: string, width: ?int, height: ?int} */
    public static function probe(Filesystem $disk, string $key): array
    {
        $stream = $disk->readStream($key);
        try {
            $head = (string) stream_get_contents($stream, self::HEAD_BYTES);
        } finally {
            is_resource($stream) && fclose($stream);
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($head);
        $size = str_starts_with($mime, 'image/') ? @getimagesizefromstring($head) : false;

        return ['bytes' => (int) $disk->size($key), 'mime' => $mime, 'width' => $size === false ? null : $size[0], 'height' => $size === false ? null : $size[1]];
    }
}
