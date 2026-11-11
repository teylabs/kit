<?php

namespace Tey\Kit\Media;

use DateTimeInterface;

/**
 * The four S3 multipart calls the kit makes for one object key. `S3MultipartStore` is the real one; checks register a fake through
 * `MultipartUploads::fake()`. A `$sha256` is the part's SHA-256 as base64, or null when part checksums are off.
 */
interface MultipartStore
{
    /** Starts the upload and returns its id. */
    public function create(string $key, string $mime, string $cacheControl, bool $checksums): string;

    /** @return array{url: string, headers: array<string, string>} a presigned UploadPart */
    public function presignPart(string $key, string $uploadId, int $part, int $bytes, ?string $sha256, DateTimeInterface $expiresAt): array;

    /** @param  list<array{part: int, etag: string, checksum: ?string}>  $parts  in part order */
    public function complete(string $key, string $uploadId, array $parts): void;

    public function abort(string $key, string $uploadId): void;
}
