<script setup lang="ts">
import type { Component } from 'vue';
import Button from '@/components/Kit/Form/Button.vue';

// A comment's quick action (CommentItem's `quick` slot, NoteCard's `actions`): a small ghost button with an icon and its label. On a narrow card (below the
// container's `sm`) only the icon shows; the label stays its accessible name and becomes its tooltip, so the date beside it keeps its room.
defineProps<{ label: string; icon: Component }>();
defineEmits<{ click: [event: MouseEvent] }>();
</script>
<template>
    <Button
        variant="ghost"
        size="sm"
        type="button"
        :title="label"
        data-quick-action
        @click="$emit('click', $event)"
        ><component
            :is="icon"
            class="size-4 shrink-0"
            aria-hidden="true"
        /><span class="sr-only @sm:not-sr-only" data-quick-action-label>{{
            label
        }}</span></Button
    >
</template>
