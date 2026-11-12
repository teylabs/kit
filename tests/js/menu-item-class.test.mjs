import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat } from './shape.mjs';

// ActionBar's and ButtonGroup's optional `itemClass`: the host's own classes on every menu row, after the row's own, with destructive and disabled kept.
// Checked by source, comments stripped; the browser (?item-class) reads a row's rendered height and colour.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue.stub');
const group = read('Action/ButtonGroup.vue.stub');
const items = read('Action/ActionMenuItems.vue.stub');

test('itemClass is an optional string on ActionBar and ButtonGroup, in the components and the contract', () => {
    assert.match(bar, /itemClass\?: string[;}]/);
    assert.match(group, /itemClass\?: string[;}]/);
    const contract = read('contract.ts.stub');
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
    for (const row of ['Menu/DropdownItem.vue.stub', 'Menu/DropdownLink.vue.stub']) assert.match(read(row), /:class="itemClass\(\)"/, row);
});
