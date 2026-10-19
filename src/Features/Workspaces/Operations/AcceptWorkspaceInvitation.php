<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Tey\Concierge\Actions\AcceptTenantInvitation;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;

class AcceptWorkspaceInvitation
{
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
        $result = AcceptTenantInvitation::run($user, $token);

        return ['workspace_id' => $result->workspace->getKey(), 'outcome' => $result->outcome->value];
    }
}
