<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;

/** Skip a welcome step: it counts as handled, comes back on the dashboard's checklist, and the flow moves on. */
class WelcomeSkipController
{
    public function __invoke(Request $request, string $step): mixed
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        Onboarding::context((int) $user->getKey(), ($workspace = $request->session()->get(WelcomeController::SESSION_WORKSPACE)) === null ? null : (int) $workspace);
        Onboarding::find($step) ?? abort(404);
        Onboarding::skip((int) $user->getKey(), $step);

        return redirect('/welcome');
    }
}
