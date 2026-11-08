<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

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
    protected array $hidden = ['lists', 'visible'];

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

    /** @return list<Action> the card header's actions */
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
}
