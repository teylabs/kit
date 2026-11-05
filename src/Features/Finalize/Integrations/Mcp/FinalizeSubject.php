<?php

namespace Tey\Kit\Features\Finalize\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
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
        return ['intent_key' => $schema->string()->required(), 'flow' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'revision' => $schema->string()->required(), 'certified' => $schema->boolean()->required(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'flow' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'revision' => ['required', 'string', 'max:64'], 'certified' => ['required', 'boolean'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function notFoundCode(): string
    {
        return 'finalize_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        // Recorded as the mcp channel, so the history shows a finalization made through an assistant.
        return app(FinalizeGate::class)->finalize($principal, $validated['flow'], $validated['subject'], $validated['revision'], (bool) $validated['certified'], 'mcp')->toArray();
    }
}
