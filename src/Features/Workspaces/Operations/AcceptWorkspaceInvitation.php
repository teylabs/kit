<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Actions\AcceptTenantInvitation;
use Tey\Concierge\Concierge;
use Tey\Concierge\Models\TenantInvitation;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Workspaces\InvitedEmail;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;

class AcceptWorkspaceInvitation
{
    use AsAction;

    public function handle(Principal $principal, #[\SensitiveParameter] string $token): array
    {
        // Initial onboarding has no workspace-bound delegated connection yet.
        if ($principal->connectionId !== null) {
            throw new \RuntimeException('onboarding_protocol_unconfigured');
        }
        $user = Users::model()::query()->findOrFail($principal->actorId);
        if (! app(Concierge::class)->account($user)->isActive()) {
            throw new \RuntimeException('account_inactive');
        }
        // The token came by email to the invited address: an unverified account with that address is verified by using it.
        InvitedEmail::verify($user, ConciergeModels::tenantInvitation()::query()->where('token_hash', TenantInvitation::hashToken($token))->value('email'));
        $result = AcceptTenantInvitation::run($user, $token);

        return ['workspace_id' => $result->workspace->getKey(), 'outcome' => $result->outcome->value];
    }
}
