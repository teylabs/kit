<script setup lang="ts">
import Workbench from '@/components/Kit/Workbench/Workbench.vue';
import { useWorkbench } from '@/components/Kit/Workbench/useWorkbench';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import type { WorkbenchItem } from '@/components/Kit/Workbench/workbench';

// A Workbench of three items with the reorder action (?workbench-reorder), over a stand-in server behind Inertia's HTTP client. Like Inertia v3's XHR client, it
// resolves `data` as the raw response text and rejects a 4xx with an HttpResponseError-shaped error. The server keeps one version token: a write must send the
// current one (else 409 stale_write, a null token included) and each accepted write advances it. Every request lands in window.kitServer.log.
type Sent = { order?: string[]; version?: number | null };
const server = ((window as unknown as { kitServer: { version: number; log: { url: string; sent: number | null; status: number }[] } }).kitServer = { version: 1, log: [] });
const json = { 'content-type': 'application/json' };
(window as unknown as { kitHttpClient: unknown }).kitHttpClient = {
    async request(config: { url: string; data?: Sent }) {
        const sent = config.data?.version ?? null;
        if (sent !== server.version) {
            server.log.push({ url: config.url, sent, status: 409 });
            const response = { status: 409, data: JSON.stringify({ error: 'stale_write', version: server.version }), headers: json };
            throw Object.assign(new Error('Request failed with status 409'), { response });
        }
        server.version++;
        server.log.push({ url: config.url, sent, status: 200 });
        return { status: 200, data: JSON.stringify({ version: server.version }), headers: json };
    },
};
const act = (key: string, label: string, extra: Partial<ActionDescriptor> = {}): ActionDescriptor => ({ key, label, url: `/slides/${key}`, method: 'post', ...extra });
const items: WorkbenchItem[] = ['Slide 1', 'Slide 2', 'Slide 3'].map((label, index) => ({ id: `s${index + 1}`, label, actions: [act('duplicate', 'Duplicate'), act('delete', 'Delete', { destructive: true })] }));
// The host's own write, as a page would send it: the Workbench's current version token.
const wb = useWorkbench('reorder-deck');
const onAction = async (action: ActionDescriptor) => {
    try {
        await (window as unknown as { kitHttpClient: { request: (c: { url: string; data: Sent }) => Promise<unknown> } }).kitHttpClient.request({ url: action.url, data: { version: wb.version as number | null } });
    } catch {
        // The 409 is in the server's log.
    }
};
</script>
<template>
    <main class="p-4">
        <h1>Deck</h1>
        <Workbench workbench-key="reorder-deck" label="Deck" :items="items" current="s1" :reorder="{ url: '/decks/1/order', method: 'put' }" :version="1" @action="onAction" />
    </main>
</template>
