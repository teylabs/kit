import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// DescriptionList and EmptyState, checked by source: the kit has no browser test runner, so nothing here renders. Markup, accessibility and exports.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const template = source => source.slice(source.indexOf('<template>'));

test('DescriptionList copies a value with a labelled button and a polite status', () => {
    const source = read('Layout/DescriptionList.vue.stub');
    assert.match(source, /navigator\.clipboard\.writeText\(String\(item\.description\)\)/);
    assert.match(source, /:aria-label="`Copy \$\{item\.term\}`"/);
    assert.match(source, /<span role="status" class="sr-only">/, 'the result is announced once, politely');
    assert.match(source, /const canCopy = \(item: DescriptionItem\) => !blank\(item\) && \(item\.copy \?\? props\.copyable\);/, 'per item, or the whole list; never a blank value');
    assert.match(source, /type="button"/);
});

test('EmptyState is a region labelled by its title, never a live region', () => {
    const source = read('Feedback/EmptyState.vue.stub');
    const markup = template(source);
    assert.match(markup, /<section :aria-labelledby="titleId"/);
    assert.match(markup, /:is="`h\$\{level\}`" :id="titleId"/);
    assert.doesNotMatch(markup, /role="(status|alert|log)"|aria-live/, 'it does not announce itself');
    assert.match(markup, /aria-hidden="true" data-empty-state-icon/, 'the icon is decorative');
});

test('both are exported from the barrel, typed in the contract and delivered by core', () => {
    const barrel = read('index.ts.stub');
    assert.match(barrel, /export \{ default as DescriptionList \} from '\.\/Layout\/DescriptionList\.vue';/);
    assert.match(barrel, /export type \{ DescriptionItem \} from '\.\/Layout\/DescriptionList\.vue';/);
    assert.match(barrel, /export \{ default as EmptyState \} from '\.\/Feedback\/EmptyState\.vue';/);
    const contract = read('contract.ts.stub');
    assert.match(contract, /export interface DescriptionListProps /);
    assert.match(contract, /export interface EmptyStateProps /);
    const feature = readFileSync(new URL('../../feature.json', kit), 'utf8');
    assert.match(feature, /"resources\/js\/components\/Kit\/Layout\/DescriptionList\.vue"/);
    assert.match(feature, /"resources\/js\/components\/Kit\/Feedback\/EmptyState\.vue"/);
});
