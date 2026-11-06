import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Kit Deliver, headless history: deliveryEvents() (deliveryEvents.ts, type-only imports, plain TypeScript) runs here; DeliverPanel's card surface, `history` prop and exposed resend()
// are checked by source and typechecked with the kit's clients.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-deliver-'));
writeFileSync(join(dir, 'deliveryEvents.ts'), read('Deliver/deliveryEvents.ts.stub'));
const { deliveryEvents } = await import(pathToFileURL(join(dir, 'deliveryEvents.ts')).href);

const recipient = (address, status = 'delivered') => ({ role: 'to', address, name: null, status });
const entry = (id, state, extra = {}) => ({
    id, state, test: false, by: 'Sam', channel: 'mail', createdAt: '2026-11-01T09:00:00Z', sendAt: null, sentAt: null, subject: 'Report',
    attachment: 'linked', recipients: [recipient('a@example.test')], stillFinal: true, linkActive: true, firstOpenedAt: null, note: null,
    resentFromId: null, actions: [], ...extra,
});

test('one event per delivery, newest first, at its sent, due or created time', () => {
    const events = deliveryEvents([
        entry(1, 'sent', { sentAt: '2026-11-02T10:00:00Z' }),
        entry(2, 'scheduled', { sendAt: '2026-11-05T08:00:00Z' }),
        entry(3, 'cancelled', { createdAt: '2026-11-03T12:00:00Z', note: 'Cancelled by Sam.' }),
    ]);
    assert.deepEqual(events.map(event => [event.key, event.kind, event.at]), [
        ['delivery-2', 'scheduled', '2026-11-05T08:00:00Z'],
        ['delivery-3', 'cancelled', '2026-11-03T12:00:00Z'],
        ['delivery-1', 'sent', '2026-11-02T10:00:00Z'],
    ]);
    assert.equal(events[1].note, 'Cancelled by Sam.');
});

test('a bounce or complaint makes the delivery bounced and names the addresses; an unconfirmed send says so', () => {
    const [bounced] = deliveryEvents([entry(4, 'sent', { sentAt: '2026-11-02T10:00:00Z', recipients: [recipient('a@example.test'), recipient('b@example.test', 'bounced'), recipient('c@example.test', 'complained')] })]);
    assert.equal(bounced.kind, 'bounced');
    assert.deepEqual(bounced.bounced.map(r => r.address), ['b@example.test', 'c@example.test']);
    assert.equal(deliveryEvents([entry(5, 'unknown')])[0].kind, 'unconfirmed');
    assert.equal(deliveryEvents([entry(6, 'failed')])[0].kind, 'failed');
});

test('the event carries the entry and its actions, so the app can hand a Resend to the panel', () => {
    const action = { key: 'resend', label: 'Resend', url: '/d/7/resend', method: 'post' };
    const [event] = deliveryEvents([entry(7, 'sent', { sentAt: '2026-11-02T10:00:00Z', actions: [action] })]);
    assert.equal(event.entry.id, 7);
    assert.deepEqual(event.actions, [action]);
    assert.deepEqual(deliveryEvents([]), []);
});

test('DeliverPanel: the form sits on a kit Card, the history is optional, and resend() is exposed', () => {
    const panel = read('Deliver/DeliverPanel.vue.stub');
    assert.match(panel, /<Card data-deliver-card>\s*<CardBody>\s*<form class="grid gap-4" data-deliver-form/);
    assert.match(panel, /history\?: boolean/);
    assert.match(panel, /\{ history: true \}/);
    assert.match(panel, /<section v-if="history"[^>]*data-deliver-history/);
    assert.match(panel, /defineExpose\(\{ resend: startResend \}\)/);
});
