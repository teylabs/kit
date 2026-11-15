<script setup lang="ts">
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
 * `level` is the heading's, `only` limits each visit's props, and `interactive` false shows no actions. Other attributes land on the section.
 */
withDefaults(
    defineProps<{
        title: string;
        comments?: CommentData[];
        groups?: CommentGroup[] | null;
        composer?: CommentComposerData | null;
        empty?: string;
        level?: 2 | 3 | 4 | 5 | 6;
        interactive?: boolean;
        only?: string[];
    }>(),
    {
        comments: () => [],
        groups: null,
        composer: null,
        empty: 'No comments yet.',
        level: 2,
        interactive: true,
        only: undefined,
    },
);
</script>
<template>
    <PageSection :title="title" :level="level" data-comment-thread>
        <CommentForm v-if="composer" :composer="composer" :only="only" />
        <ol v-if="groups && groups.length" class="grid gap-6">
            <li
                v-for="group in groups"
                :key="group.id"
                :data-comment-group="group.id"
            >
                <ol class="grid gap-3">
                    <li v-for="comment in group.comments" :key="comment.id">
                        <CommentItem
                            :comment="comment"
                            :interactive="interactive"
                            :only="only"
                        />
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
                />
            </li>
        </ol>
        <p v-else class="text-muted-foreground text-sm" data-comment-empty>
            {{ empty }}
        </p>
    </PageSection>
</template>
