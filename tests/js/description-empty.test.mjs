import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// DescriptionList and EmptyState, checked by source: the kit has no browser test runner, so nothing here renders. Markup, accessibility and exports.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const template = source => source.slice(source.indexOf('<template>'));

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
