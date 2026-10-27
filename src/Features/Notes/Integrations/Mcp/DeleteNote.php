<?php

namespace Tey\Kit\Features\Notes\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;

#[Capability('workspace.notes', destructive: true)]
final class DeleteNote extends CapabilityTool
{
    public const ABILITY = 'notes.write';

    protected string $name = 'notes_delete';

    protected string $description = 'Delete a note you may change, at its expected_version. Asks for confirmation first.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'id' => $schema->integer()->required(), 'expected_version' => $schema->integer()->required(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'id' => ['required', 'integer'], 'expected_version' => ['required', 'integer'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function notFoundCode(): string
    {
        return 'note_not_found';
    }

    protected function summary(array $validated): string
    {
        return 'Delete note '.$validated['id'].' and its attachments for everyone in this workspace.';
    }

    // The gate keeps intent_key and replays a retry itself, so the operation gets a fresh key of its own.
    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(WorkspaceNotes::class)->mutate($principal, 'delete', [...$validated, 'intent_key' => 'mcp:'.Str::uuid()])->toArray();
    }
}
