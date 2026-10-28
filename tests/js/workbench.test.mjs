import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The Workbench's logic runs here without Vue or a DOM; its components are checked by source (the kit has no browser test runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-workbench-'));
// A stand-in for Inertia's client, so the default senders load without the package; tests pass their own senders.
const inertia = join(dir, 'node_modules', '@inertiajs', 'vue3');
mkdirSync(inertia, { recursive: true });
writeFileSync(join(inertia, 'package.json'), JSON.stringify({ name: '@inertiajs/vue3', type: 'module', main: 'index.js' }));
writeFileSync(join(inertia, 'index.js'), "export const http = { getClient: () => ({ request: () => Promise.reject(new Error('offline')) }) };\n");
// Vite resolves extensionless imports; node needs the extension, so the copies point at each other's .ts files.
for (const name of ['workbench', 'workbenchLayout']) writeFileSync(join(dir, `${name}.ts`), readFileSync(new URL(`Workbench/${name}.ts.stub`, kit), 'utf8').replace("from './workbench';", "from './workbench.ts';"));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);
const { createLayoutStore, parseLayout, layoutFallbackKey } = await import(pathToFileURL(join(dir, 'workbenchLayout.ts')).href);
const source = name => readFileSync(new URL(`Workbench/${name}.stub`, kit), 'utf8');
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

// ── Breakpoints and regions ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('breakpoints: three columns from xl, navigator + stage with a drawer at lg, tabs below', () => {
    assert.equal(wb.workbenchMode(1440), 'columns');
    assert.equal(wb.workbenchMode(1280), 'columns');
    assert.equal(wb.workbenchMode(1279), 'drawer');
    assert.equal(wb.workbenchMode(1024), 'drawer');
    assert.equal(wb.workbenchMode(1023), 'tabs');
    assert.equal(wb.workbenchMode(375), 'tabs');
});
test('phone tabs: Navigator, Stage, Inspector, and the footer only when provided', () => {
    assert.deepEqual(wb.workbenchTabs(false).map(tab => tab.value), ['navigator', 'stage', 'inspector']);
    assert.deepEqual(wb.workbenchTabs(true, { ...wb.DEFAULT_LABELS, footer: 'Speaker notes' }).map(tab => tab.label), ['Navigator', 'Stage', 'Inspector', 'Speaker notes']);
});
test('F6 cycles the visible regions in reading order, skipping collapsed panes, a closed drawer and a missing footer', () => {
    assert.deepEqual(wb.regionOrder('columns', { footer: true }), ['navigator', 'stage', 'footer', 'inspector']);
    assert.deepEqual(wb.regionOrder('columns', { footer: false, collapsed: { navigator: true } }), ['stage', 'inspector']);
    assert.deepEqual(wb.regionOrder('drawer', { footer: false, drawerOpen: false }), ['navigator', 'stage']);
    assert.deepEqual(wb.regionOrder('drawer', { footer: true, drawerOpen: true }), ['navigator', 'stage', 'footer', 'inspector']);
    assert.deepEqual(wb.regionOrder('tabs', { footer: true }), ['navigator', 'stage', 'inspector', 'footer']);
    const order = wb.regionOrder('columns', { footer: false });
    assert.equal(wb.nextRegion(order, 'inspector'), 'navigator', 'wraps forward');
    assert.equal(wb.nextRegion(order, 'navigator', true), 'inspector', 'Shift+F6 wraps back');
    assert.equal(wb.nextRegion(order, null), 'navigator', 'from outside, the first region');
});

// ── Keyboard ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('keyboard: ↑/↓ and j/k only in the navigator, Alt+↑/↓ moves, ⌘[ / ⌘] collapse, / filters, Esc closes the drawer, F6 cycles, ⌘+/-/0 zoom', () => {
    const cmd = (key, extra = {}, context = {}) => wb.workbenchCommand({ key, ...extra }, { inNavigator: true, typing: false, ...context });
    assert.equal(cmd('ArrowDown'), 'next');
    assert.equal(cmd('j'), 'next');
    assert.equal(cmd('ArrowUp'), 'prev');
    assert.equal(cmd('k'), 'prev');
    assert.equal(cmd('j', {}, { inNavigator: false }), null, 'j is not a command outside the navigator');
    assert.equal(cmd('ArrowDown', { altKey: true }), 'move-down');
    assert.equal(cmd('ArrowUp', { altKey: true }), 'move-up');
    assert.equal(cmd('[', { metaKey: true }), 'collapse-navigator');
    assert.equal(cmd(']', { ctrlKey: true }), 'collapse-inspector', 'Ctrl stands in for ⌘ off a Mac');
    assert.equal(cmd('/', {}, { inNavigator: false }), 'focus-filter');
    assert.equal(cmd('Escape', {}, { drawerOpen: true }), 'close-drawer');
    assert.equal(cmd('Escape'), null, 'Esc is left alone with no drawer open');
    assert.equal(cmd('F6'), 'next-region');
    assert.equal(cmd('F6', { shiftKey: true }), 'prev-region');
    assert.equal(cmd('=', { metaKey: true }), 'zoom-in');
    assert.equal(cmd('+', { metaKey: true }), 'zoom-in');
    assert.equal(cmd('-', { metaKey: true }), 'zoom-out');
    assert.equal(cmd('0', { metaKey: true }), 'zoom-reset');
});
test('keyboard: while typing, single keys and arrows stay the field\'s; F6 and ⌘ shortcuts still work', () => {
    const typing = { inNavigator: true, typing: true };
    for (const key of ['j', 'k', '/', 'ArrowDown']) assert.equal(wb.workbenchCommand({ key }, typing), null, key);
    assert.equal(wb.workbenchCommand({ key: 'F6' }, typing), 'next-region');
    assert.equal(wb.workbenchCommand({ key: '[', metaKey: true }, typing), 'collapse-navigator');
    const el = (tagName, type, extra = {}) => ({ tagName, getAttribute: name => (name === 'type' ? type : null), ...extra });
    assert.equal(wb.isTyping(el('INPUT', 'search')), true);
    assert.equal(wb.isTyping(el('INPUT', 'checkbox')), false);
    assert.equal(wb.isTyping(el('TEXTAREA')), true);
    assert.equal(wb.isTyping(el('DIV', null, { isContentEditable: true })), true);
    assert.equal(wb.isTyping(el('LI')), false);
});

// ── Selection ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('multi-select: replace, ⌘ toggles, Shift extends a range from the anchor', () => {
    const order = ['a', 'b', 'c', 'd', 'e'];
    let s = wb.select(wb.EMPTY, 'b', 'replace', order);
    assert.deepEqual(s, { ids: ['b'], anchor: 'b' });
    s = wb.select(s, 'd', 'range', order);
    assert.deepEqual(s, { ids: ['b', 'c', 'd'], anchor: 'b' }, 'the anchor stays put');
    s = wb.select(s, 'a', 'range', order);
    assert.deepEqual(s, { ids: ['b', 'a'], anchor: 'b' }, 'a range upwards runs from the anchor');
    s = wb.select(s, 'e', 'toggle', order);
    assert.deepEqual(s, { ids: ['b', 'a', 'e'], anchor: 'e' });
    s = wb.select(s, 'e', 'toggle', order);
    assert.deepEqual(s, { ids: ['b', 'a'], anchor: 'a' }, 'toggling the anchor off moves it to the last one left');
    assert.equal(wb.selectMode({ shiftKey: true }), 'range');
    assert.equal(wb.selectMode({ metaKey: true }), 'toggle');
    assert.equal(wb.selectMode({ ctrlKey: true }), 'toggle');
    assert.equal(wb.selectMode({ metaKey: true }, false), 'replace', 'single-select ignores modifiers');
});
test('selection: next/prev step through navigator order and stop at the ends; gone items are pruned', () => {
    const order = ['a', 'b', 'c'];
    assert.equal(wb.stepItem(order, null, 1), 'a');
    assert.equal(wb.stepItem(order, null, -1), 'c');
    assert.equal(wb.stepItem(order, 'b', 1), 'c');
    assert.equal(wb.stepItem(order, 'c', 1), null);
    assert.equal(wb.stepItem(order, 'a', -1), null);
    assert.deepEqual(wb.pruneSelection({ ids: ['a', 'x', 'c'], anchor: 'x' }, order), { ids: ['a', 'c'], anchor: 'c' });
    const same = { ids: ['a'], anchor: 'a' };
    assert.equal(wb.pruneSelection(same, order), same, 'an untouched selection is the same object');
});

// ── Inspectors ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('inspector registry: the inspector follows the selection type: none → document, one type → its own, mixed → common or fallback', () => {
    const types = { t1: 'text', t2: 'text', i1: 'image', c1: 'chart' };
    const typeOf = id => types[id];
    const registry = wb.createInspectorRegistry();
    registry.define(wb.DOCUMENT, 'DocumentInspector');
    registry.define('text', 'TextInspector');
    registry.define('image', 'ImageInspector');
    registry.define(['text', 'image'], 'MediaTextInspector');
    registry.define(wb.FALLBACK, 'FallbackInspector');
    assert.deepEqual([registry.resolve([], typeOf).kind, registry.resolve([], typeOf).component], ['document', 'DocumentInspector']);
    assert.deepEqual([registry.resolve(['t1'], typeOf).kind, registry.resolve(['t1'], typeOf).component], ['single', 'MediaTextInspector'], 'a later definition that covers the type wins');
    const multi = registry.resolve(['t1', 't2'], typeOf, 't2');
    assert.deepEqual([multi.kind, multi.type, multi.context.ids, multi.context.current], ['single', 'text', ['t1', 't2'], 't2'], 'several of one type is that type\'s, with every id');
    const mixed = registry.resolve(['t1', 'i1'], typeOf);
    assert.deepEqual([mixed.kind, mixed.component, mixed.context.types], ['common', 'MediaTextInspector', ['text', 'image']]);
    const unknown = registry.resolve(['t1', 'c1'], typeOf);
    assert.deepEqual([unknown.kind, unknown.component], ['fallback', 'FallbackInspector'], 'nothing covers text + chart');
    assert.deepEqual([registry.resolve(['c1'], typeOf).kind, registry.resolve(['c1'], typeOf).component], ['fallback', 'FallbackInspector']);
});
test('inspector registry: `when` can skip an inspector, unregistering removes it, and a missing fallback resolves to null', () => {
    const registry = wb.createInspectorRegistry();
    const remove = registry.define('text', 'Wide', { when: context => context.ids.length > 1 });
    registry.define('text', 'Narrow', { when: context => context.ids.length === 1 });
    assert.equal(registry.resolve(['a'], () => 'text').component, 'Narrow');
    assert.equal(registry.resolve(['a', 'b'], () => 'text').component, 'Wide');
    remove();
    const none = registry.resolve(['a', 'b'], () => 'text');
    assert.deepEqual([none.kind, none.component], ['fallback', null]);
    assert.equal(registry.resolve([], () => 'text').component, null, 'no document inspector registered');
    assert.throws(() => registry.define('', 'Nope'));
    assert.throws(() => registry.define([], 'Nope'));
});

// ── Live preview ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

const manualTimers = () => {
    const pending = new Map();
    let next = 0;
    return { pending, set: (run, ms) => { pending.set(++next, { run, ms }); return next; }, clear: id => pending.delete(id), fire() { const all = [...pending.values()]; pending.clear(); all.forEach(entry => entry.run()); } };
};
const deferred = () => { let resolve; let reject; const promise = new Promise((res, rej) => { resolve = res; reject = rej; }); return { promise, resolve, reject }; };

test('recalculate: debounced, then only the latest result is applied; an older one that arrives late is dropped', async () => {
    const timers = manualTimers();
    const calls = [];
    const applied = [];
    const recalc = wb.createRecalculator((input, signal) => { const d = deferred(); calls.push({ input, signal, d }); return d.promise; }, { debounce: 300, apply: r => applied.push(r), timers });
    recalc.request(1);
    recalc.request(2);
    assert.equal(timers.pending.size, 1, 'a newer request replaces the waiting one');
    assert.equal([...timers.pending.values()][0].ms, 300);
    timers.fire();
    await flush();
    recalc.request(3);
    timers.fire();
    await flush();
    assert.deepEqual(calls.map(call => call.input), [2, 3]);
    assert.equal(calls[0].signal.aborted, true, 'the older run is aborted');
    calls[1].d.resolve('fresh');
    calls[0].d.resolve('stale');
    await flush();
    assert.deepEqual(applied, ['fresh'], 'the out-of-order result never overwrites the fresh one');
    assert.equal(recalc.pending, false);
});
test('recalculate: a failure reaches `failed` only for the latest run, and cancel drops what is on its way', async () => {
    const timers = manualTimers();
    const failures = [];
    const applied = [];
    let d;
    const recalc = wb.createRecalculator(() => { d = deferred(); return d.promise; }, { apply: r => applied.push(r), failed: e => failures.push(e.message), timers });
    recalc.request('x');
    timers.fire();
    await flush();
    d.reject(new Error('boom'));
    await flush();
    assert.deepEqual(failures, ['boom']);
    recalc.request('y');
    timers.fire();
    await flush();
    recalc.cancel();
    d.resolve('late');
    await flush();
    assert.deepEqual(applied, [], 'cancelled before it arrived');
});

// ── Reorder ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('reorder: applied at once, sent with the version token, kept on success with the new version', async () => {
    let ids = ['a', 'b', 'c'];
    let version = 7;
    const sent = [];
    const reorder = wb.createReorder({ get: () => ids, set: next => { ids = next; }, version: () => version, send: async (order, token) => { sent.push([order, token]); assert.deepEqual(ids, ['b', 'a', 'c'], 'optimistic: applied before the server answers'); return { ok: true, version: 8 }; }, saved: v => { version = v; } });
    assert.equal(await reorder.apply(wb.moveItem(ids, 0, 1)), true);
    assert.deepEqual(sent, [[['b', 'a', 'c'], 7]]);
    assert.deepEqual([ids, version], [['b', 'a', 'c'], 8]);
    assert.equal(await reorder.apply(['b', 'a', 'c']), true, 'the same order sends nothing');
    assert.equal(sent.length, 1);
});
test('reorder: rolls back on a conflict (409 / stale_write) or a failure, and says why', async () => {
    for (const [result, reason] of [[{ ok: false, conflict: true }, 'conflict'], [{ ok: false, conflict: false }, 'failed'], ['throw', 'failed']]) {
        let ids = ['a', 'b', 'c'];
        const reasons = [];
        const reorder = wb.createReorder({ get: () => ids, set: next => { ids = next; }, version: () => 1, send: async () => { if (result === 'throw') throw new Error('offline'); return result; }, rolledBack: why => reasons.push(why) });
        assert.equal(await reorder.apply(['c', 'b', 'a']), false);
        assert.deepEqual(ids, ['a', 'b', 'c'], `rolled back after ${reason}`);
        assert.deepEqual(reasons, [reason]);
    }
    assert.equal(wb.isConflict(409), true);
    assert.equal(wb.isConflict(422, 'stale_write'), true);
    assert.equal(wb.isConflict(422, 'stale_slide'), true);
    assert.equal(wb.isConflict(422, 'edit_conflict'), true);
    assert.equal(wb.isConflict(500, 'operation_failed'), false);
});
test('reorder: one at a time; moveItem clamps', async () => {
    let ids = ['a', 'b'];
    const gate = deferred();
    const reorder = wb.createReorder({ get: () => ids, set: next => { ids = next; }, version: () => null, send: () => gate.promise });
    const first = reorder.apply(['b', 'a']);
    assert.equal(reorder.busy, true);
    assert.equal(await reorder.apply(['a', 'b']), false, 'refused while the first is saving');
    gate.resolve({ ok: true, version: null });
    assert.equal(await first, true);
    assert.deepEqual(wb.moveItem(['a', 'b', 'c'], 2, 99), ['a', 'b', 'c']);
    assert.deepEqual(wb.moveItem(['a', 'b', 'c'], 0, 99), ['b', 'c', 'a']);
    assert.deepEqual(wb.moveItem(['a', 'b', 'c'], 5, 0), ['a', 'b', 'c']);
});

// ── Zoom ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('zoom: fit to width, fit to page, steps along the scale, clamped; a step from a fit becomes a fixed zoom', () => {
    const slide = { width: 1920, height: 1080 };
    assert.equal(wb.fitScale('width', slide, { width: 960, height: 2000 }), 0.5);
    assert.equal(wb.fitScale('page', slide, { width: 1920, height: 540 }), 0.5, 'page fits the tighter side');
    assert.equal(wb.fitScale('page', slide, { width: 0, height: 0 }), 1, 'no measurement yet: 100%');
    assert.equal(wb.scaleFor({ fit: null, zoom: 1.5 }, slide, { width: 10, height: 10 }), 1.5);
    assert.deepEqual(wb.stepZoom(0.5, 1), { fit: null, zoom: 0.67 });
    assert.deepEqual(wb.stepZoom(0.6, -1), { fit: null, zoom: 0.5 });
    assert.deepEqual(wb.stepZoom(4, 1), { fit: null, zoom: 4 }, 'clamped at the top');
    assert.deepEqual(wb.stepZoom(0.25, -1), { fit: null, zoom: 0.25 }, 'clamped at the bottom');
    assert.equal(wb.clampZoom(Number.NaN), 1);
    assert.equal(wb.zoomLabel(0.666), '67%');
});

// ── Layout persistence ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

const memory = (seed = {}) => { const items = { ...seed }; return { items, getItem: key => items[key] ?? null, setItem: (key, value) => { items[key] = String(value); } }; };
const preference = { url: '/me/workbenches/deck-editor', method: 'put' };

test('layout: the server copy wins on load; a parsed layout drops what it does not know', () => {
    const local = memory({ [layoutFallbackKey('deck')]: JSON.stringify({ tab: 'inspector' }) });
    const store = createLayoutStore({ key: 'deck', state: { layout: { groups: { columns: '{"x":1}' }, zoom: { fit: 'width', zoom: 1 }, tab: 'stage' }, version: 3 }, local: () => local });
    assert.deepEqual(store.layout, { groups: { columns: '{"x":1}' }, zoom: { fit: 'width', zoom: 1 }, tab: 'stage' });
    assert.equal(store.version, 3);
    assert.equal(createLayoutStore({ key: 'deck', local: () => local }).layout.tab, 'inspector', 'no server state: the local fallback');
    assert.deepEqual(parseLayout({ groups: { a: 5, b: 'ok' }, zoom: { fit: 'sideways', zoom: 2 }, tab: 'Bad Tab', evil: true }), { groups: { b: 'ok' } });
    assert.deepEqual(parseLayout('not json'), {});
    assert.deepEqual(parseLayout({ zoom: { fit: null, zoom: 99 } }), { zoom: { fit: null, zoom: 4 } });
});
test('layout: panes, collapsed state, zoom and tab persist through the endpoint with the version; the splitter adapter maps groups', async () => {
    const sent = [];
    const store = createLayoutStore({ key: 'deck', state: { layout: null, version: 0 }, preference, local: () => memory(), send: async (_p, body) => { sent.push(structuredClone(body)); return { status: 'ok', version: body.version + 1 }; } });
    const splitter = store.splitter();
    splitter.setItem('columns', '{"navigator,stage,inspector":{"layout":[0,74,26]}}');
    await flush();
    await store.update({ zoom: { fit: null, zoom: 1.25 } });
    await store.update({ tab: 'footer' });
    assert.deepEqual(sent.map(body => body.version), [0, 1, 2], 'each write carries the version the last one was given');
    assert.equal(splitter.getItem('columns'), '{"navigator,stage,inspector":{"layout":[0,74,26]}}', 'a collapsed navigator (size 0) round-trips');
    assert.deepEqual(sent[2].layout, { groups: { columns: '{"navigator,stage,inspector":{"layout":[0,74,26]}}' }, zoom: { fit: null, zoom: 1.25 }, tab: 'footer' });
    await store.update({ tab: 'footer' });
    assert.equal(sent.length, 3, 'an unchanged layout sends nothing');
});
test('layout: a stale write takes the server copy, puts this tab\'s change back on top and sends once more', async () => {
    const sent = [];
    const changed = [];
    let stale = true;
    const store = createLayoutStore({ key: 'deck', state: { layout: { tab: 'stage' }, version: 1 }, preference, local: () => memory(), changed: layout => changed.push(layout), send: async (_p, body) => {
        sent.push(structuredClone(body));
        if (stale) { stale = false; return { status: 'stale', layout: { tab: 'navigator', zoom: { fit: 'page', zoom: 1 } }, version: 5 }; }
        return { status: 'ok', version: body.version + 1 };
    } });
    await store.update({ zoom: { fit: null, zoom: 2 } });
    assert.deepEqual(sent.map(body => body.version), [1, 5]);
    assert.deepEqual(sent[1].layout, { tab: 'navigator', zoom: { fit: null, zoom: 2 } }, 'the other device\'s tab is kept, this tab\'s zoom wins');
    assert.equal(store.version, 6);
    assert.equal(changed.length, 1, 'the page hears about the merged copy');
});
test('layout: writes go one at a time, the latest winning; no endpoint or a failure falls back to localStorage', async () => {
    const gate = deferred();
    const sent = [];
    const store = createLayoutStore({ key: 'deck', state: { layout: null, version: 0 }, preference, local: () => memory(), send: async (_p, body) => { sent.push(structuredClone(body)); if (sent.length === 1) await gate.promise; return { status: 'ok', version: body.version + 1 }; } });
    const first = store.update({ tab: 'stage' });
    void store.update({ tab: 'inspector' });
    void store.update({ tab: 'navigator' });
    gate.resolve();
    await first;
    await flush();
    assert.deepEqual(sent.map(body => [body.layout.tab, body.version]), [['stage', 0], ['navigator', 1]], 'the queued writes collapse into the latest');

    const local = memory();
    const offline = createLayoutStore({ key: 'deck', preference, local: () => local, send: async () => ({ status: 'failed' }) });
    await offline.update({ tab: 'inspector' });
    assert.equal(JSON.parse(local.items[layoutFallbackKey('deck')]).tab, 'inspector');
    const none = memory();
    await createLayoutStore({ key: 'deck', local: () => none }).update({ tab: 'stage' });
    assert.equal(JSON.parse(none.items[layoutFallbackKey('deck')]).tab, 'stage');
    const blocked = createLayoutStore({ key: 'deck', local: () => { throw new Error('blocked'); } });
    await assert.doesNotReject(blocked.update({ tab: 'stage' }), 'blocked storage is not an error');
});

// ── Components (by source) ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

test('Workbench renders one layout per breakpoint: three Splitter columns, navigator + stage with a drawer, or tabs', () => {
    const workbench = source('Workbench.vue');
    assert.match(workbench, /v-if="wb\.mode === 'columns'"[^>]*auto-save-id="columns"/);
    assert.match(workbench, /<SplitterPanel id="navigator"[^>]*collapsible/);
    assert.match(workbench, /<SplitterPanel id="inspector"[^>]*collapsible/);
    assert.match(workbench, /v-else-if="wb\.mode === 'drawer'"/);
    assert.match(workbench, /<Modal v-model:open="wb\.drawerOpen"[^>]*placement="drawer"/);
    assert.match(workbench, /<PaneTabs :model-value="wb\.tab" :tabs="tabs"/);
    assert.match(workbench, /window\.matchMedia\('\(min-width: 80rem\)'\)/);
    assert.match(workbench, /window\.matchMedia\('\(min-width: 64rem\)'\)/);
    assert.match(workbench, /<section v-if="hasFooter" v-show="wb\.tab === 'footer'"/, 'the footer tab exists only with a footer');
    assert.match(workbench, /fill && 'h-\[calc\(100dvh-8rem\)\] min-h-\[28rem\]'/, 'it fills the viewport below the shell, so the stage has a height to fit in');
    assert.match(workbench, /fill: true \}\);/, 'filling is the default');
});
test('Workbench regions are labelled landmarks the keyboard can reach, and every command is wired', () => {
    const workbench = source('Workbench.vue');
    assert.match(workbench, /<nav :aria-label="names\.navigator" tabindex="-1"[^>]*data-workbench-region="navigator"/);
    assert.match(workbench, /<section :aria-label="names\.stage" tabindex="-1"[^>]*data-workbench-region="stage"/);
    assert.match(workbench, /<aside :aria-label="names\.inspector" tabindex="-1"[^>]*data-workbench-region="inspector"/);
    for (const command of ['next', 'prev', 'move-down', 'move-up', 'collapse-navigator', 'collapse-inspector', 'focus-filter', 'close-drawer', 'next-region', 'prev-region', 'zoom-in', 'zoom-out', 'zoom-reset']) {
        assert.match(workbench, new RegExp(`case '${command}'`), command);
    }
    assert.match(source('WorkbenchStage.vue'), /<section :aria-label="footerLabel" tabindex="-1"[^>]*data-workbench-region="footer"/);
    assert.match(source('WorkbenchNavigator.vue'), /role="listbox"[\s\S]*:aria-multiselectable[\s\S]*role="option"[\s\S]*:aria-selected/);
});
test('the stage: a sticky toolbar, a resizable and collapsible footer on a vertical Splitter remembered with the layout, and a measured viewport for fit', () => {
    const stage = source('WorkbenchStage.vue');
    assert.match(stage, /<SplitterGroup v-if="footer" direction="vertical" auto-save-id="stage" :storage=/);
    assert.match(stage, /<SplitterPanel id="footer"[^>]*collapsible/);
    assert.match(stage, /class="sticky top-0[^"]*"[^>]*data-workbench-toolbar/);
    assert.match(stage, /new ResizeObserver\(measure\)/);
    assert.match(stage, /wb\.measure\(/);
});
test('the actions bar is a StickyBar with the save state, a conflict alert, zoom and the app\'s action descriptors through ActionBar', () => {
    const actions = source('WorkbenchActions.vue');
    assert.match(actions, /<StickyBar[^>]*data-workbench-actions/);
    assert.match(actions, /role="status" aria-live="polite"[^>]*:data-save-state="wb\.saveState"/);
    assert.match(actions, /v-if="wb\.saveState === 'conflict'" role="alert"/);
    assert.match(actions, /<ActionBar v-if="actions\.length" :actions="actions"/);
    assert.match(actions, /<WorkbenchZoom v-if="zoom"/);
    assert.deepEqual(Object.keys(wb.SAVE_LABELS), ['saving', 'saved', 'unsaved', 'conflict', 'error']);
});
test('the inspector region renders what the registry resolves for the selection, with a note when there is none', () => {
    const inspector = source('WorkbenchInspector.vue');
    assert.match(inspector, /<component :is="resolved\.component" v-if="resolved\.component" :element="resolved\.context\.element" :elements="resolved\.context\.elements" :ids="resolved\.context\.ids" :items="items" :types="resolved\.context\.types" :current="resolved\.context\.current"/);
    assert.match(inspector, /:data-inspector-kind="resolved\.kind"/);
    const composable = source('useWorkbench.ts');
    assert.match(composable, /registry\.resolve\(selection\.value\.ids, id => byId\.value\.get\(id\)\?\.type \?\? null, current\.value, elements\.value\)/, 'the inspector follows the stage\'s selection');
    assert.match(composable, /window\.dispatchEvent\(new CustomEvent\('kit:toast:show'/, 'a rolled-back reorder toasts through the shell\'s Toaster');
    assert.match(readFileSync(new URL('Feedback/Toaster.vue.stub', kit), 'utf8'), /window\.addEventListener\('kit:toast:show', onClientToast\)/);
});

// ── Stage element selection ──
test('stage elements: replace selects one; ⌘ or Shift adds and removes; ids stay unique', () => {
    const a = { id: 'a', type: 'text' }, b = { id: 'b', type: 'image' };
    assert.deepEqual(wb.selectElement([], a, 'replace'), [a]);
    assert.deepEqual(wb.selectElement([a], b, 'toggle'), [a, b]);
    assert.deepEqual(wb.selectElement([a, b], a, 'range'), [b]);
    assert.deepEqual(wb.selectElement([a, b], b, 'replace'), [b]);
});
test('selected elements drive the inspector by element type; mixed types use a common inspector or fall back; items decide otherwise, as before', () => {
    const registry = wb.createInspectorRegistry();
    for (const [type, name] of [['@document', 'Doc'], ['@fallback', 'Fallback'], ['slide', 'Slide'], ['text', 'Text'], ['image', 'Image']]) registry.define(type, name);
    const typeOf = () => 'slide';
    const text = { id: 't1', type: 'text' }, image = { id: 'i1', type: 'image' };
    const one = registry.resolve(['s1'], typeOf, 's1', [text]);
    assert.equal(one.component, 'Text');
    assert.equal(one.kind, 'single');
    assert.deepEqual(one.context.element, text);
    assert.deepEqual(one.context.elements, [text]);
    assert.deepEqual(one.context.ids, ['s1'], 'the item selection still reaches the inspector');
    const mixed = registry.resolve(['s1'], typeOf, 's1', [text, image]);
    assert.equal(mixed.component, 'Fallback');
    assert.equal(mixed.context.element, undefined);
    assert.equal(registry.resolve([], typeOf, null, [text]).component, 'Text', 'elements decide even before any item is selected');
    registry.define(['text', 'image'], 'Common');
    assert.equal(registry.resolve(['s1'], typeOf, 's1', [text, image]).component, 'Common');
    // Items only: unchanged.
    const items = registry.resolve(['s1'], typeOf, 's1');
    assert.equal(items.component, 'Slide');
    assert.deepEqual(items.context.elements, []);
    assert.equal(registry.resolve([], typeOf, null).component, 'Doc');
});
test('Esc closes the drawer, then clears the stage elements, then the item selection; never while typing; F6 is unchanged', () => {
    const esc = context => wb.workbenchCommand({ key: 'Escape' }, { inNavigator: false, typing: false, ...context });
    assert.equal(esc({ drawerOpen: true, elements: true, selection: true }), 'close-drawer');
    assert.equal(esc({ elements: true, selection: true }), 'clear-elements');
    assert.equal(esc({ selection: true }), 'clear-selection');
    assert.equal(esc({}), null);
    assert.equal(esc({ typing: true, elements: true, selection: true }), null);
    assert.equal(wb.workbenchCommand({ key: 'F6' }, { inNavigator: false, typing: false, elements: true }), 'next-region');
});
test('the store keeps elements apart from the items: never pruned by them, cleared when the current item changes, Esc wired', () => {
    const composable = source('useWorkbench.ts');
    assert.match(composable, /watch\(current, \(\) => \{ elements\.value = \[\]; \}, \{ flush: 'sync' \}\)/, 'switching the current item clears the elements');
    assert.doesNotMatch(composable.slice(composable.indexOf('function setItems'), composable.indexOf('function select(')), /elements/, 'setItems never prunes the elements');
    assert.match(composable, /get elements\(\): readonly WorkbenchElement\[\] \{ return elements\.value; \}/);
    assert.match(composable, /selectElements, selectElement, clearElements,/);
    const workbench = source('Workbench.vue');
    assert.match(workbench, /elements: wb\.elements\.length > 0, selection: wb\.selected\.length > 0/);
    assert.match(workbench, /case 'clear-elements': wb\.clearElements\(\); break;/);
    assert.match(workbench, /case 'clear-selection': wb\.clear\(\); break;/);
});
