import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Workbench :actions-bar="false" drops the actions bar while the store's togglePane/focusRegion keep driving the panes, and in development it warns when the
// headings are off too and nothing has toggled a pane. Checked by source (no browser runner); comments are stripped so prose can't satisfy an assertion.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const strip = s => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1').replace(/<!--[\s\S]*?-->/g, '');
const source = path => strip(readFileSync(new URL(path, kit), 'utf8'));

