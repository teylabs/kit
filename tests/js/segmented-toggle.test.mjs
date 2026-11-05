import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat, members } from './shape.mjs';

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
    const props = members(read('contract.ts.stub'), 'SegmentedToggleProps');
    assert.equal(props.get('options'), "options: {value: string; label: string; icon?: import('vue').Component; disabled?: boolean}[]");
    assert.equal(props.get('label'), 'label: string');
    assert.equal(props.get('size'), "size?: 'default' | 'sm'");
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    assert.ok(feature.includes('"resources/js/components/Kit/Form/SegmentedToggle.vue"'));
});
