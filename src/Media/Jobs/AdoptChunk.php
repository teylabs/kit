<?php

namespace Tey\Kit\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Jobs\TenantAware;
use Tey\Kit\Media\Console\AdoptMediaCommand;

/**
 * One chunk of an adopt run: the owners by id, adopted through the app's command. Safe to retry. Tenant-aware whatever the host's default: it
 * carries the tenant it was dispatched in, runs in it, and throws rather than run with no current tenant.
 */
class AdoptChunk implements ShouldQueue, TenantAware
{
    use InteractsWithQueue, Queueable;

    public $tries = 3;

    /**
     * @param  class-string<AdoptMediaCommand>  $command
     * @param  list<int|string>  $ids
     */
    public function __construct(public string $command, public array $ids) {}

    /** @return array{adopted: int, skipped: int, missing: int} */
    public function handle(): array
    {
        if (app(IsTenant::class)::current() === null) {
            throw new RuntimeException('media.adopt.tenant_required');
        }

        return app($this->command)->adoptChunk($this->ids);
    }
}
