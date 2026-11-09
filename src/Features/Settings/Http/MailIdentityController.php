<?php

namespace Tey\Kit\Features\Settings\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Settings\Data\MailIdentityView;
use Tey\Kit\Features\Settings\ViewModels\EditMailIdentityViewModel;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings → Email sending: the workspace's sender name, from address (with its custom domain's DNS records and status) and reply-to. */
#[AuthorizedBy(SettingsUi::class)]
class MailIdentityController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function show(Request $request, int $workspace): mixed
    {
        $identity = app(SettingsUi::class)->mailIdentity($this->principal($request), $workspace);
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($identity);
        }
        $canWrite = Gate::forUser($request->user())->allows('update', Workspaces::required());
        $url = WorkspaceUrl::to($workspace, 'settings/email', 'settings');

        return Inertia::render('Workspace/Settings/Email/Page', new EditMailIdentityViewModel(
            'Email sending',
            MailIdentityView::from($identity),
            $canWrite ? new Action('Save', $url, 'put') : null,
            $canWrite ? new Action('Check now', $url.'/domain-checks', 'post') : null,
        ));
    }

    public function update(Request $request, int $workspace): mixed
    {
        $principal = $this->principal($request);
        // Only the fields the form sends reach the operation; a stray browser field is dropped, not refused.
        $input = OperationForm::input($request, ['sender_name', 'from_mode', 'domain', 'from_local', 'reply_to_mode', 'reply_to_address', 'expected_version']);
        $identity = OperationForm::run($request, fn () => app(SettingsUi::class)->updateMailIdentity($principal, $workspace, $input));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($identity);
        }
        Toast::success('Email sending saved.')->from('save');

        return redirect(WorkspaceUrl::to($workspace, 'settings/email', 'settings'));
    }
}
