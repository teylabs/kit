import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// FileList's helpers: the stub is TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-file-list-'));
copyFileSync(
    new URL(
        '../../resources/js/components/Kit/Media/fileList.ts',
        import.meta.url,
    ),
    join(dir, 'fileList.ts'),
);
const { fileExtension, fileSize } = await import(
    pathToFileURL(join(dir, 'fileList.ts')).href
);

test('the badge is the extension in capitals, at most four letters, or nothing', () => {
    assert.equal(fileExtension('Intake form.pdf'), 'PDF');
    assert.equal(fileExtension('scan.final.jpeg'), 'JPEG');
    assert.equal(fileExtension('archive.tar.gzipped'), 'GZIP');
    assert.equal(fileExtension('README'), '');
    assert.equal(fileExtension('.env'), '');
    assert.equal(fileExtension('trailing.'), '');
});

test('a size string is shown as the server wrote it; a byte count is put into words', () => {
    assert.equal(fileSize('2.4 MB'), '2.4 MB');
    assert.equal(fileSize(0), '0 B');
    assert.equal(fileSize(900), '900 B');
    assert.equal(fileSize(1536), '1.5 KB');
    assert.equal(fileSize(20 * 1024), '20 KB');
    assert.equal(fileSize(5 * 1024 * 1024 * 1024 * 1024), '5120 GB');
    assert.equal(fileSize(null), '');
    assert.equal(fileSize(-1), '');
    assert.equal(fileSize(Number.NaN), '');
});
