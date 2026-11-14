<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Routing\AuthorizedBy;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Dashboard\SaveWidgetSort;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * Saves the signed-in person's Sort by choice for one dashboard widget in this workspace (SaveWidgetSort, which authorises), then sends them back to the
 * dashboard. An order the widget does not declare is a validation error on `sort` and nothing is stored; a widget no one registered, or one hidden from the
 * person, is a 404.
 */
#[AuthorizedBy(SaveWidgetSort::class)]
class WidgetSortController
{
    public function update(Request $request, int $workspace, string $widget): RedirectResponse
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $result = app(SaveWidgetSort::class)($request, new Principal((int) $user->getKey()), $workspace, $widget, $request->input('sort'));
        abort_if($result === 'unknown', 404);
        if ($result === 'refused') {
            throw ValidationException::withMessages(['sort' => 'Choose one of the orders this card offers.']);
        }

        return redirect()->to(WorkspaceUrl::to($workspace, '', 'session-ui', $request), 303);
    }
}
