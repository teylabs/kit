<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Tey\Concierge\Actions\AcceptTenantInvitation;
use Tey\Concierge\Enums\InvitationState;
use Tey\Concierge\Exceptions\InvitationRejected;
use Tey\Concierge\Support\TenantInvitationInspection;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\TenantInvites;
use Tey\Kit\Features\SessionUi\ViewModels\InvitationViewModel;
use Tey\Kit\Features\Workspaces\Workspaces;

/** The tenant invitation link's pages and acceptance, shared by its two controllers and the throttle answer (ProtectInvitationResponse). */
final class TenantInvitationResponses
{
    public const THROTTLED = 'Too many attempts with this invitation. Wait a minute, then try again.';

    private const MESSAGES = [
        'ready' => 'Accept to create your workspace.',
        'sign_in_required' => 'Sign in to the account for this invitation to create your workspace.',
        'register_required' => 'Create your account to create your workspace.',
        'wrong_account' => 'This invitation was sent to a different email address. Sign out and use the right account.',
        'unverified' => 'Verify your email address to create your workspace.',
        'already_member' => 'You already own this workspace.',
        'accepted' => 'This invitation has already been used.',
        'expired' => 'This invitation has expired. Ask for a new one.',
        'revoked' => 'This invitation was withdrawn. Ask for a new one.',
        'invalid' => 'This invitation link is not valid. It may have been replaced by a newer link: check the link, or ask for a new invitation.',
    ];

    /** The page for a request a throttle refused (ProtectInvitationResponse), 429. */
    public static function throttled(Request $request): Response
    {
        PageTitle::name('Invitation', $request);

        return Inertia::render('Auth/Invitation/Show/Page', new InvitationViewModel('Invitation', 'throttled', self::THROTTLED, null, null, '', '', '/login', null))
            ->toResponse($request)->setStatusCode(429);
    }

    public static function accepting(Request $request, mixed $user, string $token): mixed
    {
        $json = $request->expectsJson() && ! $request->header('X-Inertia');
        try {
            $acceptance = AcceptTenantInvitation::run($user, $token);
        } catch (InvitationRejected $rejected) {
            return $json ? response()->json(['error' => $rejected->state->value], 409) : redirect(TenantInvites::url($token));
        }
        $to = InvitationController::afterJoining($request, $user, TenantInvites::landing($acceptance->workspace, $user, $request), $acceptance->workspace->getKey());

        return $json ? response()->json(['workspace_id' => $acceptance->workspace->getKey(), 'outcome' => $acceptance->outcome->value, 'redirect' => $to], 201)
            : ($request->header('X-Inertia') ? Inertia::location(url($to)) : redirect($to));
    }

    public static function page(Request $request, string $token, TenantInvitationInspection $inspection): mixed
    {
        $user = $request->user('web');
        $state = $inspection->state;
        $invitation = $inspection->invitation;
        $name = $invitation === null ? null : TenantInvites::name($invitation);
        $workspace = $state === InvitationState::AlreadyMember && $invitation?->workspace_id !== null ? Workspaces::model()::query()->find($invitation->workspace_id) : null;
        $title = match ($state) {
            InvitationState::Ready, InvitationState::SignInRequired, InvitationState::RegisterRequired => 'Create '.$name.' on '.config('app.name'),
            InvitationState::AlreadyMember => 'You already own '.$name,
            InvitationState::WrongAccount => 'This invitation is for someone else',
            InvitationState::Expired => 'This invitation has expired',
            InvitationState::Revoked => 'This invitation was withdrawn',
            InvitationState::Invalid => 'This link is not valid',
            InvitationState::Accepted => 'This invitation was already used',
            default => 'Invitation',
        };
        PageTitle::name($title, $request);

        return Inertia::render('Auth/Invitation/Show/Page', new InvitationViewModel(
            $title,
            $state->value,
            self::MESSAGES[$state->value] ?? 'This invitation cannot be used.',
            $invitation === null ? null : ['workspace' => $name, 'inviter' => $invitation->inviter?->name, 'role' => 'Owner', 'email' => (string) $invitation->email, 'message' => null],
            Users::is($user) ? (string) $user->email : null,
            TenantInvites::url($token),
            TenantInvites::url($token, '/register'),
            '/login',
            $workspace === null ? null : TenantInvites::landing($workspace, $user, $request),
            false,
            Users::is($user) && ! $user->conciergeHasVerifiedEmail() ? '/email/verify' : null,
        ));
    }
}
