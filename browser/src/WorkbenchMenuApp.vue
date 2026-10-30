<script setup lang="ts">
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A Workbench of three items with row menus (?workbench-menu), the host's descriptors per item: Move up is off on the first, Move down on the last. The third
// item's menu is withheld with ?workbench-menu=partial, so an item without actions can be checked. ?workbench-menu=checkbox adds two checkbox rows (Skip slide
// checked, Hide notes unchecked); ?workbench-menu=navitem renders each row's content through #navigator-item. Emitted actions land in window.kitEvents.
// ?workbench-menu=filmstrip lists four reorderable items in the filmstrip layout: a decorative thumbnail (alt ''), a described one in a square image (alt 'Bar
// chart'), none (the placeholder box), and one from /thumbs/slow.svg, which the test holds back to measure the box before the image arrives. Reorders go to a
// stand-in HTTP client that accepts every write and lands in window.kitServer.log.
const query = new URLSearchParams(window.location.search);
const variant = query.get('workbench-menu');
const partial = variant === 'partial';
const filmstrip = variant === 'filmstrip';
const svg = (body: string, w: number, h: number) => `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">${body}</svg>`)}`;
const thumbnails: WorkbenchItem['thumbnail'][] = [
    { src: svg('<rect width="160" height="90" fill="#e2e8f0"/><text x="12" y="50" font-size="20">Title</text>', 160, 90), alt: '' },
    { src: svg('<rect width="64" height="64" fill="#fff"/><rect x="8" y="30" width="12" height="26" fill="#2563eb"/><rect x="26" y="14" width="12" height="42" fill="#2563eb"/>', 64, 64), alt: 'Bar chart' },
    null,
    { src: '/thumbs/slow.svg', alt: '' },
];
if (filmstrip) {
    const server = ((window as unknown as { kitServer: { version: number; log: string[][] } }).kitServer = { version: 1, log: [] });
    (window as unknown as { kitHttpClient: unknown }).kitHttpClient = {
        async request(config: { data?: { order?: string[] } }) {
            server.log.push(config.data?.order ?? []);
            return { status: 200, data: JSON.stringify({ version: ++server.version }), headers: { 'content-type': 'application/json' } };
        },
    };
}
// ?actions adds a navigator action through #navigator-actions ("New slide").
const withActions = query.has('actions');
const events = ((window as unknown as { kitEvents: string[] }).kitEvents = []);
const act = (key: string, label: string, extra: Partial<ActionDescriptor> = {}): ActionDescriptor => ({ key, label, url: `/slides/${key}`, method: 'post', ...extra });
const actions = (index: number, count: number): ActionDescriptor[] => [
    act('move-up', 'Move up', { disabled: index === 0 }),
    act('move-down', 'Move down', { disabled: index === count - 1 }),
    act('duplicate', 'Duplicate'),
    act('delete', 'Delete', { destructive: true }),
    ...(variant === 'checkbox' ? [act('skip', 'Skip slide', { checked: true }), act('hide', 'Hide notes', { checked: false })] : []),
];
const labels = filmstrip ? ['Slide 1', 'Slide 2', 'Slide 3', 'Slide 4'] : ['Slide 1', 'Slide 2', 'Slide 3'];
const items: WorkbenchItem[] = labels.map((label, index) => ({ id: `s${index + 1}`, label, actions: partial && index === 2 ? [] : actions(index, labels.length), ...(filmstrip ? { thumbnail: thumbnails[index] } : {}) }));
const onAction = (action: ActionDescriptor, item?: WorkbenchItem) => events.push(`${action.key} ${item?.id ?? '-'}`);
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <Workbench workbench-key="menu-deck" label="Deck" :items="items" current="s1" filterable :navigator-layout="filmstrip ? 'filmstrip' : 'list'" :reorder="filmstrip ? { url: '/decks/1/order', method: 'put' } : null" :version="filmstrip ? 1 : null" @action="onAction">
            <template v-if="withActions" #navigator-actions><button type="button" class="h-8 rounded-md border px-2 text-sm" data-new-slide @click="events.push('new -')">New slide</button></template>
            <template v-if="variant === 'navitem'" #navigator-item="{ item, selected, index }">
                <span class="truncate" data-host-row>{{ index + 1 }}. {{ item.label }}</span><span v-if="selected" class="sr-only">(selected)</span>
            </template>
        </Workbench>
    </main>
</template>
