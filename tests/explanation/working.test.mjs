// The ComputedValue working helpers (working.ts.stub) against the synthetic fixture; display only, no arithmetic.
import assert from 'node:assert/strict';
import { copyFileSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, test } from 'node:test';

const root = new URL('../../', import.meta.url).pathname;
const directory = mkdtempSync(join(tmpdir(), 'kit-working-'));
copyFileSync(join(root, 'resources/features/core/components/Kit/Explanation/working.ts.stub'), join(directory, 'working.ts'));
const { humanWorkingText, ledgerRows, safeLink, workingText } = await import(join(directory, 'working.ts'));
after(() => rmSync(directory, { recursive: true, force: true }));
const fixture = JSON.parse(readFileSync(join(root, 'tests/fixtures/explanation.json'), 'utf8'));
const [total, mean] = fixture.roots;

test('safe links allow http(s), root-relative and fragments only', () => {
    for (const url of ['https://example.test/a', 'http://example.test', '/records/1', '#source']) assert.equal(safeLink(url), url);
    for (const url of ['javascript:alert(1)', '//evil.test', 'data:text/html,x', 'mailto:a@b']) assert.equal(safeLink(url), undefined);
});

test('ledger rows list children in operand order before their parent', () => {
    const rows = ledgerRows(total).map(row => row.id);
    assert.equal(rows.at(-1), 'total');
    assert.ok(rows.indexOf('subtotal') < rows.indexOf('total'));
    assert.equal(new Set(rows).size, rows.length);
    for (const child of total.children) assert.ok(rows.includes(child.id));
});

test('human working text carries every posted display string and nothing computed', () => {
    const text = humanWorkingText(total);
    for (const row of ledgerRows(total)) assert.ok(text.includes(`${row.label}: ${row.value.display}`));
    assert.ok(text.includes('Manual: Additional delivery requested'));
});

test('technical working text keeps exact strings, sources and the snapshot line', () => {
    const text = workingText(total, { snapshotKey: fixture.snapshotKey, rulesVersion: fixture.rulesVersion, locale: fixture.locale, frozen: fixture.frozen });
    assert.ok(text.includes('-10.125'));
    assert.ok(text.includes('Rule: '));
    assert.ok(text.endsWith(`Frozen snapshot ${fixture.snapshotKey}; schema 1; rules ${fixture.rulesVersion}; locale ${fixture.locale}`));
    assert.ok(workingText(mean).includes('Divisor: '));
});
