<?php

namespace Tey\Kit\Media;

use Closure;

/**
 * Internal: hands the checksum CompleteMultipartUpload read before its re-hash (the object's ETag on S3) to the ConfirmUpload it runs, in-process and
 * only for that call, so the confirm can refuse an object replaced at the key since. Nothing is persisted; outside `during()` nothing is expected.
 */
final class VerifiedObjects
{
    /** @var array<string, string> upload id => checksum */
    private static array $expected = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function during(string $uploadId, string $checksum, Closure $callback): mixed
    {
        $previous = self::$expected[$uploadId] ?? null;
        self::$expected[$uploadId] = $checksum;
        try {
            return $callback();
        } finally {
            if ($previous === null) {
                unset(self::$expected[$uploadId]);
            } else {
                self::$expected[$uploadId] = $previous;
            }
        }
    }

    public static function expected(string $uploadId): ?string
    {
        return self::$expected[$uploadId] ?? null;
    }
}
