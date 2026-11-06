<?php

namespace Tey\Kit\Features\Deliver\Integrations\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Deliver\Operations\DeliveryDesk;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\CapabilityTool;

#[Capability('workspace.deliver', destructive: true)]
final class RevokeDeliveryLink extends CapabilityTool
{
    public const ABILITY = 'deliver.resend';

    protected string $name = 'deliver_revoke_link';

    protected string $description = 'Turn off a delivery\'s customer link, for everyone it was sent to. Pass the revision from deliver_read.';

    public function schema(JsonSchema $schema): array
    {
        return ['intent_key' => $schema->string()->required(), 'definition' => $schema->string()->required(), 'subject' => $schema->string()->required(), 'delivery' => $schema->integer()->required(), 'revision' => $schema->string()->required(), 'confirm_token' => $schema->string()];
    }

    protected function rules(): array
    {
        return ['intent_key' => ['required', 'string', 'max:120'], 'definition' => ['required', 'string', 'max:64'], 'subject' => ['required', 'string', 'max:191'], 'delivery' => ['required', 'integer'], 'revision' => ['required', 'string', 'max:64'], 'confirm_token' => ['sometimes', 'string']];
    }

    protected function notFoundCode(): string
    {
        return 'deliver_subject_not_found';
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        return app(DeliveryDesk::class)->revoke($principal, $validated['definition'], $validated['subject'], (int) $validated['delivery'], $validated['revision'], 'mcp')->toArray();
    }
}
