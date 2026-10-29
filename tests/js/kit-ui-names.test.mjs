import assert from 'node:assert/strict';
import test from 'node:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// A host that points @kit/ui at a module of its own must export every name the kit's pages import from it. The README lists them; this keeps the list whole,
// so a page that starts importing another (the mcp Authorize page's InputSelect) is listed for those hosts.
const root = fileURLToPath(new URL('../../', import.meta.url));
const stubs = [];
const walk = dir => readdirSync(dir).forEach(name => (statSync(join(dir, name)).isDirectory() ? walk(join(dir, name)) : name.endsWith('.stub') && stubs.push(join(dir, name))));
walk(join(root, 'resources/features'));

test('the README names every value the kit\'s pages import from @kit/ui', () => {
    const imported = new Set();
    for (const file of stubs) {
        for (const [, names] of readFileSync(file, 'utf8').matchAll(/import \{([^}]*)\} from '@kit\/ui'/g)) {
            names.split(',').map(name => name.trim()).filter(name => name && !name.startsWith('type ')).forEach(name => imported.add(name.split(/\s+as\s+/)[0]));
        }
    }
    assert.ok(imported.has('InputSelect'), 'the scan finds the pages\' imports');
    const readme = readFileSync(join(root, 'README.md'), 'utf8');
    const listed = readme.match(/<!-- kit-ui-names -->([\s\S]*?)<!-- \/kit-ui-names -->/)?.[1] ?? '';
    const missing = [...imported].filter(name => !new RegExp('`' + name + '`').test(listed)).sort();
    assert.deepEqual(missing, [], 'add these to the README\'s @kit/ui list');
});
