<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\SessionUi\ViewModels\PasswordViewModel;

/** /me/password: change your password. Fortify's own update (`PUT /user/password`) does the work; this is the page. */
class PasswordController
{
    public function show(Request $request): mixed
    {
        abort_unless($request->user('web') !== null, 401);

        return Inertia::render('Workspace/Password/Edit/Page', new PasswordViewModel('Password', '/user/password', $request->session()->get('account_notice')));
    }
}
