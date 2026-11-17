# Markdown: TextMarkdown and renderMarkdown

`<TextMarkdown>` renders Markdown a person wrote (a note, a description, a comment) as safe HTML, styled as [TextProse](text.md) styles its slot. `renderMarkdown(source, options)` is the function behind it, for an app that needs the HTML string itself. Both are exported from `@kit/ui`. The text is GitHub-flavoured: tables, strikethrough, autolink literals, task lists and footnotes.

```vue
<script setup lang="ts">
import { TextMarkdown } from '@kit/ui';
</script>

<template>
    <TextMarkdown :source="note.body" />
    <TextMarkdown :source="release.notes" :heading-level="2" new-tab size="base" />
</template>
```

| Prop | Default | |
| --- | --- | --- |
| `source` | `null` | The Markdown. Empty or null renders an empty block. |
| `headingLevel` | `3` | The level a `#` heading renders at; `##` is one deeper, capped at h6. User content never emits an h1 or h2 unless you lower it. |
| `newTab` | `false` | Links to other pages open in a new tab (`target="_blank"`). In-page footnote links never do. |
| `size` | `sm` | sm, base or lg, as TextProse. |

## What is safe

Two layers, so neither has to be perfect alone:

1. **micromark** parses the Markdown with its safe defaults. Raw HTML in the source (`<script>`, `<img onerror>`, `<iframe>`, `<svg>`, a `style` attribute) is escaped and shows as text; it is never passed through. A link URL outside `http`, `https`, `mailto`, `irc`, `ircs` and `xmpp`, or an image URL outside `http` and `https`, is dropped.
2. **DOMPurify** sanitizes the result against an allowlist of the elements micromark emits (paragraphs, headings, lists, quotes, code, tables, links, images, `del`, `sup`, the footnotes section and a task-list `input`), with no `style`, no `on*` handler, no `data-*` attribute and no URL outside `http`, `https`, `mailto`, an `#anchor` or a relative path. `javascript:`, `vbscript:` and `data:` (images too) are refused, including entity and whitespace-obfuscated forms such as `jav&#x09;ascript:`.

Then every link to another page gets `rel="noopener noreferrer nofollow"`, a task-list checkbox is kept only as a disabled checkbox named by its item, and footnote ids carry a per-render prefix, so two notes on one page never collide.

It needs a DOM. Without one (server rendering) `renderMarkdown` fails closed: it returns the source as escaped text in one paragraph, and the browser renders the Markdown on hydration.

## Dependencies

Core lists `micromark` `^4.0.3`, `micromark-extension-gfm` `^3.0.0` and `dompurify` `^3.4.17` under `requires.npm`. Add them to `package.json`; the kit installs no npm packages. Bundled and minified, micromark with GFM is about 22 kB gzipped and DOMPurify about 12 kB. An app that rendered Markdown with its own micromark and sanitizer can drop that code and use these.

## Checks

`tests/js/markdown.test.mjs` runs an XSS corpus (script tags, event handlers, script URLs in plain, mixed-case, entity and whitespace forms, `iframe`, `object`, `embed`, SVG and MathML payloads, `style`, `data:` URLs, HTML inside Markdown and Markdown inside HTML, footnotes and tables carrying payloads) and the GFM features through renderMarkdown on jsdom. The browser job (`browser/run.mjs`, `?markdown`) renders payloads that set `window.pwned` and checks it stays undefined, and checks the prose styling, headings, `rel`, no page overflow on a phone, and axe in light and dark.
