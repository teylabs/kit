import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// ActionBar's opt-in `wrap` (a phone header), the menu's icon defaults, the iconless indent, Cancel's destructive row and the dev-only mixed-icons warning.
// Checked by source, comments stripped, and by running actionPlacement and the warning helper: what the components decide, not their markup.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue.stub');
const items = read('Action/ActionMenuItems.vue.stub');
const icons = read('Action/actionIcons.ts.stub');
const dir = mkdtempSync(join(tmpdir(), 'kit-wrap-'));
copyFileSync(new URL('Action/actionPlacement.ts.stub', kit), join(dir, 'actionPlacement.ts'));
const { actionIconName } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);
const a = (label, extra = {}) => ({ label, url: `/${label}`, method: 'post', ...extra });

test('wrap is an optional prop defaulting off, in the component and the contract', () => {
    assert.match(bar, /wrap\?: boolean[;}]/);
    assert.match(bar, /wrap: false[,}]/);
    assert.match(read('contract.ts.stub'), /interface ActionBarProps \{[^}]*wrap\?: boolean[;}]/);
});

test('wrap never compacts, renders no compact copy and does not measure; the default path still does', () => {
    assert.match(bar, /const compacted = computed\(\(\) => !props\.wrap && fit\.value\.compact\);/);
    assert.match(bar, /v-if="compacted"/);
    assert.doesNotMatch(bar, /fit\.compact/, 'the template reads the compact state only through compacted');
    assert.match(bar, /if \(props\.wrap\) \{measured\.value = null; return\}/);
    assert.match(bar, /if \(typeof ResizeObserver === 'undefined' \|\| !root\.value\) return;/, 'the default path keeps its observer');
});

test('wrap without declared groups gives each action its own part, no menu; only the first is the lead', () => {
    assert.match(bar, /props\.wrap \? shown\.value\.map\(\(action, index\) => \(\{key: `wrap-\$\{index\}`, inline: \[action\], menu: \[\] as ActionDescriptor\[\]\}\)\) : \[\{key: 'actions', \.\.\.placeActions\(shown\.value\)\}\]/);
    assert.match(bar, /const at = \(key: string, index: number\) => key\.startsWith\('wrap-'\) \? Number\(key\.slice\(5\)\) : index;/);
});

test('menu icon defaults by key and by label; an explicit icon always wins; inline rows keep only plus', () => {
    const cases = [
        ['Reopen order', 'reopen', 'lock-open'], ['Re-open order', 're-open', 'lock-open'],
        ['Cancel booking', 'cancel', 'x'], ['Decline invite', 'decline', 'x'], ['Merge records', 'merge', 'merge'],
        ['Delete note', 'delete', 'trash'], ['Remove member', 'remove', 'trash'], ['Download PDF', 'download', 'download'],
        ['Edit details', 'edit', 'pencil'],
    ];
    for (const [label, key, icon] of cases) {
        assert.equal(actionIconName(a(label, { key }), true), icon, `${key} by key`);
        assert.equal(actionIconName(a(label), true), icon, `${label} by label`);
        assert.equal(actionIconName(a('Something', { key }), true), icon, `${key} by key alone`);
        assert.equal(actionIconName(a(label, { key, icon: 'mail' }), true), 'mail', `${key}: an explicit icon wins`);
        assert.equal(actionIconName(a(label, { key })), null, `${key}: no default inline`);
    }
    assert.equal(actionIconName(a('Archive', { key: 'archive' }), true), null, 'an unknown intent stays iconless');
    assert.equal(actionIconName(a('Cancellation policy'), true), null, 'a whole word only');
});

test('every default names a known icon: lock-open, x, merge, download and trash', () => {
    for (const [name, component] of [["'lock-open'", 'LockOpen'], ['x', 'X'], ['merge', 'Merge'], ['download', 'Download'], ['trash', 'Trash'], ['pencil', 'Pencil']]) {
        assert.match(icons, new RegExp(`[{ ]${name}: ${component}[,}]`));
        assert.match(icons, new RegExp(`import \\{[^}]*\\b${component},`));
    }
});

test('the More menu: a destructive row is red, and a row without an icon keeps an icon-sized spacer when another row has one', () => {
    assert.equal((items.match(/action\.destructive && 'text-destructive-text focus:text-destructive-text'/g) ?? []).length, 2);
    assert.match(items, /const anyIcon = computed\(\(\) => props\.actions\.some\(action => menuIcon\(action\)\)\);/);
    // One spacer after each of the six row kinds' icon.
    assert.equal((items.match(/<span v-else-if="anyIcon" class="inline-block size-4 shrink-0" aria-hidden="true" data-menu-icon-spacer \/>/g) ?? []).length, 6);
});

test('mixed-icons warning: dev builds only, once per menu, only when a menu mixes', async () => {
    const source = readFileSync(new URL('Action/actionIcons.ts.stub', kit), 'utf8');
    const body = source.slice(source.indexOf('export function mixedIconsWarning'));
    const load = async (dev) => {
        const file = join(dir, `warning-${dev}.ts`);
        const { writeFileSync } = await import('node:fs');
        writeFileSync(file, body.replace(/import\.meta\.env\?\.DEV/g, String(dev)));
        return (await import(pathToFileURL(file).href)).mixedIconsWarning;
    };
    const original = console.warn;
    const seen = [];
    console.warn = (message) => seen.push(message);
    try {
        const dev = (await load(true))('ActionBar menu');
        dev([true, true]);
        dev([false, false]);
        assert.equal(seen.length, 0, 'all or none: no warning');
        dev([true, false]);
        dev([false, true]);
        assert.equal(seen.length, 1, 'warns once per menu');
        assert.match(seen[0], /ActionBar menu/);
        (await load(true))('ButtonGroup menu')([true, false]);
        assert.equal(seen.length, 2, 'another menu warns for itself');
        (await load(false))('ActionBar menu')([true, false]);
        assert.equal(seen.length, 2, 'production never warns');
    } finally {
        console.warn = original;
    }
    assert.match(items, /const warnMixed = mixedIconsWarning\('ActionBar menu'\);/);
    const group = read('Action/ButtonGroup.vue.stub');
    assert.match(group, /const warnMixed = mixedIconsWarning\('ButtonGroup menu'\);/);
    assert.match(group, /if \(!import\.meta\.env\?\.DEV \|\| !\(el instanceof HTMLElement\)\) return;/);
    assert.match(group, /<div :ref="checkIcons" class="py-1"><slot name="menu" \/><\/div>/);
});
