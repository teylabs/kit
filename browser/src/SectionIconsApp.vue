<script setup lang="ts">
import SectionIcon from '@/components/Kit/Layout/SectionIcon.vue';

// SectionIcon's allow-list (?section-icons): each name the kit knows, then one it does not, which is the generic Settings icon.
const names = [
    'receipt',
    'chart-column',
    'calendar',
    'newspaper',
    'file-signature',
    'hand-heart',
    'settings',
    'no-such-icon',
];
</script>
<template>
    <ul class="flex gap-4 p-8">
        <li v-for="name in names" :key="name" :data-icon="name">
            <SectionIcon :name="name" />
        </li>
    </ul>
</template>
