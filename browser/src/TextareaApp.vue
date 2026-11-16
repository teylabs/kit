<script setup lang="ts">
import { ref } from 'vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';

// Textarea's own props, for run.mjs (?textarea): an uncontrolled one with defaultValue and hasFocus, an autosize one, a v-model one (defaultValue ignored), a plain one.
const bound = ref('From the model');
</script>
<template>
    <main class="bg-background text-foreground min-h-screen p-4">
        <h1>Notes</h1>
        <label>Uncontrolled <Textarea data-uncontrolled name="first" default-value="Starting text" has-focus /></label>
        <label>Autosize <Textarea data-autosize-box autosize /></label>
        <label>Bound <Textarea data-bound v-model="bound" default-value="Ignored" /></label>
        <label>Plain <Textarea data-plain /></label>
        <p data-bound-value>{{ bound }}</p>
    </main>
</template>
