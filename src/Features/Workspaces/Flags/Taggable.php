<?php

namespace Tey\Kit\Features\Workspaces\Flags;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * A tag on a record, on `kit_taggables`. It carries the record's workspace (so the workspace scope and RLS cover it) and, for a flag, its `meta`: the
 * reason and link the flag was raised with.
 */
class Taggable extends MorphPivot
{
    protected $table = 'kit_taggables';

    protected $casts = ['meta' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $pivot): void {
            if ($pivot->getAttribute('workspace_id') === null) {
                $pivot->setAttribute('workspace_id', Workspaces::current()?->getKey());
            }
        });
    }
}
