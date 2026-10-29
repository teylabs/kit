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

## Links

The Edit link control (or Ctrl/Cmd+K) opens a Link settings popover with Apply, Remove and Cancel. `isSafeLink(value, privateLinks?)` decides what's accepted:

- http(s) with a host and no credentials, `mailto:`, `tel:` and relative links;
- not any other scheme (`javascript:`, `data:`…), protocol-relative `//` links, whitespace, backslashes, or control characters, raw or percent-encoded.

Stored links are checked on load too. Links render with `rel="noopener noreferrer"`, don't open on click while editing, and aren't auto-linked from typing or pasting.

## Coverage

- `composer test:js` (`rich-text.test.mjs`) runs the document rules without tiptap: the round trip and its normalisation, every refusal, `isSafeLink`, and the file-intent decision. It also checks by source the editor's configuration, the content-error path, the link popover's refusal, and the toolbar's and textbox's accessible names, roles and states.
- `bin/typecheck-components.sh` compiles a host's use of it (`typecheck/fixtures/RichText.vue`), including that a level-1 heading and an unknown tool don't type-check.

Not covered: no browser runs tiptap here, so typing, the toolbar commands, paste and drop events, and the popover's focus handling aren't exercised.
