<script setup lang="ts">
import { provide } from 'vue';
import Card from '@/components/Kit/Card/Card.vue';
import CollectionSection from '@/components/Kit/Layout/CollectionSection.vue';
import {
    collectionFrameKey,
    type CollectionSectionData,
} from '@/components/Kit/Layout/collectionSection';

/*
 * A CollectionSection on a kit Card (docs/collection-section.md): the same props, slots and descriptor, so swapping one for the other is only the name. It adds
 * the card and nothing else: the Card's surface, and a frame for the section's parts (the card's padding, a rule under the header and the tabs and over the
 * footer, and a body that fills the card, so its footer stays at the bottom while the body scrolls). Everything the section shows and does is the section's.
 * Attributes (a class, `data-*`) go on the card.
 */
const props = withDefaults(
    defineProps<{
        section: CollectionSectionData;
        level?: 2 | 3 | 4 | 5 | 6;
        headless?: boolean;
        minHeight?: string | null;
    }>(),
    { level: 3, headless: false, minHeight: null },
);
provide(collectionFrameKey, {
    root: 'h-full gap-0',
    header: 'min-h-12 border-b px-4 py-2',
    filters: 'border-b px-4 py-2',
    body: 'flex-1 p-4',
    footer: 'border-t px-4 py-2',
});
</script>
<template>
    <Card
        as="div"
        class="flex min-w-0 flex-col"
        data-collection-card
        :data-state="section.state ?? 'ready'"
    >
        <CollectionSection v-bind="props">
            <template v-for="(_, name) in $slots" #[name]="scope">
                <slot :name="name" v-bind="scope ?? {}" />
            </template>
        </CollectionSection>
    </Card>
</template>
