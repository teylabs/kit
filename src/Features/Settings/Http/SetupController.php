<?php

namespace Tey\Kit\Features\Settings\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\ViewModels\SetupViewModel;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings → Setup: each registered setup row, its state and its fix. */
#[AuthorizedBy(SettingsUi::class)]
class SetupController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function show(Request $request, int $workspace): mixed
    {
        $principal = $this->principal($request);
        $setup = OperationForm::run($request, fn () => app(SettingsUi::class)->setup($principal, $workspace));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['rows' => array_map(fn ($row) => $row->toArray(), $setup['rows']), 'app_rows' => $setup['app_rows']]);
        }

        return Inertia::render('Workspace/Settings/Setup/Page', new SetupViewModel(
            'Setup',
            $setup['rows'],
            $setup['app_rows'],
            $setup['app_rows'] ? new Action('Send a test mail', WorkspaceUrl::to($workspace, 'settings/setup/test-mail', 'settings'), 'post') : null,
        ));
    }
}
