<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\SettingsSections;
use Tey\Kit\Features\SessionUi\ViewModels\SectionsViewModel;
use Tey\Kit\Features\Workspaces\Workspaces;

/** A workspace's settings sections: the landing page lists the ones the user may open. */
class WorkspaceSectionsController
{
    public function index(Request $request, int $workspace): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $sections = SettingsSections::visible('workspace', Workspaces::required(), new Principal((int) $user->getKey()), $request);

        return SectionsViewModel::page($request, 'Settings', $sections);
    }
}
