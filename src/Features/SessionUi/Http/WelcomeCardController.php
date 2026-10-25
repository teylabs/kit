<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/** A workspace's welcome card on the dashboard. */
class WelcomeCardController
{
    /** Dismiss it for this person (it stays dismissed on every device). */
    public function destroy(Request $request, int $workspace): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        abort_unless(Workspace::query()->memberOf($user)->whereKey($workspace)->exists(), 404);
        UserState::set((int) $user->getKey(), 'welcome.dismissed', '1', $workspace);

        return back();
    }
}
