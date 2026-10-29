<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Lists\ScrollListViewModel;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/**
 * One card on the dashboard, registered with `Registry::widget()`. The frame (key, title, component, region, span, chrome, state, empty, error, actions) is the
 * same for every widget; a concrete widget adds its own typed props, which its Vue component reads. `component` names that component: the kit's
 * own (`activity`, `you`, `people`) or a file in the host's `resources/js/widgets/` (`queue` renders `widgets/Queue.vue`).
 *
 * A widget that throws, while it is built or while its props are read, becomes an error card: it never fails the dashboard. Its ability is checked
 * before it is built, so a widget never runs for a viewer who may not see it.
 */
#[TypeScript]
abstract class Widget extends ViewModel
{
    protected array $hidden = ['lists'];

    public function __construct(protected WidgetContext $context) {}

    abstract public function title(): string;

    abstract public function component(): string;

    final public function key(): string
    {
        return $this->context->key;
    }

    /** The dashboard region the widget sits in (`Registry::dashboardRegion()`): `main` unless the app declares another. */
    public function region(): string
    {
        return 'main';
    }

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData;
    }

    public function chrome(): WidgetChromeData
    {
        return new WidgetChromeData;
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
}
