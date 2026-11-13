<?php

namespace Tey\Kit\Features\Notes\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * A workspace note: the kit's behaviour for the note model, whichever table it reads. Always read and written inside its workspace's tenant context:
 * the global scope keeps other workspaces' notes out, and a save or delete outside the note's own workspace throws `workspace_mismatch`. Attachments are
 * the private `attachments` media collection, with a `thumb` queued at upload for images (on a sync queue it exists straight away; until then the card
 * keeps its placeholder, never the original).
 */
trait IsNote
{
    use InteractsWithMedia, SoftDeletes;

    public static function bootIsNote(): void
    {
        static::addGlobalScope('workspace', fn (Builder $q) => $q->where($q->qualifyColumn('workspace_id'), Workspaces::required()->getKey()));
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

    public function initializeIsNote(): void
    {
        $this->guarded = ['id', 'workspace_id'];
        $this->mergeCasts(['pinned' => 'boolean', 'version' => 'integer', 'author_id' => 'integer', 'subject_id' => 'integer']);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk((string) config('kit.notes.media.disk', 'local'))->acceptsMimeTypes((array) config('kit.notes.media.accept', ['image/png', 'image/jpeg', 'application/pdf']));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(320)->height(240)->performOnCollections('attachments')->queued();
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
