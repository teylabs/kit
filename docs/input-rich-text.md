# InputRichText

`InputRichText` (`Kit/Form/InputRichText`, exported from `@kit/ui`) is a rich-text field on tiptap. Its value is a `RichDocument`: a strict, portable subset of ProseMirror JSON that your app stores and your server can validate by the same rules.

```vue
<script setup lang="ts">
import { emptyDocument, Field, InputRichText, type FileIntent, type RichDocument } from '@kit/ui';
const notes = ref<RichDocument | null>(emptyDocument());
const upload = async (intent: FileIntent) => { /* upload intent.files, then add your own image blocks beside the field */ };
</script>
<template>
    <Field id="notes" label="Speaker notes" description="Shown only to you." :error="form.errors.notes">
        <InputRichText id="notes" v-model="notes" label="Speaker notes" described-by="notes-description" @file-intent="upload" />
    </Field>
</template>
```

Core lists the tiptap packages under `requires.npm` (`@tiptap/core`, `@tiptap/extension-code`, `@tiptap/extension-placeholder`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/vue-3`, `^3.32.0`). Add them to `package.json`; `kit:add` and `kit:update` flag any that are missing.

## Props and events

| Prop | Default | Notes |
| --- | --- | --- |
| `modelValue` | required | `RichDocument`, or `null` for the empty document |
| `label` | required | the textbox's accessible name. The toolbar is named "{label} formatting" |
| `id` | generated | the editable element's id: give it the Field's `id` |
| `describedBy` | none | the Field's description or error id. The field adds its own content error |
| `placeholder` | "Start typing…" | shown while the document is empty |
| `tools` | all, in order | which toolbar controls show, and in what order: `heading2`, `heading3`, `bold`, `italic`, `strike`, `code`, `link`, `bulletList`, `orderedList`, `blockquote`, `undo`, `redo` (`RICH_TEXT_TOOLS`) |
| `readOnly` | `false` | no toolbar, no edits; the content stays focusable and selectable |
| `disabled` | `false` | no edits, and out of the tab order |
| `privateLinks` | `null` | a RegExp for your app's own internal links (`/^app:\/\/record\/[0-9]+$/`), accepted only when given. Your server must enforce the same rule |

- `update:modelValue` sends the normalised `RichDocument` after each edit.
- `file-intent` sends `{ source: 'paste' | 'drop', files, position }` when files are pasted or dropped. The field never inserts them. Your app uploads them and adds its own image blocks, so no URL ever enters the document. A read-only or disabled field swallows them without an event.
- `content-error` sends `{ code: 'invalid-document', message }` when the document it's given is outside the subset. The field then locks and shows the message, rather than let the editor silently drop what it doesn't support.
- `focus`, `blur`, and an exposed `focus()`.

## The document

- **Blocks:** `paragraph`, `heading` (`attrs.level` 2 or 3 only), `blockquote`, `bulletList`, `orderedList` (`attrs.start`, 1 by default) and `listItem`, whose first child must be a paragraph.
- **Inline:** `text` with marks `bold`, `italic`, `strike`, `inlineCode` and `link` (`attrs.href`, `attrs.title`), and `hardBreak`.
- **Not supported:** code blocks, horizontal rules, underline, images, tables. Anything else (an unknown node, mark or attribute, a duplicate mark, an empty container, more than 20 levels or 10,000 nodes) is refused.

`emptyDocument()` is `{ type: 'doc', content: [{ type: 'paragraph' }] }`.

## Mirrored content: `allow`

Content mirrored from elsewhere (slides imported from a presentation tool) can carry an alignment and inline styles the strict subset refuses, which would lock the field. `allow` opts a field into preserving them. It's read when the field mounts and is none by default, so the subset is otherwise unchanged:

```vue
<InputRichText v-model="notes" label="Speaker notes" :allow="['textAlign', 'textStyle']" />
```

- **`textAlign`:** `attrs.textAlign` on a paragraph or heading, `left`, `center`, `right` or `justify` only.
- **`textStyle`:** a mark with `attrs` of `color`, `fontFamily` and `fontSize`, each checked strictly and never passed through as raw CSS.
  - `color` is `#rgb`, `#rrggbb`, `rgb(r, g, b)` or `rgba(r, g, b, a)`, with channels 0–255 and alpha 0–1.
  - `fontSize` is a number with `px` (1–200), `pt` (1–150), `em` or `rem` (0.1–12), or a bare number (above 0, up to 20, at most three decimals). A bare number is a unitless scale your app resolves itself, as mirrored decks store it. The field keeps it a number through the round trip, but doesn't size the text by it.
  - `fontFamily` is up to 10 comma-separated names (or your app's own tokens, such as `sans` or `display`) of letters, digits, spaces, hyphens and underscores, each bare or in one pair of matching quotes (`'Open Sans'`, `"Open Sans"`, as presentation tools write them): no semicolons, parentheses, `url(` or quotes inside a name.
  - `isSafeColor`, `isSafeFontSize` and `isSafeFontFamily` are exported, so a server can check the same rules.
- `families` limits `fontFamily` to your app's own names: with `:families="['sans', 'display']"`, a family is accepted only when every name in it is in that list (quoted or not). Anything else gives `content-error`. Without it, the character-class rule above stands. `isSafeFontFamily(value, allowed?)` takes the same list.
- Anything else, or a value outside these rules, is refused with `content-error`, as without `allow`. A style whose every value is cleared is dropped.

It adds no toolbar control: the field preserves and round-trips these; it doesn't edit them yet. Core lists `@tiptap/extension-text-align` and `@tiptap/extension-text-style` (`^3.32.0`) under `requires.npm`.

## Links

The Edit link control (or Ctrl/Cmd+K) opens a Link settings popover with Apply, Remove and Cancel. `isSafeLink(value, privateLinks?)` decides what's accepted:

- http(s) with a host and no credentials, `mailto:`, `tel:` and relative links;
- not any other scheme (`javascript:`, `data:`…), protocol-relative `//` links, whitespace, backslashes, or control characters, raw or percent-encoded.

Stored links are checked on load too. Links render with `rel="noopener noreferrer"`, don't open on click while editing, and aren't auto-linked from typing or pasting.

## Coverage

- `composer test:js` (`rich-text.test.mjs`) runs the document rules without tiptap: the round trip and its normalisation, every refusal, `isSafeLink`, and the file-intent decision. It also checks by source the editor's configuration, the content-error path, the link popover's refusal, and the toolbar's and textbox's accessible names, roles and states.
- `bin/typecheck-components.sh` compiles a host's use of it (`typecheck/fixtures/RichText.vue`), including that a level-1 heading and an unknown tool don't type-check.

Not covered in CI: no browser runs tiptap there, so typing, the toolbar commands, paste and drop events, and the popover's focus handling aren't exercised.
