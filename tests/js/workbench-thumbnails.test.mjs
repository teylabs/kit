import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Navigator thumbnails (`thumbnail` on a WorkbenchItem) and the filmstrip layout. Checked by source with comments stripped, so prose can't satisfy an assertion;
// browser/run.mjs renders a filmstrip in Chromium with axe, reorder and a delayed image.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));
const nav = source('Workbench/WorkbenchNavigator.vue.stub');
const bench = source('Workbench/Workbench.vue.stub');
const listbox = nav.match(/<ul\b[\s\S]*?<\/ul>/)?.[0] ?? '';

test('the item type declares an optional thumbnail of { src, alt }, alt required, and the navigator layout', () => {
    const types = source('Workbench/workbench.ts.stub');
    assert.match(types, /export type WorkbenchThumbnail = \{ src: string; alt: string \};/);
    assert.match(types, /export type WorkbenchItem = \{[^}]*\bthumbnail\?: WorkbenchThumbnail \| null \};/);
    assert.match(types, /export type WorkbenchNavigatorLayout = 'list' \| 'filmstrip';/);
});

test('WorkbenchNavigator takes `layout`, default list; the Workbench takes `navigatorLayout`, default list, and passes it in every layout', () => {
    assert.match(nav, /layout\?: WorkbenchNavigatorLayout \}>\(\), \{[^}]*layout: 'list' \}\);/);
    assert.match(bench, /navigatorLayout\?: WorkbenchNavigatorLayout;/);
    assert.match(bench, /navigatorLayout: 'list'/);
    const navigators = bench.match(/<WorkbenchNavigator\b[^>]*>/g) ?? [];
    assert.equal(navigators.length, 4, 'columns, drawer, tabs and overlay');
    for (const tag of navigators) assert.match(tag, /:layout="navigatorLayout"/);
});

test('a thumbnail is an <img> inside the option with the item\'s alt, lazy and async, never draggable on its own, in both layouts', () => {
    const images = listbox.match(/<img\b[^>]*>/g) ?? [];
    assert.equal(images.length, 2, 'one for the list row, one for the filmstrip row');
    for (const img of images) {
        assert.match(img, /:src="item\.thumbnail\.src"/);
        assert.match(img, /:alt="item\.thumbnail\.alt"/);
        assert.match(img, /loading="lazy"/);
        assert.match(img, /decoding="async"/);
        assert.match(img, /draggable="false"/);
        assert.match(img, /absolute inset-0 size-full object-cover/, 'fills its fixed box');
    }
    const option = listbox.match(/<li\b[\s\S]*?<\/li>/)?.[0] ?? '';
    assert.ok(option.includes('<img'), 'inside the role="option" row');
});

test('each image sits in a fixed 16:9 box, and a filmstrip row always has the box (an empty placeholder without a thumbnail)', () => {
    const boxes = listbox.match(/<span\b[^>]*data-workbench-thumbnail-box[^>]*>/g) ?? [];
    assert.equal(boxes.length, 2);
    for (const box of boxes) assert.match(box, /\baspect-video\b/);
    const filmstrip = listbox.match(/<template v-if="filmstrip">[\s\S]*?<\/template>/)?.[0] ?? '';
    const box = filmstrip.match(/<span\b[^>]*data-workbench-thumbnail-box[^>]*>/)?.[0] ?? '';
    assert.doesNotMatch(box, /v-if/, 'the filmstrip box renders for every item');
    assert.match(box, /\bw-full\b/);
    assert.match(filmstrip, /<img v-(?:else-)?if="item\.thumbnail"/);
    assert.match(filmstrip, /aria-hidden="true" data-workbench-number>\{\{ wb\.order\.indexOf\(item\.id\) \+ 1 \}\}/, 'the number in the whole order, not read out');
    assert.match(filmstrip, /<slot name="item"/, 'the item slot still renders the label part');
});

test('the filmstrip keeps the listbox: nothing focusable in it, and the row menus stay in the sibling column', () => {
    assert.doesNotMatch(listbox, /<(button|a|input|select|textarea)\b/);
    assert.doesNotMatch(listbox.replace(/<li\b[^>]*>/, ''), /tabindex="0"/);
    assert.match(nav, /<div v-if="anyActions" class="col-start-2 [^"]*grid-rows-subgrid[^"]*" data-workbench-item-menus>/);
    assert.match(nav, /filmstrip \? 'items-start pt-1\.5' : 'items-center'/, 'the menu cell aligns to the top of a tall filmstrip row');
});

test('a `thumbnail` slot fills the filmstrip box ahead of the image and placeholder, inert and aria-hidden, in a box that contains it', () => {
    assert.match(source('Workbench/workbench.ts.stub'), /export type WorkbenchNavigatorThumbnailSlotProps = \{ item: WorkbenchItem; index: number; selected: boolean \};/);
    assert.match(nav, /defineSlots<\{[^>]*\bthumbnail\?\(props: WorkbenchNavigatorThumbnailSlotProps\): unknown;?\s*\}>\(\)/);
    const filmstrip = listbox.match(/<template v-if="filmstrip">[\s\S]*?<\/template>/)?.[0] ?? '';
    const box = filmstrip.match(/<span\b[^>]*data-workbench-thumbnail-box[^>]*>([\s\S]*?)<\/span>\s*<span class="flex min-w-0 items-center/)?.[1] ?? '';
    const wrapper = box.match(/<span\b[^>]*data-workbench-thumbnail-slot[^>]*>/)?.[0] ?? '';
    assert.match(wrapper, /v-if="\$slots\.thumbnail"/, 'first: the slot wins');
    assert.match(wrapper, /aria-hidden="true"/);
    assert.match(wrapper, /\binert\b/);
    assert.match(wrapper, /absolute inset-0 overflow-hidden/);
    assert.match(box, /<slot name="thumbnail" :item="item" :index="index" :selected="wb\.selected\.includes\(item\.id\)" \/><\/span>\s*<img v-else-if="item\.thumbnail"/, 'then the image, else the empty placeholder');
    assert.match(filmstrip.match(/<span\b[^>]*data-workbench-thumbnail-box[^>]*>/)?.[0] ?? '', /\bcontain-strict\b/);
    const list = listbox.match(/<template v-else>[\s\S]*?<\/template>/)?.[0] ?? '';
    assert.doesNotMatch(list, /name="thumbnail"/, 'list rows take no thumbnail slot');
});

test('the Workbench forwards #navigator-thumbnail to every navigator, typed', () => {
    assert.match(bench, /'navigator-thumbnail'\?\(props: WorkbenchNavigatorThumbnailSlotProps\): unknown;/);
    const navigators = bench.match(/<WorkbenchNavigator\b[\s\S]*?<\/WorkbenchNavigator>/g) ?? [];
    assert.equal(navigators.length, 4);
    for (const n of navigators) assert.match(n, /<template v-if="hasNavigatorThumbnail" #thumbnail="\{ item, index, selected \}"><slot name="navigator-thumbnail" :item="item" :index="index" :selected="selected" \/><\/template>/);
});
