import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The Workbench stage's empty state: with no items yet the stage renders the host's `#empty` slot in place of `#stage`, ONLY when the host gives one; without it `#stage` renders as before (a stage-only
// Workbench, such as a presenter console with no navigator, keeps its stage). The empty test
// runs here from workbench.ts; the wiring through useWorkbench and Workbench is checked by source (the kit has no browser test runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-wb-empty-'));
writeFileSync(join(dir, 'workbench.ts'), read('Workbench/workbench.ts.stub'));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);
const item = { id: 'a', label: 'First' };

test('empty: no items (or none given) is empty; one item is not', () => {
    assert.equal(wb.isEmptyWorkbench([]), true);
    assert.equal(wb.isEmptyWorkbench(null), true);
    assert.equal(wb.isEmptyWorkbench(undefined), true);
    assert.equal(wb.isEmptyWorkbench([item]), false);
    assert.equal(wb.EMPTY_STAGE_TITLE, 'Nothing here yet.');
    // Keyboard steps, pruning and F6 with zero items do nothing and do not throw.
    assert.equal(wb.stepItem([], null, 1), null);
    assert.equal(wb.stepItem([], 'gone', -1), null);
    assert.deepEqual(wb.pruneSelection({ ids: ['a'], anchor: 'a' }, []), { ids: [], anchor: null });
    assert.deepEqual(wb.regionOrder('columns', { footer: false }), ['navigator', 'stage', 'inspector']);
});

test('empty: useWorkbench defines isEmpty once, from the items, and exposes it', () => {
    const store = read('Workbench/useWorkbench.ts.stub');
    assert.match(store, /const isEmpty = computed\(\(\) => isEmptyWorkbench\(items\.value\)\);/);
    assert.match(store, /items, order, isEmpty,/);
    assert.equal(store.match(/isEmptyWorkbench\(/g).length, 1, 'one definition of empty');
});

test('empty: every stage layout renders #empty only when empty AND the host gives one, else #stage; no kit default', () => {
    const source = read('Workbench/Workbench.vue.stub');
    const stage = '<template #stage><slot v-if="showEmpty" name="empty" v-bind="emptyProps" /><slot v-else name="stage" /></template>';
    assert.equal(source.split(stage).length - 1, 3, 'columns, drawer and tabs');
    assert.doesNotMatch(source, /<template #stage><slot name="stage" \/><\/template>/, 'no stage slot left without the empty branch');
    assert.match(source, /const showEmpty = computed\(\(\) => wb\.isEmpty && !!slots\.empty\);/, 'opt-in: only with a host #empty');
    assert.doesNotMatch(source, /EmptyState|EMPTY_STAGE_TITLE|data-workbench-stage-empty/, 'no kit default empty state in the stage');
    assert.match(source, /const emptyProps = computed<WorkbenchEmptySlotProps>\(\(\) => \(\{ actions: props\.actions, act: \(action: ActionDescriptor\) => emit\('action', action\), label: names\.value\.stage \}\)\);/);
    assert.match(source, /empty\?\(props: WorkbenchEmptySlotProps\): unknown;/, 'the slot is declared with its props');
    assert.doesNotMatch(source, /isEmptyWorkbench|items\.length/, 'Workbench reads wb.isEmpty, never its own test');
    // With nothing listed the navigator renders a plain, focusable message instead of an empty listbox (axe: aria-required-children, scrollable-region-focusable).
    const navigator = read('Workbench/WorkbenchNavigator.vue.stub');
    assert.match(navigator, /<p v-if="!shown\.length" tabindex="-1"[^>]*data-workbench-empty>\{\{ empty \}\}<\/p>\s*<div v-else [^>]*data-workbench-rows>\s*<ul\s+ref="list"\s+role="listbox"/);
    assert.doesNotMatch(navigator, /role="presentation"[^>]*data-workbench-empty/, 'no empty row inside the listbox');
    assert.match(navigator, /!shown\.value\.length \? root\.value\?\.querySelector<HTMLElement>\('\[data-workbench-empty\]'\)/, 'focus goes to the message');
    assert.match(source, /if \(region === 'navigator' && \(wb\.current !== null \|\| wb\.isEmpty\)\) \{ navigator\.value\?\.focusCurrent\(\); return; \}/);
});
