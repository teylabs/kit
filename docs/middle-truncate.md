# Long file names, paths and URLs: MiddleTruncate

`MiddleTruncate` (from `@kit/ui`) shows a long file name, path or URL cut in the middle, so its start and its tail stay visible: `paraframe-brand…final.pdf`, not `paraframe-brand-guidel…`. The end of a name is usually what tells two files apart (`-v2.pdf`, `-final.pdf`), and a CSS end ellipsis hides it.

```vue
<MiddleTruncate :value="file.name" />
<MiddleTruncate :value="link.url" kind="url" />
<MiddleTruncate :value="export.path" kind="path" :min-tail="12" />
```

## What is kept

| `kind` | Head | Tail |
| --- | --- | --- |
| `file` | the start of the name | the whole extension (`.pdf`, `.tar.gz`) |
| `path` | the root (`/`, `C:`) and what follows | the last segment, with its extension |
| `url` | the scheme and the start of the host | the last path segment; a query or fragment is cut first, down to its `?` or `#` |
| `auto` (the default) | read from the value: a scheme (`https://`) or a leading `www.` is a URL, a `/` or `\` a path, anything else a file name | |

Room left over goes two thirds to the head, one third to the tail. `min-tail` keeps at least that many characters at the end. A value that fits is shown whole, with no ellipsis. In a box too narrow for one character, the ellipsis and the extension, that much is shown and clipped.

Characters are graphemes: an emoji sequence, a flag or a letter with its accents is never split. Right-to-left text is cut in logical order, and the element takes its direction from the value (`dir="auto"`).

## Width, not a character count

It fits the width it is given. It measures the text with a canvas in the element's own font (size, weight, family and letter spacing), and measures again when the box resizes (a `ResizeObserver`) or a web font finishes loading. After it paints, any overflow left by the browser's own shaping (an Arabic letter's joining form at the cut, kerning across it) is taken off the width and the value is cut again.

It is a block element: give it a bounded width, as a flex child with `min-w-0`, in a table cell, or in a box of fixed width.

## Server rendering

On the server, and in the browser until it mounts, it renders the whole value with a CSS end ellipsis (`truncate`). It starts no observer or timer and touches no DOM until `onMounted`, and stops observing when it unmounts.

## Accessibility, selection and copy

The cut text stays in the element at zero size, and the drawn ellipsis is `aria-hidden`. So the accessible name (of a link around it, say), a selection and a copy are all the whole value, never the shortened one. `title` shows the whole value on hover.

## Without a component

`middleTruncate(value, width, measure, { kind, minTail })` returns `{ head, middle, tail, end, truncated }`, where `measure(text)` is the width of a string in the same unit as `width`. `head + middle + tail + end` is always the whole value; show `head`, an ellipsis when `middle` is cut, `tail`, and an ellipsis when `end` is. `truncationKind(value)` is how `auto` reads a value.

Tests: `tests/js/middle-truncate.test.mjs` covers the truncation math (every width keeps the extension and fits, graphemes stay whole, URLs, paths, RTL, `minTail`) and the component's markup. The browser job (`?middle-truncate`) resizes each row from 120 to 400px with no overflow, reads the accessible name and a copied selection, runs axe in light and dark, and renders the component under Vue's server renderer.
