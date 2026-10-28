import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// RadioCardGroup, EmojiTray and Button's lg/xl sizes and link forms. The tray's throttle and roving keys run here without Vue; the components are checked by source (the kit has
// no browser test runner, so nothing here renders or takes a tap).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-radio-emoji-'));
writeFileSync(join(dir, 'emojiTray.ts'), read('Form/emojiTray.ts.stub'));
const { DEFAULT_REACTIONS, throttleGate, rovingIndex } = await import(pathToFileURL(join(dir, 'emojiTray.ts')).href);

test('the throttle lets one press through per window and drops the rest', () => {
    let now = 0;
    const gate = throttleGate(1000, () => now);
    assert.equal(gate(), true, 'the first press passes');
    now = 400;
    assert.equal(gate(), false, 'a press inside the window is dropped');
    now = 999;
    assert.equal(gate(), false);
    now = 1000;
    assert.equal(gate(), true, 'the window is measured from the last press let through');
    now = 1500;
    assert.equal(gate(), false);
    assert.equal(throttleGate(0, () => 5)(), true, 'a zero throttle never drops');
});

test('the roving keys move and wrap within the tray', () => {
    assert.equal(rovingIndex('ArrowRight', 0, 6), 1);
    assert.equal(rovingIndex('ArrowRight', 5, 6), 0);
    assert.equal(rovingIndex('ArrowLeft', 0, 6), 5);
    assert.equal(rovingIndex('ArrowDown', 2, 6), 3);
    assert.equal(rovingIndex('Home', 4, 6), 0);
    assert.equal(rovingIndex('End', 1, 6), 5);
    assert.equal(rovingIndex('Enter', 1, 6), null, 'other keys are left to the button');
    assert.equal(rovingIndex('ArrowRight', 0, 0), null);
});

test('every default reaction has a spoken label', () => {
    assert.ok(DEFAULT_REACTIONS.length >= 4);
    for (const reaction of DEFAULT_REACTIONS) assert.ok(reaction.emoji && reaction.label && reaction.label !== reaction.emoji);
});

test('EmojiTray is a labelled toolbar with one tab stop, named buttons and reduced-motion presses', () => {
    const source = read('Form/EmojiTray.vue.stub');
    assert.match(source, /role="toolbar" :aria-label="label"/);
    assert.match(source, /:tabindex="index === tabStop \? 0 : -1"/, 'a roving tabindex: one tab stop');
    assert.match(source, /:aria-label="reaction\.label"/, 'each button is named by its label');
    assert.match(source, /<span aria-hidden="true">\{\{ reaction\.emoji \}\}<\/span>/, 'the emoji itself is not read out');
    assert.match(source, /motion-safe:active:scale-90 motion-reduce:transition-none/);
    assert.match(source, /if \(gate\(\)\) emit\('react', reaction\);/, 'a press emits only through the throttle');
    assert.match(source, /throttle: 1000/);
    assert.match(source, /type="button"/);
});

test('RadioCardGroup is native radios in cards, a radiogroup named by its Field', () => {
    const source = read('Form/RadioCardGroup.vue.stub');
    assert.match(source, /role="radiogroup" :aria-label="label" :aria-labelledby="label \? undefined : `\$\{id\}-label`"/);
    assert.match(source, /:aria-describedby="`\$\{id\}-description \$\{id\}-error`"/);
    assert.match(source, /<input :id="optionId\(index\)" v-model="model" type="radio" class="sr-only" :name="groupName" :value="option\.value" :disabled="disabled \|\| option\.disabled"/, 'one native radio per card: the browser gives one tab stop and arrow keys, and it submits with a form');
    assert.match(source, /model === option\.value \? 'border-primary ring-2 ring-primary' : 'border-border'/, 'a selected state');
    assert.match(source, /disabled \|\| option\.disabled \? 'cursor-not-allowed opacity-50'/, 'a disabled state');
    assert.match(source, /has-\[:focus-visible\]:outline-2/, 'the card shows the focus ring of its hidden radio');
    assert.match(source, /min-h-14/, 'a large tap target');
    assert.match(source, /<slot v-if="showResults" name="result" :option="option" :selected="model === option\.value" :percent="percent\(option\)">/);
    assert.match(source, /w-\(--kit-radio-result\)[^"]*" :style="\{ '--kit-radio-result': `\$\{percent\(option\)\}%` \}"/, 'the result bar width is a custom property');
    assert.match(read('Form/Field.vue.stub'), /<Label :id="`\$\{id\}-label`" :for="id">/, 'Field names its label for a group control');
});

test('Button has lg (the host size) and xl (the host lg made a ~96px tap target)', () => {
    const source = read('Form/Button.vue.stub');
    assert.match(source, /size\?: 'default' \| 'sm' \| 'lg' \| 'xl' \| 'icon'/);
    assert.match(source, /const hostSize = computed\(\(\) => \(props\.size === 'xl' \? 'lg' : props\.size\)\);/);
    assert.match(source, /const xlClass = 'h-24 min-w-24 rounded-xl px-8 text-lg \[&_svg\]:size-6';/);
    assert.match(source, /:size="hostSize"/);
});

test('Button with href is a link styled as a button, never a button inside a link', () => {
    const source = read('Form/Button.vue.stub');
    const markup = source.slice(source.indexOf('<template>'));
    assert.match(markup, /<BaseButton v-if="!href" [^>]*:type="kind"[^>]*><slot \/><\/BaseButton>/, 'without href it is the button as before');
    assert.match(markup, /<BaseButton v-else as-child :variant="base" :size="hostSize" :class="blend">/, 'with href the host button styles its child: one element, the link');
    assert.doesNotMatch(markup.slice(markup.indexOf('v-else as-child')), /<button\b/, 'no <button> is written inside the link branch');
    assert.match(markup, /<a v-if="external" :href="href" :target="target" :rel="rel" data-button-link>/, 'external is a plain <a>');
    assert.match(source, /const rel = computed\(\(\) => \(props\.target === '_blank' \? 'noopener noreferrer' : undefined\)\);/);
    assert.match(markup, /<Link v-else :href="href" :method="method" :data="data" :preserve-scroll="preserveScroll" :as="visitAsButton \? 'button' : 'a'" :type="visitAsButton \? 'button' : undefined"/, 'an Inertia Link, rendered as a <button> for a non-GET visit');
    assert.match(source, /const visitAsButton = computed\(\(\) => props\.method !== 'get'\);/);
    assert.match(source, /method: 'get'/);
    assert.doesNotMatch(source, /from '@inertiajs\/core'/, 'the visit data type is the kit\'s own');
});

test('all three are exported, typed in the contract and delivered by core', () => {
    const barrel = read('index.ts.stub');
    for (const line of ["export { default as RadioCardGroup } from './Form/RadioCardGroup.vue';", "export { default as EmojiTray } from './Form/EmojiTray.vue';", "export { DEFAULT_REACTIONS } from './Form/emojiTray';", "export type { EmojiReaction } from './Form/emojiTray';", "export type { RadioCardOption } from './Form/RadioCardGroup.vue';"]) assert.ok(barrel.includes(line), line);
    const contract = read('contract.ts.stub');
    assert.match(contract, /export interface RadioCardGroupProps /);
    assert.match(contract, /export interface EmojiTrayProps /);
    assert.match(contract, /size\?: 'default' \| 'sm' \| 'lg' \| 'xl' \| 'icon'/);
    assert.match(contract, /href\?: string; method\?: 'get' \| 'post' \| 'put' \| 'patch' \| 'delete'; data\?: import\('\.\/Form\/Button\.vue'\)\.VisitData; preserveScroll\?: boolean; external\?: boolean; target\?: string/);
    assert.ok(barrel.includes("export type { VisitData, VisitValue } from './Form/Button.vue';"));
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    for (const file of ['Form/RadioCardGroup.vue', 'Form/EmojiTray.vue', 'Form/emojiTray.ts']) assert.ok(feature.includes(`"resources/js/components/Kit/${file}"`), file);
});
