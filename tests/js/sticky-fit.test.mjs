import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const layout = new URL('../../resources/features/core/components/Kit/Layout/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-sticky-'));
copyFileSync(new URL('stickyFit.ts.stub', layout), join(dir, 'stickyFit.ts'));
const { fitsViewport } = await import(pathToFileURL(join(dir, 'stickyFit.ts')).href);

test('sticky only while the content fits below the header (13rem reserved at 16px)', () => {
    assert.equal(fitsViewport(500, 900, 16), true);
    assert.equal(fitsViewport(692, 900, 16), true, 'exactly fits: 900 - 208');
    assert.equal(fitsViewport(693, 900, 16), false);
    assert.equal(fitsViewport(100, 200, 16), false, 'a viewport shorter than the reserved space never sticks');
});
test('bad measurements fail closed to the page flow', () => {
    assert.equal(fitsViewport(Number.NaN, 900, 16), false);
    assert.equal(fitsViewport(100, 900, 0), false);
});
test('the aside has no fixed viewport height and no inner overflow', () => {
    const src = readFileSync(new URL('SplitPane.vue.stub', layout), 'utf8');
    const line = src.split('\n').find(l => l.includes('ref="asideEl"'));
    assert.ok(line, 'aside wrapper found');
    assert.doesNotMatch(line, /dvh|vh|overflow-y|max-h-|h-\[/);
    assert.match(line, /v-if="!stickyAside"/, 'PaneScroll wraps the aside only when not sticky');
});
