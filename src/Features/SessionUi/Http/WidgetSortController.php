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
 * person, is a 404. A saved choice names the widget in the session (SORTED), so the dashboard's partial reload that follows rebuilds only that widget and
 * merges its frame into the page's by key: the other cards keep theirs.
 */
#[AuthorizedBy(SaveWidgetSort::class)]
class WidgetSortController
{
    public const SORTED = 'kit.dashboard.sorted';

    public function update(Request $request, int $workspace, string $widget): RedirectResponse
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);
        $result = app(SaveWidgetSort::class)($request, new Principal((int) $user->getKey()), $workspace, $widget, $request->input('sort'));
        abort_if($result === 'unknown', 404);
        if ($result === 'refused') {
            throw ValidationException::withMessages(['sort' => 'Choose one of the orders this card offers.']);
        }

        $request->session()->put(self::SORTED, ['workspace' => $workspace, 'widget' => $widget]);

        return redirect()->to(WorkspaceUrl::to($workspace, '', 'session-ui', $request), 303);
    }
}
