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

// The count line on one page and the pager only past one page are checked in a browser (browser/run.mjs, ?datatable).
