import assert from 'node:assert/strict';
import test from 'node:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Every stacking variable a stub uses (`z-(--z-dialog)`, `var(--z-header)`) is defined in kit.css; an undefined one makes the z-index `auto`, so an overlay sits
// under a host's sticky bar. Checked by source across every feature's stubs.
const features = fileURLToPath(new URL('../../resources/features/', import.meta.url));
const css = readFileSync(join(features, 'core/css/kit.css.stub'), 'utf8');
const stubs = dir => readdirSync(dir).flatMap(name => {
    const path = join(dir, name);
    return statSync(path).isDirectory() ? stubs(path) : name.endsWith('.stub') ? [path] : [];
});
const defined = new Map([...css.matchAll(/^\s*(--z-[a-z-]+):\s*(\d+);/gm)].map(([, name, value]) => [name, Number(value)]));

test('every --z-* variable a stub uses is defined in kit.css', () => {
    const used = new Map();
    for (const path of stubs(features)) {
        for (const [, name] of readFileSync(path, 'utf8').matchAll(/(?:z-\(|var\()(--z-[a-z-]+)\)/g)) used.set(name, path.slice(features.length));
    }
    assert.ok(used.size > 0, 'the stubs use the stacking scale');
    for (const [name, where] of used) assert.ok(defined.has(name), `${name} (used in ${where}) is not defined in kit.css`);
});

test('the scale runs sticky < header < dropdown < dialog < toast, on :where(:root) so a host :root overrides it', () => {
    const order = ['--z-sticky', '--z-header', '--z-dropdown', '--z-dialog', '--z-toast'].map(name => defined.get(name));
    assert.ok(order.every(Number.isFinite), 'all five steps are defined');
    assert.deepEqual([...order].sort((a, b) => a - b), order, 'each step is above the one before');
    assert.match(css, /:where\(:root\) \{\n    --z-sticky: \d+;/);
});
