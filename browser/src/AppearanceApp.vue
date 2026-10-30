<script setup lang="ts">
import { useAppearance } from '@/components/Kit/Appearance/useAppearance';

// useAppearance for run.mjs (?appearance): main.ts runs initializeTheme() before mount, as a host's app.ts does, so storage that throws must not stop the mount.
const { appearance, updateAppearance } = useAppearance();
</script>
<template>
    <main class="min-h-screen bg-background p-4 text-foreground">
        <h1>Appearance</h1>
        <p data-appearance>{{ appearance }}</p>
        <button type="button" data-dark @click="updateAppearance('dark')">Dark</button>
    </main>
</template>
