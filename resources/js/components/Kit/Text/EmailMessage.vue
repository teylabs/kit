<script setup lang="ts">
import { Paperclip } from '@lucide/vue';
import { computed } from 'vue';
import Card from '@/components/Kit/Card/Card.vue';
import CardBody from '@/components/Kit/Card/CardBody.vue';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import Collapsible from '@/components/Kit/Layout/Collapsible.vue';
import { forwardSummary, splitForwards } from './emailForwards';
import TextCode from './TextCode.vue';
import TextLong from './TextLong.vue';
/*
 * One email, read as a mail client shows it (docs/text.md): the header rows (From, To, Cc, Reply-To, Date, Subject; a row with no value is
 * left out), then the body as a TextCode card ("Message", with Copy and Wrap, scrolling inside itself) or, with `limit`, a TextLong cut with
 * Show more, then the attachments by name. More goes in the default slot, in its own band. With `title` a heading of `level` names the card.
 * Forwarded blocks (Gmail, Apple Mail, Outlook; emailForwards.ts) are found by default: the text above the first one stays the body, and each
 * forward is a collapsed disclosure "Forwarded from <sender>, <date>" holding its header lines, with its own text shown below it. A body
 * with no forward renders exactly as before; `:forwarded="false"` turns the split off.
 * Hooks: `data-email-message` on the card, `data-email-row="from|to|cc|reply-to|date|subject"`, `data-email-body`, `data-email-attachments`,
 * `data-email-forward="<depth>"`, `data-email-forward-headers`, `data-email-forward-body`.
 */
const props = withDefaults(
    defineProps<{
        from: string | null;
        to?: string[];
        cc?: string[];
        replyTo?: string[];
        date?: string | null;
        subject?: string | null;
        body?: string | null;
        attachments?: string[];
        title?: string;
        level?: 2 | 3 | 4;
        limit?: number;
        forwarded?: boolean;
    }>(),
    {
        to: () => [],
        cc: () => [],
        replyTo: () => [],
        date: null,
        subject: null,
        body: null,
        attachments: () => [],
        title: undefined,
        level: 2,
        limit: undefined,
        forwarded: true,
    },
);
const split = computed(() =>
    props.forwarded && props.body
        ? splitForwards(props.body)
        : { body: props.body ?? '', forwards: [] },
);
const bodyLevel = computed(() =>
    props.title ? ((props.level + 1) as 3 | 4 | 5) : props.level,
);
const rows = computed(() =>
    [
        { key: 'from', label: 'From', value: props.from },
        { key: 'to', label: 'To', value: props.to.join(', ') || null },
        { key: 'cc', label: 'Cc', value: props.cc.join(', ') || null },
        {
            key: 'reply-to',
            label: 'Reply-To',
            value: props.replyTo.join(', ') || null,
        },
        { key: 'date', label: 'Date', value: props.date },
        { key: 'subject', label: 'Subject', value: props.subject },
    ].filter((row) => row.value),
);
</script>
<template>
    <Card data-email-message>
        <CardBody class="grid gap-4">
            <component
                :is="`h${level}`"
                v-if="title"
                class="text-base font-semibold"
                >{{ title }}</component
            >
            <dl
                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm"
            >
                <template v-for="row in rows" :key="row.key">
                    <dt
                        class="text-muted-foreground"
                        :data-email-label="row.key"
                    >
                        {{ row.label }}
                    </dt>
                    <dd
                        class="min-w-0 break-words"
                        :class="row.key === 'subject' && 'font-semibold'"
                        :data-email-row="row.key"
                    >
                        <DateTime
                            v-if="row.key === 'date'"
                            :value="row.value!"
                        />
                        <template v-else>{{ row.value }}</template>
                    </dd>
                </template>
            </dl>
            <div
                v-if="body"
                class="grid min-w-0 gap-4 border-t pt-4"
                data-email-body
            >
                <template v-if="split.body">
                    <TextLong
                        v-if="limit"
                        class="text-sm"
                        :text="split.body"
                        :limit="limit"
                    />
                    <TextCode
                        v-else
                        :content="split.body"
                        title="Message"
                        card
                        :level="bodyLevel"
                        max-height="md"
                    />
                </template>
                <div
                    v-for="forward in split.forwards"
                    :key="forward.depth"
                    class="grid min-w-0 gap-2"
                    :data-email-forward="forward.depth"
                >
                    <div class="rounded-md border px-3">
                        <Collapsible
                            :title="forwardSummary(forward)"
                            :level="bodyLevel"
                        >
                            <dl
                                class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm"
                                data-email-forward-headers
                            >
                                <template
                                    v-for="(header, index) in forward.headers"
                                    :key="index"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ header.name }}
                                    </dt>
                                    <dd class="min-w-0 break-words">
                                        {{ header.value }}
                                    </dd>
                                </template>
                            </dl>
                        </Collapsible>
                    </div>
                    <div v-if="forward.body" data-email-forward-body>
                        <TextLong
                            v-if="limit"
                            class="text-sm"
                            :text="forward.body"
                            :limit="limit"
                        />
                        <TextCode
                            v-else
                            :content="forward.body"
                            :title="
                                forward.sender
                                    ? `Message from ${forward.sender}`
                                    : 'Forwarded message'
                            "
                            card
                            :level="bodyLevel"
                            max-height="md"
                        />
                    </div>
                </div>
            </div>
            <ul
                v-if="attachments.length"
                class="flex flex-wrap gap-2 border-t pt-4"
                data-email-attachments
            >
                <li
                    v-for="name in attachments"
                    :key="name"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-sm"
                >
                    <Paperclip
                        class="text-muted-foreground size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ name }}</span>
                </li>
            </ul>
        </CardBody>
        <CardBody v-if="$slots.default">
            <slot />
        </CardBody>
    </Card>
</template>
