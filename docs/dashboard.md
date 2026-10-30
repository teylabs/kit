# Dashboard widgets

The workspace dashboard (`/@{workspace}`, workspace-ui) is a grid of widgets. An app registers widgets with the Registry and never forks the dashboard page. Each widget is a typed view model that the server resolves for the viewer, in order, behind its own ability.

## Registering a widget

```php
use Tey\Kit\Features\SessionUi\Dashboard\Widget;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetEmptyData;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetSpanData;

final class MyQueueWidget extends Widget
{
    public function title(): string
    {
        return 'My queue';
    }

    // Renders resources/js/widgets/Queue.vue.
    public function component(): string
    {
        return 'queue';
    }

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 6, lg: 8);
    }

    public function state(): string
    {
        return $this->rows() === [] ? 'empty' : 'ready';
    }

    public function empty(): ?WidgetEmptyData
    {
        return new WidgetEmptyData('Nothing waiting', 'New requests assigned to you show up here.');
    }

    /** @return list<array{id: int, title: string}> */
    public function rows(): array
    {
        return Request::query()->assignedTo($this->context->principal->actorId)->limit(5)->get(['id', 'title'])->toArray();
    }
}
```

Register it from a service provider's `boot()`, or from the host's composition root, after the kit has filled the Registry:

```php
app(Registry::class)->widget('queue.mine', MyQueueWidget::class, order: 0, ability: 'requests.read');
```

- **`key`** is unique and dotted lower case. The kit's own widgets use `kit.`.
- **`order`** sorts the grid: lower first, then registration order. The kit's widgets sit at -30 (You), -20 (Recent activity) and -10 (People).
- **`ability`** gates the widget. It defaults to `dashboard.read`. The viewer must pass `Access::authorize()` for it: an active member, admitted workspace, the permission, the ability's capability on, and the capability in the plan. A widget the viewer may not see is never built. An ability no feature declares hides the widget (fail closed).

## Without a dashboard

An app that turns the dashboard off (`workspace.dashboard`, in `kit.capabilities` or for one workspace): the workspace root opens its first navigation link. `/@{workspace}`, and `/` in the short form, redirect (302) to the first primary navigation page the viewer may open, in navigation order, or to `/me/workspaces` when there is none. Give your page `order` < -10 to make it first (the dashboard's own link is -10):

```php
$registry->navigation('cases.read', 'Cases', 'cases', order: -20);
```

Only the capability being off redirects. A viewer whose role lacks `dashboard.read`, a non-member and a JSON client are refused as before. `composer test:workspace-root` covers it.

## The frame

Every widget's props start with the same frame, and the class adds its own:

| Prop | Meaning |
|---|---|
| `key` | The registered key. |
| `title` | The card heading. |
| `component` | The Vue body: `activity`, `you` or `people` (the kit's own), or a name that resolves to `resources/js/widgets/{Name}.vue` in the app. |
| `span` | `WidgetSpanData`: columns of 6 at `md` and of 12 at `lg`. Phones are one column. The default is half width (6 and 6). |
| `state` | `ready`, `empty` (the card shows `empty`) or `error` (the card shows `error`, with Retry). |
| `empty` | `WidgetEmptyData`: a title, a description and an optional action. |
| `error` | `WidgetErrorData`: a fixed message and a retry. Never exception text. |
| `actions` | `list<Action>` shown in the card header. |

Props follow the `ViewModel` rules: every public method with no required parameters is a prop, with a declared return type and a docblock shape for arrays.

## Error isolation

A widget that throws, while it is built or while its props are read, becomes an error card (`component: error`, `state: error`) carrying none of its props. The dashboard still answers 200. The failure is logged as `Dashboard widget failed` with the widget key, the exception class and the trace frames (class, function, line) only: never the message, arguments or bindings.

## Lists

A widget that shows a scrolling list returns `ScrollListViewModel`s from `lists()` and names the list's key in a prop. Each list becomes a deferred page prop under its key, and the card renders `<ScrollList :list-key="…">`. A list key that clashes with another widget's list or a dashboard prop turns the widget into an error card. See the kit's `PeopleWidget` and `PeopleList`.

## The kit's widgets

| Key | Widget | Ability | Shows |
|---|---|---|---|
| `kit.you` | `YouWidget` | `dashboard.read` | The viewer's name, email, photo, role here and the workspace, with Edit profile. |
| `kit.activity` | `ActivityWidget` | `members.manage` | The workspace's latest 10 audit events: who, what (the event name) and when. An event's details are never shown. |
| `kit.people` | `PeopleWidget` | `dashboard.read` | The workspace's active members in a ScrollList, with Manage people for someone who may. |

Each is optional. `kit.dashboard.widgets` in `config/kit.php` lists the ones to keep, and it defaults to all three:

```php
'dashboard' => ['widgets' => ['you', 'people']],
```

## Frontend

`pages/Workspace/Dashboard/Page.vue` renders the welcome card, then `Partials/WidgetGrid.vue`, or the empty state when the viewer has no widgets. The grid is one column on phones, 6 at `md` and 12 at `lg`, and each card spans its widget's `span`. `Partials/WidgetCard.vue` is the card shell: the title, the header actions, then the body, the empty state or the error with Retry. An app's widget body is a component in `resources/js/widgets/` that takes a `widget` prop, typed as `WidgetFrame` from `Partials/types.ts` plus its own props.

An app that still registers its own dashboard component (`Registry::dashboardComponent()`) gets `widgets` and any list props too, and can render `<WidgetGrid>` itself.

## MCP

With the mcp feature adopted, `workspace_dashboard` (`Tey\Kit\Dashboard\ReadDashboard`, read only, `#[Capability('workspace.dashboard')]`, ability `dashboard.read`) returns the workspace and the same widget frames the person would see, gated the same way. A list's rows are not included. Session-ui declares the narrow token scope `dashboard.read` ("Read the workspace dashboard"), so a connection can be granted the dashboard alone. Each widget's ability is still checked against the connection, so a connection granted only `dashboard.read` sees only the widgets gated by `dashboard.read`. An acts-as-me connection sees what the person sees.

An app that serves its own dashboard over MCP turns the kit's tool off, so an assistant doesn't see an empty duplicate:

```php
// config/kit.php
'mcp' => ['tools' => ['workspace_dashboard' => false]],
```

A tool that is off is neither listed nor callable. `workspace_search` is turned off the same way.

## Upgrading an app with its own dashboard widgets

Since v0.4.8, `DashboardController` resolves the Registry's widgets itself, renders the page and returns `toResponse()`. An app that overrode the controller to inject its own widget props, or to wrap the response, stops working when it upgrades from v0.4.7 or earlier.

- Register the app's widgets with `Registry::widget()` (above) instead of injecting them. Each gets its own ability, ordering and error card.
- To show only the app's widgets, set `kit.dashboard.widgets` to `[]`, which drops the kit's own three.
- An app that needs a different page altogether can still register its own component with `Registry::dashboardComponent()`. It receives `widgets` and any list props.

## Coverage

`composer test:dashboard` covers registration, ordering, ability and capability gating, error isolation and the log line, the kit's widgets' props, the people list's deferred rows and the read tool's operation. It runs on SQLite memory with synthetic users and Inertia JSON. Not covered: no browser renders the grid or the cards, and the read tool is called directly, not over the MCP transport.
