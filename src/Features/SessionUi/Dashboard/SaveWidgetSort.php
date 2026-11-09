<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Http\Request;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Savepoint;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * Saves one person's Sort by choice for one dashboard widget in one workspace (`kit_user_state`, keyed by person, workspace and widget). Authorises as the
 * card is shown: the dashboard's ability, then the widget's own (a refusal throws, as `Access` does). The widget is built for the person and must still be
 * visible and declare the order. Call it inside the workspace's tenant context.
 */
final class SaveWidgetSort
{
    /** `saved`, `unknown` (no such widget, or hidden from the person) or `refused` (an order the widget does not declare); only `saved` stores anything. */
    public function __invoke(Request $request, Principal $principal, int $workspace, string $widget, mixed $sort): string
    {
        $access = app(Access::class);
        $access->authorize($principal, 'dashboard.read');
        $entry = collect(app(Registry::class)->widgets())->firstWhere('key', $widget);
        if ($entry === null) {
            return 'unknown';
        }
        $access->authorize($principal, $entry['ability']);
        // Built as the dashboard builds it, in a savepoint: a widget's failed query must not abort the request's transaction.
        $offered = Savepoint::run(function () use ($entry, $request, $principal, $workspace, $widget, $sort): ?bool {
            /** @var Widget $built */
            $built = new $entry['widget'](new WidgetContext($widget, $request, $principal, $workspace));

            return $built->visible() ? (is_string($sort) && $built->offersSort($sort)) : null;
        });
        if ($offered === null) {
            return 'unknown';
        }
        if (! $offered) {
            return 'refused';
        }
        UserState::set($principal->actorId, Widget::sortStateKey($widget), $sort, $workspace);

        return 'saved';
    }
}
