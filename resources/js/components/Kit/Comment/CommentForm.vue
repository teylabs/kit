<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Paperclip, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import Button from '@/components/Kit/Form/Button.vue';
import FormActions from '@/components/Kit/Form/FormActions.vue';
import Textarea from '@/components/Kit/Form/Textarea.vue';
import Avatar from '@/components/Kit/Layout/Avatar.vue';
import {
    intentKey,
    toneDot,
    type CommentComposerData,
    type CommentData,
} from './comments';

/*
 * The composer for a new comment, a reply or an edit (docs/comments.md): Inertia's <Form> to `composer.submit`, on a NoteCard. A new comment rests as one line beside
 * the writer's avatar and opens on focus into the full box with its toolbar (attach, colour, Cancel and the submit); it closes again when focus leaves it empty.
 * `comment` makes it the editor of that comment: open from the start, its body and colour filled in, its files listed with a Remove checkbox each, and
 * `expected_version` sent, so a comment changed meanwhile is refused, never overwritten. Every save carries a fresh `intent_key` and the composer's `fields`.
 * Files come by drop, paste or the Attach button into the kit's BulkDrop and are sent as `files`; `remove_attachments` lists an edit's removed ones.
 * Enter saves with `enterSaves` (Shift+Enter for a new line), otherwise Cmd/Ctrl+Enter; Escape closes an empty composer or cancels an edit. `only` limits the
 * visit's props. After a save it clears and keeps focus in the box for the next one. Exposed: focus(), text(), colour(), clear() and submit().
 */
const props = withDefaults(
    defineProps<{
        composer: CommentComposerData;
        comment?: CommentData | null;
        placeholder?: string;
        enterSaves?: boolean;
        only?: string[];
        avatarSize?: 'xs' | 'sm' | 'md' | 'lg';
    }>(),
    {
        comment: null,
        placeholder: undefined,
        enterSaves: false,
        only: undefined,
        avatarSize: 'md',
    },
);
const emit = defineEmits<{
    start: [];
    finish: [];
    success: [];
    cancel: [];
    typing: [text: string];
}>();
const id = useId();
const root = ref<HTMLElement>();
const drop = ref<InstanceType<typeof BulkDrop>>();
const editing = computed(() => Boolean(props.comment));
const defaultColour = () =>
    props.composer.default_colour ?? props.composer.colours?.[0]?.key ?? '';
const text = ref(props.comment?.body ?? '');
const colour = ref(props.comment?.colour?.key ?? defaultColour());
const open = ref(editing.value);
const key = ref(intentKey());
const busy = ref(false);
// The box is disabled while a save is in flight, which drops its focus; a new comment's save gives it back once the visit finishes.
let refocus = false;
// BulkDrop holds the chosen files and reports them (`files`); its own input is left out of the request and the list joins it through the transform.
const files = ref<File[]>([]);
const previews = ref<(string | null)[]>([]);
const colours = computed(() => props.composer.colours ?? []);
const noun = computed(() => props.comment?.noun ?? 'comment');
const author = computed(() => props.composer.author ?? null);

function chosen(list: File[]) {
    previews.value.forEach((url) => url && URL.revokeObjectURL(url));
    files.value = list;
    previews.value = list.map((file) =>
        file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    );
    if (list.length) open.value = true;
}
onBeforeUnmount(() => chosen([]));

const empty = () => !text.value.trim() && !files.value.length;
async function focus() {
    open.value = true;
    await nextTick();
    root.value?.querySelector<HTMLTextAreaElement>('textarea')?.focus();
}
function collapse() {
    if (!editing.value && empty() && !busy.value) open.value = false;
}
function blurred(event: FocusEvent) {
    if (!root.value?.contains(event.relatedTarget as Node | null)) collapse();
}
function clear() {
    for (let left = files.value.length; left > 0; left--) drop.value?.remove(0);
    chosen([]);
    text.value = props.comment?.body ?? '';
    colour.value = props.comment?.colour?.key ?? defaultColour();
    key.value = intentKey();
}
function cancel() {
    clear();
    if (!editing.value) {
        open.value = false;
        (document.activeElement as HTMLElement | null)?.blur();
    }
    emit('cancel');
}
function submit() {
    root.value?.querySelector('form')?.requestSubmit();
}
function keydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && (editing.value || empty())) {
        event.preventDefault();
        if (editing.value) cancel();
        else {
            open.value = false;
            (event.target as HTMLElement).blur();
        }
        return;
    }
    const saves = props.enterSaves
        ? !event.shiftKey
        : event.metaKey || event.ctrlKey;
    if (event.key === 'Enter' && saves && !event.isComposing) {
        event.preventDefault();
        if (!busy.value) submit();
    }
}
function saved() {
    refocus = !editing.value;
    clear();
    emit('success');
}
async function finished() {
    busy.value = false;
    emit('finish');
    if (refocus) {
        refocus = false;
        await focus();
    }
}
// Generic over Inertia's own form-data type, so nothing here names an @inertiajs/core type. A PUT or PATCH is sent as a POST with `_method`, so its files reach the
// server as multipart (PHP parses no other method's).
const transform = <T extends Record<string, unknown>>(data: T) => {
    const rest: Record<string, unknown> = { ...data };
    delete rest.files;
    const method = props.composer.submit.method.toLowerCase();
    return {
        ...rest,
        ...props.composer.fields,
        ...(method === 'post' ? {} : { _method: method }),
        intent_key: key.value,
        ...(props.comment ? { expected_version: props.comment.version } : {}),
        ...(files.value.length ? { files: files.value } : {}),
    };
};
// FormActions' submit: the action's label, key and disabled reason.
const submitAction = computed(() => ({
    label: props.composer.submit.label,
    key: props.composer.submit.key ?? undefined,
    disabled: props.composer.submit.disabled,
    reason: props.composer.submit.reason,
}));
const formMethod = computed(() =>
    props.composer.submit.method.toLowerCase() === 'get' ? 'get' : 'post',
);
defineExpose({
    focus,
    text: () => text.value,
    colour: () => colour.value,
    clear,
    submit,
});
</script>
<template>
    <div
        ref="root"
        data-comment-form
        :data-open="open || undefined"
        :data-editing="editing || undefined"
        @focusin="open = true"
        @focusout="blurred"
    >
        <Form
            v-slot="{ errors, processing }"
            :action="composer.submit.url"
            :method="formMethod"
            :transform="transform"
            :options="{ preserveScroll: true, ...(only ? { only } : {}) }"
            @start="
                busy = true;
                emit('start');
            "
            @finish="finished"
            @success="saved"
        >
            <BulkDrop
                ref="drop"
                name="files"
                :label="`this ${noun}`"
                :accept="composer.media?.accept ?? []"
                :max-bytes="(composer.media?.max_kb ?? 0) * 1024"
                :max-files="composer.media?.max_files"
                :hint="composer.media?.hint ?? undefined"
                :has-files="files.length > 0"
                :disabled="!composer.media || processing"
                multiple
                handle-drops
                compact
                @files="chosen"
            >
                <div
                    class="border-border bg-card text-card-foreground focus-within:ring-ring/50 grid min-w-0 gap-2 rounded-lg border p-3 focus-within:ring-2"
                    data-comment-box
                >
                    <input
                        v-if="colour"
                        type="hidden"
                        name="colour"
                        :value="colour"
                    />
                    <label
                        v-if="composer.label"
                        :for="`${id}-body`"
                        class="text-sm font-medium"
                        >{{ composer.label }}</label
                    >
                    <div class="flex min-w-0 items-start gap-3">
                        <Avatar
                            v-if="!open && author"
                            :initials="author.initials"
                            :src="author.avatar_url"
                            :src2x="author.avatar_url_2x"
                            :size="avatarSize"
                            data-comment-form-avatar
                        />
                        <Textarea
                            :id="`${id}-body`"
                            v-model="text"
                            name="body"
                            :rows="open ? 3 : 1"
                            maxlength="5000"
                            :placeholder="placeholder ?? composer.placeholder"
                            :aria-label="
                                composer.label
                                    ? undefined
                                    : composer.placeholder
                            "
                            :aria-describedby="
                                errors.body ? `${id}-body-error` : undefined
                            "
                            :invalid="Boolean(errors.body)"
                            :disabled="processing"
                            :class="[
                                'min-w-0 flex-1 resize-none border-0! bg-transparent! px-0! leading-6! shadow-none! focus-visible:ring-0!',
                                open ? 'min-h-20! py-2!' : 'min-h-0! py-1!',
                            ]"
                            data-comment-body
                            @keydown="keydown"
                            @update:model-value="emit('typing', $event ?? '')"
                        />
                    </div>
                    <p
                        v-if="errors.body"
                        :id="`${id}-body-error`"
                        role="alert"
                        class="text-destructive-text text-sm"
                    >
                        {{ errors.body }}
                    </p>
                    <ul
                        v-if="comment?.attachments?.length"
                        class="grid gap-1"
                        aria-label="Attached files"
                        data-comment-existing
                    >
                        <li
                            v-for="file in comment.attachments"
                            :key="file.id"
                            class="text-muted-foreground flex min-w-0 items-center gap-2 text-sm"
                        >
                            <input
                                :id="`${id}-remove-${file.id}`"
                                type="checkbox"
                                name="remove_attachments[]"
                                :value="file.id"
                                :disabled="processing"
                                class="accent-primary size-4"
                            />
                            <label
                                :for="`${id}-remove-${file.id}`"
                                class="min-w-0 truncate"
                                >Remove {{ file.name }}</label
                            >
                        </li>
                    </ul>
                    <ul
                        v-if="files.length"
                        class="flex flex-wrap gap-2"
                        aria-label="Files to attach"
                        data-comment-files
                    >
                        <li
                            v-for="(file, index) in files"
                            :key="file.name + index"
                            class="border-border bg-background flex max-w-full min-w-0 items-center gap-2 rounded-md border py-1 pr-1 pl-2 text-xs"
                        >
                            <img
                                v-if="previews[index]"
                                :src="previews[index]!"
                                alt=""
                                class="size-8 rounded object-cover"
                            />
                            <Paperclip
                                v-else
                                class="size-3 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="max-w-48 truncate">{{
                                file.name
                            }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-7"
                                :aria-label="`Remove ${file.name}`"
                                :disabled="processing"
                                @click="drop?.remove(index)"
                            >
                                <X class="size-3" aria-hidden="true" />
                            </Button>
                        </li>
                    </ul>
                    <p
                        v-for="[name, message] in Object.entries(errors).filter(
                            ([name]) => name !== 'body',
                        )"
                        :key="name"
                        role="alert"
                        class="text-destructive-text text-sm"
                    >
                        {{ message }}
                    </p>
                    <div
                        v-if="open"
                        class="border-border flex min-w-0 flex-wrap items-center gap-2 border-t pt-2"
                        data-comment-toolbar
                    >
                        <Avatar
                            v-if="author"
                            :initials="author.initials"
                            :src="author.avatar_url"
                            :src2x="author.avatar_url_2x"
                            size="sm"
                        />
                        <Button
                            v-if="composer.media"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-foreground"
                            :disabled="processing"
                            :title="composer.media.hint ?? undefined"
                            data-comment-attach
                            @click="drop?.open()"
                        >
                            <Paperclip class="size-4" aria-hidden="true" />
                            Attach a file
                        </Button>
                        <fieldset
                            v-if="colours.length > 1"
                            class="flex items-center gap-1"
                            data-comment-colours
                        >
                            <legend class="sr-only">Colour</legend>
                            <label
                                v-for="choice in colours"
                                :key="choice.key"
                                :title="choice.label"
                                class="has-checked:ring-ring has-focus-visible:outline-ring flex size-7 cursor-pointer items-center justify-center rounded-full has-checked:ring-2 has-focus-visible:outline-2 has-focus-visible:outline-offset-2"
                                :data-colour-choice="choice.key"
                            >
                                <input
                                    v-model="colour"
                                    type="radio"
                                    :name="`${id}-colour`"
                                    :value="choice.key"
                                    :disabled="processing"
                                    class="sr-only"
                                />
                                <span
                                    :class="[
                                        'size-3.5 rounded-full',
                                        toneDot(choice.tone),
                                    ]"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">{{ choice.label }}</span>
                            </label>
                        </fieldset>
                        <div class="ml-auto min-w-0">
                            <FormActions
                                density="compact"
                                :submit="submitAction"
                                busy-label="Saving…"
                                :saved-label="editing ? 'Saved' : 'Posted'"
                                :dirty-label="editing ? 'Unsaved changes' : ''"
                                :sticky="false"
                                :cancel="false"
                                :warn-on-leave="editing"
                            >
                                <template #actions>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        :disabled="processing"
                                        data-comment-cancel
                                        @click="cancel"
                                        >Cancel</Button
                                    >
                                </template>
                            </FormActions>
                        </div>
                    </div>
                </div>
            </BulkDrop>
        </Form>
    </div>
</template>
