<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PasskeyOffer from '@/components/Kit/Feedback/PasskeyOffer.vue';
import Field from '@/components/Kit/Form/Field.vue';
import PasswordInput from '@/components/Kit/Form/PasswordInput.vue';
import RowSaveStatus from '@/components/Kit/Form/RowSaveStatus.vue';
import InputRichText from '@/components/Kit/Form/InputRichText.vue';
import type { RichDocument } from '@/components/Kit/Form/richText';
import SearchBox from '@/components/Kit/Search/SearchBox.vue';

// Rendered contracts, for run.mjs (?a11y3): PasswordRules under a PasswordInput (a shared 10-character rule), the passkey offer (a client that refuses, so
// Add fails), RowSaveStatus states, SearchBox, and rich-text image uploads whose callback returns a src outside the allowed pattern.
const props = usePage().props as Record<string, unknown>;
props.kitPassword = { min: 10, uncompromised: true };
props.passkeyOffer = { options_url: '/passkeys/options', store_url: '/passkeys', dismiss_url: '/passkeys/offer' };
const password = ref('');
const doc = ref<RichDocument | null>({ type: 'doc', content: [{ type: 'paragraph' }] } as RichDocument);
const imageErrors = ref(0);
const upload = async () => ({ src: 'https://evil.example/x.png', alt: '' });
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Account</h1>
        <PasskeyOffer />
        <Field id="password" label="Password"><PasswordInput id="password" v-model="password" rules /></Field>
        <section data-row-saved><RowSaveStatus state="saved" /></section>
        <section data-row-failed><RowSaveStatus state="failed" reason="The server refused." /></section>
        <section data-search><SearchBox url="/search" /></section>
        <section data-images><InputRichText id="body" v-model="doc" label="Body" :allow="['image']" :image-sources="/^\/media\//" :upload-image="upload" @image-error="imageErrors++" /></section>
        <p data-image-errors>{{ imageErrors }}</p>
        <section data-no-images><InputRichText id="plain" v-model="doc" label="Plain" /></section>
    </main>
</template>
