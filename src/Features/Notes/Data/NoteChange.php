<?php

namespace Tey\Kit\Features\Notes\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What a write did: the note after it, or `deleted` with no note. A retry with the same intent key returns the same change. */
#[TypeScript]
final class NoteChange extends Data
{
    public function __construct(public int $note_id, public int $workspace_id, public bool $deleted, public ?NoteRecord $note) {}
}
