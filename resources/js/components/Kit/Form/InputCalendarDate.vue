<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import Button from './Button.vue';
import InputDate from './InputDate.vue';
import { useLocked } from './useLocked';

// InputDate (a wall date, `YYYY-MM-DD`, never shifted between zones) that says what an empty value means and can be emptied again: `emptyLabel` ("Not set")
// under the field while it has no date, and a Clear button (`clearLabel`, named "Clear <label>") while it has one that may be emptied, so not while it is
// required, disabled or in a locked LockedForm. Clear submits an empty string and moves focus to the field. `v-model` is the date or ''; without it,
// `defaultValue` is the starting value, as on InputDate. `name` defaults to `id`; `span` is a FormCard's columns, as on Field. A form reset puts the starting
// value back. Other attributes land on the field's segment group, as on InputDate.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        name?: string;
        modelValue?: string | null;
        defaultValue?: string | null;
        min?: string;
        max?: string;
        description?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        span?: 1 | 2 | 3 | 4 | 5 | 6;
        emptyLabel?: string;
        clearLabel?: string;
    }>(),
    {
        name: undefined,
        modelValue: undefined,
        defaultValue: undefined,
        min: undefined,
        max: undefined,
        description: undefined,
        error: undefined,
        span: undefined,
        emptyLabel: 'Not set',
        clearLabel: 'Clear',
    },
);
const emit = defineEmits<{
    'update:modelValue': [value: string];
    canonical: [value: string];
}>();
const initial = computed(() =>
    String(props.modelValue ?? props.defaultValue ?? '').slice(0, 10),
);
const current = ref(initial.value);
watch(initial, (value) => {
    current.value = value;
});
const lock = useLocked();
const clearable = computed(
    () =>
        current.value !== '' &&
        !props.required &&
        !props.disabled &&
        !lock.locked,
);
const root = ref<HTMLElement>();
function changed(value: string) {
    current.value = value;
    emit('update:modelValue', value);
    emit('canonical', value);
}
async function clear() {
    changed('');
    await nextTick();
    // InputDate takes the empty value from its prop, which fires no input event: the form hears it here, so it turns dirty.
    root.value
        ?.querySelector('input[type="hidden"]')
        ?.dispatchEvent(new Event('input', { bubbles: true }));
    root.value?.querySelector<HTMLElement>('[role="spinbutton"]')?.focus();
}
const reset = () => {
    current.value = initial.value;
};
onMounted(() => root.value?.closest('form')?.addEventListener('reset', reset));
onBeforeUnmount(() =>
    root.value?.closest('form')?.removeEventListener('reset', reset),
);
// Whole class strings, as Field's (Tailwind finds classes by scanning the source).
const spans = {
    1: 'col-span-6 sm:col-span-1',
    2: 'col-span-6 sm:col-span-2',
    3: 'col-span-6 sm:col-span-3',
    4: 'col-span-6 sm:col-span-4',
    5: 'col-span-6 sm:col-span-5',
    6: 'col-span-6',
} as const;
</script>
<template>
    <div
        ref="root"
        :class="['grid min-w-0 content-start gap-1', span && spans[span]]"
        data-calendar-date
        :data-empty="current === '' || undefined"
    >
        <InputDate
            v-bind="$attrs"
            :id="id"
            :name="name ?? id"
            :label="label"
            :default-value="current"
            :min="min"
            :max="max"
            :required="required"
            :disabled="disabled"
            :description="description"
            :error="error"
            @canonical="changed"
        />
        <div
            v-if="current === '' || clearable"
            class="flex min-h-8 items-center"
        >
            <p
                v-if="current === ''"
                class="text-muted-foreground text-sm"
                data-date-not-set
            >
                {{ emptyLabel }}
            </p>
            <Button
                v-else
                type="button"
                variant="ghost"
                size="sm"
                :aria-label="`${clearLabel} ${label}`"
                data-date-clear
                @click="clear"
                >{{ clearLabel }}</Button
            >
        </div>
    </div>
</template>
