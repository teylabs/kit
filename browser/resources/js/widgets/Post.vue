<script setup lang="ts">
import Composer from '@/components/Kit/Form/Composer.vue';
import { ref } from 'vue';

// An app's own seamless widget (dashboard.html `?seamless`): its frame is `headless`, so the card draws no title bar, and its body is the kit Composer alone.
defineProps<{ widget: { title: string } }>();
const body = ref('');
</script>
<template>
    <Composer v-model="body" :label="widget.title" placeholder="Post an update…" data-widget-post />
</template>
