import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat } from './shape.mjs';

// ActionBar's and ButtonGroup's optional `itemClass`: the host's own classes on every menu row, after the row's own, with destructive and disabled kept.
// Checked by source, comments stripped; the browser (?item-class) reads a row's rendered height and colour.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue');
const group = read('Action/ButtonGroup.vue');
const items = read('Action/ActionMenuItems.vue');

test('itemClass is an optional string on ActionBar and ButtonGroup, in the components and the contract', () => {
    assert.match(bar, /itemClass\?: string[;}]/);
    assert.match(group, /itemClass\?: string[;}]/);
    const contract = read('contract.ts');
    assert.match(contract, /interface ActionBarProps \{[^}]*itemClass\?: string\}/);
    assert.match(contract, /interface ButtonGroupProps \{[^}]*itemClass\?: string;[^}]*triggerClass\?: string\}/);
});

test('ActionBar hands itemClass to every ActionMenuItems and ButtonGroup it renders', () => {
    assert.equal((bar.match(/<ActionMenuItems /g) ?? []).length, (bar.match(/<ActionMenuItems [^>]*:item-class="itemClass"/g) ?? []).length);
    assert.equal((bar.match(/<ButtonGroup /g) ?? []).length, (bar.match(/<ButtonGroup [^>]*:item-class="itemClass"/g) ?? []).length);
});

test('every menu row adds it after its own classes; destructive and disabled stay', () => {
    assert.match(items, /const rowClass = computed\(\(\) => props\.itemClass \?\? groupItemClass\(\)\);/);
    assert.equal((items.match(/<DropdownMenu(?:Checkbox)?Item /g) ?? []).length, 5);
    assert.equal((items.match(/<DropdownMenu(?:Checkbox)?Item [^>]*(?::class="rowClass"|rowClass\]")/g) ?? []).length, 5, 'all five row kinds');
    assert.equal((items.match(/'text-destructive-text focus:text-destructive-text', action\.disabled && 'cursor-not-allowed opacity-50', rowClass\]/g) ?? []).length, 2);
    assert.match(group, /provideMenuItemClass\(\(\) => props\.itemClass\);/);
    // DropdownItem / DropdownLink: merged last onto the inner control (not the padded-twice row); DropdownItem's wrapper slot has no control, so its row takes it.
    const item = read('Menu/DropdownItem.vue');
    assert.match(item, /const rowControlClass = \(\) => cn\(controlClass, itemClass\(\)\);/);
    assert.equal((item.match(/:class="rowControlClass\(\)"/g) ?? []).length, 3, 'button, anchor and Inertia link');
    assert.match(item, /:class="\$slots\.wrapper \? itemClass\(\) : undefined"/);
    const link = read('Menu/DropdownLink.vue');
    assert.match(link, /const control = \(own: string\) => cn\(own, itemClass\(\)\);/);
    assert.equal((link.match(/:class="control\('[^']*px-4 py-2[^']*'\)"/g) ?? []).length, 3, 'button, anchor and Inertia link');
    assert.doesNotMatch(link, /<DropdownMenuItem[^>]*itemClass/);
    const modal = read('Menu/DropdownModalLink.vue');
    assert.match(modal, /provideMenuItemClass\(\(\) => undefined\);/, 'DropdownModalLink keeps it off its DropdownItem row');
    assert.match(modal, /:class="linkClass\(\)"/, 'and merges it onto its ModalLink');
});
