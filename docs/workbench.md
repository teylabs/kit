# Workbench

`<Workbench>` is the kit's multi-pane editing surface for a sequence of items with a live preview. Think of a slide editor (slides, the canvas, block settings) or a valuation (comparables, the working, adjustments). It follows the layout people know from PowerPoint, Keynote, Google Slides and Figma:

| Region | What goes there | Notes |
|---|---|---|
| `navigator` | The items, in order | A listbox with a filter. It can be reordered. |
| `stage` | The work itself | Has an optional sticky `toolbar` on top and zoom and fit controls. |
| `footer` | Optional, under the stage | For example, speaker notes or working totals. It's resizable and collapsible. |
| `inspector` | Settings for the stage's selection | Switches automatically by selection type. |
| actions bar | Save state, conflict, zoom, and the app's actions | A StickyBar registered with `useStickyBars`. |

It's built from the kit's own pieces: Reka's Splitter (as in SplitPane), PaneTabs, PaneToggle, PaneScroll, StickyBar, Modal (the lg drawer) and ActionBar. The core feature owns the components in `Kit/Workbench/`. The per-person layout endpoint is session-ui's, and the optional read tool is mcp's.

## Layout per width

| Width | Layout |
|---|---|
| xl and up (≥1280px) | Three columns: navigator, stage and inspector. They're resizable (keyboard resize goes in 5-point steps) and the side panes collapse. With a footer, the stage column splits vertically. |
| lg (1024–1279px) | Navigator and stage. The inspector opens in a right drawer from the actions bar (or with ⌘]); Esc closes it. |
| Below lg | Tabs: Navigator, Stage, Inspector, plus the footer's tab when there is a footer. One region shows at a time. |

One layout is mounted at a time, and the mode follows `matchMedia`. The regions' slots are rendered once per layout, so keep their state in `useWorkbench` rather than in the slot's own components when it must survive a mode change.

## Remembered layout

A collapsed navigator, inspector or footer keeps a slim bar (2rem) with its label and an Expand button, so it always has a visible way back. The bar stands beside a side pane, or under the stage for the footer. The button is in the tab order, with `aria-expanded="false"` and `aria-controls` naming the pane. Expanding, from the bar or with ⌘[ / ⌘], restores the pane's remembered size.

Pane sizes, collapsed panes (navigator, inspector and footer), the zoom and the phone tab are remembered per person and per `workbench-key`. The key names a kind of workbench, such as `deck-editor`, not a record. It uses lowercase letters, digits, `.`, `_` and `-`, up to 48 characters.

- **Where it's stored:** session-ui stores the layout in `kit_user_state` as the person's own state, so it follows them across devices. It's saved through `PUT /me/workbenches/{key}` with `{ layout, version }`.
- **What's validated:** the endpoint only accepts the layout's known shape (`groups`, `zoom`, `tab`) and size (8 KB), never content.
- **Stale writes:** a stale `version` gets 409 `stale_write` with the stored layout and version. The page then takes the server's copy, puts its own change back on top, and saves once more. That way, two devices never silently overwrite each other.
- **Without session-ui,** or when saving fails, the layout is kept in the browser's localStorage.

## Server

```php
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\WorkbenchInspector;
use Tey\Kit\Features\Core\Data\WorkbenchItem;
use Tey\Kit\Features\Core\ViewModels\WorkbenchViewModel;

final class DeckEditorViewModel extends WorkbenchViewModel
{
    public function __construct(private Deck $deck, private bool $canEdit) {}

    public function title(): string { return $this->deck->title; }

    public function workbenchKey(): string { return 'deck-editor'; }

    /** @return list<WorkbenchItem> */
    public function items(): array
    {
        return $this->deck->slides->map(fn (Slide $slide) => new WorkbenchItem((string) $slide->id, $slide->title, $slide->layout))->all();
    }

    public function current(): ?string { return (string) $this->deck->slides->first()?->id; }

    /** @return list<WorkbenchInspector> */
    public function inspectors(): array
    {
        return [new WorkbenchInspector('@document', 'Deck'), new WorkbenchInspector('text', 'Text'), new WorkbenchInspector('@fallback', 'Selection')];
    }

    /** @return list<\Tey\Kit\Features\Core\Data\Action> */
    public function actions(): array
    {
        return $this->canEdit ? [new Action('Publish', route('decks.publish', $this->deck), 'post', key: 'publish')] : [];
    }

    public function reorder(): ?Action
    {
        return $this->canEdit ? new Action('Reorder', route('decks.order', $this->deck), 'put', key: 'reorder') : null;
    }

    public function version(): string|int|null { return $this->deck->version; }
}
```

`WorkbenchViewModel` gives the page these props:

- `workbenchKey`
- `items`: id, label, type and description
- `current`
- `inspectors`
- `actions`: descriptors with keys, so `Toast::success('Published')->from('publish')` pulses the button
- `reorder`, and the `version` token it checks
- `workbenchLayout`: the person's layout and where to save it

Decide every action on the server: a viewer who may not reorder gets no `reorder`, and the navigator then has no drag or Alt+↑/↓.

**The reorder action** receives `{ order: [ids…], version }`.
- **When it saves:** it answers 2xx with `{ version }`, the new token.
- **When the version is stale:** it answers 409, or a refusal code such as `stale_write`, `stale_*` or `*conflict*`. The page then puts the old order back and shows a toast.

**The read tool.** `WorkbenchReadTool` (mcp) is an optional `CapabilityTool` base. It's always read only.
- **Subclass:** name its capability and ability as for any tool. Set `subject()`, the argument naming the record (`id` by default), and implement `read()`, which returns at least `items`.
- **Output:** items come out as `{ id, label, type, description }`. Everything else `read()` returns is passed through, and `current` defaults to null.

## Page

```vue
<script setup lang="ts">
import { Workbench, useWorkbench } from '@/components/Kit';
import TextInspector from './Inspectors/TextInspector.vue';
import DeckInspector from './Inspectors/DeckInspector.vue';

const props = defineProps<App.ViewModels.DeckEditorViewModel>();
const wb = useWorkbench(props.workbenchKey);
wb.defineInspector('@document', DeckInspector);
wb.defineInspector('text', TextInspector);

const preview = wb.onRecalculate((slide: string, signal) => fetch(`/decks/preview/${slide}`, { signal }).then(r => r.json()), { debounce: 300, apply: result => (rendered.value = result) });
</script>
<template>
    <Workbench :workbench-key="workbenchKey" label="Deck editor" :items="items" :current="current" :reorder="reorder" :version="version" :layout="workbenchLayout" :actions="actions" zoom :canvas="{ width: 1920, height: 1080 }" :labels="{ footer: 'Speaker notes' }" @action="run">
        <template #toolbar><InsertMenu /></template>
        <template #stage><SlideCanvas :scale="wb.scale" @select="(id, event) => wb.select(id, event)" /></template>
        <template #footer><SpeakerNotes :slide="wb.current" /></template>
    </Workbench>
</template>
```

**Props.** `workbench-key` and `label` are required.

| Prop | What it does |
|---|---|
| `items`, `current` | The server's items, and the one selected on arrival |
| `multiple` | Multi-select; on by default |
| `layout` | `workbenchLayout` |
| `reorder`, `version` | The reorder action and its version token |
| `actions` | Actions-bar descriptors (non-navigating ones are emitted as `action`) |
| `zoom` | Shows the zoom controls |
| `canvas` | The content's natural size, so a fit works |
| `labels` | Region names |
| `filterable` | The navigator filter; on by default |
| `fill` | Fill the viewport below the shell (`100dvh` less 8rem, at least 28rem), so the panes and the stage have a height to resize and fit in; on by default. Set it to `false` for a Workbench inside a box of a definite height. |
| `save-status` | `kit` (default) shows the save state (Saving…, Saved, Unsaved changes) as a polite status region in the actions bar, and a conflict as an alert. `none` renders neither, for an app whose own form bar in `#actions` already announces it; `wb.saveState` is still there to read. |

**Slots.** The region slots are `navigator`, `stage`, `inspector`, `footer` and `toolbar`. Defaults:
- the navigator: a filterable listbox of `items`, with an `item` slot on WorkbenchNavigator for custom rows;
- the inspector: the registry's choice (below);
- the stage with no items yet: `#stage`, as with items. The kit adds no empty state of its own; pass `#empty` for one (see [Empty workspace](#empty-workspace)).

`actions` adds controls to the bar, and `status` adds a status beside the save state. `empty`, when given, replaces the stage while there are no items.

## Empty workspace

While the workspace has no items (`useWorkbench(key).isEmpty`, true when `items` is empty), the stage renders the host's `#empty` slot in place of `#stage`, in every layout. It's opt-in: with no `#empty` slot the stage renders `#stage` as usual, so a stage-only Workbench with no items (a presenter console, say) keeps its stage. The navigator still renders, with its filter and a plain "Nothing here yet." message (WorkbenchNavigator's `empty`) in place of the listbox, since a listbox with no options and an empty scroll box both fail accessibility checks. The same message shows when the filter matches nothing. `focusRegion('navigator')` and F6 land on that message, and `togglePane` works as usual. Without an `#empty` slot the stage shows a compact `EmptyState` titled "Nothing here yet.", with no actions.

The slot's props:

| Prop | |
|---|---|
| `actions` | The Workbench's `actions` descriptors, so the empty state can offer the same create and import actions. |
| `act(action)` | Runs a descriptor through the Workbench's `action` event, as the actions bar does. |
| `label` | The stage's label (`labels.stage`). |

```vue
<Workbench workbench-key="deck-editor" label="Deck" :items="slides" :actions="actions" @action="run">
    <template #empty="{ act, actions }">
        <EmptyState title="No slides yet" body="Start from a draft, import a deck, or add a blank slide.">
            <template #action>
                <Button @click="createDraft">Create draft</Button>
                <Button variant="outline" @click="openImport">Import</Button>
                <Button variant="outline" @click="act(actions.find(action => action.key === 'new-slide')!)">+ New slide</Button>
            </template>
        </EmptyState>
    </template>
    <template #stage><SlidePreview /></template>
</Workbench>
```

`EmptyState`'s own `actions` prop takes the descriptors directly (`<EmptyState :actions="actions" @action="act" />`) when the buttons are the server's.

## useWorkbench(key)

One store per key, shared by the page, the stage, the inspectors and the Workbench. The store outlives an Inertia visit, so pane prefs and zoom carry over, but the selection belongs to the page: each Workbench mount, and every change of the server's `current` prop (a link, back, a visit that keeps the component), selects `current` again and clears the stage elements.

**Selection**
- `items`, `order`, `selection`, `selected` (ids), `current` and `currentItem`.
- `select(id, mode | event)`:
  - `replace`;
  - `toggle`, with ⌘ or Ctrl;
  - `range` from the current item, with Shift.
- Pass the click event from the stage to get multi-select. The inspector follows.
- `setSelection(ids)`, `clear()`, and `next()` / `prev()`.
- Selected ids that disappear from `items` are pruned.

**Stage elements**
- `elements` (read only) is the stage's own selection inside the current item, such as blocks on a slide, as `{ id, type }`.
- `selectElement(id, type, mode | event)`: `replace`, or with ⌘, Ctrl or Shift, add or remove. Pass the stage's click event for multi-select.
- `selectElements([{ id, type }, …])` sets them all at once, and `clearElements()` empties them.
- Elements aren't items, so `items` never prunes them. They're cleared whenever the current item changes, and on every arrival (above).
- While any are selected they pick the inspector, by element type (below).

```vue
<div v-for="block in slide.blocks" :key="block.id" :data-selected="wb.elements.some(e => e.id === block.id) || undefined"
     @click.stop="wb.selectElement(block.id, block.type, $event)">…</div>
```

**Save state**
- `dirty` and `saveState` (`idle`, `saving`, `saved`, `unsaved`, `conflict` or `error`).
- `markDirty()`, `markSaving()`, `markSaved()`, `markConflict()` and `markFailed()`.
- The actions bar announces the state politely, and a conflict as an alert.

**Live preview**
- `onRecalculate(fn, { debounce, apply, failed })` returns `{ request(input), cancel(), pending }`. `fn` runs after `debounce` ms of quiet (250 by default).
- A newer request aborts the older run's `AbortSignal`.
- A result that arrives after a newer run started is dropped, so an out-of-order response never overwrites a fresher one.
- It's cancelled when the calling component unmounts.

**Inspectors**
- `defineInspector(type, component, { when, for })` registers an inspector and returns the unregister function. `for` is `elements`, `items` or `any` (the default). Element selections consider only `elements` and `any` inspectors, and item selections (and `@document`) only `items` and `any`. So a slide layout `hero` and a block type `hero` can each have their own inspector: `defineInspector('hero', HeroLayout, { for: 'items' })` and `defineInspector('hero', HeroBlock, { for: 'elements' })`. `inspector` is the resolved one, below.

**Reorder**
- `reorder(ids)` and `moveCurrent(±1)` apply the order at once and send it with the version token.
- On a conflict or a failure the old order comes back, with a toast. One reorder runs at a time.
- The navigator offers drag and Alt+↑/↓. Reorder is off while a filter is on.

**Zoom**
- `zoom` is `{ fit: 'width' | 'page' | null, zoom }`, and `scale` is the number the canvas renders at.
- `fit('width' | 'page')`, `zoomIn()`, `zoomOut()`, `zoomReset()` and `setZoom()`.
- Zoom steps run from 25% to 400%. A fit uses `canvas` and the stage's measured space. The zoom is remembered with the layout.

**Panes**
- `mode`, `tab`, `drawerOpen` and `collapsed`.
- `togglePane(region)` and `focusRegion(region)`.

## Selection-driven inspector

The inspector region shows the inspector registered for the stage's current selection. Types are open-ended, such as a block type or a record type. Selected stage elements decide first, by element type. Without them the selected items decide, and with neither it's `@document`.

| Selection | Inspector |
|---|---|
| Nothing (no elements, no items) | `@document`: the whole document's settings |
| Stage elements of one type | That element type's inspector, with every selected element |
| Stage elements of mixed types | A common inspector for their types, otherwise `@fallback` |
| One type (one item or several of it) | That type's inspector, with every selected id |
| Mixed types | A common inspector, registered with a list of types (`defineInspector(['text', 'image'], MediaText)`) that covers them all; otherwise `@fallback` |
| A type nothing covers | `@fallback` |

Within a scope, later definitions win over earlier ones, and a `when(context)` returning false skips one. Each inspector receives `{ element, elements, ids, items, types, current }`. `element` is set only when exactly one element is selected. `types` are the element types when elements decide, otherwise the items' types. `ids` and `items` are always the item selection. With nothing to show, the region says so.

## Keyboard

| Keys | Where | Does |
|---|---|---|
| ↑ / ↓, j / k | Navigator | Previous / next item |
| Alt+↑ / Alt+↓ | Navigator | Move the current item (when reorder is allowed) |
| Shift+F10, the context-menu key | Navigator item | Open the item's menu, when it has `actions` ([Item menus](#item-menus)) |
| ⌘[ / ⌘] | Anywhere | Collapse or expand the navigator / inspector (at lg, ⌘] opens and closes the drawer) |
| / | Workbench, not while typing | Focus the navigator filter |
| Esc | Workbench, or the page after a stage click | Close the inspector drawer; otherwise clear the stage elements; otherwise clear the item selection. It also works when a click on a plain stage element left focus on the page body. Not while typing, and not while another dialog or a menu is open |
| F6 / Shift+F6 | Anywhere | Next / previous region (navigator, stage, footer, inspector), skipping collapsed ones |
| ⌘+ / ⌘- / ⌘0 | Anywhere | Zoom in / out / 100% |

⌘ is Ctrl off a Mac. While typing in a field, only F6, Esc and the ⌘ shortcuts apply. The regions are labelled landmarks: `nav` for the navigator, `section` for the stage and footer, and `aside` for the inspector. The actions bar is a labelled region with a `status` live region.

**Live sessions: disable Workbench keys; the app owns transport.** For a live session or a presenter transport, where the app maps the keys itself, pass `:keys="false"`:

```vue
<Workbench workbench-key="live-console" label="Console" :keys="false">…</Workbench>
```

None of the Workbench's own bindings fire: not ↑/↓, j/k, Alt+↑/↓, `/`, F6 / Shift+F6, Esc, ⌘[ / ⌘] or zoom, whether focus is inside the Workbench or on the page. Focus management (`focusRegion`, the navigator's `focusCurrent`) and the region landmarks stay. The decision is the pure `enabledCommand(enabled, event, context)`.

## Navigator actions

`#navigator-actions` puts the app's own controls, such as "+ New slide", at the top of the navigator. They render first in its DOM, before the filter and the list, at every width: the columns, the lg layout, the phone tabs and the overlay sheet. So the visual order and the tab order match, and no `order-first` is needed. With the slot empty, the navigator renders as before. On phones the navigator tab is a flex column like the wider layouts, so the actions sit above the list and clear of the actions bar.

```vue
<Workbench workbench-key="deck" label="Deck" :items="items" filterable>
    <template #navigator-actions><Button size="sm" @click="newSlide">New slide</Button></template>
</Workbench>
```

## Item menus

A navigator item can carry its own kit action descriptors in `actions`, and gets a row menu, like a slides app's right-click. The kit doesn't decide what's in it.
The host decides, per item, which actions to send: Move up, Move down, Duplicate, Delete or anything else. An item without `actions`, or with an empty list, has no
menu button and keeps the browser's own context menu.

```ts
import { router } from '@inertiajs/vue3';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

const items: WorkbenchItem[] = slides.map((slide, index): WorkbenchItem => ({
    id: String(slide.id),
    label: slide.title,
    actions: [
        { key: 'move-up', label: 'Move up', url: `/slides/${slide.id}/move`, method: 'post', disabled: index === 0 },
        { key: 'move-down', label: 'Move down', url: `/slides/${slide.id}/move`, method: 'post', disabled: index === slides.length - 1 },
        { key: 'duplicate', label: 'Duplicate', url: `/slides/${slide.id}/duplicate`, method: 'post' },
        { key: 'delete', label: 'Delete', url: `/slides/${slide.id}`, method: 'delete', destructive: true, confirm: { title: 'Delete this slide?', description: 'It is removed from the deck.', label: 'Delete' } },
    ],
}));

function run(action: ActionDescriptor, item?: WorkbenchItem) {
    if (!item) return; // the actions bar's own descriptors arrive without an item
    // Ask first for a `confirm` (Delete), then send it. The item may not be the current one: a right-click doesn't select.
    router.visit(action.url, { method: action.method as 'post' | 'delete', data: { id: item.id }, preserveScroll: true });
}
```

```vue
<Workbench workbench-key="deck-editor" label="Deck editor" :items="items" @action="run" />
```

- **Running one:** a chosen descriptor is emitted as the Workbench's `action` event with the item as its second argument (the actions bar's descriptors have
  none). As in every kit menu, a GET without a confirmation renders as a link, and `intent: 'dialog'` opens its route modal; everything else is emitted. A
  `disabled` descriptor shows greyed, with its `reason` as the title. The host runs `confirm` itself.
- **The button:** each item with actions has a menu button named "Actions for <label>" (`menu-label` on WorkbenchNavigator changes the prefix). It shows on
  hover and on focus, and always on a touch (coarse) pointer. It's a Tab stop for the current item only, so the list stays one stop plus one.
- **Right-click, Shift+F10 or the context-menu key** on an item open the same menu, anchored to the row. The browser's menu is prevented only when the item has
  actions.
- **Focus:** opened from the item, the first action takes focus, and Escape (or a choice) returns focus to the item. Opened from the button, focus returns to
  the button. ↑ / ↓ move within the menu, and its rows are `role="menuitem"`.
- **Reordering by keyboard** stays Alt+↑ / Alt+↓ when `reorder` is set. The menu's Move up and Move down are the touch and discoverable route to the same thing.

**Why the button sits outside the listbox.** The navigator is a `role="listbox"` of `role="option"` items, and a listbox may own only options or groups. A
button inside an option is axe's `nested-interactive`, and any focusable element inside the listbox can be skipped by a screen reader. So the menu buttons
live in a sibling column next to the listbox, not inside it. The column shares the listbox's rows through CSS subgrid, so each button lines up with its item
whatever the item's height. The listbox keeps its own keyboard model unchanged: arrows, `/` for the filter, and F6 between regions. A grid
(`role="grid"`) would also allow a button cell, but it would change that model and what a screen reader announces for every navigator. Nothing in this
layout needs a grid.

## Dense surfaces: default-collapsed panes, no headings, an overlay navigator

For a live console or another dense screen:

```vue
<Workbench workbench-key="live-console" label="Console" :default-collapsed="{ navigator: true }" :headings="false" navigator-mode="overlay" close-on-select :keys="false">…</Workbench>
```

- **`:default-collapsed="{ navigator: true, inspector: true }"`** collapses those panes the first time each layout (the xl columns, the lg columns) shows. It applies only when the person has no remembered layout for it, so their own layout always wins and you never touch the kit's storage key.
- **`:headings="false"`** drops the region headings (the small `h2`s) and the toggles beside them.
  - The regions keep their accessible names (`aria-label`), so they're still labelled landmarks.
  - The pane toggles move to the actions bar, and a collapsed pane keeps its Expand bar, so every pane is still reachable by keyboard.
- **`navigator-mode="overlay"`** (the default is `push`) opens the navigator as a sheet over the stage at every column width, the way the lg inspector drawer works.
  - The stage never resizes.
  - It's a kit Modal: focus is trapped inside, Esc closes it, and focus returns to the toggle.
  - The navigator toggle in the actions bar, ⌘[ and F6 open it.
  - With **`close-on-select`**, choosing an item closes it.
  - Below lg the tabs are unchanged.

## Editing toolbars and narrow actions

**A floating toolbar over the selection.** A `FloatingToolbar` with `mode="stage"` floats over the stage's selected elements (`wb.elements`). It centres over their bounding box, sits above it, flips below when there's no room above, and stays inside the stage at every edge. While nothing is selected it's invisible (and out of the tab order). `mode="docked"` renders the same toolbar in the stage's `toolbar` row instead, or in place when the Workbench has no `toolbar` slot. Both teleport into the stage, so you can put the toolbar in any Workbench slot.

The stage finds a selected element by its element attribute, `data-workbench-element` by default. Put it on each element you draw, with the id you pass to `selectElement(s)`:

```vue
<template #stage>
    <SlideCanvas>
        <div v-for="block in blocks" :key="block.id" :data-workbench-element="block.id" @click="wb.selectElement(block.id, block.type, $event)">…</div>
    </SlideCanvas>
    <FloatingToolbar mode="stage" label="Text" :items="[{ key: 'bold', label: 'Bold' }, { key: 'align', label: 'Align', disabled: !canAlign, reason: 'Select one block' }]" @action="format" />
</template>
```

- **Your own element attribute.** If your elements already carry their id in another attribute, name it instead of adding a second one:
  - `<Workbench element-attribute="data-block-id">`;
  - or `useWorkbench(key, { elementSelector: 'data-block-id' })`, where `[data-block-id]` works too.

  The stage toolbar's selection rects, `mode="stage"` and `reposition()` all use it. Anything that isn't a valid attribute name falls back to the default.
- **Client actions.** `items` are `ToolbarAction`s: `key`, `label`, and optional `icon` (an `actionIcons` name), `disabled` and `reason`. They have no URL, because the page runs them in the browser. Choosing one calls your `onAction` (`@action`) from the toolbar's own click handler, not from an emit in a part that might unmount. A disabled item stays focusable and shows its reason as a title. Server descriptors still go through an `ActionBar` in the default slot, as before.
- **Groups, icon-only items and overflow.** An item's `group` (`'text'`, `'block'`, `'slide'`) keeps it with the other items of that group, with a rule between groups. With `iconOnly` it shows only its icon: the label becomes its accessible name and its tooltip, and it stays a focusable 36px button. Items keep the pill to one row. When they don't fit the width the pill has (measured with a ResizeObserver, like ActionBar, with hysteresis), whole trailing groups move into a ⌄ "More actions" menu first, last group first. If only the first group is left and it still doesn't fit, its trailing items follow, so the first N stay. Groups are separated in the menu too, and choosing a menu row calls `onAction` after the menu has closed. The decision is `fitToolbarItems(items, { container, widths, gap, separator, trigger })`, which is pure.
- **Icons.** `actionIcons` names: `align-left`, `align-center`, `align-right`, `arrow-up`, `arrow-down` (move earlier or later), `trash`, `image`, `eye`, `eye-off`, `plus`, `copy`, `chart`, `poll`, plus `banknote`, `check`, `credit-card`, `file-text`, `message-square`, `pencil` and `send`. Each is imported by name.
- **Repositioning.** The toolbar repositions when the selection, zoom or stage size changes and when the stage scrolls. When you move elements without changing the selection (a drag), call its exposed `reposition()`.
- **The stage context.** `WorkbenchStage` provides `provideWorkbenchStage({ workbenchKey, viewport, toolbar })`. `useWorkbenchStage()` returns it inside a stage and `null` elsewhere, and `selectedRects(viewport, ids, attribute)` measures the selected elements (`attribute` defaults to `data-workbench-element`; `elementAttributeName()` normalises a name or selector). The positioning math is `anchorToolbar(selection, stage, toolbar)` with `unionRect(rects)`, both pure.

**Actions in a narrow bar.** ActionBar measures its own width with a ResizeObserver, not the viewport. When its full layout doesn't fit, it keeps the main action and a ⌄ menu with every other action, in order. So the actions bar of a Workbench at 390px compacts at any viewport width. The decision is `fitActions(actions, { container, parts, gap })`: an unmeasured bar (width zero, such as a server render or a hidden tab) and a bar with one action never compact.

**Inspector sections.** Use [`Collapsible`](collapsible.md) for an inspector's sections.

## Toasts

A refused reorder has no page visit to flash a toast. Instead, the Workbench sends one through the shell's Toaster with a `kit:toast:show` window event. Pages still never toast; the event is for kit components (see [Toasts](toasts.md)).

## Tests

- **`composer test:workbench-js`** (node) covers the logic:
  - the layout per breakpoint, phone tabs (with the footer only when provided) and the F6 order;
  - every key binding, and that typing keeps single keys;
  - multi-select (replace, toggle, range), next/prev and pruning;
  - the inspector registry (document, single, common, fallback, `when`);
  - the recalculate stale guard (debounce, abort, out-of-order results dropped);
  - optimistic reorder with rollback on a conflict or failure;
  - zoom and fit;
  - the versioned layout store (load, save, stale merge, one write at a time, the localStorage fallback).
  - It also checks the components' sources for the per-breakpoint layouts, landmarks, wiring, sticky toolbar, footer splitter, actions bar and inspector region.
- **`composer test:workbench`** (PHP, SQLite memory) covers the server:
  - the ViewModel's props and layout;
  - the endpoint: versions, stale-write refusal, per person and per key, validation of shape and size, routing and sign-in;
  - the null store;
  - the read tool's normalization and refusals.

- **`composer test:js`** (`tests/js/workbench-item-menu.test.mjs`) covers which keys open an item menu, the item's descriptors and the button's name, and
  the navigator's source: the buttons outside the listbox, right-click and Shift+F10 on the same menu, focus going back, and the item emitted with the action.
- **The browser job** (`browser/run.mjs`, Playwright and axe-core) renders a Workbench of three items with menus. It checks that there are no axe violations,
  with the menu closed and open, and that no focusable element sits inside the listbox. It also checks that Shift+F10 opens the focused item's menu with a
  menuitem focused, that Escape returns focus to the item (or to the button it opened from), that right-click opens the same menu and emits the chosen action
  with its item, and that an item without actions has no button and keeps the browser menu. With the menu open, axe's `region` and `color-contrast` rules are
  off: the panel is portalled to `<body>` like every kit menu, and the default `--destructive` token is shared by every destructive menu item.
- **`composer test:js`** (`tests/js/workbench-editor-gaps.test.mjs`) covers `fitActions`, `anchorToolbar` (above, flipped below, clamped left and right), `unionRect` and `runToolbarAction`, plus the sources of ActionBar's ResizeObserver, FloatingToolbar's stage and docked modes and the stage context.

**Not covered:** apart from the item menus above and the browser job's zero-item Workbench (axe, and Shift+F6 to the empty message), there's no browser test of the rendered Workbench. Resizing, drag-and-drop and focus are checked through their logic and sources, not in a real browser. Neither ActionBar's compacting nor the stage toolbar's position has been checked in a laid-out page.
