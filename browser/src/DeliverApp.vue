<script setup lang="ts">
import DeliverPanel from '@/components/Kit/Deliver/DeliverPanel.vue';
import type { DeliverStateView } from '@/components/Kit/Deliver/deliver';

// A deliver panel as the server sends it (?deliver): two live contacts (To and Cc), an internal copy, the defaults, and a history with a sent delivery
// (Resend, Revoke link), a scheduled one (Cancel), an unconfirmed one with its note, and a test. ?deliver=blocked sends Send disabled with the gate's
// reason. The preview answers from window.kitHttpClient; posts are logged to window.kitVisits. ?deliver=dark draws it under .dark.
const mode = new URLSearchParams(location.search).get('deliver');
if (mode === 'dark') document.documentElement.classList.add('dark');
const action = (label: string, url: string, key: string, extra: Partial<{ disabled: boolean; reason: string | null }> = {}) => ({
    label, url, method: 'post', disabled: false, reason: null, confirm: null, key, destructive: false, intent: null, ...extra,
});
const base = '/@acme/deliver/report.deliver/7';
const state: DeliverStateView = {
    definition: 'report.deliver',
    subject: '7',
    sendable: mode !== 'blocked',
    reason: mode === 'blocked' ? 'Finalize it first.' : null,
    defaultSubject: 'Your report: Main St',
    defaultMessage: 'Here is your report.',
    recipientOptions: [
        { address: 'robin@client.test', name: 'Robin Vale', role: 'to' },
        { address: 'sam@client.test', name: 'Sam Lee', role: 'cc' },
    ],
    internalCopies: ['office@example.test'],
    deliveries: [
        { id: 4, state: 'scheduled', test: false, by: 'Ada', channel: 'web', createdAt: '2026-11-05T10:00:00Z', sendAt: '2026-11-06T13:00:00Z', sentAt: null, subject: 'Your report: Main St', attachment: 'attached',
          recipients: [{ role: 'to', address: 'robin@client.test', name: 'Robin Vale', status: 'pending' }], stillFinal: null, linkActive: false, firstOpenedAt: null, note: null, resentFromId: null,
          actions: [action('Cancel', `${base}/deliveries/4/cancel?revision=r1`, 'cancel-4')] },
        { id: 3, state: 'unknown', test: false, by: 'Ada', channel: 'web', createdAt: '2026-11-04T10:00:00Z', sendAt: null, sentAt: null, subject: 'Your report: Main St', attachment: 'attached',
          recipients: [{ role: 'to', address: 'robin@client.test', name: 'Robin Vale', status: 'pending' }], stillFinal: true, linkActive: false, firstOpenedAt: null, note: 'The provider did not confirm the send. Check with the customer before sending again.', resentFromId: null,
          actions: [action('Resend', `${base}/deliveries/3/resend?revision=r1`, 'resend-3')] },
        { id: 2, state: 'sent', test: true, by: 'Ada', channel: 'web', createdAt: '2026-11-03T10:00:00Z', sendAt: null, sentAt: '2026-11-03T10:00:01Z', subject: '[Test] Your report: Main St', attachment: 'attached',
          recipients: [{ role: 'to', address: 'ada@example.test', name: 'Ada', status: 'sent' }], stillFinal: true, linkActive: false, firstOpenedAt: null, note: null, resentFromId: null, actions: [] },
        { id: 1, state: 'sent', test: false, by: 'Ada', channel: 'mcp', createdAt: '2026-11-02T10:00:00Z', sendAt: null, sentAt: '2026-11-02T10:00:01Z', subject: 'Your report: Main St', attachment: 'attached',
          recipients: [
              { role: 'to', address: 'robin@client.test', name: 'Robin Vale', status: 'delivered' },
              { role: 'cc', address: 'sam@client.test', name: 'Sam Lee', status: 'bounced' },
              { role: 'bcc', address: 'office@example.test', name: null, status: 'sent' },
          ], stillFinal: true, linkActive: true, firstOpenedAt: '2026-11-02T12:00:00Z', note: null, resentFromId: null,
          actions: [action('Resend', `${base}/deliveries/1/resend?revision=r1`, 'resend-1'), action('Revoke link', `${base}/deliveries/1/revoke?revision=r1`, 'revoke-1')] },
    ],
    revision: 'r1',
    preview: action('Preview', `${base}/preview`, 'preview'),
    send: action('Send', `${base}/send?revision=r1`, 'send', mode === 'blocked' ? { disabled: true, reason: 'Finalize it first.' } : {}),
    testSend: action('Send me a test', `${base}/test?revision=r1`, 'test'),
    attachmentUrl: `${base}/attachment`,
};
</script>
<template>
    <main class="bg-background mx-auto max-w-2xl p-6">
        <DeliverPanel :state="state" />
    </main>
</template>
