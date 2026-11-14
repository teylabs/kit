<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import type { SeeAll } from './seeAll';

/*
 * A list's "see all" link, drawn one way everywhere (docs/see-all.md): the server's noun-specific label, its count in brackets when it has one, then an
 * arrow: "All cases →", "All cases (42) →". The arrow is decoration, so the accessible name is the words ("All cases", "All cases (42)"). An Inertia link.
 * It places nothing: CollectionSection (and so CollectionCard and every dashboard widget) puts it in its footer or header by `placement`, and an app's
 * own layout may too.
 * `variant="title"` is the card's title linking to the same place (the default slot is the title): in the heading's own colour, never a body link's, with
 * an underline on hover and an inset focus ring, so a heading that truncates does not clip it. The card draws it only while `seeAll.linkTitle` is not false.
 */
const props = withDefaults(
    defineProps<{ seeAll: SeeAll; variant?: 'link' | 'title' }>(),
    { variant: 'link' },
);
const text = computed(() =>
    props.seeAll.count == null
        ? props.seeAll.label
        : `${props.seeAll.label} (${props.seeAll.count})`,
);
</script>
<template>
    <Link
        v-if="variant === 'title'"
        :href="seeAll.url"
        class="focus-visible:ring-ring rounded-sm py-0.5 text-inherit underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
        data-see-all-title
        ><slot
    /></Link>
    <Link
        v-else
        :href="seeAll.url"
        class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex min-h-7 items-center gap-1 rounded-md px-2 py-1 text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:outline-none"
        data-see-all
        :data-placement="seeAll.placement ?? 'footer'"
        >{{ text }}<ArrowRight class="size-4 shrink-0" aria-hidden="true"
    /></Link>
</template>
