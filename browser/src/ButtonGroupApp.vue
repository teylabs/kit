<script setup lang="ts">
import ButtonGroup from '@/components/Kit/Action/ButtonGroup.vue';
import { Button } from '@/components/ui/button';

// ButtonGroup's segments, for run.mjs (?button-group, `&dark` for the dark tokens), invented labels: two page actions joined into one outline with a divider
// between them, the same pair beside a split menu segment, and `appearance="text"`, which has no dividers. The probe carries the outline token's colour.
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-8 text-foreground">
        <span data-outline-probe class="sr-only border border-outline" aria-hidden="true"></span>
        <section data-pair>
            <ButtonGroup weight="semibold">
                <Button variant="outline">+ New booking</Button>
                <Button variant="outline">Repeat a booking</Button>
            </ButtonGroup>
        </section>
        <section data-split>
            <ButtonGroup weight="semibold" menu-label="More booking actions">
                <Button variant="outline">+ New booking</Button>
                <Button variant="outline">Repeat a booking</Button>
                <template #menu>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm">Export</button>
                </template>
            </ButtonGroup>
        </section>
        <section data-text>
            <ButtonGroup appearance="text">
                <Button variant="ghost">Edit</Button>
                <Button variant="ghost">Duplicate</Button>
            </ButtonGroup>
        </section>
    </main>
</template>
