<script setup lang="ts">
import TextCopy from '@/components/Kit/Text/TextCopy.vue';

/*
 * A button that copies `value` to the clipboard: TextCopy's button form (docs/text.md), with the same props and markup it always had. Success is claimed only
 * after the browser's write resolves, and spoken once through a polite status; a refused or missing clipboard says so and shows the value in a read-only
 * input, selected on focus, so it can still be copied by hand. A changed `value` resets it. `label` names the button ("Copy DKIM name"); `icon` makes it a
 * small icon button with the label as its accessible name and tooltip; `copiedLabel` replaces the label while copied. `variant` is the kit Button's secondary
 * (default) or ghost. Marks `data-copy-button` and `data-state` (idle, copying, copied, failed).
 */
withDefaults(
    defineProps<{
        value: string;
        label?: string;
        copiedLabel?: string;
        icon?: boolean;
        variant?: 'secondary' | 'ghost';
    }>(),
    {
        label: 'Copy',
        copiedLabel: undefined,
        icon: false,
        variant: 'secondary',
    },
);
</script>
<template>
    <TextCopy
        button
        :value="value"
        :label="label"
        :copied-label="copiedLabel"
        :icon="icon"
        :variant="variant"
    />
</template>
