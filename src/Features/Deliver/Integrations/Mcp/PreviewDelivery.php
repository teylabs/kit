<?php

namespace Tey\Kit\Features\Deliver\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.deliver')]
final class PreviewDelivery extends CapabilityTool
{
    public const ABILITY = 'deliver.read';

    protected string $name = 'deliver_preview';

    protected string $description = 'Preview the email exactly as it would be sent with this draft (to and cc from the subject\'s contacts): its subject line, its HTML and whether the document is attached or linked. Nothing is sent or written.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'definition' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'to' => $schema->array()->items($schema->string()), 'cc' => $schema->array()->items($schema->string()), 'bcc' => $schema->array()->items($schema->string()), 'subject_line' => $schema->string(), 'message' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'definition' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'to' => ['sometimes', 'array'], 'to.*' => ['string', 'max:255'], 'cc' => ['sometimes', 'array'], 'cc.*' => ['string', 'max:255'], 'bcc' => ['sometimes', 'array'], 'bcc.*' => ['string', 'max:255'], 'subject_line' => ['sometimes', 'string', 'max:255'], 'message' => ['sometimes', 'string', 'max:10000']];
    }

    protected function notFoundCode(): string
    {
        return 'deliver_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(DeliveryDesk::class)->preview($principal, $validated['definition'], $validated['subject'], array_filter(['to' => $validated['to'] ?? [], 'cc' => $validated['cc'] ?? [], 'bcc' => $validated['bcc'] ?? [], 'subject' => $validated['subject_line'] ?? null, 'message' => $validated['message'] ?? null, 'send_at' => $validated['send_at'] ?? null], fn ($value) => $value !== null))->toArray();
    }
}
