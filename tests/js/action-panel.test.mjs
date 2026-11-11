import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { flat } from './shape.mjs';

// ActionPanel's API and what it decides (tone, alignment, which parts render), and ActionCard leaving out its body when the default slot renders nothing.
// hasSlotContent runs under Vue's server renderer (Vue from typecheck/, installed by the vue-tsc job); the components are checked by source, comments stripped.
// The rendered layout, the phone stack and contrast are the browser's (?action-panel).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => flat(strip(readFileSync(new URL(path, kit), 'utf8')));
const panel = read('Card/ActionPanel.vue.stub');
const card = read('Card/ActionCard.vue.stub');
const vueDir = new URL('../../typecheck/node_modules/vue/', import.meta.url);

test('ActionPanel: title, optional description, level, tone and align, with their defaults', () => {
    assert.match(panel, /defineProps<\{title: string; description\?: string \| null; level\?: 1 \| 2 \| 3 \| 4 \| 5 \| 6; tone\?: 'default' \| 'danger'; align\?: 'center' \| 'top'\}>\(\)/);
    assert.match(panel, /\{description: null, level: 3, tone: 'default', align: 'center'\}/);
    assert.match(panel, /<CardHeader :title="title" :level="level"/, 'the title is CardHeader\'s heading at level');
});

test('ActionPanel is built on Card and CardHeader: description capped at max-w-xl; actions centred by default, pinned to the top with align="top"', () => {
    assert.match(panel, /import Card from '\.\/Card\.vue';/);
    assert.match(panel, /import CardHeader from '\.\/CardHeader\.vue';/);
    assert.match(panel, /<p class="max-w-xl" data-action-panel-description>/);
    assert.match(panel, /:actions-align="align === 'top' \? 'start' : 'center'"/);
    const header = read('Card/CardHeader.vue.stub');
    assert.match(header, /actionsAlign\?: 'center' \| 'start'[;}]/, 'CardHeader gains an optional actionsAlign');
    assert.match(header, /actionsAlign: 'center'/, 'defaulting to the old centred row');
    assert.match(header, /actionsAlign === 'start' \? 'items-start' : 'items-center'/);
});

test('ActionPanel: danger keeps the card background (no tint) and colours only the edge and the title', () => {
    assert.match(panel, /:class="tone === 'danger' && 'border-destructive\/50'"/);
    assert.match(panel, /tone === 'danger' \? 'text-destructive-text' : 'text-foreground'/);
    assert.doesNotMatch(panel, /bg-destructive/, 'no red fill behind the text or the actions');
});

test('ActionPanel: the actions and the body render only when their slot renders something; it is exported and registered', () => {
    assert.match(panel, /v-if="hasSlotContent\(\$slots\.actions\)"/);
    assert.match(panel, /<CardBody v-if="hasSlotContent\(\$slots\.default\)"/);
    assert.match(read('index.ts.stub'), /export \{default as ActionPanel\} from '\.\/Card\/ActionPanel\.vue';/);
    const files = JSON.parse(readFileSync(new URL('../../resources/features/core/feature.json', import.meta.url), 'utf8')).files;
    for (const path of ['Card/ActionPanel.vue', 'Card/slotContent.ts']) assert.ok(files[`resources/js/components/Kit/${path}`], path);
});

test('ActionCard: no CardBody when the default slot is empty; #body still replaces it', () => {
    assert.match(card, /<slot name="body"><CardBody v-if="hasSlotContent\(\$slots\.default\)" class="overflow-auto"><slot \/><\/CardBody><\/slot>/);
});

test('hasSlotContent: no slot, comments, blank text and empty fragments are empty; an element or text is content', { skip: !existsSync(vueDir) && 'Vue is installed in typecheck/ by the vue-tsc job' }, async () => {
    const require = createRequire(new URL('../../typecheck/package.json', import.meta.url));
    const vue = require('vue');
    const dir = mkdtempSync(join(tmpdir(), 'kit-slot-content-'));
    writeFileSync(join(dir, 'slotContent.ts'), readFileSync(new URL('Card/slotContent.ts.stub', kit), 'utf8').replace("from 'vue'", `from '${pathToFileURL(require.resolve('vue')).href}'`));
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
