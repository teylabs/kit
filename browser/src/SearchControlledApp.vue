<script setup lang="ts">
import { computed, ref } from 'vue';
import SearchBox from '@/components/Kit/Search/SearchBox.vue';
import type { SearchGroup } from '@/components/Kit/Search/search';

// A controlled SearchBox, for run.mjs (?search-controlled): the host filters its own list and passes the results; select and submit are recorded. No url, no fetch.
const query = ref('');
const all = [
    { id: '1', label: 'Alder Street', url: null, description: 'Case 1', meta: null },
    { id: '2', label: 'Birch Lane', url: null, description: 'Case 2', meta: null },
];
const results = computed<SearchGroup[]>(() => [{ key: 'cases', label: 'Cases', results: all.filter(c => c.label.toLowerCase().includes(query.value.toLowerCase())) }]);
const events = ref<string[]>([]);
</script>
<template>
    <main class="bg-background text-foreground min-h-screen p-4">
        <h1>Find a case</h1>
        <SearchBox v-model:query="query" :results="results" label="Find a case" @select="o => events.push(`select:${o.label}`)" @submit="t => events.push(`submit:${t}`)" />
        <p data-events>{{ events.join(',') }}</p>
    </main>
</template>
