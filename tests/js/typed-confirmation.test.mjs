import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Typed confirmation: the match that gates Confirm and the `confirmation` a visit sends run here without Vue; Dialog, RowActions, BulkBar and ActionBar are
// checked by source (no browser runner). The server re-check is tests/TypedConfirmationCheck.php.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-typed-confirm-'));
writeFileSync(join(dir, 'confirmText.ts'), read('Overlay/confirmText.ts'));
const { typedMatches, confirmationData } = await import(pathToFileURL(join(dir, 'confirmText.ts')).href);

test('Confirm is gated on the exact text, case-sensitive by default', () => {
    assert.equal(typedMatches('', 'Acme Ltd'), false, 'empty never matches');
    assert.equal(typedMatches('Acme', 'Acme Ltd'), false, 'a prefix does not match');
    assert.equal(typedMatches('acme ltd', 'Acme Ltd'), false, 'case matters by default');
    assert.equal(typedMatches('Acme Ltd', 'Acme Ltd'), true);
    assert.equal(typedMatches('  Acme Ltd \n', 'Acme Ltd'), true, 'surrounding whitespace from a paste is ignored');
    assert.equal(typedMatches('acme LTD', 'Acme Ltd', false), true, 'case-insensitive when asked');
    assert.equal(typedMatches('Acme  Ltd', 'Acme Ltd'), false, 'inner spacing still has to match');
    assert.equal(typedMatches('anything', ''), false, 'an empty expected text never enables Confirm');
});

test('a confirmed visit sends the typed text as confirmation, and nothing without one', () => {
    assert.deepEqual(confirmationData(' Acme Ltd '), { confirmation: 'Acme Ltd' });
    assert.equal(confirmationData(undefined), undefined);
});

