import assert from 'node:assert/strict';
import test from 'node:test';
import { stripTypeScriptTypes } from 'node:module';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Every TypeScript stub must parse: the kit has no typecheck of its own, so a broken stub otherwise surfaces only in a host's vue-tsc.
const root = fileURLToPath(new URL('../../resources/features', import.meta.url));
const stubs = readdirSync(root, { recursive: true }).filter((path) => path.endsWith('.ts.stub'));

test('the kit ships TypeScript stubs', () => assert.ok(stubs.length > 10));

for (const stub of stubs) {
    test(`${stub} parses`, () => {
        assert.doesNotThrow(() => stripTypeScriptTypes(readFileSync(join(root, stub), 'utf8')));
    });
}
