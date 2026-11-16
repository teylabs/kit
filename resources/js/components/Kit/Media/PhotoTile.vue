<script setup lang="ts">
import { Camera } from '@lucide/vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import BulkDrop from '@/components/Kit/Form/BulkDrop.vue';
import type { DirectRoutes } from '@/components/Kit/Form/directUpload';

/*
 * The one empty photo slot: a dashed square or 4/3 tile with a camera icon and a label. It takes a drop, a paste or a click (BulkDrop, compact) and emits
 * `files`; with `direct` it uploads them itself and emits `uploaded`. MediaCollection's photo-tile empty state and add tile, and PhotoField's empty slot.
 */
withDefaults(
    defineProps<{
        label: string;
        name?: string;
        accept: string[];
        maxBytes: number;
        multiple?: boolean;
        maxFiles?: number;
        aspect?: 'square' | '4/3';
        disabled?: boolean;
        /** Shows progress on the tile (a string is its text) and blocks adding. */
        busy?: boolean | string;
        direct?: DirectRoutes;
    }>(),
    {
        name: 'photo',
        aspect: 'square',
        multiple: false,
        disabled: false,
        busy: false,
    },
);
const emit = defineEmits<{
    files: [files: File[]];
    uploaded: [results: unknown[]];
}>();
</script>
<template>
    <BulkDrop
        :name="name"
        :label="label"
        :accept="accept"
        :max-bytes="maxBytes"
        :has-files="false"
        :multiple="multiple"
        :max-files="maxFiles"
        :disabled="disabled || !!busy"
        :direct="direct"
        compact
        handle-drops
        @files="emit('files', $event)"
        @uploaded="emit('uploaded', $event)"
    >
        <template #default="{ browse }">
            <button
                type="button"
                :class="[
                    aspect === 'square' ? 'aspect-square' : 'aspect-4/3',
                    'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40 focus-visible:outline-ring flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed',
                ]"
                :disabled="disabled || !!busy"
                :aria-busy="busy ? 'true' : undefined"
                data-photo-tile
                :data-aspect="aspect"
                @click="browse"
            >
                <template v-if="busy">
                    <Spinner />
                    <span
                        class="text-foreground font-medium"
                        data-photo-tile-busy
                        >{{
                            typeof busy === 'string' ? busy : 'Uploading…'
                        }}</span
                    >
                </template>
                <template v-else>
                    <Camera class="size-6" aria-hidden="true" />
                    <span class="text-foreground font-medium">{{ label }}</span>
                </template>
            </button>
        </template>
    </BulkDrop>
</template>
