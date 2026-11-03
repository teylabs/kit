<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The workspace's ownership: pass it to another member (the owner only, with the password confirmed again and the workspace's name typed to confirm). */
class OwnershipController
{
    public function update(Request $request, int $workspace): mixed
    {
        $data = $request->validate(['membership' => ['required', 'integer'], 'password' => ['required', 'string', 'max:255'], 'confirmation' => ['required', 'string', 'max:255']]);
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $recipient = ManagePeople::make()->transferOwnership($user, (int) $data['membership'], $data['password'], $data['confirmation']);

        return $request->expectsJson() ? response()->json(['owner_membership_id' => $recipient->getKey()]) : redirect(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
    }
}
