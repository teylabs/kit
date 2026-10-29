import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// With no current workspace (the chooser, for someone with two or more), the switcher's label is "Choose a workspace", not "Workspaces", which would repeat the
// nav link beside it, and a host can word it through the optional `placeholder` prop. Checked by source.
const switcher = readFileSync(new URL('../../resources/features/shell/pages/Workspace/Partials/WorkspaceSwitcher.vue.stub', import.meta.url), 'utf8');

test('the switcher takes an optional placeholder prop', () => {
    assert.match(switcher, /defineProps<\{[^}]*placeholder\?: string[^}]*\}>\(\)/);
});

test('with no current workspace the label is the placeholder, else "Choose a workspace", never "Workspaces"', () => {
    assert.match(switcher, /const name = \(\) => props\.workspace\?\.name \?\? props\.switcher\?\.current\?\.name \?\? props\.placeholder \?\? 'Choose a workspace';/);
    assert.doesNotMatch(switcher, /\?\? 'Workspaces'/);
});
