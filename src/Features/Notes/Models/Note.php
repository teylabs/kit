<?php

namespace Tey\Kit\Features\Notes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * A workspace note. Always read and written inside its workspace's tenant context: the global scope keeps other workspaces' notes out, and a save or
 * delete outside the note's own workspace throws `workspace_mismatch`. Attachments are the private `attachments` media collection, with a `thumb`
 * made at upload for images.
 */
class Note extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $table = 'kit_notes';

    protected $guarded = ['id', 'workspace_id'];

    protected $casts = ['pinned' => 'boolean', 'version' => 'integer', 'author_id' => 'integer', 'subject_id' => 'integer'];

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where('kit_notes.workspace_id', Workspaces::required()->getKey()));
        static::saving(function (self $note): void {
            $id = (int) Workspaces::required()->getKey();
            if ($note->exists) {
                $note->assertPersistedOwnership();
            } elseif ($note->workspace_id !== null && (int) $note->workspace_id !== $id) {
                throw new \RuntimeException('workspace_mismatch');
            }
            $note->workspace_id = $id;
        });
        static::deleting(fn (self $note) => $note->assertPersistedOwnership());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk((string) config('kit.notes.media.disk', 'local'))->acceptsMimeTypes((array) config('kit.notes.media.accept', ['image/png', 'image/jpeg', 'application/pdf']));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(320)->height(240)->performOnCollections('attachments')->nonQueued();
    }

    /** A note's crumb reads as the start of its first line. */
    public function breadcrumbTitle(): string
    {
        return Str::limit(trim(Str::before((string) $this->body, "\n")), 40) ?: 'Note';
    }

    private function assertPersistedOwnership(): void
    {
        $workspaceId = (int) Workspaces::required()->getKey();
        if ((int) $this->getOriginal('workspace_id') !== $workspaceId || (int) $this->workspace_id !== $workspaceId) {
            throw new \RuntimeException('workspace_mismatch');
        }
    }
}
