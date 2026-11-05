<?php

namespace Tey\Kit\Features\Finalize\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.finalize')]
#[IsReadOnly]
final class ReadFinalizeGate extends CapabilityTool
{
    public const ABILITY = 'finalize.read';

    protected string $name = 'finalize_read';

    protected string $description = 'Read a finalize checklist for one subject: each step with its state and errors, the progress (with the revision to send back), the certify statement and the current finalization.';

    public function schema(JsonSchema $schema): array
    {
        return ['flow' => $schema->string()->required(), 'subject' => $schema->string()->required()];
    }

    protected function rules(): array
    {
        return ['flow' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191']];
    }

    protected function notFoundCode(): string
    {
        return 'finalize_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(FinalizeGate::class)->state($principal, $validated['flow'], $validated['subject'])->toArray();
    }
}
