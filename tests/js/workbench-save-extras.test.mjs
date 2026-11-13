import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');

test('savedAgo: null before a save, "just now" under a minute, then minutes and hours in the locale', async () => {
    const dir = mkdtempSync(join(tmpdir(), 'kit-wb-ago-'));
    // workbench.ts imports only types and plain helpers; pull out savedAgo alone so the test needs no Vue.
    const source = read('Workbench/workbench.ts');
    const fn = source.slice(source.indexOf('export function savedAgo'));
    writeFileSync(join(dir, 'ago.ts'), fn.slice(0, fn.indexOf('\n}\n') + 3));
    const { savedAgo } = await import(pathToFileURL(join(dir, 'ago.ts')).href);
    const t = 10_000_000;
    assert.equal(savedAgo(null, t), null);
    assert.equal(savedAgo(t - 30_000, t), 'just now');
    assert.equal(savedAgo(t - 2 * 60_000, t, 'en'), '2 min. ago');
    assert.equal(savedAgo(t - 3 * 3_600_000, t, 'en'), '3 hr. ago');
    assert.equal(savedAgo(t + 5000, t), 'just now', 'a clock skew never reads as the future');
});
