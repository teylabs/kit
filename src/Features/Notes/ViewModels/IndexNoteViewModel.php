<?php

namespace Tey\Kit\Features\Notes\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Notes\Data\NoteRecord;

/** Props for pages/Workspace/Note/Index/Page.vue. */
#[TypeScript]
final class IndexNoteViewModel extends Data
{
    /**
     * @param  list<NoteRecord>  $notes
     * @param  array<int, array{url: string, update_intent: string, delete_intent: string}>  $actions
     */
    public function __construct(public string $title, public array $notes, public bool $can_write, public string $create_url, public string $create_intent, public array $actions) {}
}
