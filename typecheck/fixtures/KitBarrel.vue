<script setup lang="ts">
// The bare `@/components/Kit` barrel, as docs/kit-components.md maps it to the package's index.ts. Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import { Field, SegmentedToggle } from '@/components/Kit';

const view = ref('list');
</script>
<template>
    <Field id="view" label="View">
        <SegmentedToggle v-model="view" label="View" :options="[{ value: 'list', label: 'List' }, { value: 'grid', label: 'Grid' }]" />
    </Field>
</template>
