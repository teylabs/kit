# DescriptionList

`<DescriptionList>` shows term and description pairs, such as a record's details or a connection's metadata. The core feature owns it in `Kit/Layout/DescriptionList.vue`.

```vue
<script setup lang="ts">
import { DescriptionList } from '@/components/Kit';
</script>
<template>
    <DescriptionList :items="[
        { term: 'Plan', description: 'Team' },
        { term: 'Workspace ID', description: workspace.id, copy: true },
        { term: 'Renews', description: null },
    ]" />
</template>
```

- It renders a real `<dl>`: each pair is a `<div>` holding one `<dt>` and one `<dd>`.
- `items` gives the pairs. For pairs you write yourself, use the default slot and keep its children as `<div><dt/><dd/></div>`. The `description` slot (`{ item }`) renders one item's value, such as a badge or a link.
- `layout`:
  - `inline` (the default) puts each term beside its description from `sm` up, and stacks them on a phone;
  - `stacked` always stacks.
- A blank value (`null` or `''`) shows `empty`, an em dash by default. A long value wraps rather than overflowing.
- `copy: true` on an item, or `copyable` on the whole list, adds a button labelled "Copy {term}" that copies the value. The result, copied or not, is spoken once through a polite status beside it. A blank value has no copy button.

Tailwind utilities and the kit's tokens only.

## Coverage

`composer test:js` (`description-empty.test.mjs`) checks the markup, the layouts, the copy button and its status, and the exports, by source. Not covered: no browser renders it, so copying and the phone layout aren't exercised.
