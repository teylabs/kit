# Dashboard widgets

The workspace dashboard (`/@{workspace}`, workspace-ui) is two columns of widgets, main and aside. An app registers widgets with the Registry and never forks the dashboard page. Each widget is a typed view model that the server resolves for the viewer, in order, behind its own ability.

Start from who opens the dashboard and their first job, as every page does: the dashboard's `@purpose` reads "Members: see what needs them today and take the next step". Each widget earns its place against that job.

## Registering a widget

```php
use Tey\Kit\Features\SessionUi\Dashboard\Widget;
use Tey\Kit\Features\SessionUi\Dashboard\WidgetEmptyData;

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

    public function kind(): string
    {
        return 'task';
    }

    public function region(): string
    {
        return 'main';
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
- **`order`** sorts the widgets within their column: lower first, then registration order. The kit's widgets sit at -30 (You), -20 (Recent activity) and -10 (People).
- **`ability`** gates the widget. It defaults to `dashboard.read`. The viewer must pass `Access::authorize()` for it: an active member, admitted workspace, the permission, the ability's capability on, and the capability in the plan. A widget the viewer may not see is never built. An ability no feature declares hides the widget (fail closed).

## Layout: main and aside

The dashboard is two columns. Each widget names its column with `region()`:

- **`main`** (the default), the wide column: the viewer's primary work, such as queues and activity.
- **`aside`**, the narrow column: context, such as you, people and short status.

From `md` up the columns sit side by side, main 8 and aside 4 of 12 (PageGrid's `main-aside` preset), or main 7 and aside 5 (`main-aside-wide`, about 58/42) with `kit.dashboard.aside` set to `wide`:

```php
'dashboard' => ['widgets' => ['you', 'activity', 'people', 'setup'], 'aside' => 'wide'], // narrow (the default) or wide
```

It reaches the page as the `aside` prop; any other value is `narrow`. Below `md` they are one column: every main widget first, then every aside widget. Within a column, widgets stack in `order` and each card spans the column's full width. When only one column has widgets, it takes the full width. A region other than `main` or `aside` makes the widget an error card.

### Kinds

Each widget also declares its `kind()`: `task` (work waiting on the viewer), `feed` (what happened), `status` (a short read-out; the default) or `setup` (getting the workspace going). Kind does not place the widget: `region()` does, and the two are set independently. Place them by kind:

| Kind | Region |
|---|---|
| `task` | main |
| `feed` | main |
| `status` | aside |
| `setup` | aside, last (the highest `order` there) |

Have one feed. If an app has two activity sources, merge them into that one feed, for example by replacing `kit.activity` (below) with a widget that reads both. A kind other than these four makes the widget an error card.

```php
final class SetupWidget extends Widget
{
    public function kind(): string { return 'setup'; }    // getting started
    public function region(): string { return 'aside'; } // context, not work
    // …
}

app(Registry::class)->widget('app.setup', SetupWidget::class, order: 100); // last in the aside
```

### `span()` is deprecated

`span()` and `WidgetSpanData` no longer drive the layout. Widgets that still override `span()` keep compiling and it is still sent in the frame, but it is ignored. Migrate by dropping `span()` and setting `region()`: a wide widget (`lg: 8` or more) usually belongs in `main`, a narrow one (`lg: 4`) in `aside`.

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
| `kind` | `task`, `feed`, `status` (the default) or `setup`. See Kinds. |
| `region` | `main` (the default) or `aside`: the column the card stacks in. |
| `span` | Deprecated: `WidgetSpanData`, no longer used for layout. |
| `state` | `ready`, `empty` (the card shows `empty`) or `error` (the card shows `error`, with Retry). |
| `empty` | `WidgetEmptyData`: a title, a description and an optional action. |
| `error` | `WidgetErrorData`: a fixed message and a retry. Never exception text. |
| `actions` | `list<Action>` shown in the card header. |
| `sortBy` | `WidgetSortData` for the header's Sort by control, or null when the widget declares no orders. See Sort by. |

Props follow the `ViewModel` rules: every public method with no required parameters is a prop, with a declared return type and a docblock shape for arrays.

## Error isolation

A widget that throws, while it is built or while its props are read, becomes an error card (`component: error`, `state: error`) carrying none of its props. The dashboard still answers 200. The failure is logged as `Dashboard widget failed` with the widget key, the exception class and the trace frames (class, function, line) only: never the message, arguments or bindings.

## Lists

A widget that shows a scrolling list returns `ScrollListViewModel`s from `lists()` and names the list's key in a prop. Each list becomes a deferred page prop under its key, and the card renders `<ScrollList :list-key="…">`. A list key that clashes with another widget's list or a dashboard prop turns the widget into an error card. See the kit's `PeopleWidget` and `PeopleList`.

## Sort by

A widget can offer a choice of order. Its card then shows a compact **Sort by** select at the right of its header, before its actions. The server decides everything: the widget declares its orders (key and label) and a default, and its query reads `chosenSort()`, which is always one of the declared keys.

```php
use Tey\Kit\Features\SessionUi\Dashboard\Widget;

final class OngoingWorkWidget extends Widget
{
    public function title(): string
    {
        return 'Ongoing work';
    }

    public function component(): string
    {
        return 'ongoing';
    }

    public function kind(): string
    {
        return 'task';
    }

    protected function sortOptions(): array
    {
        return [
            'touched' => 'Last touched',
            'opened' => 'Newest',
            'due' => 'Due soonest',
        ];
    }

    protected function defaultSort(): ?string
    {
        return 'touched';
    }

    /** @return list<array{id: int, title: string}> */
    public function rows(): array
    {
        $query = Item::query()->where('status', 'open');
        match ($this->chosenSort()) {
            'opened' => $query->latest('created_at'),
            'due' => $query->orderBy('due_on'),
            default => $query->latest('updated_at'),
        };

        return $query->orderBy('id')->limit(10)->get(['id', 'title'])->toArray();
    }
}
```

- **`sortOptions()`** returns `key => label` in menu order. Keys are lower case (`[a-z0-9_-]`, up to 40 characters; a numeric-only key such as `'30'` works and is sent as a string); a malformed key or an empty label is dropped. Empty (the default) means no control.
- **`defaultSort()`** is the order before the viewer chooses one. Null, or a key the widget doesn't declare, means the first declared order.
- **`chosenSort()`** is the viewer's saved choice when the widget still declares it, else the default. A stored or forged key the widget doesn't declare never reaches the query (fail closed), so retiring an order needs no clean-up.

The choice is kept per person, per workspace and per widget key, in `kit_user_state` (the kit's per-person store, with the workspace's id), so it follows the person across devices. Two people never share an order, and neither do one person's two workspaces. The same widget class registered under two keys keeps two choices. It goes with the account (`UserState::forgetAll()`).

Choosing an order sends `PUT widgets/{key}/sort` (`{ sort }`) in the workspace. `SaveWidgetSort` authorises as the card is shown: `dashboard.read`, then the widget's own ability. It builds the widget for the viewer and stores the order only if the widget declares it. An undeclared order is a validation error on `sort`, and the control puts the previous order back. A widget nobody registered, or one hidden from the viewer, is a 404. The response sends the person back to the dashboard as a partial Inertia visit for the props in `sortBy.reload`: the widget's own lists (each reset to its first page), or `widgets` when it has none. Nothing else on the page reloads, and the card's body remounts on the new order.

The control is the kit's `InputSelect`, labelled by its visible "Sort by" text, shown only while the card is `ready`. From the keyboard, Tab reaches it, the arrow keys or Enter open it, the arrows move, Enter picks and Escape closes without a change.

## The kit's widgets

| Key | Widget | Ability | Kind, region | Shows |
|---|---|---|---|---|
| `kit.you` | `YouWidget` | `dashboard.read` | status, aside | No title: the viewer's photo, name, local time and timezone, with Edit (their profile), then their open personal onboarding steps. Named "You" for assistive tech. |
| `kit.activity` | `ActivityWidget` | `members.manage` | feed, main | The workspace's latest 10 audit events: who, what (the event name) and when. An event's details are never shown. |
| `kit.people` | `PeopleWidget` | `dashboard.read` | status, aside | The workspace's active members in a ScrollList, with Manage people for someone who may. |
| `kit.setup` | `SetupWidget` | `settings.write` (`members.manage` without the settings feature) | setup, aside, last | "Set up this workspace": the workspace's onboarding steps as a Checklist. |

### Onboarding steps

The kit sends no standalone welcome card (the `welcome` prop and `WelcomeCardData` are deprecated, removed in 0.17; an app that still passes one gets it rendered above the widgets as before). Each onboarding step (`Onboarding::step()`) has a `scope`, `person` (the default) or `workspace`, and the dashboard shows each where it belongs, as a kit Checklist whose rows link to their welcome step (`/welcome/{id}`):

- **Personal steps** (the kit's: your timezone and your photo) are a "Finish setting up" section of the **You** card (its `checklist` prop), for every member.
- **Workspace steps** (the kit's: the workspace timezone) are the **Set up this workspace** card (`kit.setup`), only for people who manage the workspace's settings: the existing `settings.write` ability (owner and admin), or `members.manage` when the settings feature isn't adopted. It is always the aside's last card.

Each is hidden once every one of its steps is done (a skipped step still shows, as skipped), and neither shows with the `welcome` route group off. An app adds a workspace step with `scope: 'workspace'`:

```php
Onboarding::step('branding', 'Workspace branding', 30, fn (int $user, ?int $workspace): bool => Branding::isSet($workspace), 'Add the logo', scope: 'workspace');
```

A widget may leave itself out for a viewer by returning false from `visible()` (checked after its ability, before its props are read); the setup card does once every step is done. When it is the only widget, it takes the grid's full width.

The notes feature's `notes.recent` (order 0, `notes.read`) is a task in main: its composer and two-up notes need the width, and the one feed is Recent activity.

Each is optional. `kit.dashboard.widgets` in `config/kit.php` lists the ones to keep, and it defaults to all four:

```php
'dashboard' => ['widgets' => ['you', 'people']],
```

### Replacing or suppressing a widget

An app that wants its own widget in one of these slots, such as its own activity feed instead of `kit.activity`'s audit rows, replaces it by key. Suppressing leaves the widget out:

```php
use Tey\Kit\Features\Core\Registry;

app(Registry::class)->replaceWidget('kit.activity', FeedWidget::class);              // keeps order -20 and members.manage
// or, instead (a key is replaced once): app(Registry::class)->replaceWidget('kit.activity', FeedWidget::class, ability: 'feed.read');
app(Registry::class)->withoutWidget('kit.people');
```

Both work for any registered key, the kit's or another feature's, and whichever registers first. The replacement keeps the key, so the frame's `key` stays `kit.activity`, and keeps the original's `order` and `ability` unless given. A key is replaced or suppressed once, never both. Replacing a key nobody registers (for example one dropped by `kit.dashboard.widgets`) fails when the widgets are read, so a typo never silently shows nothing; suppressing one is a no-op.

The kit doesn't swap `kit.activity` out on its own when an activity-feed package is installed: having the package doesn't mean the app has a feed widget for this slot. Replace it explicitly.

### Hiding people by grant

`kit.people.hide_grants` lists Concierge grants (roles) whose holders the people lists leave out, such as a support role an operator holds in every workspace. A member who holds one of them in the workspace is left out of Settings › People (its rows and the Members count alike, through Concierge's `MembersIndex::scopeMembersUsing()`), the People widget and people search. The default is `[]`. It needs `tey/concierge` 0.9.3 or later: with hidden grants on an older Concierge, or a value that isn't a list of names, the app refuses to boot, so no page ever shows everyone.

```php
'people' => ['hide_grants' => ['support']],
```

A hidden member is absent by URL too: their Change role and Transfer ownership pages, and changing or removing them by id, are a 404. Their own roles and permissions are unchanged. `composer test:people-hide-grants` covers it.

## Frontend

`pages/Workspace/Dashboard/Page.vue` renders the greeting as its heading, then `Partials/WidgetGrid.vue`, or the empty state when the viewer has no widgets. The grid is a PageGrid `main-aside` (or `main-aside-wide`, from the `aside` prop) with a Stack of cards in each column (see Layout). `Partials/WidgetCard.vue` is the card shell: the title, the header actions, then the body, the empty state or the error with Retry. An app's widget body is a component in `resources/js/widgets/` that takes a `widget` prop, typed as `WidgetFrame` from `Partials/types.ts` plus its own props.

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

`composer test:dashboard` covers registration, ordering, ability and capability gating, error isolation and the log line, the kit's widgets' props, the people list's deferred rows and the read tool's operation. It runs on SQLite memory with synthetic users and Inertia JSON. The browser job renders the grid with the kit's widgets (`dashboard.html`): axe in light and dark, and main and aside side by side at 1440 and 768 and stacked main-then-aside at 767, each card its column's full width, nothing overflowing. It also covers Sort by: the declared orders and the default; malformed and undeclared keys (on save, and when stored) falling back; a choice kept per person, per workspace and per widget key; the ability gate, the unknown widget and the outsider; and the account's state going with it. The browser job checks the control at 1280 and 1600, light and dark: it sits at the right of the header, is named "Sort by", opens and picks from the keyboard, sends one PUT as a partial visit for `widgets`, shows the new order, and puts the old one back when refused. Not covered: the read tool is called directly, not over the MCP transport. A widget with its own list is covered on the server (its list key is the reload, and the partial reload after a change returns the first page in the new order), not in the browser.
