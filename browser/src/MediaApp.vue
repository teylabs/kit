<script setup lang="ts">
import { ref } from 'vue';
import ItemStack from '@/components/Kit/Media/ItemStack.vue';
import MediaCollection from '@/components/Kit/Media/MediaCollection.vue';
import MediaGallery from '@/components/Kit/Media/MediaGallery.vue';
import PhotoField from '@/components/Kit/Media/PhotoField.vue';

// The media family (?media): a read-only MediaGallery capped at three, MediaCollection as tiles (badge, selected, dim, pending, tile actions) and empty
// as a photo tile, an ItemStack on a stand-in versioned reorder server (window.kitServer.stale() makes the next write stale), and PhotoField with and
// without a photo. Emitted events land in window.kitEvents.
type Sent = { order?: string[]; version?: number | null };
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
const server = ((window as unknown as { kitServer: { version: number; log: { sent: number | null; status: number }[]; stale: () => void } }).kitServer = { version: 1, log: [], stale: () => { server.version += 10; } });
const json = { 'content-type': 'application/json' };
(window as unknown as { kitHttpClient: unknown }).kitHttpClient = {
    async request(config: { data?: Sent }) {
        const sent = config.data?.version ?? null;
        if (sent !== server.version) {
            server.log.push({ sent, status: 409 });
            throw Object.assign(new Error('Request failed with status 409'), { response: { status: 409, data: JSON.stringify({ error: 'stale_write' }), headers: json } });
        }
        server.version++;
        server.log.push({ sent, status: 200 });
        return { status: 200, data: JSON.stringify({ version: server.version }), headers: json };
    },
};
const pixel = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="8" height="8"%3E%3Crect width="8" height="8" fill="%23888"/%3E%3C/svg%3E';
const attachments = [
    { id: 'a1', name: 'front.jpg', url: pixel, mimeType: 'image/jpeg', thumbnail: pixel },
    { id: 'a2', name: 'side.jpg', url: pixel, mimeType: 'image/jpeg', thumbnail: null },
    { id: 'a3', name: 'notes.pdf', url: '/notes.pdf', mimeType: 'application/pdf' },
    { id: 'a4', name: 'back.jpg', url: pixel, mimeType: 'image/jpeg', thumbnail: pixel },
    { id: 'a5', name: 'roof.jpg', url: pixel, mimeType: 'image/jpeg', thumbnail: pixel },
];
const photos = {
    collection: 'photos',
    version: '1',
    items: [
        { id: 'p1', name: 'Front', mime: 'image/jpeg', src: pixel, thumb: pixel },
        { id: 'p2', name: 'Kitchen', mime: 'image/jpeg', src: pixel, thumb: pixel },
        { id: 'p3', name: 'Garage', mime: 'image/jpeg', src: pixel, thumb: null, pending: true },
    ],
};
const direct = { request: '/uploads', confirm: '/uploads/confirm' };
const shots = ref(['One', 'Two', 'Three'].map((label, index) => ({ id: `s${index + 1}`, label, thumb: pixel, preview: pixel })));
const shot = ref(0);
const arranging = ref(false);
</script>
<template>
    <main class="grid max-w-3xl gap-8 p-6">
        <MediaGallery :items="attachments" label="Note attachments" :max-thumbnails="3" filenames />
        <MediaCollection
            :collection="photos"
            label="Subject photos"
            :accept="['image/jpeg']"
            :max-bytes="10485760"
            layout="tiles"
            :direct="direct"
            :selected-ids="['p2']"
            :badges="{ p1: 'Main photo' }"
            :dim-ids="['p3']"
            :tile-actions="[{ key: 'main', label: 'Make main photo' }, { key: 'remove', label: 'Remove', destructive: true }]"
            @action="(key, item) => events.push(`action ${key} ${item.id}`)"
            @show="(item) => events.push(`show ${item.id}`)"
        />
        <div data-fixture="empty-tiles">
            <MediaCollection
                :collection="{ collection: 'logo', version: '1', items: [] }"
                label="Add a photo"
                :accept="['image/jpeg']"
                :max-bytes="10485760"
                empty="photo-tile"
                :direct="direct"
            />
        </div>
        <div class="max-w-xs">
            <ItemStack
                v-model:index="shot"
                v-model:fanout-open="arranging"
                :items="shots"
                label="Listing"
                :noun="['shot', 'shots']"
                :actions="{ add: true, reorder: { url: '/shots/order', method: 'post' }, remove: true }"
                :version="1"
                @add="events.push('add')"
                @conflict="(order) => events.push(`conflict ${order.join(',')}`)"
                @saved="(version) => events.push(`saved ${version}`)"
            />
        </div>
        <PhotoField
            :photo="{ thumb: pixel, card: pixel }"
            label="Main photo"
            :accept="['image/jpeg']"
            :max-bytes="10485760"
            :actions="{ replace: { ticket: '/uploads', register: '/main' }, choose: { url: '/main/choose', method: 'post' }, remove: { url: '/main', method: 'delete' } }"
            :sources="[{ id: 'p2', name: 'Kitchen', thumb: pixel }]"
            :version="3"
        />
        <div data-fixture="empty-photo">
            <PhotoField :photo="null" label="Workspace logo" :accept="['image/png']" :max-bytes="1048576" :actions="{ replace: { ticket: '/uploads', register: '/logo' } }" />
        </div>
    </main>
</template>
