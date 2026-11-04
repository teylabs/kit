<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tey\Concierge\Actions\InspectTenantInvitation;
use Tey\Concierge\Enums\InvitationState;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PasswordPolicy;
use Tey\Kit\Features\SessionUi\TenantInvites;

/**
 * The account a tenant invitation is for (`/invite/{token}/register`, guests only): the invitation page's account form, served only while Concierge says
 * the invited address has no account, then the account itself, created verified (opening the emailed link proves the address), signed in and accepted.
 */
class TenantInvitationAccountController
{
    /** The account form is the invitation page's own, for the one state it serves; any other state is the page for that state. */
    public function create(Request $request, string $token): mixed
    {
        $inspection = InspectTenantInvitation::run(null, $token);
        if ($inspection->state !== InvitationState::RegisterRequired) {
            return redirect(TenantInvites::url($token));
        }
        $request->session()->put('url.intended', TenantInvites::url($token));

        return TenantInvitationResponses::page($request, $token, $inspection);
    }

    /** Creates the invited account, verified, signs it in and accepts: the new owner lands in their workspace with no second click. */
    public function store(Request $request, string $token): mixed
    {
        $inspection = InspectTenantInvitation::run(null, $token);
        $invitation = $inspection->invitation;
        if ($invitation === null || $inspection->state !== InvitationState::RegisterRequired) {
            return redirect(TenantInvites::url($token));
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()]], PasswordPolicy::messages());
        $user = Users::model()::query()->forceCreate(['name' => $data['name'], 'email' => (string) $invitation->email, 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
        InvitationController::rememberTimezone($request, $user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->passwordConfirmed();

        return TenantInvitationResponses::accepting($request, $user, $token);
    }
}
