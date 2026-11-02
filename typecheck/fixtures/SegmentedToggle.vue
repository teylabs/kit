<script setup lang="ts">
// SegmentedToggle's v-model, options (value, label, icon, disabled), size and label, as a host would use them for a view switch. Compiled by
// bin/typecheck-components.sh; never rendered.
import { LayoutGrid, List } from '@lucide/vue';
import { ref } from 'vue';
import SegmentedToggle, { type SegmentedToggleOption } from '@/components/Kit/Form/SegmentedToggle.vue';

const view = ref('slides');
const layout = ref<string>('grid');
const views: SegmentedToggleOption[] = [{ value: 'slides', label: 'Slides' }, { value: 'outline', label: 'Outline' }, { value: 'notes', label: 'Notes', disabled: true }];
</script>
<template>
    <SegmentedToggle v-model="view" :options="views" label="Editor view" />
    <SegmentedToggle v-model="layout" size="sm" label="Layout" :options="[{ value: 'grid', label: 'Grid', icon: LayoutGrid }, { value: 'list', label: 'List', icon: List, disabled: false }]" />
    <!-- @vue-expect-error size is 'default' or 'sm' -->
    <SegmentedToggle v-model="view" size="lg" :options="views" label="Editor view" />
    <!-- @vue-expect-error an option needs a label -->
    <SegmentedToggle v-model="view" :options="[{ value: 'slides' }]" label="Editor view" />
</template>
