<?php

namespace Tey\Kit\Features\Notes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Tey\Kit\Features\Workspaces\Models\Workspace;

class Note extends Model
{
    public $timestamps = false;

    protected $fillable = ['body'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('workspace_id', Workspace::required()->getKey()));
        static::saving(function (self $note): void {
            $id = Workspace::required()->getKey();
            if ($note->exists) {
                $note->assertPersistedOwnership();
            } elseif ($note->workspace_id !== null && (int) $note->workspace_id !== $id) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $note->workspace_id = $id;
        });
        static::deleting(fn (self $note) => $note->assertPersistedOwnership());
    }

    /** A note's crumb reads as the start of its first line. */
    public function breadcrumbTitle(): string
    {
        return Str::limit(trim(Str::before((string) $this->body, "\n")), 40) ?: 'Note';
    }

    private function assertPersistedOwnership(): void
    {
        $workspaceId = Workspace::required()->getKey();
        if ((int) $this->getOriginal('workspace_id') !== $workspaceId
            || (int) $this->workspace_id !== $workspaceId
            || $this->getKey() !== $this->getOriginal($this->getKeyName())
            || ! static::query()->whereKey($this->getOriginal($this->getKeyName()))->exists()) {
            throw new \RuntimeException('workspace_mismatch');
        }
    }
}
