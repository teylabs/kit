<?php

namespace Tey\Kit\Media\Jobs;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\ImageGenerators\ImageGeneratorFactory;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Media Library's conversion job, one image per job, on `kit.media.queue` (default `media`) whatever queue it is handed, with retries, backoff and a
 * timeout sized for a large PNG. Set it as `media-library.jobs.perform_conversions`. A retry regenerates only what is missing.
 *
 * Tenant-aware whatever the host's default (spatie's TenantAware): it carries the tenant it was dispatched in, runs in it, and spatie restores the
 * previous tenant afterwards. It throws rather than run with no current tenant, so queue tenant media's conversions inside the tenant.
 *
 * It fails closed, never finishing silently with nothing made: an original it cannot read (a refused read gives Media Library a null stream on a disk that does
 * not throw, and it then writes nothing and returns) is `media.conversions.unreadable_original`, and an image a generator can convert that ends with none of the
 * requested conversions is `media.conversions.none_generated`. Both are retried (tries and backoff). A media row deleted before the job runs still drops the
 * job (`$deleteWhenMissingModels`), with a warning naming the media id.
 */
class PerformConversions extends PerformConversionsJob implements TenantAware
{
    public $tries;

    public $timeout;

    /** @var list<int> */
    public $backoff;

    public function __construct(...$arguments)
    {
        parent::__construct(...$arguments);
        $this->tries = (int) config('kit.media.tries', 3);
        $this->timeout = (int) config('kit.media.timeout', 300);
        $this->backoff = config('kit.media.backoff', [30, 120, 600]);
        $this->queue = Conversions::queue();
    }

    public function onQueue($queue)
    {
        $this->queue = Conversions::queue();

        return $this;
    }

    public function handle(FileManipulator $fileManipulator): bool
    {
        if (app(IsTenant::class)::current() === null) {
            throw new RuntimeException('media.conversions.tenant_required');
        }
        // A retry after a partial run (or a timeout) does only the conversions not yet generated.
        $this->onlyMissing = $this->onlyMissing || $this->attempts() > 1;
        $limit = (string) config('kit.media.memory_limit', '1024M');
        $previous = ini_get('memory_limit');
        if ($limit !== '' && $previous !== '-1') {
            ini_set('memory_limit', $limit);
        }
        try {
            $this->assertReadableOriginal();
            $result = parent::handle($fileManipulator);
            $this->assertGenerated();

            return $result;
        } finally {
            ini_set('memory_limit', (string) $previous);
        }
    }

    /** A dropped job is still logged: the media row was deleted before the conversion ran. */
    public function __unserialize(array $values): void
    {
        try {
            parent::__unserialize($values);
        } catch (ModelNotFoundException $missing) {
            // The id comes from the serialized identifier: the restoring query does not record it on the exception.
            $ids = array_values(array_map(fn (ModelIdentifier $identifier): mixed => $identifier->id, array_filter($values, fn (mixed $value): bool => $value instanceof ModelIdentifier && $value->class === $missing->getModel())));
            Log::warning('media.conversions.missing_model', ['model' => $missing->getModel(), 'media_id' => count($ids) === 1 ? $ids[0] : $ids]);

            throw $missing;
        }
    }

    private function assertReadableOriginal(): void
    {
        $stream = Storage::disk($this->media->disk)->readStream($this->media->getPathRelativeToRoot());
        try {
            $readable = is_resource($stream) && fread($stream, 1) !== '';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $readable) {
            throw new RuntimeException('media.conversions.unreadable_original');
        }
    }

    private function assertGenerated(): void
    {
        $generator = ImageGeneratorFactory::forMedia($this->media);
        $requested = $this->conversions->map(fn (Conversion $conversion): string => $conversion->getName())->values()->all();
        if ($generator === null || ! $generator->canConvert($this->media) || $requested === []) {
            return;
        }
        $media = $this->media->fresh() ?? $this->media;
        foreach ($requested as $name) {
            if ($media->hasGeneratedConversion($name)) {
                return;
            }
        }

        throw new RuntimeException('media.conversions.none_generated');
    }
}
