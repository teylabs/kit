<?php

namespace Tey\Kit\Media;

use Closure;

/**
 * Internal: lets the ConfirmUpload that CompleteMultipartUpload runs, in-process and only for that call, confirm a record whose multipart id is
 * still set (the database-visible guard that keeps every other confirm out), and hands it the checksum complete read before its re-hash (the
 * object's ETag on S3; null for a random key, which ConfirmUpload re-hashes itself). Nothing is persisted; outside `during()` nothing is expected.
 */
final class VerifiedObjects
{
    /** @var array<string, array{upload_id: string, checksum: ?string}> record id => what complete verified */
    private static array $expected = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function during(string $recordId, string $uploadId, ?string $checksum, Closure $callback): mixed
    {
        $previous = self::$expected[$recordId] ?? null;
        self::$expected[$recordId] = ['upload_id' => $uploadId, 'checksum' => $checksum];
        try {
            return $callback();
        } finally {
            if ($previous === null) {
                unset(self::$expected[$recordId]);
            } else {
                self::$expected[$recordId] = $previous;
            }
        }
    }

    /** @return ?array{upload_id: string, checksum: ?string} */
    public static function expected(string $recordId): ?array
    {
        return self::$expected[$recordId] ?? null;
    }
}
