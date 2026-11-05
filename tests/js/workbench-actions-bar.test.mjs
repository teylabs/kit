import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Workbench :actions-bar="false" drops the actions bar while the store's togglePane/focusRegion keep driving the panes, and in development it warns when the
// headings are off too and nothing has toggled a pane. Checked by source (no browser runner); comments are stripped so prose can't satisfy an assertion.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));

test('togglePane and focusRegion still reach the panes, and are counted', () => {
    const store = source('Workbench/useWorkbench.ts.stub');
    assert.match(store, /togglePane\(region: WorkbenchRegion\) \{ paneCalls\.value\+\+; panes\.get\(region\)\?\.\(\); \}/);
    assert.match(store, /focusRegion\(region: WorkbenchRegion\) \{ paneCalls\.value\+\+; focusers\.get\(region\)\?\.\(\); \}/);
    assert.match(store, /get paneCalls\(\): number/);
});
