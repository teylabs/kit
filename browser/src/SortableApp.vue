<script setup lang="ts">
import { ref } from 'vue';
import SortableList from '@/components/Kit/Data/SortableList.vue';
import Repeater from '@/components/Kit/Form/Repeater.vue';

// The kit's shared sortable (?sortable): a SortableList whose rows hold links (update mode, the host applies the ids), a SortableList on the app's reorder action
// (?sortable=reorder) over a stand-in versioned server like WorkbenchReorderApp's, and a Repeater with `draggable`. Emitted orders, saves and conflicts land in
// window.kitEvents; window.kitServer.stale() makes the next write's token stale.
type Sent = { order?: string[]; version?: number | null };
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
const server = ((window as unknown as { kitServer: { version: number; log: { url: string; sent: number | null; status: number; order?: string[] }[]; stale: () => void } }).kitServer = { version: 1, log: [], stale: () => { server.version += 10; } });
const json = { 'content-type': 'application/json' };
(window as unknown as { kitHttpClient: unknown }).kitHttpClient = {
    async request(config: { url: string; data?: Sent }) {
        const sent = config.data?.version ?? null;
        if (sent !== server.version) {
            server.log.push({ url: config.url, sent, status: 409, order: config.data?.order });
            throw Object.assign(new Error('Request failed with status 409'), { response: { status: 409, data: JSON.stringify({ error: 'stale_write' }), headers: json } });
        }
        server.version++;
        server.log.push({ url: config.url, sent, status: 200, order: config.data?.order });
        return { status: 200, data: JSON.stringify({ version: server.version }), headers: json };
    },
};
const reorderMode = new URLSearchParams(location.search).get('sortable') === 'reorder';
const slides = ref(['Intro', 'Problem', 'Plan', 'Close'].map((title, index) => ({ id: `s${index + 1}`, title })));
const label = (item: { title: string }) => `Slide ${item.title}`;
function updated(ids: string[]) {
    events.push(`update ${ids.join(',')}`);
    slides.value = ids.map(id => slides.value.find(slide => slide.id === id)!);
}
const rows = [{ id: 'r1', name: 'Alpha' }, { id: 'r2', name: 'Beta' }, { id: 'r3', name: 'Gamma' }];
</script>
<template>
    <main class="grid max-w-xl gap-8 p-6">
        <h1 class="text-base font-semibold">Sortable</h1>
        <section aria-labelledby="slides-heading" data-sortable-fixture="slides">
            <h2 id="slides-heading" class="text-sm font-medium">Slides</h2>
            <SortableList
                v-if="reorderMode"
                :items="slides"
                :item-label="label"
                label="Slides"
                :reorder="{ url: '/decks/1/order', method: 'put' }"
                :version="1"
                @saved="version => events.push(`saved ${version}`)"
                @conflict="order => events.push(`conflict ${order.join(',')}`)"
            >
                <template #default="{ item }"><a :href="`#${item.id}`" class="text-sm underline">{{ item.title }}</a></template>
            </SortableList>
            <SortableList v-else :items="slides" :item-label="label" label="Slides" @update="updated">
                <template #default="{ item }"><a :href="`#${item.id}`" class="text-sm underline">{{ item.title }}</a> <button type="button" class="text-xs">Edit</button></template>
            </SortableList>
        </section>
        <form aria-label="Rows form" data-sortable-fixture="repeater" @submit.prevent>
            <Repeater name="rows" :rows="rows" item-label="Row" add-label="Add row" draggable @change="ids => events.push(`repeater ${ids.join(',')}`)">
                <template #row="{ row, name }"><label class="grid gap-1 text-sm">Name <input :name="name('name')" :value="row.name" class="rounded border px-2" /></label></template>
            </Repeater>
        </form>
    </main>
</template>
