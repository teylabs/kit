<script setup lang="ts">
// ActivityStream over useCursorFeed, as a host's activity page would use them: text and html headlines, a group, days, and Load more. Compiled by
// bin/typecheck-components.sh; never rendered.
import ActivityStream from '@/components/Kit/Feed/ActivityStream.vue';
import type { ActivityEntry, ActivityHeadline } from '@/components/Kit/Feed/activity';
import { useCursorFeed } from '@/components/Kit/Feed/useCursorFeed';

const feed = useCursorFeed<ActivityEntry>({ prop: 'feed', cursorKey: 'next_cursor', syncKey: 'sync_token' });
const text: ActivityHeadline = { text: 'Invoice sent' };
const html: ActivityHeadline = { html: '<strong>Ada</strong> signed' };
// @ts-expect-error a headline is text or html, never both
const both: ActivityHeadline = { text: 'a', html: 'b' };
const day = { date: '2026-10-29', label: 'Today' };
const one: ActivityEntry = { id: '1', kind: 'activity', headline: text, occurredAt: '2026-10-29T09:00:00Z', timestamp: '9:00', timestampTitle: '29 October 2026, 9:00', intent: 'success', meta: [{ label: 'Amount', value: '£10' }], count: null, truncated: false, children: [], day };
const group: ActivityEntry = { ...one, id: '2', kind: 'group', headline: html, intent: 'neutral', count: 5, truncated: true, children: [one] };
</script>
<template>
    <ActivityStream :entries="feed.entries.value" :more="feed.more.value" :loading="feed.loading.value" empty="No activity yet." live @load-more="feed.loadMore" />
    <ActivityStream :entries="[one, group]" :days="false" dense />
    <span v-if="both">{{ both }}</span>
</template>
