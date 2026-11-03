<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** Suspends a member of the workspace: their access ends and their roles are kept, until reinstated. Concierge's rules decide who may. */
class SuspendMemberController
{
    public function __invoke(Request $request, int $workspace, int $membership): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $result = ManagePeople::make()->suspend($user, $membership, is_string($reason) ? $reason : null);

        return $request->expectsJson() ? response()->json(['id' => $result->getKey(), 'status' => 'suspended']) : redirect(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
    }
}
