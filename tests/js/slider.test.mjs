import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Slider: a native range input. Checked by source; the browser job (browser/run.mjs, ?slider) renders it, drives the keys and a drag, and runs axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const source = read('Form/Slider.vue.stub');

test('Slider is exported, typed in the contract and delivered by core', () => {
    assert.ok(read('index.ts.stub').includes("export { default as Slider } from './Form/Slider.vue';"));
    assert.match(read('contract.ts.stub'), /export interface SliderProps \{ id\?: string; min\?: number; max\?: number; step\?: number; disabled\?: boolean; invalid\?: boolean; valueText\?: \(value: number\) => string; showValue\?: boolean; modelValue\?: number \}/);
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    assert.ok(feature.includes('"resources/js/components/Kit/Form/Slider.vue"'));
});
