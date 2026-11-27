<script setup lang="ts">
import { Construction } from '@lucide/vue';
import Button from '@/components/Kit/Form/Button.vue';
import EmptyState from './EmptyState.vue';

/*
 * A page that is not built yet: a dashed panel saying so honestly. "Not built yet", what the page will do, an optional "Planned for {milestone}" (words only: the dot takes
 * the accent token, and no date or version is ever shown) and a few links to go meanwhile. It is a plain region labelled by its heading, with no live role: its content is
 * there when the page loads, so announcing it would be wrong. It takes no input.
 */
defineProps<{
    description: string;
    milestone?: string | null;
    links?: { label: string; url: string }[];
}>();
</script>
<template>
    <EmptyState
        :level="2"
        variant="first-use"
        align="start"
        :icon="Construction"
        title="Not built yet"
        class="max-w-xl"
        data-slot="placeholder"
    >
        <p>{{ description }}</p>
        <p
            v-if="milestone"
            class="mt-2 flex items-center gap-2"
            data-placeholder-milestone
        >
            <span
                class="bg-primary size-2 rounded-full"
                aria-hidden="true"
            />Planned for {{ milestone }}
        </p>
        <template v-if="links?.length" #action>
            <Button
                v-for="(link, index) in links"
                :key="link.url"
                :href="link.url"
                :variant="index === 0 ? 'secondary' : 'ghost'"
                >{{ link.label }}</Button
            >
        </template>
    </EmptyState>
</template>
