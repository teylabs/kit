<script setup lang="ts">
import MiddleTruncate from '@/components/Kit/Data/MiddleTruncate.vue';
import type { MenuSubject } from './menuSubject';

// INTERNAL. A menu's subject as a header row above its items (README, "Menu subject"): the title (one line, the full text in a tooltip) and a muted caption that
// wraps, with a divider below. Not focusable and not an item, so the arrow keys skip it. The menu content names it in `aria-describedby` (`id`), so a screen
// reader hears the trigger's name ("Actions for Kilometres, menu") and then the subject.
defineProps<{ subject: MenuSubject; id: string }>();
</script>
<template>
    <div
        :id="id"
        class="border-border -mx-1 -mt-1 mb-1 grid max-w-64 min-w-0 gap-0.5 border-b px-3 py-2"
        data-menu-subject
    >
        <MiddleTruncate
            v-if="subject.kind"
            :value="subject.title"
            :kind="subject.kind"
            class="text-foreground text-sm font-medium"
            data-menu-subject-title
        />
        <span
            v-else
            class="text-foreground truncate text-sm font-medium"
            :title="subject.title"
            data-menu-subject-title
            >{{ subject.title }}</span
        >
        <span
            v-if="subject.caption"
            class="text-muted-foreground text-xs text-pretty"
            data-menu-subject-caption
            >{{ subject.caption }}</span
        >
    </div>
</template>
