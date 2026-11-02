import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The navigator's per-item menu: the item's own action descriptors in a row menu whose button sits outside the listbox, opened by the button, by Shift+F10 or
// the context-menu key, or by a right-click on the item. The logic runs here; the components are checked by source with comments stripped, so prose can't satisfy
// an assertion. browser/run.mjs drives the same menu in Chromium with axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));
const dir = mkdtempSync(join(tmpdir(), 'kit-workbench-menu-'));
writeFileSync(join(dir, 'workbench.ts'), readFileSync(new URL('Workbench/workbench.ts.stub', kit), 'utf8'));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);

test('Shift+F10 and the context-menu key open an item menu; nothing else does', () => {
    assert.equal(wb.opensItemMenu({ key: 'F10', shiftKey: true }), true);
    assert.equal(wb.opensItemMenu({ key: 'ContextMenu' }), true);
    assert.equal(wb.opensItemMenu({ key: 'F10' }), false);
    assert.equal(wb.opensItemMenu({ key: 'F10', shiftKey: true, ctrlKey: true }), false);
    assert.equal(wb.opensItemMenu({ key: 'ContextMenu', altKey: true }), false);
    assert.equal(wb.opensItemMenu({ key: 'ArrowDown' }), false);
    assert.equal(wb.workbenchCommand({ key: 'F10', shiftKey: true }, { inNavigator: true, typing: false }), null, 'no Workbench command claims Shift+F10');
});

test('an item\'s menu shows its own descriptors, none without them, and the button is named for the item', () => {
    const actions = [{ key: 'duplicate', label: 'Duplicate', url: '/d', method: 'post' }];
    assert.deepEqual(wb.itemActions({ actions }), actions);
    assert.deepEqual(wb.itemActions({}), []);
    assert.deepEqual(wb.itemActions({ actions: [] }), []);
    assert.equal(wb.itemMenuLabel({ label: 'Slide 3' }), 'Actions for Slide 3');
    assert.equal(wb.itemMenuLabel({ label: 'Slide 3' }, 'Options for'), 'Options for Slide 3');
});

test('the menu buttons render per item with actions, in a column outside the listbox, and none for an item without', () => {
    const nav = source('Workbench/WorkbenchNavigator.vue.stub');
    const listbox = nav.slice(nav.indexOf('role="listbox"'), nav.indexOf('</ul>'));
    assert.doesNotMatch(listbox, /<button\b|<DropdownMenu\b|<a\b|<input\b/, 'nothing interactive inside the listbox');
    const menus = nav.slice(nav.indexOf('</ul>'));
    assert.match(menus, /<div v-if="anyActions"[^>]*grid-rows-subgrid[^>]*data-workbench-item-menus>/);
    assert.match(menus, /<div v-for="item in shown"[\s\S]*?<DropdownMenu v-if="itemActions\(item\)\.length" :open="menuFor === item\.id"/);
    assert.match(menus, /:aria-label="itemMenuLabel\(item, menuLabel\)"/);
    assert.match(menus, /<ActionMenuItems :actions="itemActions\(item\)" :on-action="action => emit\('action', action, item\)" \/>/);
    assert.match(nav, /const anyActions = computed\(\(\) => shown\.value\.some\(item => itemActions\(item\)\.length > 0\)\);/);
    // Visible on hover, focus and an open menu, always on a coarse pointer; a Tab stop for the current item only.
    assert.match(menus, /focus-visible:opacity-100[^']*pointer-coarse:opacity-100', hovered === item\.id \|\| menuFor === item\.id \? 'opacity-100' : 'opacity-0'/);
    assert.match(menus, /:tabindex="wb\.current === item\.id \|\| \(wb\.current === null && item\.id === shown\[0\]\?\.id\) \? 0 : -1"/);
});

test('right-click and Shift+F10 open the same menu, only when the item has actions, and prevent the browser menu only then', () => {
    const nav = source('Workbench/WorkbenchNavigator.vue.stub');
    assert.match(nav, /@keydown="onItemKeydown\(item, \$event\)"/);
    assert.match(nav, /@contextmenu="openMenu\(item, \$event\)"/);
    assert.match(nav, /if \(opensItemMenu\(event\)\) openMenu\(item, event\);/);
    assert.match(nav, /function openMenu\(item: WorkbenchItem, event: Event\): void \{\s*if \(!itemActions\(item\)\.length\) return;\s*event\.preventDefault\(\);\s*menuFrom\.value = 'item';\s*menuFor\.value = item\.id;/);
});

test('opened from the item, the first action takes focus and Escape returns focus to the item; from the button, to the button', () => {
    const nav = source('Workbench/WorkbenchNavigator.vue.stub');
    assert.match(nav, /@open-auto-focus="onMenuOpenFocus" @close-auto-focus="event => onMenuCloseFocus\(item, event\)"/);
    assert.match(nav, /function onMenuCloseFocus\(item: WorkbenchItem, event: Event\): void \{\s*if \(menuFrom\.value !== 'item'\) return;\s*event\.preventDefault\(\);\s*optionEl\(item\.id\)\?\.focus\(\);/);
    assert.match(nav, /\[role=menu\]\[data-state="open"\] :is\(\[role=menuitem\], \[role=menuitemcheckbox\]\):not\(\[data-disabled\]\)'\)\?\.focus\(\)/);
});

test('a chosen descriptor is emitted with its item, and the Workbench passes it on as its own action event', () => {
    const nav = source('Workbench/WorkbenchNavigator.vue.stub');
    assert.match(nav, /defineEmits<\{ action: \[action: ActionDescriptor, item: WorkbenchItem\] \}>\(\)/);
    const bench = source('Workbench/Workbench.vue.stub');
    assert.match(bench, /defineEmits<\{ action: \[action: ActionDescriptor, item\?: WorkbenchItem\] \}>\(\)/);
    const navigators = bench.match(/<WorkbenchNavigator ref[^\n]*?\/>/g);
    assert.equal(navigators.length, 4, 'each layout\'s navigator');
    for (const tag of navigators) assert.match(tag, /@action="\(action, item\) => emit\('action', action, item\)"/);
});
