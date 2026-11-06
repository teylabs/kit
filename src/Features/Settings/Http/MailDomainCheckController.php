<?php

namespace Tey\Kit\Features\Settings\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Toast;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Settings\Adapters\SettingsUi;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Settings → Email sending → Check now: one domain check, which asks the provider to verify the workspace's custom domain. */
#[AuthorizedBy(SettingsUi::class)]
class MailDomainCheckController
{
    private function principal(Request $request): Principal
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return new Principal((int) $user->getKey());
    }

    public function store(Request $request, int $workspace): mixed
    {
        $identity = app(SettingsUi::class)->checkMailDomain($this->principal($request), $workspace);
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($identity);
        }
        $status = $identity['domain_status'];
        Toast::success(match ($status) {
            'verified' => 'Your domain is verified. Mail now goes from it.',
            'failed' => 'The provider could not verify your domain. Check the DNS records.',
            default => 'Still waiting for the DNS records. This can take a while.',
        });

        return redirect(WorkspaceUrl::to($workspace, 'settings/email', 'settings'));
    }
}
