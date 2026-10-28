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

**Slots.** The region slots are `navigator`, `stage`, `inspector`, `footer` and `toolbar`. Defaults:
- the navigator: a filterable listbox of `items`, with an `item` slot on WorkbenchNavigator for custom rows;
- the inspector: the registry's choice (below).

`actions` adds controls to the bar, and `status` adds a status beside the save state.

## useWorkbench(key)

One store per key, shared by the page, the stage, the inspectors and the Workbench.

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
- Elements aren't items, so `items` never prunes them. They're cleared whenever the current item changes.
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
- `defineInspector(type, component, { when })` registers an inspector and returns the unregister function. `inspector` is the resolved one, below.

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

Later definitions win over earlier ones, and a `when(context)` returning false skips one. Each inspector receives `{ element, elements, ids, items, types, current }`. `element` is set only when exactly one element is selected. `types` are the element types when elements decide, otherwise the items' types. `ids` and `items` are always the item selection. With nothing to show, the region says so.

## Keyboard

| Keys | Where | Does |
|---|---|---|
| ↑ / ↓, j / k | Navigator | Previous / next item |
| Alt+↑ / Alt+↓ | Navigator | Move the current item (when reorder is allowed) |
| ⌘[ / ⌘] | Anywhere | Collapse or expand the navigator / inspector (at lg, ⌘] opens and closes the drawer) |
| / | Workbench, not while typing | Focus the navigator filter |
| Esc | Workbench | Close the inspector drawer; otherwise clear the stage elements; otherwise clear the item selection. Not while typing, except for the drawer |
| F6 / Shift+F6 | Anywhere | Next / previous region (navigator, stage, footer, inspector), skipping collapsed ones |
| ⌘+ / ⌘- / ⌘0 | Anywhere | Zoom in / out / 100% |

⌘ is Ctrl off a Mac. While typing in a field, only F6, Esc and the ⌘ shortcuts apply. The regions are labelled landmarks: `nav` for the navigator, `section` for the stage and footer, and `aside` for the inspector. The actions bar is a labelled region with a `status` live region.

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

**Not covered:** there's no browser test of the rendered components (the kit has no DOM test runner). Resizing, drag-and-drop and focus are checked through their logic and sources, not in a real browser.
