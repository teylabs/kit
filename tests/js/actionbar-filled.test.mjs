import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat } from './shape.mjs';

// ActionBar's opt-in filled primary segment on the ButtonGroup (outline) path, ButtonGroup's disabled look, and the icon names a descriptor may use. Checked by
// source, comments stripped (no browser runner): what the components decide, not their markup.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue.stub');
const group = read('Action/ButtonGroup.vue.stub');

test('filled is an optional prop defaulting off, so the outline path stays semibold only unless a host opts in', () => {
    assert.match(bar, /filled\?: boolean\}>/);
    assert.match(bar, /filled: false\}/);
    assert.match(read('contract.ts.stub'), /interface ActionBarProps \{[^}]*primary\?: boolean; filled\?: boolean\}/);
    const lead = bar.match(/const lead = \(index: number\) => ([^;]+);/)?.[1];
    assert.ok(lead, 'lead() decides the leading segment class');
    assert.match(lead, /'primary-action font-semibold!'/);
    assert.match(lead, /props\.filled && 'bg-primary! text-primary-foreground!/);
    assert.match(bar, /const filled = \(index: number\) => \(props\.primary \|\| props\.filled\) && index === 0;/);
    // Both ButtonGroup layouts (full and compact) use it.
    assert.equal((bar.match(/:class="lead\(index\)"/g) ?? []).length, 2);
});

test('a disabled segment is dimmed in ButtonGroup, hover and fill included, but not the action in flight', () => {
    assert.match(group, /\[&>\*:disabled\]:opacity-50!/);
    assert.match(group, /\[&>\*\[aria-disabled=true\]:not\(\[aria-busy=true\]\)\]:opacity-50!/);
    assert.match(group, /\[&>\*:disabled\]:cursor-not-allowed!/);
    // Applied on both appearances, not only the outline one.
    assert.match(group, /weights\[weight\], disabled\]/);
});

test('icon names: the key fallbacks and plus resolve, and history and mail are named', () => {
    const icons = read('Action/actionIcons.ts.stub');
    for (const name of ["'credit-card'", 'pencil', 'plus', 'history', 'mail']) assert.match(icons, new RegExp(`[{ ]${name}: \\w+[,}]`));
});
