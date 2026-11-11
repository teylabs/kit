<?php

namespace Tey\Kit\Media;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * Opt-in S3 multipart for large direct uploads (`kit.media.multipart`, docs/media.md › Large files). Multipart is used only when the flag is on, the
 * file is over the threshold, the client offered a `complete` route, the disk is S3 (or a check faked a store for it) and the uploads table has the
 * `multipart_upload_id` column. Anything else is the single presigned PUT, unchanged.
 */
final class MultipartUploads
{
    /** The S3 minimum part size; every part but the last must be at least this. */
    public const MIN_PART_BYTES = 5 * 1024 * 1024;

    /** The S3 maximum part count. */
    public const MAX_PARTS = 10_000;

    /** @var array<string, MultipartStore> */
    private static array $fakes = [];

    /** @var array<string, bool> */
    private static array $columns = [];

    public static function enabled(): bool
    {
        return (bool) config('kit.media.multipart.enabled', false);
    }

    public static function thresholdBytes(): int
    {
        return max(0, (int) config('kit.media.multipart.threshold_bytes', 100 * 1024 * 1024));
    }

    public static function partBytes(): int
    {
        return max(self::MIN_PART_BYTES, (int) config('kit.media.multipart.part_bytes', 16 * 1024 * 1024));
    }

    public static function partChecksums(): bool
    {
        return (bool) config('kit.media.multipart.part_checksums', true);
    }

    /** The number of parts: as many whole `partBytes()` as fit (at least one, at most 10,000), so every part is at least `partBytes()`. */
    public static function partCount(int $bytes): int
    {
        return max(1, min(self::MAX_PARTS, intdiv($bytes, self::partBytes())));
    }

    /**
     * Part `$part`'s byte length. Parts are `ceil(bytes / count)` long and the last takes the rest, so the client derives the same layout from the
     * file size and the number of parts in its ticket alone.
     */
    public static function partLength(int $bytes, int $part): int
    {
        $size = (int) ceil($bytes / self::partCount($bytes));

        return min($size, $bytes - ($part - 1) * $size);
    }

    /**
     * Where an upload's parts are joined: a per-record key beside its final key (`<dir>/.staging/<record id>`), never the final key itself, so
     * nothing a client sends lands on a content-addressed key. CompleteMultipartUpload copies it over once verified; the pruner deletes leftovers.
     */
    public static function stagingKey(string $key, string $recordId): string
    {
        $dir = dirname($key);

        return ($dir === '.' ? '' : $dir.'/').'.staging/'.$recordId;
    }

    public static function store(string $disk): ?MultipartStore
    {
        if (isset(self::$fakes[$disk])) {
            return self::$fakes[$disk];
        }
        $adapter = Storage::disk($disk);

        return $adapter instanceof AwsS3V3Adapter ? new S3MultipartStore($adapter) : null;
    }

    public static function hasColumn(): bool
    {
        // Remembered per connection, database and table, so a process that switches tenant databases checks each one.
        $connection = (new MediaUpload)->getConnection();
        $table = MediaUploads::table();
        $name = $connection->getName().'|'.$connection->getDatabaseName().'|'.$table;

        return self::$columns[$name] ??= Schema::connection($connection->getName())->hasColumn($table, 'multipart_upload_id');
    }

    public static function eligible(string $disk, int $bytes): bool
    {
        return self::enabled() && $bytes > self::thresholdBytes() && self::store($disk) !== null && self::hasColumn();
    }

    /** A seam for checks: a store for a disk (null removes it). Also forgets the remembered column check. */
    public static function fake(string $disk, ?MultipartStore $store): void
    {
        if ($store === null) {
            unset(self::$fakes[$disk]);
        } else {
            self::$fakes[$disk] = $store;
        }
        self::$columns = [];
    }
}
