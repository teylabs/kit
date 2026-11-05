<?php

namespace Tey\Kit\Features\Finalize\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.finalize')]
final class ConfirmFinalizeStep extends CapabilityTool
{
    public const ABILITY = 'finalize.confirm';

    protected string $name = 'finalize_confirm';

    protected string $description = 'Confirm one step of a finalize checklist, as you, after you have checked what it shows. Pass the revision from finalize_read; a changed checklist is refused.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'flow' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'revision' => $schema->string()->required(), 'step' => $schema->string()->required()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'flow' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'revision' => ['required', 'string', 'max:64'], 'step' => ['required', 'string', 'max:64']];
    }

    protected function notFoundCode(): string
    {
        return 'finalize_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        // Recorded as the mcp channel, so the history shows a finalization made through an assistant.
        return app(FinalizeGate::class)->confirm($principal, $validated['flow'], $validated['subject'], $validated['step'], $validated['revision'], 'mcp')->toArray();
    }
}
