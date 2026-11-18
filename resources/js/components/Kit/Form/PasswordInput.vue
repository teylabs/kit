<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { Toggle } from 'reka-ui';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useAttrs,
    useId,
} from 'vue';
import Input from './Input.vue';
import PasswordRules from './PasswordRules.vue';

// A password input with a show/hide toggle (Reka's Toggle holds the pressed state); the other attributes pass to the input. `rules` (for a new password) shows
// the password rules under it (PasswordRules) and joins them to the input's description.
// `secret` is for a value pasted in rather than remembered (an API key, a token, a signing secret): password managers are told to leave it alone
// (autocomplete="new-password", data-1p-ignore, data-lpignore, data-bwignore), so a saved login is never filled into it and the form doesn't turn unsaved
// on load, and the toggle says "Show value". An attribute passed by the page wins over these.
// It follows its form's `reset` (a native reset, or Inertia's whole-form reset): the value it holds becomes the input's reset value, so a re-render never
// writes the old password back. A reset of named fields only (Inertia's `reset-on-error` list) fires no event: the page clears its v-model.
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    invalid?: boolean;
    rules?: boolean;
    secret?: boolean;
}>();
const model = defineModel<string>();
const shown = ref(false);
const attrs = useAttrs();
const rulesId = `password-rules-${useId()}`;
const describedBy = computed(() =>
    props.rules
        ? [attrs['aria-describedby'], rulesId].filter(Boolean).join(' ')
        : (attrs['aria-describedby'] as string | undefined),
);
// Password managers' opt-outs: 1Password, LastPass and Bitwarden each read their own attribute; new-password keeps browsers from filling a saved one.
const SECRET_ATTRS = {
    autocomplete: 'new-password',
    'data-1p-ignore': '',
    'data-lpignore': 'true',
    'data-bwignore': '',
} as const;
const inputAttrs = computed(() =>
    props.secret ? { ...SECRET_ATTRS, ...attrs } : attrs,
);
const noun = computed(() => (props.secret ? 'value' : 'password'));
const root = ref<HTMLElement | null>(null);
let owner: HTMLFormElement | null = null;
// The reset event fires before the browser resets the fields, so the value is read after it.
const followReset = () =>
    nextTick(
        () => (model.value = root.value?.querySelector('input')?.value ?? ''),
    );
onMounted(() => {
    owner = root.value?.closest('form') ?? null;
    owner?.addEventListener('reset', followReset);
});
onBeforeUnmount(() => owner?.removeEventListener('reset', followReset));
</script>
<template>
    <div ref="root" class="relative">
        <Input
            v-bind="inputAttrs"
            v-model="model"
            :aria-describedby="describedBy"
            :type="shown ? 'text' : 'password'"
            :invalid="invalid"
            class="pr-10"
        />
        <Toggle
            v-model="shown"
            :aria-label="shown ? `Hide ${noun}` : `Show ${noun}`"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 focus-visible:ring-[3px] focus-visible:outline-none"
            :tabindex="-1"
        >
            <EyeOff v-if="shown" class="size-4" aria-hidden="true" />
            <Eye v-else class="size-4" aria-hidden="true" />
        </Toggle>
    </div>
    <PasswordRules v-if="rules" :id="rulesId" :value="model" />
</template>
