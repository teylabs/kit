<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\SettingsSections;
use Tey\Kit\Features\SessionUi\ViewModels\SectionsViewModel;

/** The user's own sections (`/me`): the landing page lists the personal ones. */
class PersonalSectionsController
{
    public function index(Request $request): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return SectionsViewModel::page($request, 'Account', SettingsSections::visible('personal', null, new Principal((int) $user->getKey()), $request));
    }
}
