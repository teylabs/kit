<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * One card on the dashboard, registered with `Registry::widget()`. The frame (key, title, component, kind, region, span, state, empty, error, actions) is the
 * same for every widget; a concrete widget adds its own typed props, which its Vue component reads. `component` names that component: the kit's
 * own (`activity`, `you`, `people`) or a file in the host's `resources/js/widgets/` (`queue` renders `widgets/Queue.vue`).
 *
 * A widget that throws, while it is built or while its props are read, becomes an error card: it never fails the dashboard. Its ability is checked
 * before it is built, so a widget never runs for a viewer who may not see it.
 */
#[TypeScript]
abstract class Widget extends ViewModel
{
    // `loadingHeight` is never read as a prop: only a widget that implements DeclaresLoadingHeight is asked for it, by `Widgets`, which validates it.
    protected array $hidden = ['lists', 'visible', 'loadingHeight'];

    public function __construct(protected WidgetContext $context) {}

    abstract public function title(): string;

    abstract public function component(): string;

    final public function key(): string
    {
        return $this->context->key;
    }

    /**
     * What the card is: `task` (work waiting on the viewer), `feed` (what happened; a dashboard has one), `status` (a short read-out, such as you or people)
     * or `setup` (getting the workspace going). Placement follows it by convention (task and feed in main, status and setup in aside, setup last), but
     * `region()` is set on its own and never derived from it. Any other value turns the widget into an error card.
     *
     * @return 'task'|'feed'|'status'|'setup'
     */
    public function kind(): string
    {
        return 'status';
    }

    /**
     * The dashboard column the card stacks in: `main` (the viewer's primary work, such as queues and activity) or `aside` (context, such as you, people and
     * short status). Within a column, cards sit in registry order. Any other value turns the widget into an error card.
     *
     * @return 'main'|'aside'
     */
    public function region(): string
    {
        return 'main';
    }

    /**
     * @deprecated Since the main/aside dashboard, `span()` no longer drives the layout: every card spans its column. Set `region()` instead. Still accepted
     * and sent in the frame so existing widgets compile; it will be removed in a later release.
     */
    public function span(): WidgetSpanData
    {
        return new WidgetSpanData;
    }

    /** Whether the card shows at all for this viewer, after its ability passed: false leaves it out (the setup card once every step is done). */
    public function visible(): bool
    {
        return true;
    }

    /** `ready`, `empty` (show `empty()`) or `error` (show `error()`). */
    public function state(): string
    {
        return 'ready';
    }

    public function empty(): ?WidgetEmptyData
    {
        return null;
    }

    public function error(): ?WidgetErrorData
    {
        return $this->state() === 'error' ? new WidgetErrorData("This couldn't load.", new Action('Retry', $this->context->request->getPathInfo(), 'get', key: $this->key())) : null;
    }

    /** @return list<\Tey\Kit\Features\Core\Data\Action> the card header's actions */
    public function actions(): array
    {
        return [];
    }

    /**
     * Scrolling lists the widget renders with ScrollList: each becomes a deferred page prop under its own key, so a key must not clash with another widget's.
     *
     * @return list<ScrollListViewModel>
     */
    public function lists(): array
    {
        return [];
    }

    /**
     * The orders the card offers in its header's Sort by control, as key => label in menu order (`['touched' => 'Last touched', 'opened' => 'Newest']`).
     * Empty (the default): no control. Keys are lower case (`[a-z0-9_-]`, up to 40). The widget's query reads `chosenSort()`, never the request.
     *
     * @return array<string, string>
     */
    protected function sortOptions(): array
    {
        return [];
    }

    /** The order a viewer gets before they choose one, or after their choice is no longer offered. Null, or a key not in `sortOptions()`: the first declared. */
    protected function defaultSort(): ?string
    {
        return null;
    }

    /**
     * The order to show: the viewer's saved choice for this widget in this workspace when the widget still offers it, else the default (fail closed: a
     * stored or forged key the widget does not declare never reaches its query). Null when the widget offers none.
     */
    protected function chosenSort(): ?string
    {
        $options = $this->declaredSorts();
        if ($options === []) {
            return null;
        }
        $saved = UserState::get($this->context->principal->actorId, self::sortStateKey($this->key()), $this->context->workspace);
        if ($saved !== null && isset($options[$saved])) {
            return $saved;
        }
        $default = $this->defaultSort();

        return $default !== null && isset($options[$default]) ? $default : (string) array_key_first($options);
    }

    /** The header's Sort by control, or null when the widget offers no orders. */
    public function sortBy(): ?WidgetSortData
    {
        $options = $this->declaredSorts();
        if ($options === []) {
            return null;
        }
        $reload = array_values(array_map(fn (ScrollListViewModel $list): string => $list->key(), $this->lists()));

        return new WidgetSortData('Sort by', array_map(fn (int|string $key, string $label): array => ['key' => (string) $key, 'label' => $label], array_keys($options), array_values($options)), (string) $this->chosenSort(),
            WorkspaceUrl::to($this->context->workspace, 'widgets/'.$this->key().'/sort', 'session-ui', $this->context->request), 'put', $reload === [] ? ['widgets'] : $reload);
    }

    /** Whether the widget offers this order (the save endpoint asks before it stores a choice). Not a prop: it takes an argument. */
    public function offersSort(string $key): bool
    {
        return isset($this->declaredSorts()[$key]);
    }

    /** Where the viewer's choice for one widget is kept in `user_state` (with the workspace's id): the key fits the 60-character column whatever its length. */
    public static function sortStateKey(string $widget): string
    {
        return strlen($widget) <= 48 ? 'widget.sort.'.$widget : 'widget.sort.#'.substr(hash('sha256', $widget), 0, 46);
    }

    /** @return array<string, string> the declared orders whose key and label are well formed; any other is dropped */
    private function declaredSorts(): array
    {
        $valid = [];
        foreach ($this->sortOptions() as $key => $label) {
            // PHP stores a numeric-only key (`'30'`) as an int: it is still a declared key, compared and sent as a string.
            $key = (string) $key;
            if (preg_match('/\A[a-z0-9_-]{1,40}\z/', $key) === 1 && is_string($label) && $label !== '') {
                $valid[$key] = $label;
            }
        }

        return $valid;
    }
}
