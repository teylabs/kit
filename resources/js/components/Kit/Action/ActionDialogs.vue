<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import type { ActionRunner } from '@/components/Kit/Action/actionRunner';
import Dialog from '@/components/Kit/Overlay/Dialog.vue';
import Modal from '@/components/Kit/Overlay/Modal.vue';

/*
 * The dialogs of a page's action runner (useActionRunner), hosted once: a page that runs descriptors from its own buttons, rows or menus puts one ActionDialogs
 * beside them and calls `runner.run(action)`. An action's `confirm` asks in a Dialog (danger when the action is `destructive`): its typed text when it has `typed`,
 * and its `prompt` as a labelled textarea posted as the prompt's `name` (default `reason`; `required` keeps Confirm disabled until it has text), the server's
 * error for that field under it and any other error above the buttons. An action's `form` names a slot, `form-<name>`, drawn in a Modal and scoped by
 * `action`, `submit(data)` (the visit, with the form's fields), `errors`, `busy` and `close`: the host owns the fields (a date to record), the kit the visit.
 */
const props = defineProps<{ runner: ActionRunner }>();
const { pending, asking, errors, visit, running } = props.runner;
const id = useId();
const text = ref('');
watch(
    () => pending.value,
    () => (text.value = ''),
);
const prompt = computed(() => pending.value?.confirm?.prompt ?? null);
const promptName = computed(() => prompt.value?.name || 'reason');
const promptError = computed(() =>
    prompt.value ? (errors.value[promptName.value] ?? null) : null,
);
const otherErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([key]) => !prompt.value || key !== promptName.value)
        .map(([, message]) => message),
);
const blocked = computed(
    () => Boolean(prompt.value?.required) && !text.value.trim(),
);
function confirm(confirmation?: string) {
    if (!pending.value || blocked.value) return;
    visit(
        pending.value,
        confirmation,
        prompt.value ? { [promptName.value]: text.value } : {},
    );
}
const formOpen = computed({
    get: () => Boolean(asking.value && pending.value?.form),
    set: (now: boolean) => {
        if (!now) asking.value = false;
    },
});
const submit = (data: Record<string, unknown>) =>
    pending.value && visit(pending.value, undefined, data);
const close = () => (asking.value = false);
</script>
<template>
    <Dialog
        v-if="pending?.confirm && !pending.form"
        v-model:open="asking"
        :variant="pending.destructive ? 'danger' : 'neutral'"
        :title="pending.confirm.title ?? `${pending.label}?`"
        :description="pending.confirm.description"
        :confirm-label="pending.confirm.label"
        :confirm-text="pending.confirm.typed ?? null"
        :confirm-disabled="blocked"
        data-action-dialog
        @confirm="confirm"
    >
        <template v-if="prompt || otherErrors.length" #body>
            <div v-if="prompt" class="grid gap-1.5" data-action-prompt>
                <label :for="`${id}-prompt`" class="text-sm font-medium">{{
                    prompt.label
                }}</label>
                <textarea
                    :id="`${id}-prompt`"
                    v-model="text"
                    :name="promptName"
                    :placeholder="prompt.placeholder ?? undefined"
                    :required="prompt.required || undefined"
                    :aria-invalid="promptError ? 'true' : undefined"
                    :aria-describedby="
                        promptError ? `${id}-prompt-error` : undefined
                    "
                    rows="3"
                    class="border-input bg-background focus-visible:ring-ring min-h-20 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                />
                <p
                    v-if="promptError"
                    :id="`${id}-prompt-error`"
                    class="text-destructive-text text-sm"
                    data-action-prompt-error
                >
                    {{ promptError }}
                </p>
            </div>
            <ul
                v-if="otherErrors.length"
                role="alert"
                class="text-destructive-text grid gap-1 text-sm"
                data-action-errors
            >
                <li v-for="message in otherErrors" :key="message">
                    {{ message }}
                </li>
            </ul>
        </template>
    </Dialog>
    <Modal
        v-if="pending?.form"
        v-model:open="formOpen"
        :title="pending.confirm?.title ?? pending.label"
        :description="pending.confirm?.description"
        size="sm"
        :data-action-form="pending.form"
    >
        <slot
            :name="`form-${pending.form}`"
            :action="pending"
            :submit="submit"
            :errors="errors"
            :busy="running !== null"
            :close="close"
        />
    </Modal>
</template>
