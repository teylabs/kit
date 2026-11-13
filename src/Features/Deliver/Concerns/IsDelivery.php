<?php

namespace Tey\Kit\Features\Deliver\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * One delivery: who sent what to whom, when, and what became of it. The kit's behaviour for the delivery model, whichever table it reads.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own
 * workspace throws `workspace_mismatch`. Rows are never deleted. The scheduler sweep, webhooks and the customer link find a row without the scope and
 * then work inside its workspace. The trait sets what the kit relies on (no timestamps, `id` and `workspace_id` guarded, the casts).
 */
trait IsDelivery
{
    public static function bootIsDelivery(): void
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

    public function initializeIsDelivery(): void
    {
        $this->timestamps = false;
        $this->guarded = ['id', 'workspace_id'];
        $this->mergeCasts(['test' => 'boolean', 'created_by' => 'integer', 'created_at' => 'immutable_datetime', 'send_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'finalization_id' => 'integer', 'resent_from_id' => 'integer', 'cancelled_by' => 'integer', 'cancelled_at' => 'immutable_datetime', 'token_expires_at' => 'immutable_datetime', 'token_revoked_at' => 'immutable_datetime', 'first_opened_at' => 'immutable_datetime']);
    }

    /** @return HasMany<\Illuminate\Database\Eloquent\Model, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(Kit::model('delivery_recipient'), 'delivery_id')->orderBy('id');
    }
}
