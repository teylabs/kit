<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\InspectInvitation;
use Tey\Concierge\Enums\InvitationState;
use Tey\Concierge\Exceptions\InvitationRejected;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\ViewModels\InvitationViewModel;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * A workspace invitation link (`/invitations/{token}`), public: it shows the workspace, who invited you and the role, then what to do next (sign in, create
 * the account the invitation is for, accept), or why the link no longer works (expired, withdrawn, used, not valid). Concierge decides every state and
 * checks acceptance again under its own lock; this only presents them. A token that matches nothing says only that the link is not valid.
 */
class InvitationController
{
    private const MESSAGES = [
        'ready' => 'You have been invited. Accept to join.',
        'sign_in_required' => 'Sign in to the account for this invitation to join.',
        'register_required' => 'Create your account to join.',
        'wrong_account' => 'This invitation was sent to a different email address. Sign out and use the right account.',
        'unverified' => 'Verify your email address before accepting this invitation.',
        'already_member' => 'You are already a member of this workspace.',
        'accepted' => 'This invitation has already been used.',
        'expired' => 'This invitation has expired. Ask for a new one.',
        'revoked' => 'This invitation was withdrawn. Ask for a new one.',
        'invalid' => 'This invitation link is not valid. Check the link, or ask for a new invitation.',
        'suspended' => 'Your membership of this workspace is suspended.',
        'authority_lapsed' => 'The person who invited you can no longer grant this role. Ask for a new invitation.',
        'workspace_unavailable' => 'This workspace is not available right now.',
        'grants_changed' => 'This invitation changed after it was sent. Ask for a new one.',
        'account_inactive' => 'Your account is not active.',
    ];

    public function show(Request $request, string $token): mixed
    {
        $user = $request->user('web');
        $inspection = InspectInvitation::run(Users::is($user) ? $user : null, $token);
        $state = $inspection->state;
        $invitation = $inspection->invitation;
        // A guest who will sign in comes back here after.
        if ($state === InvitationState::SignInRequired) {
            $request->session()->put('url.intended', url('/invitations/'.$token));
        }
        $workspace = $invitation?->workspace;
        $name = $invitation === null ? null : (string) ($workspace?->conciergeName() ?? config('app.name'));
        $role = $invitation === null ? '' : (string) config('concierge.roles.'.($invitation->roleNames()[0] ?? '').'.label', ucfirst($invitation->roleNames()[0] ?? ''));
        $title = match ($state) {
            InvitationState::Ready, InvitationState::SignInRequired, InvitationState::RegisterRequired => 'Join '.$name,
            InvitationState::AlreadyMember => 'You are in '.$name,
            default => 'Invitation',
        };
        PageTitle::name($title, $request);
        $model = new InvitationViewModel(
            $title,
            $state->value,
            self::MESSAGES[$state->value] ?? 'This invitation cannot be used.',
            $invitation === null ? null : ['workspace' => $name, 'inviter' => $invitation->inviter?->name, 'role' => $role, 'email' => (string) $invitation->email],
            Users::is($user) ? (string) $user->email : null,
            url('/invitations/'.$token),
            url('/invitations/'.$token.'/account'),
            '/login',
            $state === InvitationState::AlreadyMember && $workspace !== null ? WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', $request) : null,
        );

        return Inertia::render('Auth/Invitation/Show/Page', $model);
    }

    /** Accept: the signed-in user joins. A rejection is shown as the page for its state, never as a server error. */
    public function store(Request $request, string $token): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        try {
            $acceptance = AcceptInvitation::run($user, $token);
        } catch (InvitationRejected $rejected) {
            return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->json(['error' => $rejected->state->value], 409) : redirect('/invitations/'.$token);
        }
        $workspace = $acceptance->membership?->workspace;
        $to = $workspace === null ? '/me/workspaces' : WorkspaceUrl::to($workspace, 'dashboard', 'session-ui', $request);

        return $request->expectsJson() && ! $request->header('X-Inertia') ? response()->json(['workspace_id' => $workspace?->getKey(), 'outcome' => $acceptance->outcome->value, 'redirect' => $to], 201) : Inertia::location(url($to));
    }
}
