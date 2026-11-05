import assert from 'node:assert/strict';
import test from 'node:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Kit fields and the Repeater work outside an Inertia <Form> with no Vue warning: only formContext.ts calls Inertia's useFormContext, and it injects with a default.
const kit = fileURLToPath(new URL('../../resources/features/core/components/Kit/', import.meta.url));
const files = dir => readdirSync(dir).flatMap(name => (statSync(join(dir, name)).isDirectory() ? files(join(dir, name)) : [join(dir, name)]));

test('no kit component calls useFormContext() itself', () => {
    const offenders = files(kit).filter(file => file.endsWith('.stub') && !file.endsWith('formContext.ts.stub') && /\buseFormContext\(/.test(readFileSync(file, 'utf8')));
    assert.deepEqual(offenders, []);
});
