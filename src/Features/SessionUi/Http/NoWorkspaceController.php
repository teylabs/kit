<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\ViewModels\NoWorkspaceViewModel;
use Tey\Kit\Features\Workspaces\WorkspaceChooser;
use Tey\Kit\Features\Workspaces\Workspaces;

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
        if (app(PageShell::class)->choices($request) !== [] || Workspaces::query()->memberOf($user)->exists()) {
            return redirect('/me/workspaces');
        }
        PageTitle::name('No workspace yet', $request);
        $email = (string) $user->email;

        return Inertia::render('Provision/NoWorkspace/Page', new NoWorkspaceViewModel(
            'No workspace yet',
            $email,
            WorkspaceChooser::invitations($email),
            "You're not in a workspace yet. Ask your admin to invite ".$email.'.',
            WorkspaceChooser::createAction(),
            new Action('Account settings', '/me/profile', 'get'),
        ));
    }
}
