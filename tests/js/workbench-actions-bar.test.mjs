import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Workbench :actions-bar="false" drops the actions bar while the store's togglePane/focusRegion keep driving the panes, and in development it warns when the
// headings are off too and nothing has toggled a pane. Checked by source (no browser runner); comments are stripped so prose can't satisfy an assertion.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));

test('actionsBar is a prop that defaults to true and gates the bar', () => {
    const wb = source('Workbench/Workbench.vue.stub');
    assert.match(wb, /actionsBar\?: boolean;/);
    assert.match(wb, /actionsBar: true/);
    assert.match(wb, /<WorkbenchActions v-if="actionsBar"/);
    assert.equal(wb.match(/<WorkbenchActions\b/g).length, 1, 'no other bar renders');
});

test('togglePane and focusRegion still reach the panes, and are counted', () => {
    const store = source('Workbench/useWorkbench.ts.stub');
    assert.match(store, /togglePane\(region: WorkbenchRegion\) \{ paneCalls\.value\+\+; panes\.get\(region\)\?\.\(\); \}/);
    assert.match(store, /focusRegion\(region: WorkbenchRegion\) \{ paneCalls\.value\+\+; focusers\.get\(region\)\?\.\(\); \}/);
    assert.match(store, /get paneCalls\(\): number/);
});

test('the dev warning fires only with headings and the bar both off and no pane toggled', () => {
    const wb = source('Workbench/Workbench.vue.stub');
    assert.match(wb, /if \(!import\.meta\.env\?\.DEV \|\| props\.actionsBar \|\| props\.headings\) return;/);
    assert.match(wb, /if \(wb\.paneCalls === 0\) console\.warn\(/);
});
