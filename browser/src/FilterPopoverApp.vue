<script setup lang="ts">
import FilterPopover from '@/components/Kit/Table/FilterPopover.vue';
import type { FilterDescriptor } from '@/components/Kit/Table/filters';

if (new URLSearchParams(window.location.search).get('filter-popover') === 'dark') document.documentElement.classList.add('dark');

// FilterPopover for run.mjs (?filter-popover, `=dark` for the dark tokens): TableQuery-shaped declarations (Location and Status selects, a one-option Site
// select that hides, a room search narrowing rooms grouped by floor). The stand-in router logs each GET to window.kitGets.
const floors = { 'Ground floor': ['Lobby', 'Atrium', 'Studio A', 'Studio B'], 'First floor': ['Boardroom', 'Library', 'Room 101', 'Room 102', 'Room 103'], 'Second floor': ['Room 201', 'Room 202', 'Room 203', 'Lounge', 'Terrace'] };
const rooms = Object.entries(floors).flatMap(([group, names]) => names.map(label => ({ value: label.toLowerCase().replace(/\s+/g, '-'), label, group })));
const filters: FilterDescriptor[] = [
    { key: 'location', label: 'Location', options: [{ value: 'north', label: 'North campus' }, { value: 'south', label: 'South campus' }] },
    { key: 'status', label: 'Status', default: 'open', options: [{ value: 'open', label: 'Open' }, { value: 'held', label: 'On hold' }, { value: 'closed', label: 'Closed' }] },
    { key: 'site', label: 'Site', options: [{ value: 'main', label: 'Main site' }] },
    { key: 'room-search', label: 'Search rooms', kind: 'search', narrows: 'rooms', options: [] },
    { key: 'rooms', label: 'Rooms', kind: 'multi', options: rooms },
];
</script>
<template>
    <main class="bg-background text-foreground min-h-dvh p-4">
        <div class="flex items-center gap-2" data-test-toolbar>
            <h1 class="text-xl font-semibold">Bookings</h1>
            <div class="ms-auto flex items-center gap-2">
                <FilterPopover :filters="filters" resettable />
                <button type="button" class="rounded-md border px-2 py-1 text-sm" data-test-after>After</button>
            </div>
        </div>
    </main>
</template>
