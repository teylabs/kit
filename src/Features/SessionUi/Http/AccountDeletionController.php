<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\DeleteAccount;
use Tey\Kit\Features\SessionUi\ViewModels\DeleteAccountViewModel;

/** /me/delete: delete your account with your current password, refused while you own a workspace. Afterwards the session is signed out and invalidated. */
class AccountDeletionController
{
    public function show(Request $request): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);

        return Inertia::render('Workspace/Account/Delete/Page', new DeleteAccountViewModel('Delete account', DeleteAccount::ownedWorkspaces($user), new Action('Delete account', '/me/delete', 'delete', destructive: true)));
    }

    public function destroy(Request $request): mixed
    {
        $password = (string) $request->validate(['password' => ['required', 'string', 'max:255']])['password'];
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        DeleteAccount::run($user, $password);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['deleted' => true]) : redirect('/');
    }
}
