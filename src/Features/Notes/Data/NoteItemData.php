<?php

namespace Tey\Kit\Features\Notes\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Lists\ListItemData;

/** A note as a ScrollList row: the list's own fields plus the note, and the edit (`update`) and delete actions the viewer may take (null when not). */
#[TypeScript]
final class NoteItemData extends ListItemData
{
    public function __construct(string $id, string $title, public NoteRecord $note, public ?Action $update = null, public ?Action $delete = null)
    {
        parent::__construct($id, $title);
    }
}
