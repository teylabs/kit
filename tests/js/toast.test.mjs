import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-toast-'));
copyFileSync(new URL('Feedback/toastPlacement.ts.stub', kit), join(dir, 'toastPlacement.ts'));
const { resolvePosition, toastPlacement, flashedToast, resolveTrigger, toastPlan, toastTiming, isAway, whenBack, toastGate } = await import(pathToFileURL(join(dir, 'toastPlacement.ts')).href);

const shell = (over = {}) => ({ bars: { count: 0, height: 0 }, small: false, headerActions: false, headerHeight: 104, ...over });
const bar = { count: 1, height: 56 };

test('auto: under sm bottom-center, above the sticky bars', () => {
    assert.deepEqual(toastPlacement('auto', shell({ small: true, bars: bar, headerActions: true })), { position: 'bottom-center', offset: 16 + 56 + 8 });
    assert.equal(resolvePosition('top-right', shell({ small: true })), 'bottom-center', 'an explicit position still yields to phones');
});
test('auto: sticky bars and header actions is top-center, under the header', () => {
    assert.deepEqual(toastPlacement('auto', shell({ bars: bar, headerActions: true })), { position: 'top-center', offset: 104 + 16 });
});
test('auto: sticky bars only is top-right', () => assert.equal(resolvePosition('auto', shell({ bars: bar })), 'top-right'));
test('auto: otherwise bottom-right; header actions alone do not move it', () => {
    assert.equal(resolvePosition('auto', shell()), 'bottom-right');
    assert.equal(resolvePosition('auto', shell({ headerActions: true })), 'bottom-right');
});
test('an explicit position is kept; an unknown one is auto', () => {
    for (const position of ['top-center', 'top-right', 'bottom-right', 'bottom-center']) assert.equal(resolvePosition(position, shell({ bars: bar, headerActions: true })), position);
    assert.equal(resolvePosition('top-left', shell()), 'bottom-right');
    assert.deepEqual(toastPlacement('bottom-right', shell({ bars: bar })), { position: 'bottom-right', offset: 80 });
});
test('the toaster renders only what the server flashed', () => {
    assert.equal(flashedToast(null), null);
    assert.equal(flashedToast({ type: 'danger', message: 'x' }), null);
    assert.equal(flashedToast({ type: 'success', message: '' }), null);
    assert.equal(flashedToast({ type: 'success', message: 'Saved' }).message, 'Saved');
});

const el = (key, box = { top: 100, left: 100, bottom: 136, right: 200, width: 100, height: 36 }, isConnected = true) => ({ isConnected, getAttribute: (n) => (n === 'data-action' ? key : null), getBoundingClientRect: () => box });
const viewport = { width: 1280, height: 800 };

test('resolve: the only element with the key, or the last activated one among many', () => {
    const save = el('save');
    assert.equal(resolveTrigger('save', [el('cancel'), save], null, viewport), save);
    const rowA = el('change-role');
    const rowB = el('change-role');
    assert.equal(resolveTrigger('change-role', [rowA, rowB], rowB, viewport), rowB);
    assert.equal(resolveTrigger('change-role', [rowA, rowB], null, viewport), null, 'ambiguous with no activation: no trigger');
});
test('fallback: no from, a missing, removed or offscreen element is no trigger, and a plain toast', () => {
    assert.equal(resolveTrigger(undefined, [el('save')], null, viewport), null);
    assert.equal(resolveTrigger('save', [], null, viewport), null);
    const gone = el('save', undefined, false);
    assert.equal(resolveTrigger('save', [gone], gone, viewport), null);
    assert.equal(resolveTrigger('save', [el('save', { top: 900, left: 10, bottom: 930, right: 60, width: 50, height: 30 })], null, viewport), null);
    for (const style of ['fly-out', 'anchored', 'pulse', 'plain']) assert.deepEqual(toastPlan(style, false, false), { shell: true, flight: false, anchored: false, pulse: false, animate: true });
});
test('styles with a trigger', () => {
    assert.deepEqual(toastPlan('fly-out', true, false), { shell: true, flight: true, anchored: false, pulse: false, animate: true });
    assert.deepEqual(toastPlan('anchored', true, false), { shell: false, flight: false, anchored: true, pulse: false, animate: true });
    assert.deepEqual(toastPlan('pulse', true, false), { shell: true, flight: false, anchored: false, pulse: true, animate: true });
});
test('reduced motion: no flight (a toast and a pulse); anchored stays, unanimated', () => {
    assert.deepEqual(toastPlan('fly-out', true, true), { shell: true, flight: false, anchored: false, pulse: true, animate: false });
    assert.deepEqual(toastPlan('anchored', true, true), { shell: false, flight: false, anchored: true, pulse: false, animate: false });
});
test('the shell mounts one Toaster and pages have no toast API', () => {
    const index = readFileSync(new URL('index.ts.stub', kit), 'utf8');
    assert.match(index, /export \{ default as Toaster \}/);
    assert.doesNotMatch(index, /useToast\b|export \{[^}]*\btoast\b/);
    for (const shellStub of ['../../../shell/pages/Workspace/Layout.vue.stub', '../../../shell/pages/Workspace/Partials/GuestShell.vue.stub']) {
        assert.equal(readFileSync(new URL(shellStub, kit), 'utf8').match(/<Toaster(?: v-if="(?:props\.)?toaster")? \/>/g)?.length, 1, shellStub); // one Toaster per shell; GuestShell's can be switched off with :toaster="false"
    }
    assert.match(readFileSync(new URL('Layout/FloatingToolbar.vue.stub', kit), 'utf8'), /useStickyBars\(\)\.track\(pill/);
});

test('durations: timed as sent, null persistent, a missing duration the default', () => {
    assert.deepEqual(toastTiming(4000, false), { duration: 4000, deferred: null });
    assert.deepEqual(toastTiming(null, false), { duration: Infinity, deferred: null });
    assert.deepEqual(toastTiming(null, true), { duration: Infinity, deferred: null }, 'a persistent toast is unaffected by the away rule');
    assert.deepEqual(toastTiming(undefined, false), { duration: 4000, deferred: null });
});
test('away: hidden at show is paused, and the duration starts only when visible and focused again', () => {
    const doc = Object.assign(new EventTarget(), { hidden: true, focused: false, hasFocus() { return this.focused; } });
    const win = new EventTarget();
    assert.equal(isAway(doc), true);
    assert.deepEqual(toastTiming(8000, isAway(doc)), { duration: Infinity, deferred: 8000 });
    let started = 0;
    whenBack(doc, win, () => started++);
    doc.hidden = false;
    doc.dispatchEvent(new Event('visibilitychange'));
    assert.equal(started, 0, 'visible but unfocused is still away');
    doc.focused = true;
    win.dispatchEvent(new Event('focus'));
    assert.equal(started, 1, 'focus starts it');
    win.dispatchEvent(new Event('focus'));
    doc.dispatchEvent(new Event('visibilitychange'));
    assert.equal(started, 1, 'once');
});
test('away: unfocused at show waits for focus; cancelling stops it', () => {
    const doc = Object.assign(new EventTarget(), { hidden: false, focused: false, hasFocus() { return this.focused; } });
    const win = new EventTarget();
    let started = 0;
    const cancel = whenBack(doc, win, () => started++);
    cancel();
    doc.focused = true;
    win.dispatchEvent(new Event('focus'));
    assert.equal(started, 0);
    assert.equal(isAway(doc), false);
    assert.deepEqual(toastTiming(4000, isAway(doc)), { duration: 4000, deferred: null }, 'present at show: timed at once');
});
test('the Toaster times each toast from the server and the away rule', () => {
    const toaster = readFileSync(new URL('Feedback/Toaster.vue.stub', kit), 'utf8');
    assert.match(toaster, /const timing = toastTiming\(flashed\.duration, isAway\(document\)\);/);
    assert.match(toaster, /duration: timing\.duration,/);
    assert.match(toaster, /whenBack\(document, window, /);
    assert.match(toaster, /toast\.dismiss\(id\)/);
});
test('dedupe: the same id twice shows once; two ids with the same content show twice', () => {
    const fresh = toastGate();
    const saved = (id) => ({ id, type: 'success', message: 'Saved', style: 'pulse', duration: 4000 });
    assert.equal(fresh(saved('01A')), true);
    assert.equal(fresh(saved('01A')), false, 'an equal copy with the same id is not shown again');
    assert.equal(fresh(saved('01B')), true, 'a second identical save is a second toast');
    assert.equal(fresh(undefined), false);
});
test('dedupe: a full page load (onMounted, then the first flash event) shows once', () => {
    const fresh = toastGate();
    const onPage = { id: '01C', type: 'info', message: 'Connected' };
    const fromEvent = JSON.parse(JSON.stringify(onPage));
    assert.notEqual(onPage, fromEvent);
    assert.deepEqual([fresh(onPage), fresh(fromEvent)], [true, false]);
});
test('dedupe: a toast without an id falls back to identity', () => {
    const fresh = toastGate();
    const old = { type: 'success', message: 'Saved' };
    assert.deepEqual([fresh(old), fresh(old), fresh({ ...old })], [true, false, true]);
});
test('dedupe: only a few recent ids are kept', () => {
    const fresh = toastGate(2);
    ['1', '2', '3'].forEach((id) => fresh({ id, type: 'info', message: 'x' }));
    assert.equal(fresh({ id: '3', type: 'info', message: 'x' }), false);
    assert.equal(fresh({ id: '1', type: 'info', message: 'x' }), true, 'the oldest id has been forgotten');
});
test('the Toaster gates every toast through toastGate, not identity alone', () => {
    const toaster = readFileSync(new URL('Feedback/Toaster.vue.stub', kit), 'utf8');
    assert.match(toaster, /const fresh = toastGate\(\);/);
    assert.match(toaster, /if \(!fresh\(value\)\) return;/);
    assert.doesNotMatch(toaster, /value === last/);
});
