<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue';
import { Check } from '@lucide/vue';
import { useFieldAria } from './fieldAria';

export type CheckboxCardOption = {
    value: string | number;
    label: string;
    description?: string | null;
    disabled?: boolean;
};

/*
 * RadioCardGroup's many-choice twin: each option is a card around a native checkbox, so each is its own tab stop and Space toggles it, and the chosen values
 * submit under `name[]` with a plain or Inertia <Form>. The group is a `role="group"` named by its Field's label (`<id>-label`) or `label`, and described by
 * the Field's description and error. `v-model` is the array of chosen values. A checked card takes the primary ring and a check mark, so the state isn't colour
 * alone; a disabled option (or a `disabled` group) is dimmed. The `badge` slot (`{ option, checked }`) sits at the end of a card's label row. `columns` (2 or
 * 3) lays the cards out in a grid; one column by default. Utilities and tokens only.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        id: string;
        name?: string;
        options: CheckboxCardOption[];
        label?: string;
        disabled?: boolean;
        invalid?: boolean;
        columns?: 2 | 3;
    }>(),
    {
        name: undefined,
        label: undefined,
        disabled: false,
        invalid: false,
        columns: undefined,
    },
);
const model = defineModel<(string | number)[]>({ default: () => [] });
const attrs = useAttrs();
const aria = useFieldAria(attrs, () => props.invalid);
const uid = useId();
const optionId = (index: number) => `${props.id}-${uid}-${index}`;
const checked = (option: CheckboxCardOption) =>
    model.value.includes(option.value);
const toggle = (option: CheckboxCardOption, on: boolean) => {
    const rest = model.value.filter((value) => value !== option.value);
    model.value = on ? [...rest, option.value] : rest;
};
const grid = computed(
    () =>
        props.columns === 3
            ? 'sm:grid-cols-3'
            : props.columns === 2
              ? 'sm:grid-cols-2'
              : '',
);
</script>
<template>
    <div
        v-bind="{ ...attrs, ...aria }"
        :id="id"
        role="group"
        :aria-labelledby="label ? undefined : `${id}-label`"
        :aria-label="label"
        :class="['grid gap-3', grid, attrs.class]"
        data-checkbox-card-group
    >
        <label
            v-for="(option, index) in options"
            :key="option.value"
            :for="optionId(index)"
            :class="[
                'bg-card text-card-foreground relative grid min-h-14 content-start gap-1 rounded-xl border p-4 text-left transition-colors motion-reduce:transition-none',
                'has-[:focus-visible]:outline-ring has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2',
                checked(option)
                    ? 'border-primary ring-primary ring-2'
                    : 'border-border',
                disabled || option.disabled
                    ? 'cursor-not-allowed opacity-50'
                    : 'hover:bg-accent/50 cursor-pointer',
            ]"
            :data-selected="checked(option) || undefined"
            :data-value="String(option.value)"
            data-checkbox-card
        >
            <input
                :id="optionId(index)"
                type="checkbox"
                class="sr-only"
                :name="name ? `${name}[]` : undefined"
                :value="option.value"
                :checked="checked(option)"
                :disabled="disabled || option.disabled"
                :aria-describedby="
                    option.description
                        ? `${optionId(index)}-description`
                        : undefined
                "
                @change="
                    toggle(option, ($event.target as HTMLInputElement).checked)
                "
            />
            <span class="flex items-center gap-3">
                <span
                    :class="[
                        'flex size-5 shrink-0 items-center justify-center rounded-md border-2',
                        checked(option)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-muted-foreground',
                    ]"
                    aria-hidden="true"
                    ><Check v-if="checked(option)" class="size-3.5"
                /></span>
                <span class="min-w-0 text-base font-medium break-words">{{
                    option.label
                }}</span>
                <span
                    v-if="$slots.badge"
                    class="ml-auto shrink-0"
                    data-checkbox-card-badge
                    ><slot
                        name="badge"
                        :option="option"
                        :checked="checked(option)"
                /></span>
            </span>
            <span
                v-if="option.description"
                :id="`${optionId(index)}-description`"
                class="text-muted-foreground pl-8 text-sm"
                >{{ option.description }}</span
            >
        </label>
    </div>
</template>
