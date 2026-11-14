<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { numberDefaults } from '@/components/Kit/Form/number';
import { numericParts } from './text';

/*
 * A plain number for reading (docs/text.md): a count, a page number, a quantity, in the locale's grouping and decimal separator and in tabular figures, so a
 * column lines up. `decimals` fixes the places (rounded); unset keeps up to three. `notation="compact"` writes 1.2K. `prefix` and `suffix` (or the
 * `prefix` and `suffix` slots) sit outside the number. `negative="parentheses"` is the accounting form, (1,234): the parentheses are visual, so a screen
 * reader hears "negative" instead, and `data-negative` marks it for the app's own colour. The locale is `locale`, else the host's `kitNumber`, else the browser's, else en-CA. A value that is not
 * a number (null, empty, "n/a") renders nothing, never NaN. For money with a currency, or a percentage, use Amount.
 */
const props = withDefaults(
    defineProps<{
        value: number | string | null | undefined;
        decimals?: number;
        notation?: 'standard' | 'compact';
        locale?: string;
        prefix?: string;
        suffix?: string;
        negative?: 'minus' | 'parentheses';
    }>(),
    {
        decimals: undefined,
        notation: 'standard',
        locale: undefined,
        prefix: undefined,
        suffix: undefined,
        negative: 'minus',
    },
);
let page: { props: Record<string, unknown> } | null = null;
try {
    page = usePage();
} catch {
    page = null;
}
const shown = computed(() =>
    numericParts(props.value, {
        decimals: props.decimals,
        notation: props.notation,
        locale:
            props.locale ??
            numberDefaults(
                page?.props.kitNumber as { locale?: string } | undefined,
                typeof navigator === 'undefined' ? null : navigator.language,
            ).locale,
    }),
);
</script>
<template>
    <span
        v-if="shown !== null"
        class="tabular-nums"
        data-slot="text-numeric"
        :data-negative="shown.negative || undefined"
        ><slot name="prefix">{{ prefix }}</slot
        ><template v-if="shown.negative && negative === 'parentheses'"
            ><span class="sr-only">negative </span
            ><span aria-hidden="true">(</span>{{ shown.text
            }}<span aria-hidden="true">)</span></template
        ><template v-else-if="shown.negative"
            >{{ shown.minus }}{{ shown.text }}</template
        ><template v-else>{{ shown.text }}</template
        ><slot name="suffix">{{ suffix }}</slot></span
    >
</template>
