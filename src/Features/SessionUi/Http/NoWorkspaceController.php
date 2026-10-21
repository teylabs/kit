<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Concierge\Enums\InvitationStatus;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\ViewModels\NoWorkspaceViewModel;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * Where a signed-in person with no workspace lands: a new account, or someone who left or was removed. The invitations sent to their email come first, then what to do
 * (with `invite_only`, ask an admin to invite them: there is no create action). Someone who does have a workspace is sent to their list instead. It never answers 403.
 */
class NoWorkspaceController
{
    public function __invoke(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        if (app(PageShell::class)->choices($request) !== [] || Workspace::query()->memberOf($user)->exists()) {
            return redirect('/me/workspaces');
        }
        PageTitle::name('No workspace yet', $request);
        $email = (string) $user->email;

        return Inertia::render('Provision/NoWorkspace/Page', new NoWorkspaceViewModel(
            'No workspace yet',
            $email,
            $this->invitations($email),
            "You're not in a workspace yet. Ask your admin to invite ".$email.'.',
            config('kit.workspaces.provisioning') === 'self_serve' ? new Action('Create a workspace', '/me/workspaces/create', 'get') : null,
            new Action('Account settings', '/me/profile', 'get'),
        ));
    }

    /** @return list<array{workspace: string, inviter: ?string, message: ?string, expires: ?string}> */
    private function invitations(string $email): array
    {
        return ConciergeModels::invitation()::query()->with(['workspace', 'inviter'])
            ->where('email', mb_strtolower(trim($email)))->where('status', InvitationStatus::Pending)->where('expires_at', '>', now())
            ->orderByDesc('id')->limit(10)->get()
            ->map(fn ($invitation): array => ['workspace' => (string) ($invitation->workspace?->conciergeName() ?? config('app.name')), 'inviter' => $invitation->inviter?->name, 'message' => $invitation->message, 'expires' => $invitation->expires_at?->toIso8601String()])->all();
    }
}
