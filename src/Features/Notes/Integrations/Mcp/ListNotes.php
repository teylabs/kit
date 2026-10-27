<?php

namespace Tey\Kit\Features\Notes\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;

#[Capability('workspace.notes')]
#[IsReadOnly]
final class ListNotes extends CapabilityTool
{
    public const ABILITY = 'notes.read';

    protected string $name = 'notes_list';

    protected string $description = 'List the workspace\'s newest notes (up to 50), pinned first; optionally one colour, or the notes about one record.';

    public function schema(JsonSchema $schema): array
    {
        return ['colour' => $schema->string(), 'subject_type' => $schema->string(), 'subject_id' => $schema->integer()];
    }

    protected function rules(): array
    {
        return ['colour' => ['sometimes', 'string'], 'subject_type' => ['sometimes', 'string'], 'subject_id' => ['sometimes', 'integer']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return ['notes' => array_map(fn ($note) => $note->toArray(), app(WorkspaceNotes::class)->listing($principal, $validated))];
    }
}
