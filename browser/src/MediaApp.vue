<script setup lang="ts">
import { ref } from 'vue';
import ItemStack from '@/components/Kit/Media/ItemStack.vue';
import MediaCollection from '@/components/Kit/Media/MediaCollection.vue';
import MediaGallery from '@/components/Kit/Media/MediaGallery.vue';
import PhotoField from '@/components/Kit/Media/PhotoField.vue';
import PhotoTile from '@/components/Kit/Media/PhotoTile.vue';
import RowActions from '@/components/Kit/Table/RowActions.vue';

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
// Tiles labelled by the host (item.label, or the #tile-label slot), with long file names that the ⋯ menu's subject header cuts in the middle.
const labelled = {
    collection: 'labelled',
    version: '1',
    items: [
        { id: 'l1', name: 'Screenshot-2026-11-16-at-10.36.11-AM.png', label: 'Feature', caption: 'Photo 1 · in report', mime: 'image/png', src: pixel, thumb: pixel },
        { id: 'l2', name: 'listing-photo-from-the-dealer-website-2026.jpg', label: 'Photo 2', mime: 'image/jpeg', src: pixel, thumb: pixel },
        { id: 'l3', name: 'IMG_20261116_103611_HDR_portrait_mode.jpg', mime: 'image/jpeg', src: pixel, thumb: pixel },
    ],
};
const shotMenu = [{ key: 'remove', label: 'Remove', url: '#', method: 'delete', destructive: true, icon: 'trash', confirm: null }];
const direct = { request: '/uploads', confirm: '/uploads/confirm' };
const shots = ref(['One', 'Two', 'Three'].map((label, index) => ({ id: `s${index + 1}`, label, thumb: pixel, preview: pixel })));
const shot = ref(0);
const arranging = ref(false);
const single = ref([{ id: 'one', label: 'One', thumb: pixel, preview: pixel }]);
const headedOpen = ref(false);
// Lightbox only: no thumbnail row, opened through the exposed show().
const viewer = ref<InstanceType<typeof MediaGallery>>();
// A strip the host saves itself (sortable, no reorder url), numbered from 3, with an upload in progress.
const strip = ref({ ...photos, collection: 'strip', items: photos.items.slice(0, 2).map((item) => ({ ...item, id: `x${item.id}` })) });
function reordered(from: number, to: number) {
    events.push(`reordered ${from} ${to}`);
    const items = [...strip.value.items];
    items.splice(to, 0, items.splice(from, 1)[0]);
    strip.value = { ...strip.value, items };
}
// ItemStack with the host's own save: window.kitPersist.conflict makes the next one a conflict.
const persisted = ref(['Red', 'Green', 'Blue'].map((label, index) => ({ id: `c${index + 1}`, label, thumb: pixel })));
const persistOpen = ref(false);
const kitPersist = ((window as unknown as { kitPersist: { conflict: boolean; calls: { order: string[]; version: string | number | null }[] } }).kitPersist = { conflict: false, calls: [] });
async function persist(order: string[], version: string | number | null) {
    kitPersist.calls.push({ order, version });
    return kitPersist.conflict ? { ok: false, conflict: true } : { ok: true, version: Number(version) + 1 };
}
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
            :tile-actions="[{ key: 'main', label: 'Make main photo', icon: 'image', attrs: { 'data-test': 'make-main' } }, { key: 'remove', label: 'Remove', destructive: true, icon: 'trash' }]"
            @action="(key, item) => events.push(`action ${key} ${item.id}`)"
            @show="(item) => events.push(`show ${item.id}`)"
        />
        <div data-fixture="labelled" class="bg-background text-foreground p-2" style="width: 420px">
            <MediaCollection
                :collection="labelled"
                label="Labelled"
                :accept="['image/jpeg']"
                :max-bytes="10485760"
                layout="tiles"
                :tile-actions="[{ key: 'remove', label: 'Remove', destructive: true, icon: 'trash' }]"
            >
                <template #tile-label="{ item, index }"><template v-if="item.id === 'l3'">Slot {{ index + 1 }}</template><template v-else>{{ item.label }}</template></template>
            </MediaCollection>
        </div>
        <div data-fixture="viewer">
            <MediaGallery ref="viewer" :items="attachments" label="Viewer" :thumbnails="false" />
            <button type="button" data-open-viewer @click="viewer?.show('a4')">Open back</button>
        </div>
        <MediaCollection
            :collection="strip"
            label="Strip"
            :accept="['image/jpeg']"
            :max-bytes="10485760"
            layout="tiles"
            :direct="direct"
            sortable
            :index-offset="2"
            :max-files="4"
            busy="Uploading… 40%"
            @reordered="reordered"
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
        <div data-fixture="host-upload">
            <MediaCollection
                :collection="{ collection: 'host', version: '1', items: [] }"
                label="Host photos"
                :accept="['image/jpeg']"
                :max-bytes="10485760"
                empty="photo-tile"
                host-upload
                @files="(files) => events.push(`host-files:${files.map((file) => file.name).join(',')}`)"
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
        <div class="max-w-xs" data-fixture="persist">
            <ItemStack
                v-model:fanout-open="persistOpen"
                :items="persisted"
                label="Colours"
                :noun="['colour', 'colours']"
                :persist="persist"
                :version="5"
                @conflict="(order) => events.push(`persist-conflict ${order.join(',')}`)"
                @saved="(version) => events.push(`persist-saved ${version}`)"
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
        <div data-fixture="emit-photo">
            <PhotoField
                :photo="{ thumb: pixel, card: pixel }"
                label="Cover"
                title="Swap the cover"
                :accept="['image/png']"
                :max-bytes="1048576"
                :actions="{ replace: true, remove: { url: '/cover', method: 'delete', confirm: { title: 'Take the cover off?', description: 'Lists show a placeholder instead.', label: 'Take off' } } }"
                @files="(files) => events.push(`files ${files.map((f) => f.name).join(',')}`)"
            />
        </div>
        <div data-fixture="endpoint-photo">
            <PhotoField :photo="null" label="Badge" :accept="['image/png']" :max-bytes="1048576" :actions="{ replace: { url: '/badge', method: 'post' } }" :version="7" />
        </div>
        <div data-fixture="fill-photo" style="height: 320px; width: 420px">
            <PhotoField :photo="{ thumb: pixel, card: pixel }" label="Feature photo" :accept="['image/jpeg']" :max-bytes="10485760" :actions="{ replace: true }" size="fill" />
        </div>
        <div data-fixture="tile-photo" class="grid grid-cols-2 gap-4" style="width: 520px">
            <PhotoField
                variant="tile"
                badge="Main photo"
                :photo="{ thumb: pixel, card: pixel }"
                label="Front"
                :accept="['image/jpeg']"
                :max-bytes="10485760"
                :actions="{ replace: true, remove: { url: '/front', method: 'delete' } }"
                :tile-actions="[{ key: 'download', label: 'Download', icon: 'download' }]"
                @action="(key) => events.push(`photo-action ${key}`)"
            />
            <div data-tall style="height: 480px">Tall sibling</div>
        </div>
        <div data-fixture="tile-empty" style="width: 240px">
            <PhotoField variant="tile" badge="Main photo" :photo="null" label="Front" :accept="['image/jpeg']" :max-bytes="10485760" :actions="{ replace: true }" />
        </div>
        <div data-fixture="tall-row" class="grid grid-cols-2 gap-4" style="width: 520px">
            <PhotoField :photo="{ thumb: pixel, card: pixel }" label="Logo" :accept="['image/png']" :max-bytes="1048576" :actions="{ replace: true }" />
            <div style="height: 480px">Tall sibling</div>
        </div>
        <div data-fixture="fill" style="height: 260px; width: 240px">
            <ItemStack :items="shots" label="Filled" :noun="['shot', 'shots']" fill>
                <template #footer-label="{ index, count }">Listing · {{ index + 1 }} of {{ count }}</template>
            </ItemStack>
        </div>
        <div data-fixture="tile-fill" style="height: 300px; width: 240px">
            <PhotoTile name="fill" label="Add photos" :accept="['image/jpeg']" :max-bytes="10485760" aspect="fill" />
        </div>
        <div data-fixture="narrow-stack" class="bg-background text-foreground" style="width: 220px">
            <ItemStack :items="shots" label="Narrow" :noun="['shot', 'shots']" :actions="{ add: true }" :arrange="false">
                <template #footer-label="{ index, count }">Listing · {{ index + 1 }} of {{ count }}</template>
                <template #menu="{ item, index }"
                    ><RowActions
                        :actions="shotMenu"
                        :label="`Actions for shot ${index + 1}`"
                        variant="menu"
                        trigger="vertical"
                        :intercept="['remove']"
                        :subject="{ title: `Listing-${item.label}-screenshot-2026-11-16.png`, caption: `Listing · ${index + 1} of ${shots.length}`, kind: 'file' }"
                        @act="events.push(`shot-remove ${item.id}`)"
                /></template>
            </ItemStack>
        </div>
        <div data-fixture="single-stack" class="bg-background text-foreground" style="width: 260px">
            <ItemStack :items="single" label="Single" :noun="['shot', 'shots']" :actions="{ add: true }" :arrange="false" @add="events.push('single-add')">
                <template #footer-label="{ index, count }">{{ index + 1 }} of {{ count }}</template>
                <template #menu="{ index }"><RowActions :actions="shotMenu" :label="`Actions for single shot ${index + 1}`" variant="menu" :intercept="['remove']" /></template>
            </ItemStack>
        </div>
        <div data-fixture="headed-stack" class="max-w-xs">
            <ItemStack
                v-model:fanout-open="headedOpen"
                :items="shots"
                label="Headed"
                :noun="['shot', 'shots']"
                title="Listing shots"
                description="Comparable 2 · 1996 Sample Tractor"
                :accept="['image/jpeg']"
                :actions="{ add: true }"
                @add="events.push('headed-add')"
                @files="(files) => events.push(`headed-files ${files.map((f) => f.name).join(',')}`)"
            >
                <template #after><p data-fixture-after>Drop more from the comparable's folder.</p></template>
            </ItemStack>
        </div>
        <div data-fixture="slotted-stack" class="max-w-xs">
            <ItemStack :items="shots" label="Slotted" :noun="['shot', 'shots']" :actions="{ add: true }" @add="events.push('slotted-add')">
                <template #fanout-header="{ count }"><span data-fixture-header>Listing shots · {{ count }} on file</span></template>
            </ItemStack>
        </div>
    </main>
</template>
