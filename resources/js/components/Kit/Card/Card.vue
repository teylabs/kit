<script setup lang="ts">
import Surface from '@/components/Kit/Surface/Surface.vue';
import type { CardVariant } from './cardTone';

// A card (docs/cards.md): a Surface with no padding, whose parts (CardHeader, CardBody, CardFooter, CardBanner) own their gutters and are divided by a rule.
// `as` is its element (`section` by default, so a CardHeader's heading names it). `variant` (docs/cards.md, Variants) picks a named look for this card and its
// parts, over the theme's: `ruled` (a rule between the parts) or `open` (no rules, a larger title). Unset, the card follows the theme's tokens
// (`--kit-card-rule`, `--kit-card-title`), whose defaults are `ruled`.
withDefaults(defineProps<{ as?: string; variant?: CardVariant | null }>(), {
    as: 'section',
    variant: null,
});
</script>
<template>
    <Surface
        :as="as"
        padding="none"
        class="divide-border [&>:not(:last-child)]:border-b-[length:var(--kit-card-rule,1px)]"
        :data-card-variant="variant ?? undefined"
        data-card
        ><slot
    /></Surface>
</template>
