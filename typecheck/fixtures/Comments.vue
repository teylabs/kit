<script setup lang="ts">
// The comment and note components' props, their data types and CommentForm's exposed methods, as a host would use them on a record page and a dashboard.
// Compiled by bin/typecheck-components.sh; never rendered.
import { ref } from 'vue';
import {
    Avatar,
    COMMENT_TONES,
    CommentForm,
    CommentItem,
    CommentThread,
    NoteCard,
    type CommentComposerData,
    type CommentData,
    type CommentGroup,
    type CommentTone,
} from '@kit/ui';

const composer: CommentComposerData = {
    submit: { label: 'Post', url: '/comments', method: 'post' },
    placeholder: 'Write a comment…',
    colours: [{ key: 'yellow', label: 'Yellow', tone: 'yellow' }],
    default_colour: 'yellow',
    media: { accept: ['image/png'], max_files: 2, max_kb: 1024 },
    fields: { subject_type: 'project', subject_id: 4 },
    author: { name: 'Sam Reed', initials: 'SR' },
};
const comment: CommentData = {
    id: 1,
    body: 'Looks good.',
    author: { name: 'Sam Reed', initials: 'SR', avatar_url: null },
    created_at: '2026-11-15T12:00:00Z',
    version: 2,
    colour: { key: 'yellow', label: 'Yellow', tone: 'yellow', meaning: null },
    attachments: [{ id: 'a', name: 'a.png', mime: 'image/png', url: '/a.png', thumb_pending: true }],
    editor: { ...composer, submit: { label: 'Save', url: '/comments/1', method: 'put' } },
    reply: composer,
    delete: { label: 'Delete', url: '/comments/1', method: 'delete' },
    replies: [],
};
const groups: CommentGroup[] = [{ id: 9, comments: [comment] }];
const tone: CommentTone = COMMENT_TONES[0];
const form = ref<InstanceType<typeof CommentForm>>();
const typed: string | undefined = form.value?.text();
form.value?.clear();
</script>
<template>
    <Avatar initials="SR" size="xs" />
    <NoteCard :accent="{ tone, label: 'Muted' }" accent-edge="top" accent-width="thin" density="flush">Body</NoteCard>
    <CommentItem :comment="comment" variant="compact" collapsible :only="['comments']">
        <template #quick="{ comment: shown }">{{ shown.id }}</template>
        <template #footer>{{ typed }}</template>
    </CommentItem>
    <CommentForm ref="form" :composer="composer" enter-saves @typing="(text: string) => text.length" @finish="() => undefined" />
    <CommentThread title="Comments" :comments="[comment]" :groups="groups" :composer="composer" :level="3" />
    <!-- @vue-expect-error comment is required -->
    <CommentItem />
    <!-- @vue-expect-error accentEdge is left or top -->
    <NoteCard accent-edge="right" />
    <!-- @vue-expect-error size is xs, sm, md, lg or xl -->
    <Avatar initials="SR" size="huge" />
</template>
