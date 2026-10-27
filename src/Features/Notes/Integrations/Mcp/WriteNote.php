<?php

namespace Tey\Kit\Features\Notes\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;

/** Creates a note (no `id`), or changes one (`id` and `expected_version`): its body, colour or pin. Attachments are added on the page only. */
#[Capability('workspace.notes')]
final class WriteNote extends CapabilityTool
{
    public const ABILITY = 'notes.write';

    protected string $name = 'notes_write';

    protected string $description = 'Create a note, or change one you may change (pass id and expected_version): body, colour, pinned. Retry with the same intent_key.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'id' => $schema->integer(), 'expected_version' => $schema->integer(), 'body' => $schema->string(), 'colour' => $schema->string(), 'pinned' => $schema->boolean(), 'subject_type' => $schema->string(), 'subject_id' => $schema->integer()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'id' => ['sometimes', 'integer'], 'expected_version' => ['sometimes', 'integer'], 'body' => ['sometimes', 'string'], 'colour' => ['sometimes', 'string'], 'pinned' => ['sometimes', 'boolean'], 'subject_type' => ['sometimes', 'string'], 'subject_id' => ['sometimes', 'integer']];
    }

    protected function notFoundCode(): string
    {
        return 'note_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        // The gate keeps intent_key and replays a retry itself (ConsentGate::recall), so the operation runs once per intent and gets a fresh key of its own.
        $input = [...$validated, 'intent_key' => 'mcp:'.Str::uuid()];

        return app(WorkspaceNotes::class)->mutate($principal, isset($validated['id']) ? 'update' : 'create', $input)->toArray();
    }
}
