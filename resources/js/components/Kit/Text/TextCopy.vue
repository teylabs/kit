<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Input from '@/components/Kit/Form/Input.vue';
import { useCopy } from './useCopy';

/*
 * The kit's copy primitive (docs/text.md). By default it is inline text that copies `value` when pressed: a real button, so Enter and Space copy too,
 * showing the default slot or the value, named by what it shows (so the name is the visible text), with `tooltip` ("Copy") as its title and so its description. `button` makes it a labelled kit Button instead (CopyButton's
 * look), and `icon` a small icon button named by `label`. Either way success is claimed only once the clipboard write resolved, spoken once through a polite
 * status, and the inline text shows a check; a refused or missing clipboard says so, and the button form shows the value in a read-only input, selected on
 * focus, to copy by hand. A changed `value` resets it. CopyField is the same copy beside a labelled field. Marks `data-copy-button` (the button form) or
 * `data-text-copy` (inline), and `data-state` (idle, copying, copied, failed).
 */
const props = withDefaults(
    defineProps<{
        value: string | number;
        tooltip?: string;
        button?: boolean;
        label?: string;
        copiedLabel?: string;
        icon?: boolean;
        variant?: 'secondary' | 'ghost';
    }>(),
    {
        tooltip: 'Copy',
        button: false,
        label: 'Copy',
        copiedLabel: undefined,
        icon: false,
        variant: 'secondary',
    },
);
const text = computed(() => String(props.value));
const { state, copy } = useCopy(() => text.value);
const status = computed(() =>
    state.value === 'copied'
        ? 'Copied.'
        : state.value === 'failed'
          ? 'Could not copy. Select the text to copy it, or try again.'
          : '',
);
</script>
<template>
    <span
        v-if="button || icon"
        class="inline-grid min-w-0 gap-2"
        data-copy-button
        :data-state="state"
    >
        <span class="inline-flex items-center gap-2">
            <Button
                v-if="icon"
                type="button"
                :variant="variant"
                size="icon"
                class="size-7 shrink-0"
                :aria-label="
                    state === 'copied' ? (copiedLabel ?? label) : label
                "
                :title="label"
                :disabled="state === 'copying'"
                @click="copy"
            >
                <Check
                    v-if="state === 'copied'"
                    class="size-4"
                    aria-hidden="true"
                />
                <Copy v-else class="size-4" aria-hidden="true" />
            </Button>
            <Button
                v-else
                type="button"
                :variant="variant"
                :disabled="state === 'copying'"
                @click="copy"
                >{{
                    state === 'copied' && copiedLabel ? copiedLabel : label
                }}</Button
            >
            <span role="status" aria-live="polite" class="sr-only">{{
                status
            }}</span>
        </span>
        <Input
            v-if="state === 'failed'"
            :model-value="text"
            :aria-label="`${label}: select to copy`"
            readonly
            @focus="($event.target as HTMLInputElement).select()"
        />
    </span>
    <span v-else class="inline" data-text-copy :data-state="state"
        ><button
            type="button"
            :title="tooltip"
            class="decoration-muted-foreground/60 hover:decoration-foreground focus-visible:outline-ring inline cursor-copy rounded-sm text-start break-words underline decoration-dotted underline-offset-4 select-text focus-visible:outline-2 focus-visible:outline-offset-2"
            :aria-busy="state === 'copying' || undefined"
            @click="copy"
        >
            <slot>{{ text }}</slot></button
        ><Check
            v-if="state === 'copied'"
            class="text-success-text ms-1 inline size-3.5 align-[-0.125em]"
            aria-hidden="true"
        /><span role="status" aria-live="polite" class="sr-only">{{
            status
        }}</span></span
    >
</template>
