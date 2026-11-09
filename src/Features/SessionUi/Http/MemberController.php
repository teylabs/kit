<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Tey\Kit\Features\Core\Http\OperationForm;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Operations\ManagePeople;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** A member of the workspace: change their role, or remove them (Suspend and Reinstate have their own controllers). Concierge's rules decide (owner, rank, grantable roles). */
#[AuthorizedBy(ManagePeople::class)]
class MemberController
{
    public function update(Request $request, int $workspace, int $membership): mixed
    {
        $role = (string) $request->validate(['role' => ['required', 'string', 'max:64']])['role'];
        $actor = $this->actor($request);
        // A role the actor may not give (or a scoped one) is the role field's error on a page visit, not the 403 page.
        $result = OperationForm::run($request, fn () => ManagePeople::make()->changeRole($actor, $membership, $role), ManagePeople::ROLE_REFUSALS);

        return $request->expectsJson() ? response()->json(['id' => $result->getKey(), 'role' => $role]) : redirect(WorkspaceUrl::to(Workspaces::query()->findOrFail($workspace), 'settings/people', 'session-ui', $request));
    }

    public function destroy(Request $request, int $workspace, int $membership): mixed
    {
        $result = ManagePeople::make()->remove($this->actor($request), $membership);

        return $request->expectsJson() ? response()->json(['id' => $result->getKey(), 'removed' => true]) : redirect(WorkspaceUrl::to($workspace, 'settings/people', 'session-ui'));
    }

    private function actor(Request $request): Model
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return $user;
    }
}
