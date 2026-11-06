<?php

namespace Tey\Kit\Features\Deliver\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.deliver', destructive: true)]
final class ResendDelivery extends CapabilityTool
{
    public const ABILITY = 'deliver.resend';

    protected string $name = 'deliver_resend';

    protected string $description = 'Send an earlier delivery again as a new one, as you, to these recipients; revoke_previous turns the earlier customer link off. Pass the revision from deliver_read.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'definition' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'delivery' => $schema->integer()->required(), 'revision' => $schema->string()->required(), 'to' => $schema->array()->items($schema->string()), 'cc' => $schema->array()->items($schema->string()), 'bcc' => $schema->array()->items($schema->string()), 'subject_line' => $schema->string(), 'message' => $schema->string(), 'revoke_previous' => $schema->boolean(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'definition' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'delivery' => ['required', 'integer'], 'revision' => ['required', 'string', 'max:64'], 'to' => ['sometimes', 'array'], 'to.*' => ['string', 'max:255'], 'cc' => ['sometimes', 'array'], 'cc.*' => ['string', 'max:255'], 'bcc' => ['sometimes', 'array'], 'bcc.*' => ['string', 'max:255'], 'subject_line' => ['sometimes', 'string', 'max:255'], 'message' => ['sometimes', 'string', 'max:10000'], 'revoke_previous' => ['sometimes', 'boolean'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function notFoundCode(): string
    {
        return 'deliver_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(DeliveryDesk::class)->resend($principal, $validated['definition'], $validated['subject'], (int) $validated['delivery'], $validated['revision'], array_filter(['to' => $validated['to'] ?? [], 'cc' => $validated['cc'] ?? [], 'bcc' => $validated['bcc'] ?? [], 'subject' => $validated['subject_line'] ?? null, 'message' => $validated['message'] ?? null, 'send_at' => $validated['send_at'] ?? null], fn ($value) => $value !== null), 'mcp:'.$validated['intent_key'], (bool) ($validated['revoke_previous'] ?? false), 'mcp')->toArray();
    }
}
