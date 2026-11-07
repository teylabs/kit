<?php

namespace Tey\Kit\Features\Deliver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * One delivery: who sent what to whom, when, and what became of it.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own
 * workspace throws `workspace_mismatch`. Rows are never deleted. The scheduler sweep, webhooks and the customer link find a row without the scope and
 * then work inside its workspace.
 */
class DeliveryRecord extends Model
{
    public $timestamps = false;

    protected $table = 'kit_deliveries';

    protected $guarded = ['id', 'workspace_id'];

    protected $casts = ['test' => 'boolean', 'created_by' => 'integer', 'created_at' => 'immutable_datetime', 'send_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'finalization_id' => 'integer', 'resent_from_id' => 'integer', 'cancelled_by' => 'integer', 'cancelled_at' => 'immutable_datetime', 'token_expires_at' => 'immutable_datetime', 'token_revoked_at' => 'immutable_datetime', 'first_opened_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('kit_deliveries.workspace_id', Workspaces::required()->getKey()));
        static::saving(function (self $row): void {
            $id = (int) Workspaces::required()->getKey();
            if ($row->exists ? (int) $row->getOriginal('workspace_id') !== $id : ($row->workspace_id !== null && (int) $row->workspace_id !== $id)) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $row->workspace_id = $id;
        });
        static::deleting(fn () => throw new \RuntimeException('deliver_history_is_kept'));
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<DeliveryRecipient, $this> */
    public function recipients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliveryRecipient::class, 'delivery_id')->orderBy('id');
    }
}
