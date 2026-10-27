<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
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
                $widget = new $entry['widget']($context);
                $frame = $widget->toArray();
                $own = [];
                foreach ($widget->lists() as $list) {
                    $key = $list->key();
                    if (isset($lists[$key]) || isset($own[$key]) || in_array($key, $reserved, true)) {
                        throw new \RuntimeException('dashboard_list_clash');
                    }
                    $own[$key] = $list->items();
                }
                $widgets[] = $frame;
                $lists = [...$lists, ...$own];
            } catch (Throwable $exception) {
                $this->report($entry['key'], $exception);
                $widgets[] = (new FailedWidget($context))->toArray();
            }
        }

        return ['widgets' => $widgets, 'lists' => $lists];
    }

    /** Fail closed: an ability no feature declares, or any refusal, hides the widget. */
    private function visible(Principal $principal, string $ability): bool
    {
        try {
            app(Access::class)->authorize($principal, $ability);

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
