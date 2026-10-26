import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// The row count shows on a single page too; only the page links need more than one page. The stub has no SSR harness, so this reads its template.
const stub = readFileSync(fileURLToPath(new URL('../../resources/features/core/components/Kit/Table/DataTable.vue.stub', import.meta.url)), 'utf8');
const footer = stub.split('\n').find((line) => line.includes('data-table-pagination'));
const pager = stub.split('\n').find((line) => line.includes('aria-label="Pages"'));

test('the table has a footer line and a pager', () => {
    assert.ok(footer && pager);
});

test('the count line shows whenever there are rows, not only past one page', () => {
    assert.match(footer, /v-if="table\.pagination\.total > 0"/);
    assert.doesNotMatch(footer, /lastPage/);
    assert.match(stub, /\{\{ table\.pagination\.from \}\}–\{\{ table\.pagination\.to \}\} of \{\{ table\.pagination\.total \}\}/);
});

test('the pager nav shows only past one page', () => {
    assert.match(pager, /<nav v-if="table\.pagination\.lastPage > 1"/);
});
