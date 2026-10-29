<script setup lang="ts">
// InputTags with each prop. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import InputTags from '@/components/Kit/Form/InputTags.vue';

const tags = ref<string[]>(['launch']);
</script>
<template>
    <InputTags v-model="tags" />
    <InputTags v-model="tags" name="tags" :suggestions="['launch', 'beta']" :separators="[',', '|']" :max="5" :max-length="24" pattern="[a-z0-9-]+" :normalize="(tag) => tag.toLowerCase()" locale="fr-CA" placeholder="Add a tag" :messages="{ duplicate: 'Déjà là' }" id="tags" aria-describedby="tags-help" />
    <InputTags v-model="tags" disabled readonly invalid :max="null" :max-length="null" :pattern="null" :normalize="null" />
</template>
