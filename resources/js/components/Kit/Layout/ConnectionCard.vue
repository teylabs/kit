<script setup lang="ts">
import { computed } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import DateTime from '@/components/Kit/Data/DateTime.vue';
import Badge from '@/components/Kit/Feedback/Badge.vue';
import SectionHeader from '@/components/Kit/Layout/SectionHeader.vue';
import { provideCard } from '@/components/Kit/Surface/surface';

// One external connection on a settings page (docs/connection-card.md), the shape of an accounting, payments or agent provider: its name, a status badge
// (connected, not connected, checking, failed), what it is connected to and since when, the last check, the last error, and the connect or disconnect
// `actions`. `status` checking marks it aria-busy and says so; its actions stay usable (Disconnect cancels a stuck check). The header keeps one height with or without `description`, so a row of cards aligns. Every state is the same Badge with a dot; only the tone changes. The `#default` slot holds extra settings (a FormCard stays outside, as its own section). Its own card.
type Status = 'connected' | 'disconnected' | 'checking' | 'error';
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        status: Status;
        account?: string | null;
        connectedAt?: string | null;
        lastCheckedAt?: string | null;
        error?: string | null;
        actions?: ActionDescriptor[];
        level?: 2 | 3;
    }>(),
    {
        description: null,
        account: null,
        connectedAt: null,
        lastCheckedAt: null,
        error: null,
        actions: () => [],
        level: 2,
    },
);
defineEmits<{ action: [action: ActionDescriptor] }>();
provideCard();
const badge = computed(
    () =>
        (
            ({
                connected: ['success', 'Connected'],
                disconnected: ['neutral', 'Not connected'],
                checking: ['info', 'Checking…'],
                error: ['danger', 'Failed'],
            }) as const
        )[props.status],
);
</script>
<template>
    <section
        :aria-label="title"
        :aria-busy="status === 'checking' || undefined"
        class="bg-card text-card-foreground divide-border divide-y rounded-xl border shadow-xs"
        :data-connection="status"
    >
        <div class="flex flex-wrap items-start justify-between gap-3 px-6 py-4">
            <div class="flex min-w-0 flex-1 flex-col">
                <SectionHeader
                    :title="title"
                    :description="description"
                    :level="level"
                />
                <p
                    v-if="!description"
                    aria-hidden="true"
                    class="invisible mt-0.5 text-sm"
                    data-description-placeholder
                >
                    &#8203;
                </p>
            </div>
            <Badge :tone="badge[0]" dot>{{ badge[1] }}</Badge>
        </div>
        <div class="flex flex-col gap-3 p-6">
            <dl
                v-if="account || connectedAt || lastCheckedAt"
                class="text-muted-foreground grid gap-1 text-sm"
                data-connection-facts
            >
                <div v-if="account" class="flex gap-2">
                    <dt>Connected to</dt>
                    <dd class="text-foreground">{{ account }}</dd>
                </div>
                <div v-if="connectedAt" class="flex gap-2">
                    <dt>Since</dt>
                    <dd class="text-foreground">
                        <DateTime :value="connectedAt" format="date" />
                    </dd>
                </div>
                <div v-if="lastCheckedAt" class="flex gap-2">
                    <dt>Last check</dt>
                    <dd class="text-foreground">
                        <DateTime :value="lastCheckedAt" />
                    </dd>
                </div>
            </dl>
            <p
                v-if="status === 'checking'"
                class="text-muted-foreground text-sm"
                role="status"
                data-connection-checking
            >
                Checking the connection…
            </p>
            <p
                v-else-if="status === 'disconnected' && !account"
                class="text-muted-foreground text-sm"
                data-connection-empty
            >
                Not connected yet.
            </p>
            <p
                v-if="error"
                role="alert"
                class="text-destructive text-sm"
                data-connection-error
            >
                {{ error }}
            </p>
            <slot />
            <ActionBar
                v-if="actions.length"
                :actions="actions"
                @action="(a: ActionDescriptor) => $emit('action', a)"
            />
        </div>
    </section>
</template>
