<script setup lang="ts">
// MiddleTruncate's props and its helpers, as a host would use them for an attachment name, a link and a path. Compiled by bin/typecheck-components.sh;
// never rendered.
import { MiddleTruncate, middleTruncate, truncationKind, type MiddleTruncateKind, type MiddleTruncation } from '@kit/ui';

const kind: MiddleTruncateKind = 'url';
const cut: MiddleTruncation = middleTruncate('paraframe-brand-guidelines-final.pdf', 120, (text) => text.length * 7, { kind: 'file', minTail: 8 });
const read: 'file' | 'path' | 'url' = truncationKind('https://example.com/a.pdf');
</script>
<template>
    <MiddleTruncate value="paraframe-brand-guidelines-final.pdf" />
    <MiddleTruncate value="https://example.com/a/b/final.pdf" :kind="kind" :min-tail="12" />
    <span>{{ cut.head }}{{ cut.tail }}{{ read }}</span>
    <!-- @vue-expect-error value is required -->
    <MiddleTruncate kind="file" />
    <!-- @vue-expect-error kind is file, path, url or auto -->
    <MiddleTruncate value="a.pdf" kind="folder" />
</template>
