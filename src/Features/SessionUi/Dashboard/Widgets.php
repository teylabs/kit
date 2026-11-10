<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\Savepoint;
use Throwable;

/**
 * Resolves the registered widgets for one viewer in the current workspace: in registry order, only those whose ability the viewer holds (checked by
 * `Access`, so the ability's capability, entitlement and the viewer's permission all apply), each built and read on its own. A refusal hides the widget;
 * any other failure becomes its error card. Call it inside the workspace's tenant context.
 */
final class Widgets
{
    /** @return array{widgets: list<array<string, mixed>>, lists: array<string, mixed>} the frames, and each list's deferred prop by key */
    public function resolve(Request $request, Principal $principal, int $workspace, array $reserved = []): array
    {
        $widgets = [];
        $lists = [];
        foreach (app(Registry::class)->widgets() as $entry) {
            if (! $this->visible($principal, $entry['ability'])) {
                continue;
            }
            $context = new WidgetContext($entry['key'], $request, $principal, $workspace);
            try {
                // In a savepoint: a widget's failed query must not abort the request's transaction (RLS) for the widgets and the page after it.
                $built = Savepoint::run(function () use ($entry, $context, $lists, $reserved): ?array {
                    $widget = new $entry['widget']($context);
                    if (! $widget->visible()) {
                        return null;
                    }
                    $frame = $widget->toArray();
                    // Fail closed: a widget names one of the two columns, or it is an error card.
                    if (! in_array($frame['region'] ?? null, ['main', 'aside'], true)) {
                        throw new \RuntimeException('dashboard_widget_region');
                    }
                    if (! in_array($frame['kind'] ?? null, ['task', 'feed', 'status', 'setup'], true)) {
                        throw new \RuntimeException('dashboard_widget_kind');
                    }
                    if (($frame['loadingHeight'] ?? null) !== null && (! is_string($frame['loadingHeight']) || preg_match('/\A(?:\d{1,4}(?:\.\d{1,3})?)(?:px|rem|em)\z/', $frame['loadingHeight']) !== 1)) {
                        throw new \RuntimeException('dashboard_widget_loading_height');
                    }
                    $own = [];
                    foreach ($widget->lists() as $list) {
                        $key = $list->key();
                        if (isset($lists[$key]) || isset($own[$key]) || in_array($key, $reserved, true)) {
                            throw new \RuntimeException('dashboard_list_clash');
                        }
                        $own[$key] = $list->items();
                    }

                    return [$frame, $own];
                });
                if ($built === null) {
                    continue;
                }
                [$frame, $own] = $built;
                $widgets[] = $frame;
                $lists = [...$lists, ...$own];
            } catch (Throwable $exception) {
                $this->report($entry['key'], $exception);
                $widgets[] = (new FailedWidget($context))->toArray();
            }
        }

        // The kit's setup card is the aside's last, whatever order an app gives its own widgets.
        usort($widgets, fn (array $a, array $b): int => ($a['key'] === 'kit.setup') <=> ($b['key'] === 'kit.setup'));

        return ['widgets' => $widgets, 'lists' => $lists];
    }

    /** Fail closed: an ability no feature declares, or any refusal, hides the widget. */
    private function visible(Principal $principal, string $ability): bool
    {
        try {
            Savepoint::run(fn () => app(Access::class)->authorize($principal, $ability));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** A failure's class and its trace frames only: never the message, arguments, bindings or paths. */
    private function report(string $key, Throwable $exception): void
    {
        $trace = array_map(fn (array $frame): array => array_intersect_key($frame, array_flip(['class', 'function', 'type', 'line'])), $exception->getTrace());
        Log::warning('Dashboard widget failed', ['widget' => $key, 'exception_class' => $exception::class, 'trace' => $trace]);
    }
}
