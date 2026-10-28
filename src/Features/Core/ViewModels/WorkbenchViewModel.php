<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\WorkbenchInspector;
use Tey\Kit\Features\Core\Data\WorkbenchItem;
use Tey\Kit\Features\Core\Data\WorkbenchLayout;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Core\Workbench\WorkbenchKey;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;

/**
 * A Workbench page's props (the `<Workbench>` component, docs/workbench.md): its `workbenchKey`, the navigator's `items`, the `current` item, the `inspectors` it
 * offers, the actions-bar `actions` (descriptors, so `Toast::success()->from()` finds them), the `reorder` action with its `version` token (null: the navigator does
 * not reorder) and the person's remembered `workbenchLayout`. Decide every action here, never in the page: a viewer who may not reorder gets no `reorder`.
 */
abstract class WorkbenchViewModel extends PageViewModel
{
    /** The kind of workbench (`deck-editor`): the layout is remembered per person and key. */
    abstract public function workbenchKey(): string;

    /** @return list<WorkbenchItem> */
    abstract public function items(): array;

    /** The item selected on arrival, or null for none. */
    public function current(): ?string
    {
        return null;
    }

    /** @return list<WorkbenchInspector> */
    public function inspectors(): array
    {
        return [];
    }

    /** @return list<\Tey\Kit\Features\Core\Data\Action> */
    public function actions(): array
    {
        return [];
    }

    /** Where a new navigator order goes, as `{ order, version }`; null when the viewer may not reorder. */
    public function reorder(): ?Action
    {
        return null;
    }

    /** The version token the reorder action checks (a version column, an updated-at): a stale one is refused with 409 and the order rolls back. */
    public function version(): string|int|null
    {
        return null;
    }

    /** The person's remembered layout and where to save it (not `layout()`, which is the page's shell layout). */
    public function workbenchLayout(): WorkbenchLayout
    {
        $key = WorkbenchKey::assert($this->workbenchKey());
        $user = auth()->user();

        return Users::is($user) ? app(WorkbenchPreferences::class)->layout((int) $user->getKey(), $key) : new WorkbenchLayout(null, null);
    }
}
