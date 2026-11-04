# Sortable lists

The kit has one reorder model, shared by the Workbench navigator, the Repeater and `SortableList`. It covers:

- the move itself (`moveItem`)
- an optimistic apply that rolls back on a conflict (`createReorder`)
- the app's reorder action, which carries a version token (`sendOrder`, `ReorderAction`)
- the announcement after a move

```ts
import { SortableList, SortableHandle, useSortable } from '@/components/Kit';
```

It needs `vue-draggable-plus` (`npm install vue-draggable-plus@^0.6.1`). `kit:status` and `kit:preflight` say so when it is missing.

## SortableList

A plain `ul`/`li` list, never a listbox, so a row may hold links and buttons. Each row has:

- **a grip.** Drag it with a pointer. While it has focus, ↑/↓ (or ←/→) move the row one place, and Home/End move it to either end. Focus stays on the moved row's grip.
- **Move up / Move down buttons**, the non-drag way for touch and switch users. Turn them off with `:move-buttons="false"`.

After each move, a polite live region says where the row went: "Slide Plan moved to position 2 of 4".

```vue
<SortableList :items="slides" :item-label="slide => `Slide ${slide.title}`" label="Slides" @update="ids => (order = ids)">
    <template #default="{ item }"><Link :href="item.url">{{ item.title }}</Link></template>
</SortableList>
```

The order is the host's: `update` gives the new ids, and `update:items` gives the reordered items, so `v-model:items` works too.

### Saving through the app's reorder action

Pass `reorder` (the app's ReorderAction) and `version`. The list then sends `{ order, version }` itself, optimistically:

- **A 2xx** answers `{ version }`. The list keeps that token for the next move and emits `saved`.
- **A 409, or a `stale_*` / `*conflict*` code,** rolls the order back and calls `on-conflict` with the order that was refused (or emits `conflict`). The live region says so.
- **Any other failure** rolls back and emits `failed`.

Only one reorder runs at a time. This is the same transport, and the same stale-write rule, as the Workbench's `reorder`.

```vue
<SortableList :items="slides" :item-label="labelFor" label="Slides" :reorder="page.props.reorder" :version="page.props.version" :on-conflict="() => router.reload()" />
```

`disabled` turns drag, the grip keys and the buttons off, for example while a filter is on or a save is in flight.

## Repeater

`<Repeater draggable>` gives each row a grip next to its Move buttons, when `reorderable` is on and the form isn't locked. A drag and the grip's keys go through the Repeater's own move, so the announcement and the `change` event are the same as for Move down. A nested Repeater's rows never join the outer one's drag. Without `draggable` nothing changes.

## Your own list

`useSortable({ ids, move, container?, itemSelector?, handleSelector?, disabled? })` adds pointer drag by a grip and the grip's arrow keys to any container of rows. It only calls `move(from, to)`; the order stays yours. Put a `SortableHandle` (a real button named "Reorder {label}") in each row and call `onGripKeydown(index, event)` from it.

## The Workbench navigator

The navigator keeps its own interactions:

- native drag on its options
- Alt+↑/↓ on the current item
- the row menu

It saves through the same transport: `Kit/Data/sortable` re-exports the Workbench's pure steps (`moveItem`, `createReorder`, `isConflict`), and `useWorkbench` re-exports `sendOrder` and `ReorderAction`, so existing imports keep working.
