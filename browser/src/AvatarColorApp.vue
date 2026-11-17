<script setup lang="ts">
import Avatar from '@/components/Kit/Layout/Avatar.vue';
import NoteCard from '@/components/Kit/Comment/NoteCard.vue';
import { avatarColor, avatarPalette } from '@/components/Kit/Layout/avatarColor';

// One colour per person (?avatar-color, =dark under .dark): people by user id at every size, a stored colour, the neutral disc with neither, and a note card
// whose author carries the server's `avatar_color`, which must match the person's disc above it.
if (new URLSearchParams(window.location.search).get('avatar-color') === 'dark') document.documentElement.classList.add('dark');
const people = [
    { id: 1, initials: 'AL' },
    { id: 2, initials: 'BK' },
    { id: 3, initials: 'MR' },
    { id: 4, initials: 'JT' },
    { id: 5, initials: 'SK' },
    { id: 6, initials: 'PN' },
    { id: 7, initials: 'DW' },
    { id: 8, initials: 'EH' },
];
const sizes = ['xs', 'sm', 'md', 'lg', 'xl'] as const;
// The palette with its initials, then light stored colours that take dark initials (amber, yellow, sky), on the page and on a card.
const swatches = [...avatarPalette, '#f59e0b', '#fde047', '#7dd3fc'];
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-sm text-foreground">
        <h1 class="mb-3 font-semibold">People</h1>
        <ul class="mb-4 flex flex-wrap gap-2" data-people>
            <li v-for="person in people" :key="person.id" :data-person="person.id"><Avatar :initials="person.initials" :identity="person.id" /></li>
            <li data-person="stored"><Avatar initials="ST" :identity="9" color="#0F766E" /></li>
            <li data-person="neutral"><Avatar initials="NN" /></li>
            <li data-person="no-ring"><Avatar initials="NR" :identity="2" :ring="false" /></li>
        </ul>
        <div class="mb-4 flex flex-wrap items-center gap-2" data-sizes>
            <Avatar v-for="size in sizes" :key="size" initials="AL" :identity="1" :size="size" />
        </div>
        <div class="mb-4 grid gap-2" data-palette>
            <ul v-for="ground in ['page', 'card']" :key="ground" :class="['flex flex-wrap gap-2 rounded-lg p-2', ground === 'card' && 'border border-border bg-card [--avatar-ring:var(--card)]']" :data-ground="ground">
                <li v-for="swatch in swatches" :key="swatch" :data-swatch="swatch"><Avatar initials="AB" :color="swatch" size="lg" /></li>
            </ul>
        </div>
        <NoteCard class="max-w-md" data-note :author="{ name: 'Ana Lopez', initials: 'AL', avatar_color: avatarColor(1) }">Called the shop; parts arrive Thursday.</NoteCard>
    </main>
</template>
