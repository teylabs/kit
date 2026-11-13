<?php

namespace Tey\Kit\Features\Finalize\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Who confirmed a finalize step, when and through which channel, with the fingerprint of what they confirmed. Never updated or deleted. The kit's
 * behaviour for the finalization confirmation model, whichever table it reads.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own workspace throws `workspace_mismatch`.
 */
trait IsConfirmation
{
    public static function bootIsConfirmation(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where($q->qualifyColumn('workspace_id'), Workspaces::required()->getKey()));
        static::saving(function (self $row): void {
            $id = (int) Workspaces::required()->getKey();
            if ($row->exists ? (int) $row->getOriginal('workspace_id') !== $id : ($row->workspace_id !== null && (int) $row->workspace_id !== $id)) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $row->workspace_id = $id;
        });
        static::deleting(fn () => throw new \RuntimeException('finalize_history_is_kept'));
    }

    public function initializeIsConfirmation(): void
    {
        $this->timestamps = false;
        $this->guarded = ['id', 'workspace_id'];
        $this->mergeCasts(['confirmed_by' => 'integer', 'confirmed_at' => 'immutable_datetime']);
    }
}
