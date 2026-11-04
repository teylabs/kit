import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// SwatchPicker: swatch.ts (plain TypeScript) runs here without Vue; the component's markup and keys are checked by source, and the browser job
// (browser/run.mjs, ?swatch) renders it, drives the keys and runs axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-swatch-'));
writeFileSync(join(dir, 'swatch.ts'), read('Form/swatch.ts.stub'));
const { contrastRatio, luminance, markColour, normaliseHex, swatchList } = await import(pathToFileURL(join(dir, 'swatch.ts')).href);
const source = read('Form/SwatchPicker.vue.stub');

test('normaliseHex takes six digits with or without #, any case and spacing, and refuses the rest', () => {
    assert.equal(normaliseHex(' #1d4ed8 '), '#1D4ED8');
    assert.equal(normaliseHex('deb100'), '#DEB100');
    for (const bad of ['', '#abc', '#12345G', 'red', '#1234567', '##123456']) assert.equal(normaliseHex(bad), null, bad);
});

test('luminance and contrast follow WCAG 2', () => {
    assert.equal(luminance('#000000'), 0);
    assert.equal(luminance('#FFFFFF'), 1);
    assert.equal(Math.round(contrastRatio('#000000', '#FFFFFF') * 100) / 100, 21);
    assert.equal(contrastRatio('#777777', '#FFFFFF'), contrastRatio('#FFFFFF', '#777777'));
    assert.ok(contrastRatio('#DEB100', '#FFFFFF') < 3, 'the light gold is faint on white');
    assert.ok(contrastRatio('#1D4ED8', '#FFFFFF') >= 4.5);
});

test('the check mark is black on light swatches and white on dark ones', () => {
    assert.equal(markColour('#FFFFFF'), '#000000');
    assert.equal(markColour('#DEB100'), '#000000');
    assert.equal(markColour('#1F2937'), '#FFFFFF');
    assert.equal(markColour('#000000'), '#FFFFFF');
    for (const hex of ['#DEB100', '#1D4ED8', '#B91C1C', '#15803D', '#7C3AED']) assert.ok(contrastRatio(hex, markColour(hex)) >= 3, hex);
});

test('swatchList puts the suggested first, each colour once, normalised, and drops non-colours', () => {
    assert.deepEqual(swatchList(['#abcdef', 'nope', '#1D4ED8'], ['#1d4ed8', '#000000', '#ABCDEF', '#000000']), [
        { hex: '#ABCDEF', suggested: true },
        { hex: '#1D4ED8', suggested: true },
        { hex: '#000000', suggested: false },
    ]);
    assert.deepEqual(swatchList([], []), []);
});

test('SwatchPicker is one named radiogroup of radios with a roving tabindex, arrows, Home and End', () => {
    assert.match(source, /role="radiogroup" :aria-label="label"/);
    assert.match(source, /role="radio"/);
    assert.match(source, /:aria-checked="isPicked\(swatch\.hex\) \? 'true' : 'false'"/);
    assert.match(source, /:tabindex="swatch\.index === stop \? 0 : -1"/);
    for (const key of ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End']) assert.ok(source.includes(`${key}:`), key);
    assert.match(source, /\(index \+ 1\) % count/, 'the arrows wrap');
    assert.match(source, /event\.preventDefault\(\)/);
});

test('SwatchPicker: the default swatch stands for an empty value, the mark colour comes from the swatch, a faint colour is hinted, a bad hex is posted as typed', () => {
    assert.match(source, /model\.value = swatch\.hex === fallback\.value \? '' : swatch\.hex/);
    assert.match(source, /color: markColour\(swatch\.hex\)/);
    assert.match(source, /contrastRatio\(shown\.value, normaliseHex\(props\.background\) as string\) < props\.minContrast/);
    assert.match(source, /role="status" data-swatch-contrast/);
    assert.match(source, /const posted = computed\(\(\) => \(typedInvalid\.value \? typed\.value\.trim\(\) : picked\.value\)\)/);
    assert.match(source, /<Collapsible :title="hexLabel"/);
    assert.match(source, /<slot name="preview" :colour="shown" \/>/);
});

test('SwatchPicker is exported from @kit/ui with its colour helpers, and typed in the contract', () => {
    assert.match(read('index.ts.stub'), /export \{ default as SwatchPicker \} from '\.\/Form\/SwatchPicker\.vue';/);
    assert.match(read('index.ts.stub'), /export \{ contrastRatio, luminance, markColour, normaliseHex \} from '\.\/Form\/swatch';/);
    assert.match(read('contract.ts.stub'), /export interface SwatchPickerProps \{ label: string; palette: string\[\]/);
});
