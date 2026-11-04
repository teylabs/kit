<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Concierge\Actions\InspectTenantInvitation;
use Tey\Concierge\Enums\InvitationState;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\TenantInvites;

/**
 * A tenant invitation link (`/invite/{token}`): an invitation to own a new workspace. Concierge decides every state and accepts under its own lock, creating
 * the workspace through the host's provisionWorkspaceUsing(); this only presents the states, creates the invited account (verified: opening the emailed link
 * proves the address) and signs it in. The page is kit's invitation page, so a tenant invitation reads like a workspace one.
 */
class TenantInvitationController
{
    public function show(Request $request, string $token): mixed
    {
        $user = $request->user('web');
        $inspection = InspectTenantInvitation::run(Users::is($user) ? $user : null, $token);
        // Signing in or verifying comes back here.
        if (in_array($inspection->state, [InvitationState::SignInRequired, InvitationState::RegisterRequired, InvitationState::Unverified], true)) {
            $request->session()->put('url.intended', TenantInvites::url($token));
        }

        return TenantInvitationResponses::page($request, $token, $inspection);
    }

    public function store(Request $request, string $token): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        InvitationController::rememberTimezone($request, $user);

        return TenantInvitationResponses::accepting($request, $user, $token);
    }
}
