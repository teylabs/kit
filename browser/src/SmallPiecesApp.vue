<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import BusyIndicator from '@/components/Kit/Feedback/BusyIndicator.vue';
import { applyFormDefaults } from '@/components/Kit/Form/formDefaults';
import { reveal } from '@/components/Kit/Layout/reveal';
import EmailMessage from '@/components/Kit/Text/EmailMessage.vue';
import CardHeader from '@/components/Kit/Card/CardHeader.vue';
import ActionBar from '@/components/Kit/Action/ActionBar.vue';
import InertiaAction from '@/components/Kit/Action/InertiaAction.vue';

// Small pieces (?small-pieces): EmailMessage, BusyIndicator, reveal() and applyFormDefaults(). window.revealTarget(duration) reveals the far row.
const applied = applyFormDefaults();
// ?small-pieces&forwards: only the forwarded fixtures (one TextCode "Message" per page keeps axe's landmark-unique quiet).
const forwards = new URLSearchParams(location.search).has('forwards');
// Synthetic: a Gmail forward of an Apple Mail forward.
const forwardedBody = [
    'FYI, see below.',
    '',
    '---------- Forwarded message ---------',
    'From: Ana Lopez <ana@example.test>',
    'Date: Mon, Nov 9, 2026 at 3:14 PM',
    'Subject: Farm order',
    'To: <desk@example.test>',
    '',
    'Hello, please see the order below.',
    '',
    'Begin forwarded message:',
    '',
    'From: Bo Chen <bo@example.test>',
    'Subject: Order',
    'Date: November 8, 2026 at 9:00 AM AST',
    'To: Ana Lopez <ana@example.test>',
    '',
    'Twelve crates, pickup Tuesday.',
].join('\n');
const defaultsOnSuccess = (Form as unknown as { props: { setDefaultsOnSuccess: { default: unknown } } }).props.setDefaultsOnSuccess.default;
(window as unknown as { revealTarget: (duration: number) => void }).revealTarget = (duration) => {
    reveal('#far', { duration });
};
</script>
<template>
    <main class="grid max-w-xl gap-4 p-4">
        <h1>Small pieces</h1>
        <p data-case="defaults">{{ applied }} {{ defaultsOnSuccess }}</p>
        <BusyIndicator class="text-primary">Saving…</BusyIndicator>
        <template v-if="forwards">
        <EmailMessage
            id="forwarded"
            title="Forwarded in"
            from="Desk <desk@example.test>"
            subject="Fwd: Farm order"
            :body="forwardedBody"
        />
        <EmailMessage id="plain-forward" from="desk@example.test" :body="forwardedBody" :forwarded="false" :limit="2000" />
        </template>
        <template v-else>
        <EmailMessage
            title="What came in"
            from="Ana Lopez <ana@example.test>"
            :to="['desk@example.test', 'ops@example.test']"
            subject="New order"
            body="Hello,&#10;Please see attached."
            :attachments="['order.pdf']"
        />
        <EmailMessage id="limited" from="bo@example.test" :body="'word '.repeat(80)" :limit="40" />
        </template>
        <div style="width: 480px">
            <CardHeader id="long-header" title="2014 Audi S5 Cabriolet 3.0 TFSI 2dr Conv Auto Technik">
                <template #actions><button type="button" data-header-action>Edit</button><button type="button">Archive</button></template>
            </CardHeader>
        </div>
        <div id="row-gaps">
            <ActionBar
                :actions="[
                    { key: 'open', label: 'Open', url: '/c/1', method: 'get', group: 'r' },
                    { key: 'dup', label: 'Duplicate', url: '/c/1/dup', method: 'post', group: 'r', menu: true },
                    { key: 'delete', label: 'Delete', url: '/c/1', method: 'delete', destructive: true, group: 'r', menu: true },
                ]"
            />
            <InertiaAction
                :action="{ key: 'val', label: 'Open valuation', url: '/c/1/val', method: 'get', disabled: true, reason: 'No valuation yet' }"
                class="host-class"
                data-host="yes"
            />
        </div>
        <div id="narrow-menu" style="width: 120px">
            <ActionBar
                :actions="[
                    { key: 'edit', label: 'Edit', url: '/l/1/edit', method: 'get', group: 'g' },
                    { key: 'pdf', label: 'Saved listing (PDF)', url: '/l/1.pdf', method: 'get', icon: 'file-text', newTab: true, group: 'g', menu: true, description: 'The listing as saved on 12 Nov' },
                    { key: 'copy', label: 'Copy link', url: '/l/1/copy', method: 'post', group: 'g', menu: true },
                ]"
            />
        </div>
        <div style="height: 2000px" />
        <p id="far">Far row</p>
    </main>
</template>
