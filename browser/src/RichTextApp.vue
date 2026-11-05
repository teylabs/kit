<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputRichText from '@/components/Kit/Form/InputRichText.vue';
import type { RichDocument } from '@/components/Kit/Form/richText';

// InputRichText in a real browser, for run.mjs (?rich-text): a Field with a description, a caller's describedby and required; a stored document whose links
// are safe; one whose link is javascript: (it must lock, never render the link); and an invalid document, which must lock the field and emit content-error.
const doc = ref<RichDocument | null>({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'safe', marks: [{ type: 'link', attrs: { href: 'https://example.com' } }] }] }] } as RichDocument);
const unsafe = ref<RichDocument | null>({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'bad', marks: [{ type: 'link', attrs: { href: 'javascript:window.kitPwned=1' } }] }] }] } as RichDocument);
const unsafeErrors = ref<string[]>([]);
const broken = ref({ type: 'doc', content: [{ type: 'mystery', content: [] }] } as unknown as RichDocument);
const errors = ref<string[]>([]);
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Notes</h1>
        <p id="caller-hint">Markdown shortcuts work.</p>
        <Field id="notes" label="Notes" description="Shown to the client."><InputRichText id="notes" v-model="doc" label="Notes" aria-describedby="caller-hint" required class="mt-2" /></Field>
        <section data-unsafe><InputRichText id="unsafe" v-model="unsafe" label="Unsafe" @content-error="e => unsafeErrors.push(e.code)" /></section>
        <p data-unsafe-errors>{{ unsafeErrors.join(',') }}</p>
        <section data-broken><InputRichText id="broken" v-model="broken" label="Broken" @content-error="e => errors.push(e.code)" /></section>
        <p data-errors>{{ errors.join(',') }}</p>
    </main>
</template>
