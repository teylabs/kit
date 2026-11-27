<?php

namespace Tey\Kit\Features\Settings\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings → Setup's test mail: sends one to the viewer through the app's mailer. */
#[AuthorizedBy(SettingsUi::class)]
class SetupTestMailController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function store(Request $request, int $workspace): mixed
    {
        $principal = $this->principal($request);
        $address = OperationForm::run($request, fn () => app(SettingsUi::class)->sendSetupTestMail($principal, $workspace));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['sent_to' => $address]);
        }
        Toast::success('Test mail sent to '.$address.'.');

        return redirect(WorkspaceUrl::to($workspace, 'settings/setup', 'settings'));
    }
}
