import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Checkbox menu items (`checked` on an action descriptor) and the Workbench's `#navigator-item` slot. The role logic runs here; the components are checked by
// source with comments stripped, so prose can't satisfy an assertion. browser/run.mjs renders both in Chromium with axe.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));
const dir = mkdtempSync(join(tmpdir(), 'kit-checkbox-'));
copyFileSync(new URL('Action/actionPlacement.ts.stub', kit), join(dir, 'actionPlacement.ts'));
const placement = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);
const a = (extra = {}) => ({ label: 'Skip slide', url: '/skip', method: 'post', ...extra });

test('a boolean `checked` makes a menuitemcheckbox; undefined or null keeps a plain menuitem', () => {
    assert.equal(typeof placement.menuItemRole, 'function', 'actionPlacement exports menuItemRole');
    assert.equal(placement.menuItemRole(a({ checked: true })), 'menuitemcheckbox');
    assert.equal(placement.menuItemRole(a({ checked: false })), 'menuitemcheckbox');
    assert.equal(placement.menuItemRole(a()), 'menuitem');
    assert.equal(placement.menuItemRole(a({ checked: null })), 'menuitem', 'the server sends null for an action that is not a checkbox');
});

