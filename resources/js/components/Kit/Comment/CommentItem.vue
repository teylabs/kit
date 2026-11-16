<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Reply, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import FileList from '@/components/Kit/Media/FileList.vue';
import Dropdown from '@/components/Kit/Menu/Dropdown.vue';
import DropdownConfirm from '@/components/Kit/Menu/DropdownConfirm.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';
import TextLong from '@/components/Kit/Text/TextLong.vue';
import CommentForm from './CommentForm.vue';
import NoteCard from './NoteCard.vue';
import { intentKey, type CommentData } from './comments';

/*
 * One comment or note with its replies (docs/comments.md), on a NoteCard: the author, the time (a relative DateTime, its full date on hover), the colour, the body
 * (TextLong; `collapsible` cuts a long one with Show more), its files (FileList) and the record it is about (`subject`, in the footer, a link when it has a `url`; the `footer` slot,
 * scoped by `comment`, replaces it). Its menu offers what the server sent: Edit (`editor`, opening CommentForm in place), Reply (`reply`, a CommentForm below it)
 * and Delete (`delete`, confirmed with the action's `confirm` (one that needs typing, or a disabled one, shows disabled with its reason), then an Inertia DELETE with `expected_version` and an `intent_key`). A refused delete (a conflict
 * arrives as an error) shows inline. The `quick` slot puts the host's own buttons before the menu. `interactive` false shows no actions (a read-only feed).
 * `timeFormat` absolute shows the date and time in place of the relative time. `attachmentLayout` gallery shows image files as a grid of thumbnails (other
 * files stay a FileList); the `attachments` slot, scoped by `files` and `comment`, replaces both. The `meta` slot, scoped by `comment`, adds to the header line
 * after the time (a case link, its icon). `onViewImage(image, images)` opens a gallery tile in the host's own viewer instead of a new tab. `menuIcons` puts an icon on each menu row (Edit, Reply, Delete). All four are off by default.
 * `variant` compact is one line: no actions, files or replies. `density`, `accentEdge` and `accentWidth` are NoteCard's; `only` limits each visit's props.
 */
const props = withDefaults(
    defineProps<{
        comment: CommentData;
        variant?: 'full' | 'compact';
        density?: 'default' | 'compact' | 'flush';
        accentEdge?: 'left' | 'top';
        accentWidth?: 'thin' | 'medium' | 'wide';
        interactive?: boolean;
        collapsible?: boolean;
        only?: string[];
        timeFormat?: 'relative' | 'absolute';
        attachmentLayout?: 'list' | 'gallery';
        menuIcons?: boolean;
        onViewImage?:
            | ((
                  image: import('./comments').CommentAttachment,
                  images: import('./comments').CommentAttachment[],
              ) => void)
            | null;
    }>(),
    {
        variant: 'full',
        density: 'default',
        accentEdge: 'left',
        accentWidth: 'medium',
        interactive: true,
        collapsible: false,
        only: undefined,
        timeFormat: 'relative',
        attachmentLayout: 'list',
        menuIcons: false,
        onViewImage: null,
    },
);
const emit = defineEmits<{ deleted: [] }>();
const editing = ref(false);
const replying = ref(false);
const failure = ref('');
const root = ref<HTMLElement>();
const editor = ref<InstanceType<typeof CommentForm>>();
const reply = ref<InstanceType<typeof CommentForm>>();
const noun = computed(() => props.comment.noun ?? 'comment');
const full = computed(() => props.variant === 'full');
const menu = computed(
    () =>
        props.interactive &&
        full.value &&
        Boolean(
            props.comment.editor || props.comment.reply || props.comment.delete,
        ),
);
// A typed confirmation can't be asked from a menu (as DropdownAction): such a delete, or a disabled one, shows disabled with its reason and never runs.
// The delete's accessible name names what it deletes, as in the kit's row menus: "Delete comment by Ana Lopez".
const deleteName = computed(
    () =>
        `${props.comment.delete?.label ?? 'Delete'} ${noun.value} by ${props.comment.author?.name ?? 'an unknown author'}`,
);
const deleteBlocked = computed(() =>
    props.comment.delete?.confirm?.typed
        ? 'Needs a typed confirmation, which this menu cannot ask for.'
        : props.comment.delete?.disabled
          ? (props.comment.delete.reason ?? 'Not available.')
          : null,
);
const files = computed(() =>
    (props.comment.attachments ?? []).map((file) => ({
        id: file.id,
        name: file.name,
        url: file.url,
        mime: file.mime,
        size: file.size ?? null,
        thumb: file.thumb_pending ? null : (file.thumb_url ?? null),
    })),
);

// In a gallery, images (with a thumbnail, or one still being made) show as tiles and the rest stay a list.
const isImage = (file: { mime: string }) => file.mime.startsWith('image/');
const images = computed(() =>
    props.attachmentLayout === 'gallery'
        ? (props.comment.attachments ?? []).filter(isImage)
        : [],
);
const listed = computed(() =>
    props.attachmentLayout === 'gallery'
        ? files.value.filter((file) => !isImage(file))
        : files.value,
);

// A gallery tile opens the host's in-page viewer when it gives one (`onViewImage`, with every image of the comment), else the file in a new tab.
function viewImage(
    image: import('./comments').CommentAttachment,
    event: MouseEvent,
) {
    if (!props.onViewImage || event.metaKey || event.ctrlKey || event.shiftKey)
        return;
    event.preventDefault();
    props.onViewImage(image, images.value);
}

async function edit() {
    editing.value = true;
    await nextTick();
    await editor.value?.focus();
}
async function closeEdit() {
    editing.value = false;
    await nextTick();
    root.value?.querySelector<HTMLElement>('[data-comment-menu]')?.focus();
}
async function startReply() {
    replying.value = true;
    await nextTick();
    await reply.value?.focus();
}
const remove = () =>
    new Promise<void>((resolve) => {
        failure.value = '';
        router.delete(props.comment.delete!.url, {
            data: {
                expected_version: props.comment.version,
                intent_key: intentKey(),
            },
            preserveScroll: true,
            ...(props.only ? { only: props.only } : {}),
            onSuccess: () => emit('deleted'),
            onError: (errors: Record<string, string>) => {
                failure.value =
                    Object.values(errors)[0] ??
                    `This ${noun.value} changed. Reload and review it before trying again.`;
            },
            onFinish: () => resolve(),
        });
    });
</script>
<template>
    <div
        ref="root"
        class="grid min-w-0 gap-3"
        :data-comment="comment.id"
        :data-editing="editing || undefined"
    >
        <CommentForm
            v-if="editing && comment.editor"
            ref="editor"
            :composer="comment.editor"
            :comment="comment"
            :only="only"
            @cancel="closeEdit"
            @success="closeEdit"
        />
        <NoteCard
            v-else
            :accent="comment.colour"
            :author="comment.author"
            :variant="variant"
            :density="density"
            :accent-edge="accentEdge"
            :accent-width="accentWidth"
        >
            <template #timestamp>
                <DateTime
                    :value="comment.created_at"
                    :format="timeFormat === 'absolute' ? 'local' : 'relative'"
                    :live="timeFormat !== 'absolute'"
                />
            </template>
            <template v-if="$slots.meta" #meta>
                <slot name="meta" :comment="comment" />
            </template>
            <template
                v-if="interactive && full && (menu || $slots.quick)"
                #actions
            >
                <span class="flex items-center gap-1">
                    <slot name="quick" :comment="comment" />
                    <Dropdown
                        v-if="menu"
                        horizontal
                        :aria-label="`Actions for ${noun} by ${comment.author?.name ?? 'an unknown author'}`"
                        data-comment-menu
                    >
                        <DropdownItem
                            v-if="comment.editor"
                            as="button"
                            data-comment-edit
                            @chosen="edit"
                            ><template v-if="menuIcons" #icon
                                ><Pencil
                                    class="size-4"
                                    aria-hidden="true" /></template
                            >Edit</DropdownItem
                        >
                        <DropdownItem
                            v-if="comment.reply"
                            as="button"
                            data-comment-reply
                            @chosen="startReply"
                            ><template v-if="menuIcons" #icon
                                ><Reply
                                    class="size-4"
                                    aria-hidden="true" /></template
                            >Reply</DropdownItem
                        >
                        <DropdownItem
                            v-if="comment.delete && deleteBlocked"
                            as="button"
                            disabled
                            aria-disabled="true"
                            :title="deleteBlocked"
                            :aria-label="deleteName"
                            destructive
                            data-comment-delete
                            ><template v-if="menuIcons" #icon
                                ><Trash2
                                    class="size-4"
                                    aria-hidden="true" /></template
                            >{{ comment.delete.label
                            }}<template #caption>{{
                                deleteBlocked
                            }}</template></DropdownItem
                        >
                        <DropdownConfirm
                            v-else-if="comment.delete"
                            :on-confirmed="remove"
                            variant="danger"
                            :item-label="deleteName"
                            :confirm-label="
                                comment.delete.confirm?.label ??
                                `Delete ${noun}`
                            "
                            data-comment-delete
                        >
                            <template v-if="menuIcons" #icon
                                ><Trash2 class="size-4" aria-hidden="true"
                            /></template>
                            {{ comment.delete.label }}
                            <template #title>{{
                                comment.delete.confirm?.title ??
                                `Delete this ${noun}?`
                            }}</template>
                            <template #confirmation
                                ><p>
                                    {{
                                        comment.delete.confirm?.description ??
                                        `The ${noun} is removed for everyone.`
                                    }}
                                </p></template
                            >
                        </DropdownConfirm>
                    </Dropdown>
                </span>
            </template>
            <template v-if="!full">{{
                comment.body || 'Attached files'
            }}</template>
            <template v-else>
                <TextLong
                    v-if="comment.body"
                    class="text-sm leading-normal"
                    :text="comment.body"
                    :collapsible="collapsible"
                    data-comment-text
                />
                <slot
                    v-if="files.length"
                    name="attachments"
                    :files="comment.attachments ?? []"
                    :comment="comment"
                >
                    <ul
                        v-if="images.length"
                        class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                        aria-label="Images"
                        data-comment-gallery
                    >
                        <li v-for="image in images" :key="image.id">
                            <a
                                :href="image.url"
                                target="_blank"
                                rel="noopener"
                                class="bg-muted focus-visible:outline-ring block aspect-square overflow-hidden rounded-md focus-visible:outline-2 focus-visible:outline-offset-2"
                                data-comment-gallery-item
                                @click="viewImage(image, $event)"
                            >
                                <img
                                    v-if="
                                        !image.thumb_pending && image.thumb_url
                                    "
                                    :src="image.thumb_url"
                                    :alt="image.name"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                                <span v-else class="sr-only">{{
                                    image.name
                                }}</span>
                            </a>
                        </li>
                    </ul>
                    <FileList
                        v-if="listed.length"
                        :items="listed"
                        label="Attachments"
                    />
                </slot>
            </template>
            <template v-if="full && ($slots.footer || comment.subject)" #footer>
                <slot name="footer" :comment="comment">
                    <a
                        v-if="comment.subject?.url"
                        :href="comment.subject.url"
                        class="text-muted-foreground focus-visible:outline-ring w-fit rounded-sm text-sm hover:underline focus-visible:outline-2"
                        data-comment-subject
                        >{{ comment.subject.label }}</a
                    >
                    <span
                        v-else-if="comment.subject"
                        class="text-muted-foreground w-fit text-sm"
                        data-comment-subject
                        >{{ comment.subject.label }}</span
                    >
                </slot>
            </template>
        </NoteCard>
        <p v-if="failure" role="alert" class="text-destructive-text text-sm">
            {{ failure }}
        </p>
        <CommentForm
            v-if="replying && comment.reply"
            ref="reply"
            :composer="comment.reply"
            :only="only"
            @cancel="replying = false"
            @success="replying = false"
        />
        <ol
            v-if="full && comment.replies?.length"
            class="border-border grid gap-3 border-l pl-4"
            :aria-label="`Replies to ${comment.author?.name ?? 'this ' + noun}`"
        >
            <li v-for="child in comment.replies" :key="child.id">
                <CommentItem
                    :comment="child"
                    :interactive="interactive"
                    :collapsible="collapsible"
                    :only="only"
                    :time-format="timeFormat"
                    :attachment-layout="attachmentLayout"
                    :menu-icons="menuIcons"
                    :on-view-image="onViewImage"
                >
                    <template v-if="$slots.meta" #meta
                        ><slot name="meta" :comment="child"
                    /></template>
                    <template v-if="$slots.attachments" #attachments
                        ><slot
                            name="attachments"
                            :files="child.attachments ?? []"
                            :comment="child"
                    /></template>
                </CommentItem>
            </li>
        </ol>
    </div>
</template>
