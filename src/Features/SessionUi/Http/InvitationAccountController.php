<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InspectInvitation;
use Tey\Concierge\Enums\InvitationState;
use Tey\Concierge\Registration\Registration;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PasskeyOffer;
use Tey\Kit\Features\SessionUi\PasswordPolicy;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The account an invitation is for. Registration may be closed for everyone else: a valid invitation is the one way in, and only for the address it was
 * sent to (Concierge's own check, so the token cannot be used for another email). The invited address is verified by the invitation itself.
 */
class InvitationAccountController
{
    public function store(Request $request, string $token): mixed
    {
        abort_if(Auth::guard('web')->check(), 403);
        $inspection = InspectInvitation::run(null, $token);
        $invitation = $inspection->invitation;
        abort_unless($invitation !== null && $inspection->state === InvitationState::RegisterRequired, 422, 'This invitation cannot be used to create an account.');
        $email = (string) $invitation->email;
        abort_if(Registration::invitationRefusal($email, $token) !== null, 422, 'This invitation cannot be used to create an account.');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()]], PasswordPolicy::messages());
        $user = Users::model()::query()->forceCreate(['name' => $data['name'], 'email' => $email, 'password' => Hash::make($data['password']), 'email_verified_at' => now()]);
        InvitationController::rememberTimezone($request, $user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        // They have just chosen this password, so adding a passkey straight away doesn't ask for it again; and they're offered one for next time.
        $request->session()->passwordConfirmed();
        PasskeyOffer::offer($user);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['id' => $user->getKey()], 201);
        }
        // The account was made for this invitation: join straight away and go on to the welcome steps, with no second click.
        $acceptance = AcceptInvitation::run($user, $token);
        $workspace = $acceptance->membership?->workspace;
        $to = InvitationController::afterJoining($request, $user, $workspace === null ? '/me/workspaces' : WorkspaceUrl::to($workspace, '', 'session-ui', $request), $workspace?->getKey());

        return $request->header('X-Inertia') ? Inertia::location(url($to)) : redirect($to);
    }
}
