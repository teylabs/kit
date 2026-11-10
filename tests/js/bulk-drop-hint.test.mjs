import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// BulkDrop's limit hint, checked by source (the kit has no Vue renderer in node): `showLimit` (default true) shows sizeHint(maxBytes) when no `hint` is
// given; `showLimit: false` with no hint renders no hint element, and nothing points aria-describedby at one.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const source = readFileSync(new URL('Form/BulkDrop.vue.stub', kit), 'utf8');
const template = source.slice(source.indexOf('<template>'));

test('showLimit is an optional prop, default true, typed in the contract', () => {
    assert.match(source, /showLimit\?: boolean;/);
    assert.match(source, /withDefaults\([\s\S]*\{ showLimit: true \}\)/);
    const contract = readFileSync(new URL('contract.ts.stub', kit), 'utf8');
    const props = contract.slice(contract.indexOf('export interface BulkDropProps'));
    assert.match(props.slice(0, props.indexOf('}')), /showLimit\?: boolean;/);
});

test('the hint text is hint, else the size limit when showLimit, else empty', () => {
    assert.match(source, /props\.hint \?\? \(props\.showLimit \? sizeHint\(props\.maxBytes\) : ''\)/);
});

test('an empty hint renders no element and no aria-describedby', () => {
    const hint = template.match(/<p\s[^>]*data-dropzone-hint[^>]*>/);
    assert.ok(hint, 'the hint element is in the template');
    assert.match(hint[0], /v-if="hintText\(\)"/);
    assert.doesNotMatch(template, /aria-describedby/);
});
