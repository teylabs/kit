<script setup lang="ts">
import { ref } from 'vue';
import Field from '@/components/Kit/Form/Field.vue';
import InputTags from '@/components/Kit/Form/InputTags.vue';

// Tags for run.mjs (?tags): free text with suggestions, a three-tag limit and a pattern, for axe, separators, Enter, paste, Backspace and refusals.
const tags = ref<string[]>([]);
</script>
<template>
    <main class="max-w-md p-4">
        <h1>Post</h1>
        <form class="grid gap-4" @submit.prevent>
            <Field id="tags" label="Tags">
                <InputTags id="tags" v-model="tags" name="tags" :suggestions="['launch', 'beta', 'design']" :max="3" pattern="[a-z0-9 -]+" :normalize="(tag) => tag.toLowerCase()" />
            </Field>
        </form>
        <output data-test-model>{{ JSON.stringify(tags) }}</output>
    </main>
</template>
