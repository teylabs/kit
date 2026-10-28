<?php

namespace Tey\Kit\Features\Workspaces\Operations;

use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Models\TenantInvitation;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Workspaces\Workspaces;

class ProvisionWorkspace
{
    use AsAction;

    // Host callback inside Concierge's token-validated acceptance transaction.
    public function handle(Member $owner, TenantInvitation $invitation): Workspace
    {
        $workspace = Workspaces::model()::create(['name' => $owner->conciergeDisplayName(), 'admitted' => true, 'provisioning_invitation_id' => $invitation->id]);
        $workspace->execute(function () use ($workspace): void {
            foreach (app(Registry::class)->provisioners() as $provision) {
                $provision($workspace);
            }
        });

        return $workspace;
    }
}
