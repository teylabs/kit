<?php

namespace Tey\Kit\Features\Deliver\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.deliver', destructive: true)]
final class SendDelivery extends CapabilityTool
{
    public const ABILITY = 'deliver.send';

    protected string $name = 'deliver_send';

    protected string $description = 'Send a subject to its customer as you, now or at send_at (ISO 8601, in the future). To and cc must be the subject\'s contacts (see deliver_read); bcc is for internal copies. Show the person deliver_preview first. Pass the revision from deliver_read; the intent_key makes a retry send at most once.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'definition' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'revision' => $schema->string()->required(), 'to' => $schema->array()->items($schema->string()), 'cc' => $schema->array()->items($schema->string()), 'to_other' => $schema->array()->items($schema->string()), 'cc_other' => $schema->array()->items($schema->string()), 'bcc' => $schema->array()->items($schema->string()), 'subject_line' => $schema->string(), 'message' => $schema->string(), 'send_at' => $schema->string(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'definition' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'revision' => ['required', 'string', 'max:64'], 'to' => ['sometimes', 'array'], 'to.*' => ['string', 'max:255'], 'cc' => ['sometimes', 'array'], 'cc.*' => ['string', 'max:255'], 'to_other' => ['sometimes', 'array'], 'to_other.*' => ['string', 'max:255'], 'cc_other' => ['sometimes', 'array'], 'cc_other.*' => ['string', 'max:255'], 'bcc' => ['sometimes', 'array'], 'bcc.*' => ['string', 'max:255'], 'subject_line' => ['sometimes', 'string', 'max:255'], 'message' => ['sometimes', 'string', 'max:10000'], 'send_at' => ['sometimes', 'string', 'max:40'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function notFoundCode(): string
    {
        return 'deliver_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(DeliveryDesk::class)->send($principal, $validated['definition'], $validated['subject'], $validated['revision'], array_filter(['to' => $validated['to'] ?? [], 'cc' => $validated['cc'] ?? [], 'to_other' => $validated['to_other'] ?? [], 'cc_other' => $validated['cc_other'] ?? [], 'bcc' => $validated['bcc'] ?? [], 'subject' => $validated['subject_line'] ?? null, 'message' => $validated['message'] ?? null, 'send_at' => $validated['send_at'] ?? null], fn ($value) => $value !== null), 'mcp:'.($this->intentKey() ?? throw new \RuntimeException('intent_required')), 'mcp')->toArray();
    }
}
