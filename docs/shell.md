# The app shell's top stack

The Workspace Layout (`pages/Workspace/Layout.vue`, the shell feature) pins its top stack: the environment edge outside production, the bar, and the trail bar under it when `breadcrumbs` is `bar`. The SubNav strip and a "Back to …" strip scroll with the page and are not part of it.

## `--shell-top`

The shell publishes the stack's height on the root element as `--shell-top`, in whole pixels. One ResizeObserver, owned by the shell, keeps it current: the trail bar coming and going, a host's bar growing at a narrow width, a theme changing the mark. It is written only when the height changes, is `0px` while nothing is pinned (a guest without `guestHeader`), and is removed when the shell unmounts. Pages read the variable instead of measuring `[data-shell-top]` themselves.

A row that should stay pinned just under the bar:

```vue
<div class="sticky top-(--shell-top) z-10 border-b bg-background">…</div>
```

In your own CSS, give it a fallback for pages outside the shell: `top: var(--shell-top, 0px)`.

## Scroll targets

`kit.css` gives `:target`, any `[id]:focus-visible` element and a focused `input`, `select` or `textarea` a `scroll-margin-top` of `calc(var(--shell-top, 0px) + 0.5rem)`. A skip link, a `#hash` link, keyboard focus and the scroll to the first invalid field after a failed save (`focusFirstInvalid`) all land below the bar rather than under it. Outside the shell the margin is the 0.5rem alone.

## Module navigation

`Registry::modulePage($module, $ability, $label, $suffix, …)` registers a module's pages; while one of them is current, `<Page>` draws them as tabs (or the Layout's `subNav="strip"` as the SubNav strip). A primary link is a module when its suffix is the module id.

**Two levels.** A page whose suffix is another module's id nests that module: `modulePage('admin', 'admin.read', 'Billing', 'billing')` beside `modulePage('billing', …)` makes Billing a tab in Admin's row, and Billing's own pages a second row under it while one of them is current. URLs do not change. The rules:

- A section's row keeps `modulePage` order and may mix plain pages and nested modules. Exactly one tab is current: the most specific match (longest suffix) wins, and a nested module matches only through its own pages, so it beats a plain page only when one of them matched.
- Abilities are inherited: the nested tab's own ability (the section's) gates everything under it, so a viewer with `billing.read` but not `admin.read` sees no Billing tab through Admin. The nested module's page abilities still filter its row, and a nested module with no page the viewer may open has no tab.
- Below `lg` the drawer lists the section's tabs under its link, with the nested module's pages expanded under its tab.
- Two levels at most: a nested module that nests another, or a cycle, throws at boot in local and testing.
- One-level apps see no change.

**The rule that switches it on is implicit.** Any module page whose suffix equals another module's id nests it. So that a suffix that only happens to match a module id is noticed, each nesting logs a warning at boot in local and testing, naming both modules (`module 'orders' nests module 'customers' through its page 'customers'`). If it is not intended, give the page a suffix that is not a module id.

**Pages outside the prefix.** A page sits under its module's suffix (`bookings/calendar` for `bookings`); one outside it logs a warning in local and testing, unless it nests a module or gives its own `active` (`modulePage('admin', …, 'Settings', 'settings', active: ['settings', 'settings/*'])`, current on every `settings/*` page).
