import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// SegmentedToggle (a neutral view switch) and PaneTabs, which takes its look. Checked by source and by kit.css's tokens; the browser job (browser/run.mjs,
// ?segmented) renders it, drives the keys and runs axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const css = readFileSync(new URL('../../css/kit.css.stub', kit), 'utf8');

function block(selector) {
    const start = css.indexOf(`${selector} {`);
    assert.ok(start >= 0, `kit.css has ${selector}`);
    const body = css.slice(css.indexOf('{', start) + 1, css.indexOf('}', start));
    return Object.fromEntries([...body.matchAll(/(--[\w-]+):\s*([^;]+);/g)].map(match => [match[1], match[2].trim()]));
}
function rgb(value) {
    const match = value.match(/^hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)$/);
    assert.ok(match, `an hsl() colour: ${value}`);
    const [h, s, l] = [Number(match[1]), Number(match[2]) / 100, Number(match[3]) / 100];
    const f = n => { const k = (n + h / 30) % 12; return Math.round((l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k - 3, 9 - k, 1))) * 255) / 255; };
    return [f(0), f(8), f(4)];
}
const luminance = c => { const [r, g, b] = c.map(v => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4)); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
const contrast = (a, b) => { const [l1, l2] = [luminance(rgb(a)), luminance(rgb(b))].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };
const selectedClasses = source => source.match(/=== (?:option|tab)\.value \? '([^']*\bborder-[^']*)'/)?.[1] ?? '';

test('SegmentedToggle is a named radiogroup of radios with aria-checked and a roving tabindex', () => {
    const source = read('Form/SegmentedToggle.vue.stub');
    assert.match(source, /role="radiogroup" :aria-label="label"/);
    assert.match(source, /role="radio"/);
    assert.match(source, /:aria-checked="model === option\.value \? 'true' : 'false'"/);
    assert.match(source, /:tabindex="index === stop \? 0 : -1"/);
    assert.match(source, /:disabled="option\.disabled"/);
    assert.match(source, /defineModel<string>/);
    assert.match(source, /options: SegmentedToggleOption\[\]; label: string; size\?: 'default' \| 'sm'/);
    assert.match(source, /icon\?: Component; disabled\?: boolean/);
});

test('SegmentedToggle handles the arrows, Home and End over the enabled options only', () => {
    const source = read('Form/SegmentedToggle.vue.stub');
    for (const key of ['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End']) assert.match(source, new RegExp(`${key}: list\\[`), key);
    assert.match(source, /option\.disabled \? -1 : index/, 'disabled options leave the roving list');
    assert.match(source, /@keydown="key\(\$event, index\)"/);
    assert.match(source, /event\.preventDefault\(\)/);
    assert.match(source, /focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring/, 'a visible focus ring');
});

test('the selected segment is neutral in SegmentedToggle and PaneTabs: a bordered surface, never primary', () => {
    for (const file of ['Layout/PaneTabs.vue.stub', 'Form/SegmentedToggle.vue.stub']) {
        const source = read(file);
        const selected = selectedClasses(source);
        assert.match(selected, /\bborder-muted-foreground\b/, `${file} borders the selected segment`);
        assert.match(selected, /\bbg-background\b/, `${file} raises the selected segment`);
        assert.doesNotMatch(source, /bg-primary|'primary'|text-primary-foreground/, `${file} has no primary fill`);
        assert.match(source, /bg-muted p-0\.5/, `${file} sits in a muted track`);
    }
});

test('PaneTabs keeps its single-choice toggle group semantics', () => {
    const source = read('Layout/PaneTabs.vue.stub');
    assert.match(source, /<ToggleGroupRoot type="single" :model-value="selected" :aria-label="label"/);
    assert.match(source, /<ToggleGroupItem/);
});

test('the selected border keeps 3:1 against the track and the segment, light and dark (WCAG 1.4.11)', () => {
    for (const [mode, selector] of [['light', ':where(:root)'], ['dark', ':where(.dark)']]) {
        const t = block(selector);
        for (const surface of ['muted', 'background']) {
            const ratio = contrast(t['--muted-foreground'], t[`--${surface}`]);
            assert.ok(ratio >= 3, `${mode} --muted-foreground on --${surface}: ${ratio.toFixed(2)}:1`);
        }
        assert.ok(contrast(t['--foreground'], t['--muted']) >= 4.5, `${mode} label on the track`);
        assert.ok(contrast(t['--foreground'], t['--background']) >= 4.5, `${mode} label on the segment`);
    }
});

test('SegmentedToggle is exported, typed in the contract and delivered by core', () => {
    assert.ok(read('index.ts.stub').includes("export { default as SegmentedToggle } from './Form/SegmentedToggle.vue';"));
    assert.ok(read('index.ts.stub').includes("export type { SegmentedToggleOption } from './Form/SegmentedToggle.vue';"));
    assert.match(read('contract.ts.stub'), /export interface SegmentedToggleProps \{ options: \{ value: string; label: string; icon\?: import\('vue'\)\.Component; disabled\?: boolean \}\[\]; label: string; size\?: 'default' \| 'sm'; modelValue: string \}/);
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    assert.ok(feature.includes('"resources/js/components/Kit/Form/SegmentedToggle.vue"'));
});
