import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// FloatingToolbar's client items: the overflow fit, groups and the menu's handler run here without Vue (toolbarAction.ts is plain TypeScript); the markup, the
// iconOnly accessibility and the icon set are checked by source, comments stripped (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const dir = mkdtempSync(join(tmpdir(), 'kit-toolbar-items-'));
writeFileSync(join(dir, 'toolbarAction.ts'), read('Layout/toolbarAction.ts.stub'));
const { fitToolbarItems, toolbarGroups, deferToolbarAction, TOOLBAR_FIT_HYSTERESIS } = await import(pathToFileURL(join(dir, 'toolbarAction.ts')).href);

const item = (key, group = null, extra = {}) => ({ key, label: key, group, ...extra });
const keys = list => list.map(entry => entry.key);
// Nine items in three groups, each 80px, 4px gaps, a 9px separator (with its gap), a 36px trigger: 9×80 + 8×4 + 2×9 = 770 to show them all.
const nine = [item('bold', 'text'), item('italic', 'text'), item('align', 'text'), item('up', 'block'), item('down', 'block'), item('duplicate', 'block'), item('hide', 'slide'), item('chart', 'slide'), item('delete', 'slide')];
const measure = container => ({ container, widths: nine.map(() => 80), gap: 4, separator: 9, trigger: 36 });

test('everything fits: every item inline, no menu', () => {
    const fit = fitToolbarItems(nine, measure(770));
    assert.equal(fit.overflowing, false);
    assert.deepEqual(keys(fit.inline), keys(nine));
    assert.deepEqual(fit.menu, []);
});

test('too narrow: whole trailing groups move into the menu first, from the last', () => {
    // Two groups: 6×80 + 5×4 + 9 + 4 + 36 = 549.
    let fit = fitToolbarItems(nine, measure(600));
    assert.equal(fit.overflowing, true);
    assert.deepEqual(keys(fit.inline), ['bold', 'italic', 'align', 'up', 'down', 'duplicate']);
    assert.deepEqual(keys(fit.menu), ['hide', 'chart', 'delete']);
    // One group: 3×80 + 2×4 + 4 + 36 = 288.
    fit = fitToolbarItems(nine, measure(300));
    assert.deepEqual(keys(fit.inline), ['bold', 'italic', 'align']);
    assert.deepEqual(keys(fit.menu), ['up', 'down', 'duplicate', 'hide', 'chart', 'delete'], 'the menu keeps the items\' order');
});

test('narrower than the first group: its first N stay, the rest follow into the menu', () => {
    const fit = fitToolbarItems(nine, measure(210));
    assert.deepEqual(keys(fit.inline), ['bold', 'italic'], '2×80 + 4 + 4 + 36 = 204');
    assert.equal(fit.menu.length, 7);
    assert.deepEqual(fitToolbarItems(nine, measure(30)).inline, [], 'nothing fits: the menu alone');
});

test('ungrouped items are one run: the first N stay', () => {
    const plain = ['a', 'b', 'c', 'd', 'e'].map(key => item(key));
    const fit = fitToolbarItems(plain, { container: 300, widths: plain.map(() => 80), gap: 4, trigger: 36 });
    assert.deepEqual(keys(fit.inline), ['a', 'b', 'c']);
    assert.deepEqual(keys(fit.menu), ['d', 'e']);
    assert.deepEqual(toolbarGroups(plain).map(group => group.length), [5]);
});

test('unmeasured shows everything; an overflowing toolbar needs slack before showing everything again', () => {
    assert.equal(fitToolbarItems(nine, measure(0)).overflowing, false, 'zero width: server render or a hidden tab');
    assert.equal(fitToolbarItems(nine, { container: 500, widths: [] }).overflowing, false, 'widths not read yet');
    assert.equal(fitToolbarItems(nine, measure(773), true).overflowing, true, 'within the hysteresis it stays overflowing');
    assert.equal(fitToolbarItems(nine, measure(770 + TOOLBAR_FIT_HYSTERESIS), true).overflowing, false);
});

test('a menu row reaches onAction after the menu closes; a disabled one never does', () => {
    const ran = [];
    const later = [];
    deferToolbarAction(action => ran.push(action.key), item('chart'), run => later.push(run));
    assert.deepEqual(ran, [], 'not while the menu is still closing');
    later.forEach(run => run());
    assert.deepEqual(ran, ['chart']);
    deferToolbarAction(action => ran.push(action.key), item('delete', null, { disabled: true }), run => run());
    deferToolbarAction(undefined, item('hide'), run => run());
    assert.deepEqual(ran, ['chart']);
    const toolbar = strip(read('Layout/FloatingToolbar.vue.stub'));
    assert.match(toolbar, /<DropdownMenuItem v-for="item in group" :key="item\.key" :disabled="item\.disabled"[^>]*@select="chooseFromMenu\(item\)">/);
    assert.match(toolbar, /const chooseFromMenu = \(item: ToolbarAction\) => deferToolbarAction\(props\.onAction, item\);/, 'the handler is the prop, not an emit from the menu row');
});

test('the toolbar: measured full row, the fit, separators between groups, the "More actions" trigger, one row when it has items', () => {
    const toolbar = strip(read('Layout/FloatingToolbar.vue.stub'));
    assert.match(toolbar, /const fit = computed\(\(\) => fitToolbarItems\(props\.items, itemMeasure\.value \?\? \{ container: 0, widths: \[\] \}, wasOverflowing\.value\)\);/);
    assert.match(toolbar, /itemObserver = new ResizeObserver\(scheduleItems\);/);
    assert.match(toolbar, /:aria-hidden="fit\.overflowing \|\| undefined" :inert="fit\.overflowing \|\| undefined" data-toolbar-items-full/);
    assert.equal(toolbar.match(/data-toolbar-separator/g).length, 3, 'two inline rows and the measure');
    assert.match(toolbar, /<DropdownMenuSeparator v-if="index" \/>/, 'groups are separated in the menu too');
    assert.match(toolbar, /aria-label="More actions" title="More actions" data-toolbar-menu/);
    assert.match(toolbar, /items\.length && 'flex-nowrap!'/);
});

test('iconOnly: the label is the accessible name and the tooltip; the button stays a focusable button', () => {
    const toolbar = strip(read('Layout/FloatingToolbar.vue.stub'));
    assert.match(toolbar, /:aria-label="item\.iconOnly \? item\.label : undefined"/);
    assert.match(toolbar, /const tip = \(item: ToolbarAction\) => item\.reason \?\? \(item\.iconOnly \? item\.label : undefined\);/);
    assert.match(toolbar, /<span v-if="!item\.iconOnly">\{\{ item\.label \}\}<\/span>/);
    assert.match(toolbar, /<button v-for="item in group" :key="item\.key" type="button"/);
    assert.match(toolbar, /item\.iconOnly \? 'w-9 justify-center' : 'px-3\.5'/, 'a 36px target');
    assert.doesNotMatch(toolbar, /tabindex="-1"/);
});

test('the editor icon set, imported by name', () => {
    const icons = read('Action/actionIcons.ts.stub');
    for (const name of ['align-left', 'align-center', 'align-right', 'arrow-up', 'arrow-down', 'trash', 'image', 'eye', 'eye-off', 'plus', 'copy', 'chart', 'poll']) {
        assert.match(icons, new RegExp(`'${name}': [A-Z]\\w+`), name);
    }
    assert.match(icons, /^import \{[^}]+\} from '@lucide\/vue';/, 'named imports only');
    assert.doesNotMatch(icons, /import \* as/);
});
