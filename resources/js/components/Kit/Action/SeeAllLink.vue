<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import type { SeeAll } from './seeAll';

/*
 * A list's "see all" link, drawn one way everywhere (docs/see-all.md): the server's noun-specific label, its count in brackets when it has one, then an
 * arrow: "All cases →", "All cases (42) →". The arrow is decoration, so the accessible name is the words ("All cases", "All cases (42)"). An Inertia link.
 * It places nothing: WidgetCard, SummaryCard and PageSection put it in their footer or header by `placement`.
 */
const props = defineProps<{ seeAll: SeeAll }>();
const text = computed(() =>
    props.seeAll.count == null
        ? props.seeAll.label
        : `${props.seeAll.label} (${props.seeAll.count})`,
);
</script>
<template>
    <Link
        :href="seeAll.url"
        class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex min-h-7 items-center gap-1 rounded-md px-2 py-1 text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:outline-none"
        data-see-all
        :data-placement="seeAll.placement ?? 'footer'"
        >{{ text }}<ArrowRight class="size-4 shrink-0" aria-hidden="true"
    /></Link>
</template>
