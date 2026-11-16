<script setup lang="ts">
// EmptyState with align, variant and an icon by name or component. Compiled by bin/typecheck-components.sh; never rendered.
import { Inbox } from '@lucide/vue';
import EmptyState from '@/components/Kit/Feedback/EmptyState.vue';
</script>
<template>
    <EmptyState title="No visits" align="start" variant="first-use" icon="calendar" />
    <EmptyState title="Nothing yet" :icon="Inbox" compact />
</template>
