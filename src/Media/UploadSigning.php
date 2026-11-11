<?php

namespace Tey\Kit\Media;

use InvalidArgumentException;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * What a direct upload's presigned PUT signs (`kit.media.signing`, docs/media.md › Signing). `full`, the default, signs Content-Type, Content-Length,
 * the immutable Cache-Control and the SHA-256 checksum under a content-addressed key, so the bucket proves the content and the same bytes dedupe.
 * `content_type` is for a bucket or client that chokes on the extra signed headers: the PUT signs only Content-Type, the key is random (a ULID with
 * the collection's extension), the declared SHA-256 is kept as advisory metadata, nothing is reused by it, and ConfirmUpload proves the content by
 * re-hashing the whole object as a stream before the record is ready.
 *
 * A record's key, not the setting, says which it was: a key named after its SHA-256 was proven by the bucket, any other key is re-hashed. So a record
 * requested in one mode and confirmed after the setting changed is still verified.
 */
final class UploadSigning
{
    public const FULL = 'full';

    public const CONTENT_TYPE = 'content_type';

    public static function mode(): string
    {
        $mode = config('kit.media.signing', self::FULL) ?? self::FULL;

        return in_array($mode, [self::FULL, self::CONTENT_TYPE], true)
            ? $mode
            : throw new InvalidArgumentException("kit.media.signing must be 'full' or 'content_type'.");
    }

    public static function full(): bool
    {
        return self::mode() === self::FULL;
    }

    /** Whether the record's key is named after its SHA-256, so the bucket's signed checksum proved the content (or dedupe trusted it). */
    public static function contentAddressed(MediaUpload $upload): bool
    {
        return pathinfo($upload->key, PATHINFO_FILENAME) === $upload->sha256;
    }

    /** Whether ConfirmUpload must re-hash the object: its key does not prove the content. */
    public static function needsRehash(MediaUpload $upload): bool
    {
        return ! self::contentAddressed($upload);
    }
}
