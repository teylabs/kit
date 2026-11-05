<?php

namespace Tey\Kit\Features\Finalize\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Who confirmed a finalize step, when and through which channel, with the fingerprint of what they confirmed. Never updated or deleted.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own workspace throws `workspace_mismatch`.
 */
class Confirmation extends Model
{
    public $timestamps = false;

    protected $table = 'kit_finalize_confirmations';

    protected $guarded = ['id', 'workspace_id'];

    protected $casts = ['confirmed_by' => 'integer', 'confirmed_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('kit_finalize_confirmations.workspace_id', Workspaces::required()->getKey()));
        static::saving(function (self $row): void {
            $id = (int) Workspaces::required()->getKey();
            if ($row->exists ? (int) $row->getOriginal('workspace_id') !== $id : ($row->workspace_id !== null && (int) $row->workspace_id !== $id)) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $row->workspace_id = $id;
        });
        static::deleting(fn () => throw new \RuntimeException('finalize_history_is_kept'));
    }
}
