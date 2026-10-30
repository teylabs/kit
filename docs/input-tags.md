# InputTags

`<InputTags>` takes free-text tags as chips: labels on a post, skills on a profile, keywords on a document. It's part of core and exported from the kit barrel. It's built on `InputSelect` (multiple and creatable), so the combobox, the chips with their "Remove …" buttons, the keyboard and the live announcements are the ones you already have. There are no new dependencies.

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { Field, InputTags } from '@kit/ui';

const tags = ref<string[]>([]);
</script>

<template>
    <Field id="tags" label="Tags">
        <InputTags id="tags" v-model="tags" name="tags" :suggestions="['launch', 'beta']" :max="10" :max-length="32" pattern="[a-z0-9 -]+" :normalize="(tag) => tag.toLowerCase()" />
    </Field>
</template>
```

- **Adding.** A separator (`separators`, `,` and `;` by default) or Enter adds what was typed. Enter takes a suggestion only after the arrow keys move to it, so typing "des" and pressing Enter adds "des", not "design". Pasting text that holds separators, line breaks or tabs (a column from a spreadsheet) adds each piece. An IME composition never commits a tag halfway.
- **Removing.** Each chip has a "Remove …" button. On an empty field the first Backspace selects the last chip and announces it, and the second removes it, as in every kit multi-select.
- **Tidying.** Surrounding space is trimmed and inner runs collapsed, then your `normalize` runs (lowercase, strip a leading `#`).
- **Refusals.** A duplicate (the same tag ignoring case in the locale), a tag longer than `maxLength` characters, one that doesn't match `pattern` (a regular expression the whole tag must match) or one past `max` is refused, never trimmed to fit. The reason shows under the field, announced politely and on the input's `aria-describedby`. The server validates again.
- **Suggestions.** `suggestions` are offered as options and filtered locally as you type. Chosen tags leave the list.
- **The value.** A `string[]`, submitted as `<name>[]`.
- **Strings.** The refusal reasons are the `tags.*` keys in the kit's `ui` lang file, shared as `kitUi`, and `messages` overrides them per use. The combobox strings are InputSelect's `select.*`.
- **Attributes.** Caller attributes land on the input, so `id` pairs it with its `Field` label, and your own `aria-describedby` is kept beside the refusal's.

`composer test:js` (`tests/js/tags.test.mjs`) covers splitting, tidying, duplicates in the locale, the limits and their messages. The browser run (`browser/run.mjs`, `?tags`) covers axe, separators, Enter on typed text and on a suggestion, a multi-line paste, the two-step Backspace and each refusal. The adoption inventory's `form.tags` surface counts a `ChipList` as display, not as a tag input.
