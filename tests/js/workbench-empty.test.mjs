import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The Workbench stage's empty state: with no items yet the stage renders the host's `#empty` slot in place of `#stage`, ONLY when the host gives one; without it `#stage` renders as before (a stage-only
// Workbench, such as a presenter console with no navigator, keeps its stage). The empty test
// runs here from workbench.ts; the wiring through useWorkbench and Workbench is checked by source (the kit has no browser test runner).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-wb-empty-'));
writeFileSync(join(dir, 'workbench.ts'), read('Workbench/workbench.ts'));
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

