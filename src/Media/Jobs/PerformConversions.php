<?php

namespace Tey\Kit\Media\Jobs;

use Spatie\MediaLibrary\Conversions\FileManipulator;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Media Library's conversion job, one image per job, on `kit.media.queue` (default `media`) whatever queue it is handed, with retries, backoff and a
 * timeout sized for a large PNG. Set it as `media-library.jobs.perform_conversions`. A retry regenerates only what is missing.
 *
 * Tenant-aware whatever the host's default (spatie's TenantAware): it carries the tenant it was dispatched in, runs in it, and spatie restores the
 * previous tenant afterwards. It throws rather than run with no current tenant, so queue tenant media's conversions inside the tenant.
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
            return parent::handle($fileManipulator);
        } finally {
            ini_set('memory_limit', (string) $previous);
        }
    }
}
