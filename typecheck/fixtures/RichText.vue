<script setup lang="ts">
// InputRichText in a Field, with a chosen toolbar, a private-link pattern and the host's file upload, as a host would use it. Compiled by
// bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputRichText from '@/components/Kit/Form/InputRichText.vue';
import { emptyDocument, type ContentError, type FileIntent, type RichDocument } from '@/components/Kit/Form/richText';

const notes = ref<RichDocument | null>(emptyDocument());
const body = ref<RichDocument | null>({ type: 'doc', content: [{ type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Agenda', marks: [{ type: 'bold' }] }] }, { type: 'bulletList', content: [{ type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Budget', marks: [{ type: 'link', attrs: { href: '/budget' } }] }] }] }] }] });
// @ts-expect-error headings are level 2 or 3 only
const h1: RichDocument = { type: 'doc', content: [{ type: 'heading', attrs: { level: 1 } }] };
const upload = (intent: FileIntent) => intent.files.map(file => file.name);
const failed = ref<ContentError | null>(null);
</script>
<template>
    <Field id="notes" label="Speaker notes" description="Shown only to you.">
        <InputRichText id="notes" v-model="notes" label="Speaker notes" described-by="notes-description" placeholder="Add notes…" />
    </Field>
    <InputRichText v-model="body" label="Body" :tools="['bold', 'italic', 'link', 'undo', 'redo']" :private-links="/^app:\/\/record\/[0-9]+$/" @file-intent="upload" @content-error="error => (failed = error)" />
    <InputRichText :model-value="body" label="Body" read-only />
    <span v-if="h1">{{ failed?.message }}</span>
</template>
