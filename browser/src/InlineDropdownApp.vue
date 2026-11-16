<script setup lang="ts">
import { ArrowDownUp } from '@lucide/vue';
import { ref } from 'vue';
import InputSelect from '@/components/Kit/Form/InputSelect.vue';
import InlineDropdown from '@/components/Kit/Menu/InlineDropdown.vue';

// InlineDropdown and the select-only InputSelect (?inline-dropdown, `&dark`), for run.mjs: a sort order chosen by state (window.kitPicked records the
// emits), a menu of links with sections, and a five-option InputSelect (select-only by default) beside a searchable one.
const order = ref('touched');
const picked: string[] = [];
(window as unknown as { kitPicked: string[] }).kitPicked = picked;
const orders = [
    { key: 'touched', label: 'Last touched' },
    { key: 'newest', label: 'Newest' },
    { key: 'due', label: 'Due soonest' },
];
const pages = [
    { key: 'overview', label: 'Overview', href: '/@acme/cases/7', section: 'This case' },
    { key: 'details', label: 'Details', href: '/@acme/cases/7/details', section: 'This case' },
    { key: 'case-8', label: 'A-8 Roof survey', href: '/@acme/cases/8', section: 'Recent cases' },
];
const status = ref<string | null>('actual');
const statuses = ['Actual', 'Exempt', 'Not actual', 'Broken', 'Unknown'].map((label) => ({ value: label.toLowerCase(), label }));
const who = ref<string | null>(null);
const people = ['Ada Lovelace', 'Grace Hopper', 'Frances Allen'].map((label) => ({ value: label, label }));
</script>
<template>
    <main class="bg-background text-foreground grid min-h-screen max-w-xl content-start gap-6 p-4">
        <h1 class="text-lg font-medium">Inline dropdown</h1>
        <p class="flex items-center justify-between gap-2" data-case="sort">
            <span class="font-medium">Ongoing cases</span>
            <InlineDropdown
                v-model="order"
                :items="orders"
                label="Sort by"
                :subject="{ title: 'Sort by', caption: 'Ongoing cases' }"
                :icon="ArrowDownUp"
                align="end"
                @select="picked.push($event)"
            />
        </p>
        <p data-case="links">
            Valuation ·
            <InlineDropdown model-value="overview" :items="pages" label="Go to" subject="A-7 Boiler check" />
        </p>
        <div class="grid gap-2" data-case="select-only">
            <label for="status" class="text-sm font-medium">Odometer status</label>
            <InputSelect id="status" v-model="status" :options="statuses" />
        </div>
        <div class="grid gap-2" data-case="searchable">
            <label for="who" class="text-sm font-medium">Assignee</label>
            <InputSelect id="who" v-model="who" :options="people" searchable />
        </div>
    </main>
</template>
