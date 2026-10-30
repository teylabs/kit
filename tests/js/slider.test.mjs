import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Slider: a native range input. Checked by source; the browser job (browser/run.mjs, ?slider) renders it, drives the keys and a drag, and runs axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const source = read('Form/Slider.vue.stub');

test('Slider is a native range input with v-model, min, max and step', () => {
    assert.match(source, /type="range"/);
    assert.match(source, /const model = defineModel<number>\(\);/);
    assert.match(source, /min: 0, max: 100, step: 1/);
    assert.match(source, /:min="min"\s+:max="max"\s+:step="step"/);
    assert.match(source, /:disabled="disabled"/);
    assert.doesNotMatch(source, /role="slider"/, 'the native role, not an ARIA one');
});

test('Slider emits input live and change on release, and names its value', () => {
    assert.match(source, /defineEmits<\{ input: \[value: number\]; change: \[value: number\] \}>\(\)/);
    assert.match(source, /@input="onInput"\s+@change="onChange"/);
    assert.match(source, /model\.value = next;\s+emit\('input', next\);/);
    assert.match(source, /:aria-valuetext="props\.valueText !== undefined \? text : undefined"/);
    assert.match(source, /<output v-if="showValue" :for="inputId" aria-live="off"/, 'a visible value linked by for, not announced on every step');
});

test('Slider forwards caller attributes to the input and joins the Field ids', () => {
    assert.match(source, /defineOptions\(\{ inheritAttrs: false \}\);/);
    assert.match(source, /<input\s+v-bind="\$attrs"/, 'attributes land on the input, not the wrapper');
    assert.match(source, /:aria-invalid="invalid \|\| undefined"/);
    assert.match(source, /`\$\{props\.id\}-description`, `\$\{props\.id\}-error`/);
    assert.match(source, /:aria-describedby="describedBy"/);
    assert.match(source, /accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring/, 'token styling and a focus ring');
});

test('Slider is read-only inside a locked LockedForm, and a caller\'s disabled wins', () => {
    assert.match(source, /const locked = computed\(\(\) => lock\.locked && !props\.disabled\);/);
    assert.match(source, /:aria-readonly="locked \|\| undefined"/);
    assert.match(source, /if \(locked\.value\) \{\s+input\.value = String\(value\.value\);\s+return null;/, 'a key or drag puts the value back');
});

test('Slider is exported, typed in the contract and delivered by core', () => {
    assert.ok(read('index.ts.stub').includes("export { default as Slider } from './Form/Slider.vue';"));
    assert.match(read('contract.ts.stub'), /export interface SliderProps \{ id\?: string; min\?: number; max\?: number; step\?: number; disabled\?: boolean; invalid\?: boolean; valueText\?: \(value: number\) => string; showValue\?: boolean; modelValue\?: number \}/);
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    assert.ok(feature.includes('"resources/js/components/Kit/Form/Slider.vue"'));
});
