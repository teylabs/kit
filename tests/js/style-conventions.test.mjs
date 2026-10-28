import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Kit stubs style with utility classes only: a `style=` or `:style` may set nothing but `--*` custom properties, which a class then reads
// (`w-(--kit-form-progress)`). A host's Tailwind-only conventions then hold for every kit file. InputSelect forwards the caller's own `style` unchanged.
const root = fileURLToPath(new URL('../../resources/features', import.meta.url));
const stubs = readdirSync(root, { recursive: true }).filter((path) => path.endsWith('.vue.stub'));
const passthrough = new Set([':style="attrs.style as string | undefined"']);

export function styleViolations(source) {
    const template = source.replace(/<script[\s\S]*?<\/script>/g, '').replace(/<style[\s\S]*?<\/style>/g, '');
    const found = [];
    for (const [binding, bound, value] of template.matchAll(/(?<![\w-])(:?)style="([^"]*)"/g)) {
        if (passthrough.has(binding)) continue;
        if (bound === '') {
            // A static style: every declaration names a custom property.
            const properties = value.split(';').map((part) => part.split(':')[0].trim()).filter(Boolean);
            if (properties.some((property) => !property.startsWith('--'))) found.push(binding);
            continue;
        }
        // A bound style: an object literal (or a ternary of them, or undefined) whose keys are all quoted custom properties.
        const keys = [...value.matchAll(/(?:^|[{,]\s*)(['"`]?)([\w$-]+)\1\s*:/g)].map((match) => [match[1], match[2]]);
        const objects = /\{/.test(value);
        if (!objects || keys.length === 0 || keys.some(([quote, key]) => quote === '' || !key.startsWith('--'))) found.push(binding);
    }

    return found;
}

test('the check fails on a geometry or colour style and passes custom properties', () => {
    assert.deepEqual(styleViolations('<template><div :style="{ width: `${p}%` }" /></template>'), [':style="{ width: `${p}%` }"']);
    assert.deepEqual(styleViolations('<template><div :style="{ top: `${t}px`, \'--kit-x\': 1 }" /></template>').length, 1);
    assert.deepEqual(styleViolations('<template><div style="width: 2rem" /></template>'), ['style="width: 2rem"']);
    assert.deepEqual(styleViolations('<template><div :style="styles" /></template>'), [':style="styles"']);
    assert.deepEqual(styleViolations('<template><div :style="{ \'--kit-x\': `${p}%` }" /><div style="--a: 1; --b: none" /></template>'), []);
    assert.deepEqual(styleViolations('<template><div :style="open ? { \'--kit-x\': `${h}px` } : undefined" /></template>'), []);
});

for (const stub of stubs) {
    test(`${stub} binds only custom properties in style`, () => assert.deepEqual(styleViolations(readFileSync(join(root, stub), 'utf8')), []));
}
