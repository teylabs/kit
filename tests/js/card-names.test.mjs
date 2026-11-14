import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// The swapped card names (v0.16): ActionCard is the plain panel (title, description, actions, tone, align), SummaryCard the header-band card, which leaves out
// its body when the default slot renders nothing. hasSlotContent runs under Vue's server renderer (Vue from typecheck/, installed by the vue-tsc job); the
// components are checked by source, comments stripped. The rendered layout, the phone stack and contrast are the browser's (?card-names).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const action = read('Card/ActionCard.vue');
const summary = read('Card/SummaryCard.vue');
const vueDir = new URL('../../typecheck/node_modules/vue/', import.meta.url);

test('ActionCard (the panel): title, optional description, level, tone and align, with their defaults', () => {
    assert.match(action, /defineProps<\{title: string; description\?: string \| null; level\?: 1 \| 2 \| 3 \| 4 \| 5 \| 6; tone\?: 'default' \| 'danger'; align\?: 'center' \| 'top'\}>\(\)/);
    assert.match(action, /\{description: null, level: 3, tone: 'default', align: 'center'\}/);
    assert.match(action, /<CardHeader :title="title" :level="level"/, 'the title is CardHeader\'s heading at level');
    assert.doesNotMatch(action, /tone="muted"/, 'no header band');
});

test('ActionCard: description capped at max-w-xl; actions centred by default, pinned to the top with align="top"', () => {
    assert.match(action, /<p class="max-w-xl" data-action-card-description>/);
    assert.match(action, /v-if="\$slots\.description \? hasSlotContent\(\$slots\.description\) : description"/, 'a #description that renders nothing leaves no meta line');
    assert.match(action, /:actions-align="align === 'top' \? 'start' : 'center'"/);
    const header = read('Card/CardHeader.vue');
    assert.match(header, /actionsAlign\?: 'center' \| 'start'[;}]/, 'CardHeader gains an optional actionsAlign');
    assert.match(header, /actionsAlign: 'center'/, 'defaulting to the old centred row');
    assert.match(header, /actionsAlign === 'start' \? 'items-start' : 'items-center'/);
    assert.match(action, /stack-actions/, 'the panel stacks its actions on a phone');
    assert.match(header, /stackActions && 'max-sm:basis-full max-sm:justify-start'/);
});

test('ActionCard: danger keeps the card background (no tint) and colours only the edge and the title', () => {
    assert.match(action, /:class="tone === 'danger' && 'border-destructive\/50'"/);
    assert.match(action, /tone === 'danger' \? 'text-destructive-text' : 'text-foreground'/);
    assert.doesNotMatch(action, /bg-destructive/, 'no red fill behind the text or the actions');
    assert.match(action, /v-if="hasSlotContent\(\$slots\.actions\)"/);
    assert.match(action, /<CardBody v-if="hasSlotContent\(\$slots\.default\)"/);
});

test('SummaryCard (the header band): a muted header with #icon and #actions, no CardBody when the default slot is empty, #body still replaces it', () => {
    assert.match(summary, /<CardHeader :title="title" :level="level" tone="muted">/);
    assert.match(summary, /<template v-if="\$slots\.icon" #avatar>/);
    assert.match(summary, /<slot name="body"><CardBody v-if="hasSlotContent\(\$slots\.default\)" class="overflow-auto"><slot \/><\/CardBody><\/slot>/);
    assert.match(summary, /<CardFooter v-if="\$slots\.footer" sticky>/);
});

test('both names stay exported; no ActionPanel; the helper is registered', () => {
    const index = read('index.ts');
    assert.match(index, /export \{default as ActionCard\} from '\.\/Card\/ActionCard\.vue';/);
    assert.match(index, /export \{default as SummaryCard\} from '\.\/Card\/SummaryCard\.vue';/);
    assert.doesNotMatch(index, /ActionPanel/);
    assert.ok(existsSync(new URL('Card/slotContent.ts', kit)));
    assert.equal(existsSync(new URL('Card/ActionPanel.vue', kit)), false);
});

test('hasSlotContent: no slot, comments, blank text and empty fragments are empty; an element or text is content', { skip: !existsSync(vueDir) && 'Vue is installed in typecheck/ by the vue-tsc job' }, async () => {
    const require = createRequire(new URL('../../typecheck/package.json', import.meta.url));
    const vue = require('vue');
    const dir = mkdtempSync(join(tmpdir(), 'kit-slot-content-'));
    writeFileSync(join(dir, 'slotContent.ts'), readFileSync(new URL('Card/slotContent.ts', kit), 'utf8').replace("from 'vue'", `from '${pathToFileURL(require.resolve('vue')).href}'`));
    const { hasSlotContent } = await import(pathToFileURL(join(dir, 'slotContent.ts')).href);
    const { h, createCommentVNode, createTextVNode, Fragment } = vue;
    assert.equal(hasSlotContent(undefined), false, 'no slot');
    assert.equal(hasSlotContent(() => []), false, 'a slot that renders nothing');
    assert.equal(hasSlotContent(() => [createCommentVNode('v-if')]), false, 'a false v-if');
    assert.equal(hasSlotContent(() => [createTextVNode('  \n ')]), false, 'whitespace');
    assert.equal(hasSlotContent(() => [h(Fragment, null, [createCommentVNode('v-if'), h(Fragment, null, [])])]), false, 'empty fragments');
    assert.equal(hasSlotContent(() => [h(Fragment, null, [h('p', 'Kept')])]), true, 'an element in a fragment');
    assert.equal(hasSlotContent(() => [createTextVNode('Two members')]), true, 'text');
});
