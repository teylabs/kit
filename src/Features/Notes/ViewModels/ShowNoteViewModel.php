<?php

namespace Tey\Kit\Features\Notes\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Notes\Data\NoteRecord;

/** Props for pages/Workspace/Note/Show/Page.vue. */
#[TypeScript]
final class ShowNoteViewModel extends Data
{
    public function __construct(public string $title, public NoteRecord $note, public string $list_url) {}
}
