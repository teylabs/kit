<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed, nextTick, useId } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';
import Field from '@/components/Kit/Form/Field.vue';
import Input from '@/components/Kit/Form/Input.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import { useCopy } from './useCopy';

/*
 * A value to copy, shown in a labelled read-only field with a Copy button beside it (docs/text.md): an API key, a webhook URL, a DNS record. The field is the
 * native `readonly`, so it stays focusable and selectable, and selects its whole value on focus. Copy is named "Copy {label}"; success is spoken once through
 * a polite status, only after the clipboard write resolved. A refused or missing clipboard says so, and focuses and selects the field to copy by hand.
 * `multiline` is a textarea (a certificate, a JSON blob); `description` is the Field's. The write and its state are useCopy's, shared with CopyButton and TextCopy.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        description?: string;
        multiline?: boolean;
        id?: string;
    }>(),
    { description: undefined, multiline: false, id: undefined },
);
const fallbackId = useId();
const fieldId = props.id ?? `copy-field-${fallbackId}`;
const { state, copy } = useCopy(() => props.value);
const message = computed(() =>
    state.value === 'copied'
        ? `Copied ${props.label}.`
        : state.value === 'failed'
          ? `Could not copy ${props.label}. It is selected in the field: copy it from there, or try again.`
          : '',
);
const selectAll = (event: FocusEvent) =>
    (event.target as HTMLInputElement | HTMLTextAreaElement).select();

async function press(): Promise<void> {
    await copy();
    if (state.value !== 'failed') return;
    await nextTick();
    document.getElementById(fieldId)?.focus();
}
</script>
<template>
    <div class="min-w-0" data-slot="copy-field" :data-state="state">
        <Field :id="fieldId" :label="label" :description="description">
            <div class="flex min-w-0 items-start gap-2">
                <Textarea
                    v-if="multiline"
                    :id="fieldId"
                    :model-value="value"
                    readonly
                    class="min-w-0 flex-1 font-mono"
                    @focus="selectAll"
                />
                <Input
                    v-else
                    :id="fieldId"
                    :model-value="value"
                    readonly
                    class="min-w-0 flex-1 font-mono"
                    @focus="selectAll"
                />
                <Button
                    type="button"
                    variant="secondary"
                    class="shrink-0"
                    :disabled="state === 'copying'"
                    data-copy-field-button
                    @click="press"
                >
                    <Check
                        v-if="state === 'copied'"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <Copy v-else class="size-4" aria-hidden="true" />
                    Copy<span class="sr-only"> {{ label }}</span>
                </Button>
            </div>
        </Field>
        <p
            role="status"
            aria-live="polite"
            aria-atomic="true"
            :class="['text-muted-foreground text-sm', message && 'mt-2']"
        >
            {{ message }}
        </p>
    </div>
</template>
