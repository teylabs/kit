import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// ActionBar's fit, the stage-anchored toolbar's position and client toolbar actions run here without Vue; the components are checked by source (the kit has no
// browser test runner, so nothing here renders, lays out or observes a resize).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-editor-gaps-'));
for (const [name, path] of [['actionFit', 'Action/actionFit.ts.stub'], ['toolbarAnchor', 'Layout/toolbarAnchor.ts.stub'], ['toolbarAction', 'Layout/toolbarAction.ts.stub']]) {
    writeFileSync(join(dir, `${name}.ts`), read(path));
}
const { fitActions } = await import(pathToFileURL(join(dir, 'actionFit.ts')).href);
const { anchorToolbar, unionRect } = await import(pathToFileURL(join(dir, 'toolbarAnchor.ts')).href);
const { runToolbarAction } = await import(pathToFileURL(join(dir, 'toolbarAction.ts')).href);

const actions = ['publish', 'preview', 'duplicate', 'archive'].map(key => ({ key }));
const keys = list => list.map(action => action.key);

test('fitActions: a full layout that fits stays whole', () => {
    const fit = fitActions(actions, { container: 400, parts: [120, 100, 80], gap: 12 });
    assert.equal(fit.compact, false);
    assert.deepEqual(keys(fit.inline), ['publish', 'preview', 'duplicate', 'archive']);
    assert.deepEqual(fit.menu, []);
    assert.equal(fitActions(actions, { container: 324, parts: [120, 100, 80], gap: 12 }).compact, false, 'exactly the needed width fits');
    assert.equal(fitActions(actions, { container: 323.6, parts: [120, 100, 80], gap: 12 }).compact, false, 'half a pixel of rounding is forgiven');
});

test('fitActions: one that does not fit keeps the main action and moves the rest, in order, to the menu', () => {
    const fit = fitActions(actions, { container: 358, parts: [120, 100, 80, 70], gap: 12 });
    assert.equal(fit.compact, true);
    assert.deepEqual(keys(fit.inline), ['publish']);
    assert.deepEqual(keys(fit.menu), ['preview', 'duplicate', 'archive']);
    assert.equal(fitActions(actions, { container: 300, parts: [310] }).compact, true, 'one wide group that overflows compacts too');
});

test('fitActions: only the primary action never compacts, however narrow', () => {
    const fit = fitActions([{ key: 'publish' }], { container: 40, parts: [120] });
    assert.equal(fit.compact, false);
    assert.deepEqual(keys(fit.inline), ['publish']);
    assert.deepEqual(fitActions([], { container: 40, parts: [] }), { compact: false, inline: [], menu: [] });
});

test('fitActions: an unmeasured bar (zero or no width) shows everything', () => {
    for (const container of [0, -1, Number.NaN]) {
        const fit = fitActions(actions, { container, parts: [120, 100, 80, 70], gap: 12 });
        assert.equal(fit.compact, false, `container ${container}`);
        assert.equal(fit.inline.length, 4);
    }
});

test('ActionBar measures its own root with a ResizeObserver, not the viewport, and disconnects on unmount', () => {
    const bar = read('Action/ActionBar.vue.stub');
    const script = bar.slice(0, bar.indexOf('</script>'));
    assert.match(script, /observer = new ResizeObserver\(schedule\);\n\s+observer\.observe\(root\.value\);/);
    assert.match(script, /typeof ResizeObserver === 'undefined'/, 'SSR-safe guard');
    assert.match(script, /onBeforeUnmount\(\(\) => \{\n\s+observer\?\.disconnect\(\);/);
    assert.match(script, /container: root\.value\.clientWidth/);
    assert.match(script, /fitActions\(ordered\.value, /);
    assert.doesNotMatch(bar, /matchMedia|window\.inner|innerWidth|max-sm:|sm:hidden/, 'no viewport or breakpoint decides the compact layout');
    assert.match(bar, /<div v-if="shown\.length" ref="root" [^>]*data-action-bar/);
    assert.match(bar, /:inert="fit\.compact \|\| undefined"/, 'the measured full layout is inert while the compact one shows');
    assert.match(bar, /<div v-if="fit\.compact" [^>]*data-action-layout-compact>/);
});

test('anchorToolbar: centred above the selection inside the stage', () => {
    const stage = { top: 100, left: 50, width: 800, height: 600 };
    const at = anchorToolbar({ top: 300, left: 350, width: 200, height: 100 }, stage, { width: 160, height: 40 });
    assert.deepEqual(at, { top: 300 - 100 - 8 - 40, left: 350 - 50 + 100 - 80, placement: 'top' });
});

test('anchorToolbar: flips below when there is no room above', () => {
    const stage = { top: 100, left: 50, width: 800, height: 600 };
    const at = anchorToolbar({ top: 120, left: 350, width: 200, height: 100 }, stage, { width: 160, height: 40 });
    assert.equal(at.placement, 'bottom');
    assert.equal(at.top, 20 + 100 + 8);
    const tall = anchorToolbar({ top: 110, left: 350, width: 200, height: 590 }, stage, { width: 160, height: 40 });
    assert.equal(tall.top, 600 - 8 - 40, 'no room either way: clamped inside the stage');
});

test('anchorToolbar: clamps at the left and right edges', () => {
    const stage = { top: 0, left: 0, width: 800, height: 600 };
    assert.equal(anchorToolbar({ top: 300, left: 0, width: 40, height: 40 }, stage, { width: 160, height: 40 }).left, 8, 'left');
    assert.equal(anchorToolbar({ top: 300, left: 780, width: 20, height: 40 }, stage, { width: 160, height: 40 }).left, 800 - 8 - 160, 'right');
    assert.equal(anchorToolbar({ top: 300, left: 10, width: 20, height: 40 }, { ...stage, width: 100 }, { width: 160, height: 40 }).left, 8, 'wider than the stage: at the left inset');
});

test('unionRect: the bounding box of the selected elements, null for none', () => {
    assert.equal(unionRect([]), null);
    assert.deepEqual(unionRect([{ top: 10, left: 20, width: 30, height: 40 }, { top: 5, left: 60, width: 10, height: 10 }]), { top: 5, left: 20, width: 50, height: 45 });
});

test('client toolbar actions reach the host through the toolbar\'s own handler, never a disabled one', () => {
    const received = [];
    assert.equal(runToolbarAction(action => received.push(action.key), { key: 'bold', label: 'Bold' }), true);
    assert.equal(runToolbarAction(action => received.push(action.key), { key: 'align', label: 'Align', disabled: true, reason: 'Select text first' }), false);
    assert.equal(runToolbarAction(undefined, { key: 'bold', label: 'Bold' }), false);
    assert.deepEqual(received, ['bold']);

    const toolbar = read('Layout/FloatingToolbar.vue.stub');
    assert.match(toolbar, /onAction\?: \(action: ToolbarAction\) => void/);
    assert.match(toolbar, /const choose = \(item: ToolbarAction\) => runToolbarAction\(props\.onAction, item\);/);
    assert.doesNotMatch(toolbar, /defineEmits/, 'items are never emitted');
    assert.match(toolbar, /:data-toolbar-action="item\.key"/);
    assert.match(toolbar, /:aria-disabled="item\.disabled \|\| undefined"/);
    assert.match(toolbar, /<slot :revealed="revealed" \/>/, 'server descriptors still come through the slot (ActionBar)');
    assert.doesNotMatch(read('Layout/toolbarAction.ts.stub').match(/export type ToolbarAction = [^\n]*/)[0], /url/i, 'a client action has no URL');
});

test('stage and docked modes read the Workbench stage context, which WorkbenchStage provides', () => {
    const toolbar = read('Layout/FloatingToolbar.vue.stub');
    assert.match(toolbar, /mode\?: 'auto' \| 'float' \| 'inline' \| 'stage' \| 'docked'/);
    assert.match(toolbar, /unionRect\(selectedRects\(viewport, wb\.elements\.map\(element => element\.id\), wb\.elementAttribute\)\)/);
    assert.match(toolbar, /anchorToolbar\(selection, /);
    assert.match(toolbar, /<Teleport :to="target \?\? 'body'" :disabled="!target">/);
    assert.match(toolbar, /props\.mode === 'docked' \? stage\?\.toolbar\.value/);
    assert.match(toolbar, /useStickyBars\(\)\.track\(pill, \(\) => props\.mode === 'auto' \|\| props\.mode === 'float'\)/);
    assert.match(toolbar, /resize\?\.disconnect\(\);/);
    const stage = read('Workbench/WorkbenchStage.vue.stub');
    assert.match(stage, /provideWorkbenchStage\(\{ workbenchKey: props\.workbenchKey, viewport, toolbar: toolbarEl \}\);/);
    assert.equal((stage.match(/<div v-if="toolbar" ref="toolbarEl"/g) ?? []).length, 2);
    assert.match(read('Workbench/workbenchStage.ts.stub'), /export const DEFAULT_ELEMENT_ATTRIBUTE = 'data-workbench-element';/);
});

test('Collapsible: a real button with aria-expanded and aria-controls naming the content, v-model:open, hidden when closed, no motion when reduced', () => {
    const source = read('Layout/Collapsible.vue.stub');
    assert.match(source, /<button\s+:id="triggerId"\s+type="button"/);
    assert.match(source, /:aria-expanded="open \? 'true' : 'false'"/);
    assert.match(source, /:aria-controls="contentId"/);
    assert.match(source, /<div v-show="open" :id="contentId" role="region" :aria-labelledby="triggerId"/);
    // `boolean | undefined`: vue-tsc refuses `defineModel<boolean>('open', { default: undefined })` (TS2769).
    assert.match(source, /const model = defineModel<boolean \| undefined>\('open'\);/);
    assert.match(source, /get: \(\) => model\.value \?\? local\.value,/);
    assert.match(source, /const local = ref\(props\.defaultOpen\);/);
    assert.match(source, /defaultOpen\?: boolean/);
    assert.match(source, /<slot name="summary" :open="open">\{\{ title \}\}<\/slot>/);
    assert.equal((source.match(/motion-reduce:transition-none/g) ?? []).length, 3, 'chevron, enter and leave');
    assert.doesNotMatch(source, /@keydown|tabindex/, 'the native button handles Enter and Space');
});

test('the new pieces are shipped, exported and in the contract', () => {
    const feature = readFileSync(new URL('../../resources/features/core/feature.json', import.meta.url), 'utf8');
    for (const file of ['Action/actionFit.ts', 'Layout/toolbarAnchor.ts', 'Layout/toolbarAction.ts', 'Layout/Collapsible.vue', 'Workbench/workbenchStage.ts']) {
        assert.ok(feature.includes(`"resources/js/components/Kit/${file}"`), file);
    }
    const index = read('index.ts.stub');
    assert.match(index, /export \{ default as Collapsible \} from '\.\/Layout\/Collapsible\.vue';/);
    assert.match(index, /type ToolbarAction/);
    assert.match(index, /fitActions/);
    assert.match(index, /useWorkbenchStage/);
    const contract = read('contract.ts.stub');
    assert.match(contract, /export interface CollapsibleProps \{/);
    assert.match(contract, /items\?: import\('\.\/Layout\/toolbarAction'\)\.ToolbarAction\[\]/);
});

test('every ActionBar menu, compact ones included, hands its rows the mounted handler', () => {
    const bar = readFileSync(new URL('../../resources/features/core/components/Kit/Action/ActionBar.vue.stub', import.meta.url), 'utf8');
    const menus = bar.match(/<ActionMenuItems\b[^>]*>/g) ?? [];
    assert.equal(menus.length, 4);
    for (const tag of menus) assert.match(tag, /:on-action="run"/);
});

test('the barrel exports Surface (the component) and SurfaceContext (the type) under different names', () => {
    const barrel = readFileSync(new URL('../../resources/features/core/components/Kit/index.ts.stub', import.meta.url), 'utf8');
    assert.match(barrel, /export \{ default as Surface \} from '\.\/Surface\/Surface\.vue';/);
    assert.match(barrel, /export type \{ Surface as SurfaceContext \} from '\.\/Surface\/surface';/);
    const names = [...barrel.matchAll(/export (?:type )?\{([^}]*)\}/g)].flatMap(m => m[1].split(',').map(n => n.trim().split(/\s+as\s+/).pop()).filter(Boolean));
    assert.deepEqual(names.filter((n, i) => names.indexOf(n) !== i), [], 'no name is exported twice (TS2300)');
});
