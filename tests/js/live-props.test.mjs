import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Live props: liveTransport.ts (plain TypeScript) runs here against a fake Echo; useLive.ts is checked by source, and its Vue and
// Inertia calls are typechecked against the clients the kit supports.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-live-'));
writeFileSync(join(dir, 'liveTransport.ts'), read('Live/liveTransport.ts.stub'));
const { echoLiveTransport, listenerLiveTransport } = await import(pathToFileURL(join(dir, 'liveTransport.ts')).href);
const settle = () => new Promise(resolve => setTimeout(resolve, 0));

function fakeEcho() {
    const log = { opened: [], left: [], listening: new Map() };
    const channel = full => ({
        listen(event, callback) {
            const key = `${full} ${event}`;
            log.listening.set(key, [...(log.listening.get(key) ?? []), callback]);
        },
        stopListening(event, callback) {
            const key = `${full} ${event}`;
            log.listening.set(key, (log.listening.get(key) ?? []).filter(cb => cb !== callback));
        },
    });
    const opening = prefix => name => (log.opened.push(prefix + name), channel(prefix + name));
    return {
        log,
        emit: (full, event, payload) => (log.listening.get(`${full} ${event}`) ?? []).forEach(cb => cb(payload)),
        echo: { channel: opening(''), private: opening('private-'), join: opening('presence-'), leaveChannel: name => log.left.push(name) },
    };
}

test('subscribes on the private channel with the broadcast name dotted, and delivers the payload', async () => {
    const { echo, emit, log } = fakeEcho();
    const got = [];
    echoLiveTransport(() => echo).subscribe({ name: 'authoring.a', type: 'private' }, 'invalidated', p => got.push(p));
    await settle();
    emit('private-authoring.a', '.invalidated', { n: 1 });
    assert.deepEqual(log.opened, ['private-authoring.a']);
    assert.deepEqual(got, [{ n: 1 }]);
});

test('connects lazily, once, on the first subscription', async () => {
    const { echo } = fakeEcho();
    let calls = 0;
    const transport = echoLiveTransport(() => (calls++, echo));
    await settle();
    assert.equal(calls, 0, 'nothing connects before a page subscribes');
    transport.subscribe({ name: 'a', type: 'private' }, 'x', () => {});
    transport.subscribe({ name: 'b', type: 'private' }, 'x', () => {});
    await settle();
    assert.equal(calls, 1);
});

test('no broadcaster (null) or a failed connect: subscriptions do nothing and stop cleanly', async () => {
    for (const connect of [() => null, () => { throw new Error('down'); }, () => Promise.reject(new Error('down'))]) {
        const stop = echoLiveTransport(connect).subscribe({ name: 'a', type: 'private' }, 'x', () => {});
        await settle();
        stop();
    }
});

test('two subscriptions on one channel share it, and it is left only when the last one stops', async () => {
    const { echo, emit, log } = fakeEcho();
    const transport = echoLiveTransport(() => echo);
    const a = [];
    const b = [];
    const stopA = transport.subscribe({ name: 'authoring.a', type: 'private' }, 'invalidated', p => a.push(p));
    const stopB = transport.subscribe({ name: 'authoring.a', type: 'private' }, 'other', p => b.push(p));
    await settle();
    assert.deepEqual(log.opened, ['private-authoring.a'], 'opened once');
    stopA();
    stopA();
    emit('private-authoring.a', '.invalidated', 1);
    emit('private-authoring.a', '.other', 2);
    assert.deepEqual(a, [], 'the stopped one hears nothing');
    assert.deepEqual(b, [2]);
    assert.deepEqual(log.left, [], 'still held by the other');
    stopB();
    assert.deepEqual(log.left, ['private-authoring.a']);
});

test('a stop before the connection resolves never listens', async () => {
    const { echo, log } = fakeEcho();
    const stop = echoLiveTransport(() => Promise.resolve(echo)).subscribe({ name: 'a', type: 'private' }, 'x', () => {});
    stop();
    await settle();
    assert.deepEqual(log.opened, []);
});

test('the event name is configurable: an allow-list and a custom Echo name', async () => {
    const { echo, emit, log } = fakeEcho();
    const got = [];
    const transport = echoLiveTransport(() => echo, { events: ['invalidated'], eventName: e => `App.${e}` });
    transport.subscribe({ name: 'a', type: 'private' }, 'something-else', p => got.push(['else', p]));
    transport.subscribe({ name: 'a', type: 'private' }, 'invalidated', p => got.push(['inv', p]));
    await settle();
    emit('private-a', 'App.invalidated', 1);
    emit('private-a', '.something-else', 2);
    assert.deepEqual(got, [['inv', 1]]);
    assert.deepEqual([...log.listening.keys()], ['private-a App.invalidated']);
});

test('public and presence channels open their own way; an unknown or unsupported type fails closed', async () => {
    const { echo, log } = fakeEcho();
    const transport = echoLiveTransport(() => echo);
    transport.subscribe({ name: 'news' }, 'x', () => {});
    transport.subscribe({ name: 'room', type: 'presence' }, 'x', () => {});
    transport.subscribe({ name: 'secret', type: 'encrypted-private' }, 'x', () => {});
    transport.subscribe({ name: 'odd', type: 'mystery' }, 'x', () => {});
    await settle();
    assert.deepEqual(log.opened, ['news', 'presence-room']);
});

test('connection status is passed through when the host reports it, and absent otherwise', () => {
    const report = () => () => {};
    assert.equal(echoLiveTransport(() => null, { onStatusChange: report }).onStatusChange, report);
    assert.equal('onStatusChange' in echoLiveTransport(() => null), false);
});

test("listenerLiveTransport: the host's own listen, connected lazily and once, with the allow-list and the stop guard", async () => {
    const calls = [];
    let connects = 0;
    const listen = (channel, event, handler) => {
        calls.push(['listen', channel.name, event]);
        handler({ from: channel.name });
        return () => calls.push(['stop', channel.name, event]);
    };
    const transport = listenerLiveTransport(() => (connects++, listen), { events: ['invalidated'] });
    await settle();
    assert.equal(connects, 0, 'nothing connects before a page subscribes');
    const got = [];
    const stop = transport.subscribe({ name: 'authoring.a', type: 'private' }, 'invalidated', p => got.push(p));
    transport.subscribe({ name: 'authoring.a', type: 'private' }, 'other', () => got.push('other'));
    const early = transport.subscribe({ name: 'authoring.b', type: 'private' }, 'invalidated', () => got.push('early'));
    early();
    await settle();
    assert.equal(connects, 1);
    assert.deepEqual(got, [{ from: 'authoring.a' }], 'the allowed event only; a stop before the connection never listens');
    stop();
    stop();
    assert.deepEqual(calls, [['listen', 'authoring.a', 'invalidated'], ['stop', 'authoring.a', 'invalidated']], "the host's stop runs once");
});

test('listenerLiveTransport: no connection (null or a throw) means subscriptions that do nothing', async () => {
    for (const connect of [() => null, () => { throw new Error('down'); }]) {
        const stop = listenerLiveTransport(connect).subscribe({ name: 'a', type: 'private' }, 'x', () => assert.fail('no listener'));
        await settle();
        stop();
    }
});

test('useLive: readers stand down on a live prop; the fallback poll is visible-tab only, slow with a socket', () => {
    const source = read('Live/useLive.ts.stub');
    assert.match(source, /export function useIsLive\(prop: string\)/);
    assert.match(source, /liveProps/);
    assert.match(source, /options\.socket \? \(options\.slow \?\? 30000\) : \(options\.fast \?\? 5000\)/);
    assert.match(source, /keepAlive: false, autoStart: false/);
    assert.match(source, /visibilitychange/);
    assert.doesNotMatch(read('Live/liveTransport.ts.stub') + source, /from '@inertiajs\/core'/, "the kit never imports Inertia's live API, so older clients still build");
});
