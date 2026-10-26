import assert from 'node:assert/strict';
import test from 'node:test';
import { execFileSync } from 'node:child_process';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const dir = mkdtempSync(join(tmpdir(), 'kit-popover-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Overlay/popover.ts.stub', import.meta.url), join(dir, 'popover.ts'));
const p = await import(pathToFileURL(join(dir, 'popover.ts')).href);
const lang = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require $argv[1]);', fileURLToPath(new URL('../../lang/en/ui.php', import.meta.url))]).toString());

test('the close label comes from the shared lang file, then messages, and a gap shows its key', () => {
    assert.equal(p.popoverMessages(lang.popover).close, 'Close');
    assert.equal(p.popoverMessages(lang.popover, { close: 'Close working' }).close, 'Close working');
    assert.equal(p.popoverMessages(undefined).close, 'popover.close');
});
test('every width is capped at the viewport minus 2rem', () => {
    assert.deepEqual(Object.keys(p.popoverWidths), ['auto', 'sm', 'md', 'lg', 'trigger']);
    for (const classes of Object.values(p.popoverWidths)) assert.match(classes, /max-w-\[(min\(30rem,)?calc\(100vw-2rem\)/);
});
