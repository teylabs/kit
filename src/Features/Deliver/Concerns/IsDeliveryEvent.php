<?php

namespace Tey\Kit\Features\Deliver\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * One provider event for one address, kept once: a webhook delivered twice adds nothing. The kit's behaviour for the delivery event model,
 * whichever table it reads.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own
 * workspace throws `workspace_mismatch`. Rows are never deleted. The scheduler sweep, webhooks and the customer link find a row without the scope and
 * then work inside its workspace.
 */
trait IsDeliveryEvent
{
    public static function bootIsDeliveryEvent(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where($q->qualifyColumn('workspace_id'), Workspaces::required()->getKey()));
        static::saving(function (self $row): void {
            $id = (int) Workspaces::required()->getKey();
            if ($row->exists ? (int) $row->getOriginal('workspace_id') !== $id : ($row->workspace_id !== null && (int) $row->workspace_id !== $id)) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $row->workspace_id = $id;
        });
        static::deleting(fn () => throw new \RuntimeException('deliver_history_is_kept'));
    }

    public function initializeIsDeliveryEvent(): void
    {
        $this->timestamps = false;
        $this->guarded = ['id', 'workspace_id'];
        $this->mergeCasts(['recipient_id' => 'integer', 'occurred_at' => 'immutable_datetime']);
    }
}
