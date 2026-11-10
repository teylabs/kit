<?php

namespace Tey\Kit\Features\Finalize\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Finalize\Flow;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.finalize', destructive: true)]
final class FinalizeSubject extends CapabilityTool
{
    public const ABILITY = 'finalize.certify';

    protected string $name = 'finalize_certify';

    protected string $description = 'Certify and finalize a subject whose checklist is complete, as you, agreeing to its statement (certified must be true). Pass the revision from finalize_read.';

    public function schema(JsonSchema $schema): array
    {
        $arguments = ['intent_key' => $schema->string()->required(), 'flow' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'revision' => $schema->string()->required(), 'certified' => $schema->boolean()->required(), 'confirm_token' => $schema->string()];
        // Only when a flow declares extras (Flow::extras()): every such flow's fields, named per flow; the gate validates them against the one finalized.
        $flows = array_filter(app(Flows::class)->all(), fn (Flow $flow): bool => $flow->hasExtras());
        if ($flows !== []) {
            $properties = [];
            foreach ($flows as $flow) {
                $properties += $flow->extraSchema($schema);
            }
            $arguments['extra'] = $schema->object($properties)->description('Further fields the flow declares, sent only for that flow: '.implode('; ', array_map(fn (Flow $flow): string => $flow->name.': '.implode(', ', $flow->extraFields()), $flows)).'.');
        }

        return $arguments;
    }

    protected function rules(): array
    {
        $rules = ['intent_key' => ['required', 'string', 'max:120'], 'flow' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'revision' => ['required', 'string', 'max:64'], 'certified' => ['required', 'boolean'], 'confirm_token' => ['sometimes', 'string']];
        // Without a flow that declares extras, `extra` stays an unknown argument, exactly as before.
        if (array_filter(app(Flows::class)->all(), fn (Flow $flow): bool => $flow->hasExtras()) !== []) {
            $rules['extra'] = ['sometimes', 'array'];
        }

        return $rules;
    }

    protected function notFoundCode(): string
    {
        return 'finalize_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        // Recorded as the mcp channel, so the history shows a finalization made through an assistant.
        return app(FinalizeGate::class)->finalize($principal, $validated['flow'], $validated['subject'], $validated['revision'], (bool) $validated['certified'], 'mcp', (array) ($validated['extra'] ?? []))->toArray();
    }
}
