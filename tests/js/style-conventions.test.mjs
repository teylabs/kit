import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Kit stubs style with utility classes only: a `style=` or `:style` may set nothing but `--*` custom properties, which a class then reads
// (`w-(--kit-form-progress)`). A host's Tailwind-only conventions then hold for every kit file. A caller's own `style` reaches a kit component only through
// `customProperties(...)` (inputSelect.ts), which keeps its `--*` properties and drops the rest. There are no exemptions.
const root = fileURLToPath(new URL('../../resources/features', import.meta.url));
const stubs = readdirSync(root, { recursive: true }).filter((path) => path.endsWith('.vue.stub'));

export function styleViolations(source) {
    const template = source.replace(/<script[\s\S]*?<\/script>/g, '').replace(/<style[\s\S]*?<\/style>/g, '');
    const found = [];
    for (const [binding, bound, value] of template.matchAll(/(?<![\w-])(:?)style="([^"]*)"/g)) {
        if (bound === '') {
            // A static style: every declaration names a custom property.
            const properties = value.split(';').map((part) => part.split(':')[0].trim()).filter(Boolean);
            if (properties.some((property) => !property.startsWith('--'))) found.push(binding);
            continue;
        }
        // A bound style: customProperties(...) of a caller's style, or an object literal (or a ternary of them, or undefined) whose keys are all quoted
        // custom properties, which may spread customProperties(...) and conditional objects of the same kind.
        if (/^customProperties\([\w.]+\)$/.test(value.trim())) continue;
        const rest = value.replace(/\.\.\.customProperties\([\w.]+\)/g, '').replace(/\.\.\.\(/g, '(');
        const keys = [...rest.matchAll(/(?:^|[{,]\s*)(['"`]?)([\w$-]+)\1\s*:/g)].map((match) => [match[1], match[2]]);
        const objects = /\{/.test(rest);
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
    assert.deepEqual(styleViolations('<template><div :style="customProperties(attrs.style)" /><div :style="{ ...customProperties(attrs.style), ...(h ? { \'--kit-x\': `${h}px` } : {}) }" /></template>'), []);
    assert.deepEqual(styleViolations('<template><div :style="attrs.style as string | undefined" /></template>').length, 1);
    assert.deepEqual(styleViolations('<template><div :style="{ ...customProperties(attrs.style), color: c }" /></template>').length, 1);
});

for (const stub of stubs) {
    test(`${stub} binds only custom properties in style`, () => assert.deepEqual(styleViolations(readFileSync(join(root, stub), 'utf8')), []));
}
