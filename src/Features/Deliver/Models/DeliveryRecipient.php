<?php

namespace Tey\Kit\Features\Deliver\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * One address on a delivery: its role (to, cc, bcc, or copy: a CopiesSends Bcc) and what the provider last said about it.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own
 * workspace throws `workspace_mismatch`. Rows are never deleted. The scheduler sweep, webhooks and the customer link find a row without the scope and
 * then work inside its workspace.
 */
class DeliveryRecipient extends Model
{
    public $timestamps = false;

    protected $table = 'kit_delivery_recipients';

    protected $guarded = ['id', 'workspace_id'];

    protected $casts = ['delivery_id' => 'integer', 'last_event_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('kit_delivery_recipients.workspace_id', Workspaces::required()->getKey()));
        static::saving(function (self $row): void {
            $id = (int) Workspaces::required()->getKey();
            if ($row->exists ? (int) $row->getOriginal('workspace_id') !== $id : ($row->workspace_id !== null && (int) $row->workspace_id !== $id)) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $row->workspace_id = $id;
        });
        static::deleting(fn () => throw new \RuntimeException('deliver_history_is_kept'));
    }
}
