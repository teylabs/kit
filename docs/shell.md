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
