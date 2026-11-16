<script setup lang="ts">
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import ButtonGroup from '@/components/Kit/Action/ButtonGroup.vue';
import DropdownItem from '@/components/Kit/Menu/DropdownItem.vue';
import DropdownLink from '@/components/Kit/Menu/DropdownLink.vue';
import DropdownModalLink from '@/components/Kit/Menu/DropdownModalLink.vue';
import { Button } from '@/components/ui/button';

// ButtonGroup with and without `item-class="gap-2 px-4 py-2"` around DropdownItem and DropdownLink rows (button, anchor, Inertia link) and a DropdownModalLink: the rows match in height.
// ActionBar with `item-class`, for run.mjs (?item-class), invented labels: its More menu's rows take the host's row height, Cancel stays destructive.
const post = (key: string, label: string, extra = {}) => ({ key, label, url: `/orders/12/${key}`, method: 'post', destructive: false, confirm: null, ...extra });
const actions = [
    post('approve', 'Approve order'),
    post('edit', 'Edit details', { description: 'Change the delivery address, the contact and the notes the warehouse sees on the packing slip.' }),
    post('archive', 'Archive order', { disabled: true, reason: 'Already archived' }),
    post('cancel', 'Cancel order', { destructive: true }),
];
</script>
<template>
    <main class="flex min-h-screen flex-col items-start gap-6 bg-background p-4 text-foreground">
        <span data-destructive-probe class="sr-only text-destructive-text" aria-hidden="true"></span>
        <section data-item-class class="w-full"><ActionBar :actions="actions" item-class="min-h-12" /></section>
        <section v-for="cls in [undefined, 'gap-2 px-4 py-2']" :key="cls ?? 'none'" :data-group-item-class="cls ? 'set' : 'unset'">
            <ButtonGroup :item-class="cls" menu-label="More order actions">
                <Button variant="outline">Open order</Button>
                <template #menu>
                    <DropdownItem as="button">Print order<template #caption>Opens the packing slip for the warehouse.</template></DropdownItem>
                    <DropdownItem as="a" href="#print">Download order</DropdownItem>
                    <DropdownItem href="#history">Order history</DropdownItem>
                    <DropdownLink as="button">Resend receipt</DropdownLink>
                    <DropdownLink as="a" href="#notes">Order notes</DropdownLink>
                    <DropdownLink href="#invoice">Order invoice</DropdownLink>
                    <DropdownModalLink href="#refund">Refund order</DropdownModalLink>
                </template>
            </ButtonGroup>
        </section>
        <section data-file-menu class="w-40">
            <ButtonGroup menu-label="Actions for the photo" :menu-subject="{ title: 'Screenshot-2026-11-16-at-10.36.11-AM.png', caption: '2.4 MB · PNG', kind: 'file' }">
                <Button variant="outline">Photo</Button>
                <template #menu><DropdownItem as="button">Set as cover</DropdownItem></template>
            </ButtonGroup>
        </section>
    </main>
</template>
