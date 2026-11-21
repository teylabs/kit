<?php

namespace Tey\Kit\Features\Workspaces\Flags;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Spatie\Tags\Tag as SpatieTag;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The kit's tag, on `tags`: spatie/laravel-tags' model with a workspace. A flag (type `flag`) is one global vocabulary, `workspace_id` null, shared
 * by every workspace. Any other tag (a person's label) belongs to the workspace it was made in, and a workspace sees only its own labels and the flags.
 */
class Tag extends SpatieTag
{
    protected $table = 'tags';

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            if ($tag->type !== Flags::TYPE && $tag->workspace_id === null) {
                // Fail closed: a non-flag tag saved with no workspace would read as shared vocabulary in every workspace.
                $tag->workspace_id = Workspaces::current()?->getKey() ?? throw new RuntimeException('tag_workspace_required: a tag that is not a flag needs a current workspace or a workspace_id');
            }
        });
        static::addGlobalScope('kit.workspace', function (Builder $query): void {
            $workspace = Workspaces::current()?->getKey();
            $query->where(fn (Builder $inner) => $inner->whereNull($query->qualifyColumn('workspace_id'))
                ->when($workspace !== null, fn (Builder $mine) => $mine->orWhere($query->qualifyColumn('workspace_id'), $workspace)));
        });
    }
}
