<script setup lang="ts">
import { computed } from 'vue';
import SeeAllLink from '@/components/Kit/Action/SeeAllLink.vue';
import { seeAllPlacement, type SeeAll } from '@/components/Kit/Action/seeAll';
import Card from './Card.vue';
import CardBody from './CardBody.vue';
import CardFooter from './CardFooter.vue';
import CardHeader from './CardHeader.vue';
import { hasSlotContent } from './slotContent';

// A card with a header band (a settings group, a details panel; ActionCard before v0.16): a muted header with an optional #icon and #actions, the content (or #body for
// your own parts), and a sticky #footer. With an empty default slot (none, or one that renders nothing) and no #body, there is no body: no empty gutters or rule.
// `seeAll` (docs/see-all.md) is the card's "All cases →" link: at the right of the sticky footer, after the content (`footer`, the default, for a list), or after
// the header's actions (`header`, for a summary, stat or chart). Its title links to the same place unless the descriptor says `linkTitle: false`.
const props = withDefaults(
    defineProps<{
        title: string;
        level?: 1 | 2 | 3 | 4 | 5 | 6;
        seeAll?: SeeAll | null;
    }>(),
    {
        level: 3,
        seeAll: null,
    },
);
const placement = computed(() => seeAllPlacement(props.seeAll));
const titleSeeAll = computed(() =>
    props.seeAll && props.seeAll.linkTitle !== false ? props.seeAll : null,
);
</script>
<template>
    <Card data-summary-card>
        <CardHeader :title="title" :level="level" tone="muted">
            <template v-if="titleSeeAll" #default
                ><SeeAllLink :see-all="titleSeeAll" variant="title">{{
                    title
                }}</SeeAllLink></template
            >
            <template v-if="$slots.icon" #avatar
                ><span class="text-muted-foreground" aria-hidden="true"
                    ><slot name="icon" /></span
            ></template>
            <template v-if="$slots.actions || placement === 'header'" #actions
                ><slot name="actions" /><SeeAllLink
                    v-if="seeAll && placement === 'header'"
                    :see-all="seeAll"
            /></template>
        </CardHeader>
        <slot name="body"
            ><CardBody
                v-if="hasSlotContent($slots.default)"
                class="overflow-auto"
                ><slot /></CardBody
        ></slot>
        <CardFooter v-if="$slots.footer || placement === 'footer'" sticky
            ><slot name="footer" />
            <div
                v-if="seeAll && placement === 'footer'"
                class="flex justify-end"
            >
                <SeeAllLink :see-all="seeAll" /></div
        ></CardFooter>
    </Card>
</template>
