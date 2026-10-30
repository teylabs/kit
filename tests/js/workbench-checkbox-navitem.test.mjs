import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Checkbox menu items (`checked` on an action descriptor) and the Workbench's `#navigator-item` slot. The role logic runs here; the components are checked by
// source with comments stripped, so prose can't satisfy an assertion. browser/run.mjs renders both in Chromium with axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));
const dir = mkdtempSync(join(tmpdir(), 'kit-checkbox-'));
copyFileSync(new URL('Action/actionPlacement.ts.stub', kit), join(dir, 'actionPlacement.ts'));
const placement = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);
const a = (extra = {}) => ({ label: 'Skip slide', url: '/skip', method: 'post', ...extra });

test('a boolean `checked` makes a menuitemcheckbox; undefined or null keeps a plain menuitem', () => {
    assert.equal(typeof placement.menuItemRole, 'function', 'actionPlacement exports menuItemRole');
    assert.equal(placement.menuItemRole(a({ checked: true })), 'menuitemcheckbox');
    assert.equal(placement.menuItemRole(a({ checked: false })), 'menuitemcheckbox');
    assert.equal(placement.menuItemRole(a()), 'menuitem');
    assert.equal(placement.menuItemRole(a({ checked: null })), 'menuitem', 'the server sends null for an action that is not a checkbox');
});

test('the descriptor type declares `checked`', () => {
    assert.match(source('Action/actionPlacement.ts.stub'), /export type ActionDescriptor = \{[\s\S]*?\bchecked\?: boolean \| null;/);
});

test('ActionMenuItems renders a checkable action as a DropdownMenuCheckboxItem bound to `checked`, emitting the action on select', () => {
    const items = source('Action/ActionMenuItems.vue.stub');
    assert.match(items, /import \{ DropdownMenuCheckboxItem, DropdownMenuItem \} from '@\/components\/ui\/dropdown-menu';/);
    const checkbox = items.match(/<DropdownMenuCheckboxItem\b[^>]*>/)?.[0] ?? '';
    assert.match(checkbox, /v-if="menuItemRole\(action\) === 'menuitemcheckbox'"/, 'the first branch, before any link branch');
    assert.match(checkbox, /:model-value="action\.checked === true"/);
    assert.match(checkbox, /:disabled="busy \|\| action\.disabled"/);
    assert.match(checkbox, /@select="select\(action\)"/);
    assert.ok(items.indexOf('<DropdownMenuCheckboxItem') < items.indexOf('<DropdownMenuItem'), 'checked before the dialog, document and link rows');
    assert.doesNotMatch(checkbox, /@update:model-value/, 'the host flips `checked`; the row never keeps its own state');
});

test('a row menu opened from the item focuses its first item, checkbox items included', () => {
    assert.match(source('Workbench/WorkbenchNavigator.vue.stub'), /\[role=menu\]\[data-state="open"\] :is\(\[role=menuitem\], \[role=menuitemcheckbox\]\):not\(\[data-disabled\]\)/);
});

test('the navigator declares its `item` slot with typed props', () => {
    assert.match(source('Workbench/WorkbenchNavigator.vue.stub'), /defineSlots<\{\s*item\?\(props: \{ item: WorkbenchItem; index: number; selected: boolean; current: boolean \}\): unknown;?\s*\}>\(\)/);
});

test('the Workbench declares `#navigator-item` with typed props and forwards it in every layout that renders the navigator', () => {
    assert.match(source('Workbench/workbench.ts.stub'), /export type WorkbenchNavigatorItemSlotProps = \{ item: WorkbenchItem; selected: boolean; index: number \};/);
    const wb = source('Workbench/Workbench.vue.stub');
    assert.match(wb, /'navigator-item'\?\(props: WorkbenchNavigatorItemSlotProps\): unknown;/);
    const navigators = wb.match(/<WorkbenchNavigator\b[\s\S]*?<\/slot>/g) ?? [];
    assert.equal(navigators.length, 4, 'columns, drawer, tabs and the overlay sheet');
    for (const nav of navigators) {
        assert.match(nav, /<template v-if="hasNavigatorItem" #item="\{ item, selected, index \}"><slot name="navigator-item" :item="item" :selected="selected" :index="index" \/><\/template>/);
    }
    assert.match(wb, /const hasNavigatorItem = computed\(\(\) => !!slots\['navigator-item'\]\);/);
    for (const layout of ['data-workbench-columns', 'data-workbench-columns-lg', 'data-workbench-tabs', 'data-workbench-navigator-overlay']) assert.ok(wb.includes(layout), layout);
});

test('the option wrapper stays the navigator\'s: the slot renders inside role="option", and the menus stay outside the listbox', () => {
    const nav = source('Workbench/WorkbenchNavigator.vue.stub');
    const listbox = nav.slice(nav.indexOf('role="listbox"'), nav.indexOf('</ul>'));
    const option = listbox.slice(listbox.indexOf('<li'), listbox.indexOf('</li>'));
    assert.match(option, /role="option"/);
    assert.match(option, /<slot name="item" :item="item" :index="index" :selected="wb\.selected\.includes\(item\.id\)" :current="wb\.current === item\.id">/);
    assert.doesNotMatch(listbox, /<button\b|<DropdownMenu\b|<ActionMenuItems\b/);
});
