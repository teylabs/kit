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
final class ReadNote extends CapabilityTool
{
    public const ABILITY = 'notes.read';

    protected string $name = 'notes_read';

    protected string $description = 'Read one note by its id, with its current version.';

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1']];
    }

    protected function notFoundCode(): string
    {
        return 'note_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(WorkspaceNotes::class)->read($principal, $validated)->toArray();
    }
}
