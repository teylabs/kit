<?php

namespace Tey\Kit\Features\Finalize\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * One finalization of a subject, and its undoing if it was undone. Rows are never deleted: they are the subject's finalize history.
 * Read and written only inside its workspace's tenant context: the global scope keeps other workspaces' rows out, and a save outside the row's own workspace throws `workspace_mismatch`.
 */
class Finalization extends Model
{
    public $timestamps = false;

    protected $table = 'kit_finalizations';

    protected $guarded = ['id', 'workspace_id'];

    protected $casts = ['finalized_by' => 'integer', 'finalized_at' => 'immutable_datetime', 'unfinalized_by' => 'integer', 'unfinalized_at' => 'immutable_datetime', 'snapshot' => 'array', 'snapshot_hashes' => 'array'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('kit_finalizations.workspace_id', Workspaces::required()->getKey()));
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
