<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { actionIcons } from '@/components/Kit/Action/actionIcons';
import FileName from '@/components/Kit/Data/FileName.vue';
import MiddleTruncate from '@/components/Kit/Data/MiddleTruncate.vue';
import type { MenuSubject, MenuSubjectAction } from './menuSubject';

// INTERNAL. A menu's subject as a header row above its items (README, "Menu subject"): the title (one line, the full text in a tooltip) and a muted caption that
// wraps, with a divider below. The text is not focusable and not an item, so the arrow keys skip it; the menu content names it in `aria-describedby` (`id`, on
// the title, and the caption's id after it), so a screen reader hears the trigger's name ("Actions for Kilometres, menu") and then the subject. The subject's `actions` (at most three) are
// icon menu items at the end of the caption line (the title keeps the full width), first in the arrow-key order: no separate toolbar and no extra tab stop.
const props = defineProps<{ subject: MenuSubject; id: string }>();
const actions = computed(() => (props.subject.actions ?? []).slice(0, 3));
const iconOf = (action: MenuSubjectAction) => actionIcons[action.icon];
function choose(action: MenuSubjectAction, event: Event) {
    if (action.disabled) return event.preventDefault();
    action.onSelect?.();
}
const itemClass =
    'text-muted-foreground hover:text-foreground focus:text-foreground size-8 justify-center p-0';
</script>
<template>
    <div
        class="border-border -mx-1 -mt-1 mb-1 grid max-w-64 min-w-0 gap-0.5 border-b px-3 py-2"
        data-menu-subject
    >
        <FileName
            v-if="subject.kind === 'file'"
            :id="id"
            :name="subject.title"
            class="text-foreground text-sm font-medium"
            data-menu-subject-title
        />
        <MiddleTruncate
            v-else-if="subject.kind"
            :id="id"
            :value="subject.title"
            :kind="subject.kind"
            class="text-foreground text-sm font-medium"
            data-menu-subject-title
        />
        <span
            v-else
            :id="id"
            class="text-foreground truncate text-sm font-medium"
            :title="subject.title"
            data-menu-subject-title
            >{{ subject.title }}</span
        >
        <div
            v-if="subject.caption || actions.length"
            class="flex min-w-0 items-center gap-2"
        >
            <span
                v-if="subject.caption"
                :id="`${id}-caption`"
                class="text-muted-foreground min-w-0 flex-1 text-xs text-pretty"
                data-menu-subject-caption
                >{{ subject.caption }}</span
            >
            <div
                v-if="actions.length"
                class="-my-1 -mr-2 ml-auto flex shrink-0 items-center"
                data-menu-subject-actions
            >
                <template
                    v-for="action in actions"
                    :key="action.key ?? action.label"
                >
                    <DropdownMenuItem
                        v-if="action.url && !action.disabled"
                        as-child
                        :class="itemClass"
                        :aria-label="action.label"
                        :title="action.label"
                        :data-menu-subject-action="action.key ?? action.label"
                    >
                        <a
                            v-if="action.newTab"
                            :href="action.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            ><component
                                :is="iconOf(action)"
                                class="size-4"
                                aria-hidden="true"
                        /></a>
                        <Link v-else :href="action.url"
                            ><component
                                :is="iconOf(action)"
                                class="size-4"
                                aria-hidden="true"
                        /></Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else
                        :class="[
                            itemClass,
                            action.disabled && 'cursor-not-allowed opacity-50',
                        ]"
                        :aria-label="action.label"
                        :aria-disabled="action.disabled ? 'true' : undefined"
                        :title="
                            (action.disabled && action.reason) || action.label
                        "
                        :data-menu-subject-action="action.key ?? action.label"
                        @select="choose(action, $event)"
                        ><component
                            :is="iconOf(action)"
                            class="size-4"
                            aria-hidden="true"
                    /></DropdownMenuItem>
                </template>
            </div>
        </div>
    </div>
</template>
