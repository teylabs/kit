<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';

/*
 * A seamless composer (docs/composer.md): one quiet field on the page, no card title and no boxed textarea inside a card. Closed it is a single line, with
 * `#avatar` before the placeholder. Focus it, or give it text, and it opens in place: the field grows to a few lines, `#below` shows under it (chosen files,
 * an error), and a toolbar row shows `#tools` (attach, colour) on the left and Cancel and the submit button on the right. The whole box draws the focus ring.
 * Escape or Cancel closes it while it is empty; ⌘/Ctrl+Enter submits. `as="form"` (the default) emits `submit` with the text; `as="div"` sits inside the
 * page's own <Form> (Inertia's), whose fields it submits: the textarea is a named field (`name`), and Post is that form's submit button.
 */
const props = withDefaults(
    defineProps<{
        placeholder?: string;
        label?: string;
        submitLabel?: string;
        busy?: boolean;
        open?: boolean;
        as?: 'form' | 'div';
        name?: string;
        id?: string;
        maxlength?: number;
        invalid?: boolean;
        describedby?: string;
    }>(),
    {
        as: 'form',
        name: 'body',
        id: undefined,
        maxlength: undefined,
        invalid: false,
        describedby: undefined,
        placeholder: 'Write something…',
        label: undefined,
        submitLabel: 'Post',
        busy: false,
        open: false,
    },
);
const body = defineModel<string>({ default: '' });
const emit = defineEmits<{ submit: [body: string]; cancel: [] }>();
const focused = ref(props.open);
const field = ref<HTMLTextAreaElement | null>(null);
// Open while focused, while it holds text, or while the host says so (`open`: files chosen, say).
const expanded = computed(
    () => focused.value || body.value.length > 0 || props.open,
);
const empty = computed(() => body.value.trim() === '');
function close() {
    if (empty.value) focused.value = false;
    emit('cancel');
}
async function expand() {
    focused.value = true;
    await nextTick();
    field.value?.focus();
}
function submit() {
    if (empty.value || props.busy) return;
    if (props.as === 'form') emit('submit', body.value);
    else field.value?.form?.requestSubmit();
}
</script>
<template>
    <component
        :is="as"
        :class="[
            'bg-card text-card-foreground focus-within:ring-ring rounded-xl border shadow-xs focus-within:ring-2',
            expanded ? 'grid gap-0' : '',
        ]"
        :data-composer="expanded ? 'open' : 'closed'"
        @submit="as === 'form' && ($event.preventDefault(), submit())"
    >
        <div
            :class="[
                'flex gap-3 px-3',
                expanded ? 'items-start pt-3' : 'min-h-14 items-center',
            ]"
        >
            <div v-if="$slots.avatar" class="shrink-0">
                <slot name="avatar" />
            </div>
            <textarea
                :id="id"
                ref="field"
                v-model="body"
                :name="name"
                :maxlength="maxlength"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedby"
                :aria-label="label ?? placeholder"
                :placeholder="placeholder"
                :rows="expanded ? 3 : 1"
                class="placeholder:text-muted-foreground min-h-0 w-full flex-1 resize-none bg-transparent py-1.5 text-sm outline-none"
                data-composer-field
                @focus="expand"
                @keydown.esc.prevent="close"
                @keydown.meta.enter.prevent="submit"
                @keydown.ctrl.enter.prevent="submit"
            />
        </div>
        <div v-if="expanded && $slots.below" class="grid gap-2 px-3 pt-2">
            <slot name="below" />
        </div>
        <div
            v-if="expanded"
            class="flex flex-wrap items-center justify-between gap-2 px-3 pt-1 pb-3"
            data-composer-toolbar
        >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <slot name="tools" />
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex min-h-9 items-center rounded-md px-3 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                    @mousedown.prevent
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="empty || busy"
                    :aria-busy="busy || undefined"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-9 items-center rounded-md px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50"
                >
                    {{ submitLabel }}
                </button>
            </div>
        </div>
    </component>
</template>
