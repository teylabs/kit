import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stubs are TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-menu-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Menu/menuAlign.ts.stub', import.meta.url), join(dir, 'menuAlign.ts'));
const { alignThatFits } = await import(pathToFileURL(join(dir, 'menuAlign.ts')).href);

// A 256px menu in a 390px viewport.
const at = (left, right) => ({ left, right });

test('keeps the preferred edge while the menu fits that way', () => {
    assert.equal(alignThatFits('start', at(16, 100), 256, 390), 'start');
    assert.equal(alignThatFits('end', at(200, 374), 256, 390), 'end');
});

test('swaps to the other edge when the preferred one would overflow and the other fits', () => {
    assert.equal(alignThatFits('start', at(300, 380), 256, 390), 'end');
    assert.equal(alignThatFits('end', at(10, 90), 256, 390), 'start');
});

test('keeps the preferred edge when neither fits', () => {
    assert.equal(alignThatFits('start', at(150, 240), 400, 390), 'start');
    assert.equal(alignThatFits('end', at(150, 240), 400, 390), 'end');
});

test('a menu exactly as wide as the room still fits', () => {
    assert.equal(alignThatFits('start', at(134, 200), 256, 390), 'start');
    assert.equal(alignThatFits('end', at(0, 256), 256, 390), 'end');
});
