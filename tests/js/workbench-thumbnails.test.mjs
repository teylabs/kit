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

