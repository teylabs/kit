<script setup lang="ts">
import { computed } from 'vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
import PageSection from '@/components/Kit/Layout/PageSection.vue';
import CommentForm from './CommentForm.vue';
import CommentItem from './CommentItem.vue';
import type {
    CommentComposerData,
    CommentData,
    CommentGroup,
} from './comments';

/*
 * A titled discussion (docs/comments.md): a PageSection with the composer on top (`composer`, for someone who may write), then the comments, each a CommentItem
 * with its replies. `groups` shows runs of comments together (one list per group, keyed by its id) in place of `comments`, even when it is
 * empty; with nothing to show, `empty` shows.
 * `timeFormat`, `attachmentLayout` and `menuIcons` pass to every CommentItem, and its `meta` and `attachments` slots pass through; the `composer-before` and
 * `composer-after` slots are the composer's `before` and `after`.
 * `level` is the heading's, `only` limits each visit's props, and `interactive` false shows no actions. Other attributes land on the section.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        comments?: CommentData[];
        groups?: CommentGroup[] | null;
        composer?: CommentComposerData | null;
        empty?: string;
        level?: 2 | 3 | 4 | 5 | 6;
        interactive?: boolean;
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
        comments: () => [],
        groups: null,
        composer: null,
        empty: 'No comments yet.',
        level: 2,
        interactive: true,
        only: undefined,
        timeFormat: 'relative',
        attachmentLayout: 'list',
        menuIcons: false,
        onViewImage: null,
    },
);
// Only groups with comments are drawn: a list of empty groups shows the empty line, not a blank region.
const shown = computed(() =>
    (props.groups ?? []).filter((group) => group.comments.length),
);
</script>
<template>
    <PageSection :title="title" :level="level" data-comment-thread>
        <CommentForm v-if="composer" :composer="composer" :only="only">
            <template v-if="$slots['composer-before']" #before="slotProps"
                ><slot name="composer-before" v-bind="slotProps"
            /></template>
            <template v-if="$slots['composer-after']" #after="slotProps"
                ><slot name="composer-after" v-bind="slotProps"
            /></template>
        </CommentForm>
        <ol v-if="shown.length" class="grid gap-6">
            <li
                v-for="group in shown"
                :key="group.id"
                :data-comment-group="group.id"
            >
                <ol class="grid gap-3">
                    <li v-for="comment in group.comments" :key="comment.id">
                        <CommentItem
                            :comment="comment"
                            :interactive="interactive"
                            :only="only"
                            :time-format="timeFormat"
                            :attachment-layout="attachmentLayout"
                            :menu-icons="menuIcons"
                            :on-view-image="onViewImage"
                        >
                            <template v-if="$slots.meta" #meta="slotProps"
                                ><slot name="meta" v-bind="slotProps"
                            /></template>
                            <template
                                v-if="$slots.attachments"
                                #attachments="slotProps"
                                ><slot name="attachments" v-bind="slotProps"
                            /></template>
                        </CommentItem>
                    </li>
                </ol>
            </li>
        </ol>
        <ol v-else-if="!groups && comments.length" class="grid gap-4">
            <li v-for="comment in comments" :key="comment.id">
                <CommentItem
                    :comment="comment"
                    :interactive="interactive"
                    :only="only"
                    :time-format="timeFormat"
                    :attachment-layout="attachmentLayout"
                    :menu-icons="menuIcons"
                    :on-view-image="onViewImage"
                >
                    <template v-if="$slots.meta" #meta="slotProps"
                        ><slot name="meta" v-bind="slotProps"
                    /></template>
                    <template v-if="$slots.attachments" #attachments="slotProps"
                        ><slot name="attachments" v-bind="slotProps"
                    /></template>
                </CommentItem>
            </li>
        </ol>
        <EmptyState
            v-else
            compact
            align="start"
            icon="message-square"
            :title="empty"
            data-comment-empty
        />
    </PageSection>
</template>
