import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// ActionBar's opt-in `fillAll` (one primary pill) by source, comments stripped, and intent `panel` as an operation by running actionPlacement.
// The rendered fill, padding, dimming and the panel button are the browser's (?fill-all).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const bar = read('Action/ActionBar.vue');
const dir = mkdtempSync(join(tmpdir(), 'kit-fill-all-'));
copyFileSync(new URL('Action/actionPlacement.ts', kit), join(dir, 'actionPlacement.ts'));
const { isNavigation, rendersAsLink } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);

test('fillAll is a new optional boolean defaulting off; filled keeps its boolean type', () => {
    assert.match(bar, /filled\?: boolean; fillAll\?: boolean;/);
    assert.match(bar, /fillAll: false,/);
    assert.match(read('contract.ts'), /interface ActionBarProps \{[^}]*filled\?: boolean;[^}]*fillAll\?: boolean;/);
    assert.match(bar, /fillAll\?: boolean; dividerClass\?: string;/, 'dividerClass is a new optional string');
    assert.match(bar, /dividerClass: undefined,/);
    assert.match(bar, /dividerClass\?: string; triggerClass\?: string;/, 'triggerClass is a new optional string');
    assert.match(bar, /triggerClass: undefined,/);
    assert.match(read('contract.ts'), /interface ActionBarProps \{[^}]*dividerClass\?: string;[^}]*triggerClass\?: string;/);
    assert.equal((bar.match(/!fillAll && 'px-2\.5', fillAll && triggerClass\]/g) ?? []).length, 2, 'both fillAll triggers take triggerClass last');
    assert.match(read('contract.ts'), /interface ActionBarProps \{[^}]*fillAll\?: boolean;[^}]*dividerClass\?: string;/);
});

test('fillAll applies on a pill only: the group is the primary pill with ButtonGroup\'s divider, semibold segments py-2, the first pl-3.5 and the rest px-3, disabled still dims', () => {
    assert.match(bar, /const fillAll = computed\(\(\) => pill\.value && props\.fillAll\);/);
    assert.match(bar, /fillAll\.value && 'rounded-full bg-primary text-primary-foreground shadow-xs \[--kit-outline:color-mix\(in_oklab,currentColor_20%,transparent\)\] \[:where\(&>\*:not\(:last-child\)\)\]:border-r \[:where\(&>\*:not\(:last-child\)\)\]:border-solid \[:where\(&>\*:not\(:last-child\)\)\]:border-outline'\]\)/, 'ButtonGroup\'s divider in the outline token, set as the inverse FloatingToolbar sets it, under :where() so dividerClass overrides it');
    assert.match(read('Layout/FloatingToolbar.vue'), /\[--kit-outline:color-mix\(in_oklab,currentColor_20%,transparent\)\]/, 'the same outline the inverse toolbar defines');
    assert.match(bar, /if \(fillAll\.value\) return \[base\.replace\('font-medium', 'font-semibold'\), 'bg-transparent py-2 text-primary-foreground hover:bg-primary-foreground\/10', first \? 'pr-3 pl-3\.5' : 'px-3', props\.dividerClass \?\? ''\];/);
    assert.match(bar, /const base = '[^']*disabled:opacity-50';/, 'the shared base keeps the disabled dimming');
    assert.match(bar, /pill\.value \? 'h-9 px-3\.5' : 'h-9 px-3'/, 'the default pill padding is unchanged');
    assert.equal((bar.match(/segment\(filled\([^)]*\)\)?, index === 0\)/g) ?? []).length, 2, 'both pill paths mark the first segment');
    assert.match(bar, /segment\(false, part\.inline\.length === 0\)/, 'a menu-only group\'s trigger is its first segment');
    assert.match(bar, /segment\(false, compact\.inline\.length === 0\)/);
});

test('menuTriggerLabel: unset keeps "Actions" on a menu-only trigger, a string replaces it, null leaves the chevron named "More actions"', () => {
    assert.match(bar, /triggerClass\?: string; menuTriggerLabel\?: string \| null;/);
    assert.match(bar, /menuTriggerLabel: undefined,/);
    assert.match(read('contract.ts'), /interface ActionBarProps \{[^}]*menuTriggerLabel\?: string \| null;/);
    assert.match(bar, /:label="part\.inline\.length \? undefined : menuOnlyLabel" :trigger-class="triggerClass"/);
    assert.doesNotMatch(bar, /:menu-label=/, 'null falls back to ButtonGroup\'s "More actions"');
    assert.match(read('Action/ButtonGroup.vue'), /menuLabel \?\? 'More actions'/);
    assert.match(bar, /const menuOnlyLabel = computed\(\(\) => props\.menuTriggerLabel === undefined \? 'Actions' : props\.menuTriggerLabel,?\s*\);/);
    assert.doesNotMatch(bar, /:label="part\.inline\.length \? undefined : 'Actions'"/, 'no hard-coded trigger text');
});

test('triggerClass reaches ButtonGroup\'s trigger, last, in the full and compact layouts', () => {
    const group = read('Action/ButtonGroup.vue');
    assert.match(group, /itemClass\?: string; triggerClass\?: string;?\}/);
    assert.match(group, /triggerClass: undefined,?\}/);
    assert.match(group, /'-ml-px rounded-l-none', triggerClass,?\s*\]/, 'last on the trigger');
    assert.equal((bar.match(/:trigger-class="triggerClass"/g) ?? []).length, 2, 'both ButtonGroup layouts pass it');
    assert.match(read('contract.ts'), /interface ButtonGroupProps \{[^}]*triggerClass\?: string;?\s*\}/);
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
