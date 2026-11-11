import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// ActionBar's opt-in `fillAll` (one primary pill) by source, comments stripped, and intent `panel` as an operation by running actionPlacement.
// The rendered fill, padding, dimming and the panel button are the browser's (?fill-all).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue.stub');
const dir = mkdtempSync(join(tmpdir(), 'kit-fill-all-'));
copyFileSync(new URL('Action/actionPlacement.ts.stub', kit), join(dir, 'actionPlacement.ts'));
const { isNavigation, rendersAsLink } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);

test('fillAll is a new optional boolean defaulting off; filled keeps its boolean type', () => {
    assert.match(bar, /filled\?: boolean; fillAll\?: boolean;/);
    assert.match(bar, /fillAll: false,/);
    assert.match(read('contract.ts.stub'), /interface ActionBarProps \{[^}]*filled\?: boolean;[^}]*fillAll\?: boolean;/);
});

test('fillAll applies on a pill only: the group is the primary pill, segments py-2, the first pl-3.5 and the rest px-3, disabled still dims', () => {
    assert.match(bar, /const fillAll = computed\(\(\) => pill\.value && props\.fillAll\);/);
    assert.match(bar, /fillAll\.value && 'rounded-full bg-primary text-primary-foreground shadow-xs'/);
    assert.match(bar, /if \(fillAll\.value\) return \[base, 'bg-transparent py-2 text-primary-foreground hover:bg-primary-foreground\/10', first \? 'pr-3 pl-3\.5' : 'px-3'\];/);
    assert.match(bar, /const base = '[^']*disabled:opacity-50';/, 'the shared base keeps the disabled dimming');
    assert.match(bar, /pill\.value \? 'h-9 px-3\.5' : 'h-9 px-3'/, 'the default pill padding is unchanged');
    assert.equal((bar.match(/segment\(filled\([^)]*\)\)?, index === 0\)/g) ?? []).length, 2, 'both pill paths mark the first segment');
    assert.match(bar, /segment\(false, part\.inline\.length === 0\)/, 'a menu-only group\'s trigger is its first segment');
    assert.match(bar, /segment\(false, compact\.inline\.length === 0\)/);
});

test('a GET with intent panel is an operation (a button), like a dialog; a plain GET stays a link', () => {
    const get = (extra = {}) => ({ label: 'Details', url: '/trips/3', method: 'get', ...extra });
    for (const intent of ['panel', 'dialog']) {
        assert.equal(isNavigation(get({ intent })), false, intent);
        assert.equal(rendersAsLink(get({ intent })), false, intent);
    }
    assert.equal(isNavigation(get()), true);
    assert.equal(rendersAsLink(get()), true);
    assert.equal(rendersAsLink(get({ intent: 'document' })), true);
});
