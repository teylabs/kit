<?php

namespace Tey\Kit\Media\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\MediaUploads;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\Operations\AbortMultipartUpload;
use Throwable;

/**
 * Prunes pending uploads nobody confirmed: rows still `pending` and last touched more than `--older-than` hours ago (default
 * `kit.media.prune_older_than`, 24). Ready rows are never touched. `--dry-run` reports the counts, the bytes and a few sample keys and changes nothing.
 *
 * The object is reference-counted. Keys are content-addressed under the app's prefix (`<prefix>/<sha[0..2]>/<sha>.<ext>`), not under a tenant or
 * scope, so the same object may back rows in several scopes and Media items of several owners. A key is therefore never unique to a tenant, and
 * matching references only within one would delete an object another tenant still uses. References are matched on the disk and key across the whole
 * ledger and the whole media table on the current connection: that only ever keeps more, and nothing of another scope is read beyond whether it
 * exists, changed or reported. The object goes only when no other upload row (any status, any scope) and no Media item over the key remain. Where
 * tenants have separate databases (`multitenancy.tenant_database_connection_name`), another tenant's rows can't be seen, so objects are always kept.
 *
 * Fail closed: when the references for a key can't be checked (a missing table, a disk error), nothing for that key is deleted, not even its rows;
 * it is reported and the command exits nonzero. Stale rows are read in id chunks (`--chunk`, default 500).
 *
 * A stale row with an S3 multipart upload still open (`multipart_upload_id`) has it aborted first, so the bucket drops its parts. An abort that fails
 * keeps that key's rows and object, is reported and fails the run, like any other failure. On a disk that can do multipart, a stale row's staging
 * object (MultipartUploads::stagingKey, left by a complete that died) is deleted after that, and a delete that fails keeps the rows the same way.
 */
class PruneUploadsCommand extends Command
{
    protected $signature = 'kit:uploads:prune
        {--older-than= : Hours a pending upload must be untouched before it is pruned (default kit.media.prune_older_than, 24)}
        {--dry-run : Report what would be deleted; change nothing}
        {--chunk=500 : Pending rows read per chunk}';

    protected $description = 'Prune abandoned pending direct uploads, deleting an object only when nothing else references it';

    private const SAMPLES = 5;

    /** Registers the prune on the schedule when `kit.media.prune_schedule` asks for it (true for daily at 03:00, or a cron expression); off by default. */
    public static function schedule(Schedule $schedule): void
    {
        $when = config('kit.media.prune_schedule', false);
        if ($when === true || (is_string($when) && $when !== '')) {
            $schedule->command('kit:uploads:prune')->cron(is_string($when) ? $when : '0 3 * * *')->withoutOverlapping()->onOneServer();
        }
    }

    public function handle(): int
    {
        $hours = $this->option('older-than') ?? config('kit.media.prune_older_than') ?? 24;
        // A whole number only, from the option or the config: `1.5` is refused rather than truncated to 1 and pruning more than asked.
        if (! (is_int($hours) || (is_string($hours) && ctype_digit($hours))) || (int) $hours < 1) {
            $this->error('--older-than must be a whole number of hours, at least 1.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subHours((int) $hours);
        $counts = ['rows' => 0, 'objects' => 0, 'kept' => 0, 'bytes' => 0, 'failed' => 0, 'aborted' => 0];
        $samples = [];
        $failures = [];
        if (! $this->ledgerExists()) {
            $this->error('The upload ledger ('.MediaUploads::table().') is missing; nothing pruned.');

            return self::FAILURE;
        }
        $seen = [];
        MediaUpload::query()->where('status', 'pending')->where('updated_at', '<', $cutoff)
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $chunk) use ($cutoff, $dry, &$counts, &$samples, &$failures, &$seen): void {
                foreach ($chunk->groupBy(fn (MediaUpload $upload): string => $upload->disk."\0".$upload->key) as $group => $uploads) {
                    // A key whose rows span chunks is settled the first time it is seen, so a later chunk never re-counts it.
                    if (isset($seen[$group])) {
                        continue;
                    }
                    $seen[$group] = true;
                    $this->prune($uploads->first()->disk, $uploads->first()->key, $cutoff, $dry, $counts, $samples, $failures);
                }
            });
        $this->abortStrays($cutoff, $dry, $counts, $failures);
        $verb = $dry ? 'would delete' : 'deleted';
        $this->line(sprintf('Pending uploads older than %d h: %s %d rows and %d objects (%d bytes); %d objects kept as still referenced; %d keys failed.',
            (int) $hours, $verb, $counts['rows'], $counts['objects'], $counts['bytes'], $counts['kept'], $counts['failed']));
        // Printed only when there was one, so a run with no multipart uploads reads exactly as before.
        if ($counts['aborted'] > 0) {
            $this->line(sprintf('S3 multipart uploads %s: %d.', $dry ? 'that would be aborted' : 'aborted', $counts['aborted']));
        }
        foreach ($samples as $key) {
            $this->line('  '.$key);
        }
        foreach ($failures as $failure) {
            $this->warn('  not pruned: '.$failure);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Prunes every stale pending row over one disk and key, and the object when nothing else references it.
     *
     * @param  array{rows: int, objects: int, kept: int, bytes: int, failed: int, aborted: int}  $counts
     * @param  list<string>  $samples
     * @param  list<string>  $failures
     */
    private function prune(string $disk, string $key, mixed $cutoff, bool $dry, array &$counts, array &$samples, array &$failures): void
    {
        try {
            $stale = $this->stale($disk, $key, $cutoff);
            $referenced = $this->referenced($disk, $key, $stale);
            $storage = Storage::disk($disk);
            $exists = $storage->exists($key);
            $bytes = $exists && ! $referenced ? (int) $storage->size($key) : 0;
            $multipart = $this->multipart($stale);
        } catch (Throwable $error) {
            $counts['failed']++;
            $failures[] = $disk.':'.$key.' ('.$this->reason($error).')';

            return;
        }
        if (count($samples) < self::SAMPLES) {
            $samples[] = $disk.':'.$key;
        }
        if (! $dry) {
            try {
                // Checked again just before the delete, so a reference made since the first check still keeps the object. The object goes
                // before the rows: a delete the disk refuses leaves the rows for the next run instead of an object nothing points at.
                // The stale set is read again too: a row confirmed or touched since the first read is no longer stale, so it counts as a
                // reference and is not deleted.
                $stale = $this->stale($disk, $key, $cutoff);
                // Open multipart uploads are aborted before anything is deleted: an abort that fails throws, and the rows and object stay.
                $multipart = $this->multipart($stale);
                foreach ($multipart as $upload) {
                    AbortMultipartUpload::run($upload);
                }
                // Then any staged object a complete left behind (deleted only after the abort, so no part can still be joined there).
                $this->deleteStaging($disk, $key, $stale);
                if ($exists && ! $referenced) {
                    $referenced = $this->referenced($disk, $key, $stale);
                    if (! $referenced && ! $storage->delete($key)) {
                        throw new \RuntimeException('media.prune.delete_failed');
                    }
                }
                MediaUpload::query()->whereKey($stale)->where('status', 'pending')->where('updated_at', '<', $cutoff)->delete();
            } catch (Throwable $error) {
                $counts['failed']++;
                $failures[] = $disk.':'.$key.' ('.$this->reason($error).')';

                return;
            }
        }
        $counts['rows'] += count($stale);
        $counts['aborted'] += count($multipart);
        if ($referenced) {
            $counts['kept']++;
        } elseif ($exists) {
            $counts['objects']++;
            $counts['bytes'] += $bytes;
        }
    }

    /** @return list<string> the ids of every stale pending row over the key */
    private function stale(string $disk, string $key, mixed $cutoff): array
    {
        return MediaUpload::query()->where('disk', $disk)->where('key', $key)->where('status', 'pending')->where('updated_at', '<', $cutoff)
            ->pluck('id')->all();
    }

    /**
     * The stale rows with an S3 multipart upload still open; none when the table has no multipart column.
     *
     * @param  list<string>  $stale
     * @return Collection<int, MediaUpload>
     */
    private function multipart(array $stale): Collection
    {
        if ($stale === [] || ! MultipartUploads::hasColumn()) {
            return new Collection;
        }

        return MediaUpload::query()->whereKey($stale)->whereNotNull('multipart_upload_id')->get();
    }

    /**
     * A ready row can still hold a multipart id when a replay started an upload while its complete ran and the abort there failed: that upload is
     * aborted here (once the row is past the cutoff) and the id cleared; a failed abort keeps the id, is reported and fails the run.
     *
     * @param  array{rows: int, objects: int, kept: int, bytes: int, failed: int, aborted: int}  $counts
     * @param  list<string>  $failures
     */
    private function abortStrays(mixed $cutoff, bool $dry, array &$counts, array &$failures): void
    {
        if (! MultipartUploads::hasColumn()) {
            return;
        }
        foreach (MediaUpload::query()->where('status', 'ready')->whereNotNull('multipart_upload_id')->where('updated_at', '<', $cutoff)->get() as $upload) {
            try {
                if (! $dry) {
                    $store = MultipartUploads::store($upload->disk) ?? throw new \RuntimeException('media.upload.multipart_unavailable');
                    $store->abort(MultipartUploads::stagingKey($upload->key, $upload->id), (string) $upload->multipart_upload_id);
                    MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $upload->multipart_upload_id)->toBase()->update(['multipart_upload_id' => null]);
                }
                $counts['aborted']++;
            } catch (Throwable $error) {
                $counts['failed']++;
                $failures[] = $upload->disk.':'.$upload->key.' ('.$this->reason($error).')';
            }
        }
    }

    /**
     * Deletes the staging objects (MultipartUploads::stagingKey) of stale rows on a disk that can do multipart. A delete the disk refuses throws, so
     * the rows stay for the next run.
     *
     * @param  list<string>  $stale
     */
    private function deleteStaging(string $disk, string $key, array $stale): void
    {
        if ($stale === [] || MultipartUploads::store($disk) === null || ! MultipartUploads::hasColumn()) {
            return;
        }
        $storage = Storage::disk($disk);
        foreach ($stale as $id) {
            $staging = MultipartUploads::stagingKey($key, $id);
            if ($storage->exists($staging) && ! $storage->delete($staging)) {
                throw new \RuntimeException('media.prune.delete_failed');
            }
        }
    }

    /** @param  list<string>  $stale */
    private function referenced(string $disk, string $key, array $stale): bool
    {
        if (MediaUpload::query()->where('disk', $disk)->where('key', $key)->whereKeyNot($stale)->exists()) {
            return true;
        }
        $class = config('media-library.media_model', Media::class);
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new \RuntimeException('media.prune.no_media_model');
        }
        /** @var Model $model */
        $model = new $class;
        if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
            throw new \RuntimeException('media.prune.no_media_table');
        }
        // Checked after the media table, so a tenant-database setup still fails closed when it can't see the table.
        if (config('multitenancy.tenant_database_connection_name') !== null) {
            return true;
        }

        // An item over the key (AttachUpload, AdoptObject) has the key's basename as its file name; any such item on the disk counts, whatever its
        // custom properties say, so a false match only ever keeps the object.
        return $model->newQuery()->where('disk', $disk)->where('file_name', basename($key))->exists();
    }

    private function reason(Throwable $error): string
    {
        return $error->getMessage() !== '' ? $error->getMessage() : $error::class;
    }

    private function ledgerExists(): bool
    {
        try {
            return Schema::connection((new MediaUpload)->getConnectionName())->hasTable(MediaUploads::table());
        } catch (Throwable) {
            return false;
        }
    }
}
