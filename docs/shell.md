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

**A module with no primary link.** A module reached from a landing page rather than the bar (Billing, linked from Admin's landing) still gets its row. When no primary link is current, the shell picks the module whose page matches the route, through its prefix or its own `active`, and draws that module's row with the most specific page current. Abilities still filter the row; the row is labelled with the module's own landing page (the page whose suffix is the module id), else its id. A module under a primary link, and nested modules, behave as above.

**Levels: tabs, pills, sidebar.** The nested-navigation rule is tabs, then pills (2 to 5 sub-pages), then a sidebar (about 8 or more pages, or any grouping), then a hub landing (about 15 or more), then a breakout. On phones, each level becomes a select.

- **Pills.** A nested module's row (Front desk › Sales: Sales, Café menu) is drawn as a pill row under the tabs (`PageTabs variant="secondary"`). This is never a third row and never a filter. Every nested module is drawn this way; the data is the same `nested` group as before.
- **Sidebar.** A module whose pages carry a `group` (`modulePage('billing', …, 'Invoices', 'billing/invoices', group: 'Invoicing')`) is a sidebar module. `<Page>` frames its content with SettingsFrame's sidebar: one labelled group per `group`, in page order, with ungrouped pages first. It draws no tab row. Abilities and "most specific page current" work as for tabs. Nothing switches automatically: an ungrouped module with many pages stays tabs, and grouping is how a module opts in. On phones the sidebar is one select, with the groups as option groups. Below `lg`, the drawer lists the pages under the module's current primary link, under their group headings. A module page takes an optional `icon` (`modulePage(…, icon: 'receipt')`), a name from SectionIcon's allow-list (settings-section.md), drawn in its sidebar item, its tab or its pill; a page without one draws no icon, not the gear, and a name outside the list stops the boot in local and testing. A nested module is never a sidebar, because that would make a third tier. Its `group`s are ignored, and the boot check in local and testing warns, naming both modules.
- A sidebar module can be a with-trail breakout (HC's Billing): the trail, then the banded strip, then the sidebar frame.

**Module width.** `Registry::moduleWidth('billing', 'lg')` declares a module's page width once. Each of its tab pages' `<Page>` takes it (from `layout.width`) unless the page sets a width prop, which wins. Sibling tabs therefore line up without each repeating the width. A nested module's width applies on its pages; otherwise the section's applies. With none declared, nothing changes.

## Breakout pages

A breakout page (a route's `->defaults('kit.breakout', true)`, or a ViewModel's `breaksOut()`) shows a banded "Back to <parent>" strip, derived from its breadcrumb parent, in place of the trail. With `'with-trail'` instead of `true` (from the route default or `breaksOut()`), it keeps the whole trail as well, with the strip under it, so a deep page can still go straight to any ancestor.

On a module's own tab page, the strip skips every ancestor crumb that is a page of that same module, because the tab row already says where you are. With a trail of Home › Admin › Billing › Invoices, both `/billing` and `/billing/invoices` read "Back to Admin", never "Back to Billing" or a link to the page itself. A deeper page that is not a tab (an invoice, `/billing/invoices/10C-0001`) keeps its parent: "Back to Invoices". The trail itself stays the app's own; the kit registers no crumbs.

## The Settings link

The bar shows the workspace's Settings link (to the settings landing, when the viewer can open any section) where `kit.shell.settings_link` says: `'primary'` among the pages (the default), `'menu'` in the account menu, or `false` nowhere, for an app that places Settings itself, such as a module tab (`modulePage('admin', …, 'Settings', 'settings', active: ['settings', 'settings/*'])`). Only the link moves: the settings pages, the workspace landing and the switcher's "Workspace settings" stay.
