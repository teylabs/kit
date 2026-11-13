import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// MiddleTruncate: middleTruncate.ts (plain TypeScript) runs here without a DOM, against a stand-in measure: one unit per character, two for a wide one (an
// emoji, a CJK ideograph), so a cut that counts code units instead of graphemes fits the wrong amount. The component's markup is checked by source; the
// browser job (browser/run.mjs, ?middle-truncate) measures real text with a canvas, resizes it, and reads the accessible name and a copy.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-middle-truncate-'));
writeFileSync(join(dir, 'middleTruncate.ts'), read('Data/middleTruncate.ts'));
const { graphemes, middleTruncate, truncationKind, extensionStart, MIDDLE_ELLIPSIS } = await import(pathToFileURL(join(dir, 'middleTruncate.ts')).href);
const source = read('Data/MiddleTruncate.vue');

const wide = /\p{Extended_Pictographic}|\p{Regional_Indicator}|\p{Script=Han}/u;
const measure = text => graphemes(text).reduce((sum, g) => sum + (wide.test(g) ? 2 : 1), 0);
const cut = (value, width, options) => middleTruncate(value, width, measure, options);
// What the reader sees: the head, an ellipsis for each cut part, the tail.
const shown = r => r.head + (r.middle ? MIDDLE_ELLIPSIS : '') + r.tail + (r.end ? MIDDLE_ELLIPSIS : '');
const whole = r => r.head + r.middle + r.tail + r.end;

const file = 'paraframe-brand-guidelines-2026-final.pdf';
const url = 'https://cdn.paraframe.studio/clients/acme/2026/brand/guidelines/final-v3.pdf';

test('a value that fits, or is too short to cut, is returned whole with no ellipsis', () => {
    assert.deepEqual(cut(file, file.length), { head: file, middle: '', tail: '', end: '', truncated: false }, 'exactly the width fits');
    assert.deepEqual(cut('a.pdf', 100), { head: 'a.pdf', middle: '', tail: '', end: '', truncated: false });
    assert.equal(cut('ab', 1).truncated, false, 'two characters have no middle to cut; the element clips them');
    assert.equal(cut('', 0).truncated, false);
});

test('one character over the width cuts the middle, never the end: the start and the whole extension stay', () => {
    const r = cut(file, file.length - 1);
    assert.equal(r.truncated, true);
    assert.ok(measure(shown(r)) <= file.length - 1, 'what is shown fits');
    assert.ok(r.tail.endsWith('.pdf') && r.head.startsWith('paraframe'), shown(r));
    assert.equal(shown(r), 'paraframe-brand-guidelin…-2026-final.pdf', 'the room left goes two thirds to the head');
});

test('a file name keeps its whole extension at every width, and the shown text always fits', () => {
    for (const [value, extension] of [[file, '.pdf'], ['backup-2026-11-13.tar.gz', '.tar.gz'], ['quarterly-report-final-final.xlsx', '.xlsx']]) {
        for (let width = value.length - 1; width >= extension.length + 2; width--) {
            const r = cut(value, width, { kind: 'file' });
            assert.ok(r.tail.endsWith(extension), `${value} at ${width}: ${shown(r)}`);
            assert.ok(measure(shown(r)) <= width, `${value} at ${width} overflows: ${shown(r)}`);
            assert.equal(whole(r), value, 'nothing is lost, only hidden');
        }
    }
    assert.equal(shown(cut(file, 6)), 'p….pdf', 'narrower than the anchors: one head character, the ellipsis and the extension');
    assert.equal(shown(cut(file, 2)), 'p….pdf', 'below that floor the floor is returned, and the element clips it');
});

test('a wider box shows more, never less, of the head and the tail', () => {
    let previous = { head: '', tail: '' };
    for (let width = 6; width < file.length; width++) {
        const r = cut(file, width);
        assert.ok(r.head.startsWith(previous.head) && r.tail.endsWith(previous.tail), `${width}: ${shown(r)} after ${previous.head}…${previous.tail}`);
        previous = r;
    }
});

test('extensions: a dotfile has none, .tar.gz is one, a dot inside a directory name is not one', () => {
    assert.equal(extensionStart('.env'), -1);
    assert.equal(extensionStart('README'), -1);
    assert.equal(extensionStart('backup.tar.gz'), 6);
    assert.equal(extensionStart('photo.jpeg'), 5);
    assert.equal(extensionStart('name.with spaces'), -1, 'an "extension" with a space is part of the name');
    assert.equal(extensionStart('.env.production'), -1, 'a ten-letter suffix is a name, not an extension');
    const plain = cut('a-very-long-name-without-an-extension', 12);
    assert.ok(plain.head.length > plain.tail.length && plain.tail.length >= 1, `a name without an extension cuts with more head than tail: ${shown(plain)}`);
});

test('auto reads a URL, a path and a file name', () => {
    assert.equal(truncationKind(url), 'url');
    assert.equal(truncationKind('www.example.com/a/b'), 'url');
    assert.equal(truncationKind('mailto:someone@example.com'), 'file', 'no authority, no URL handling');
    assert.equal(truncationKind('/srv/projects/brand/final.pdf'), 'path');
    assert.equal(truncationKind('C:\\Projects\\Brand\\final.docx'), 'path');
    assert.equal(truncationKind(file), 'file');
    assert.equal(truncationKind(url, 'file'), 'file', 'an explicit kind wins');
});

test('a URL keeps its scheme, the start of its host and its last path segment', () => {
    const r = cut(url, 40);
    assert.equal(shown(r), 'https://cdn.paraframe.s…nes/final-v3.pdf');
    assert.ok(measure(shown(r)) <= 40);
    for (let width = 39; width >= 20; width--) {
        const s = shown(cut(url, width));
        assert.ok(s.startsWith('https://cd'), `${width}: the host's start stays: ${s}`);
        assert.ok(s.endsWith('.pdf'), `${width}: ${s}`);
    }
    assert.ok(shown(cut(url, 30)).endsWith('/final-v3.pdf'), 'the whole last segment while it fits');
    assert.ok(cut('https://example.com/', 10).truncated, 'a bare host still cuts');
});

test('a URL\'s query and fragment are cut first, down to the ? or #, and kept in the text', () => {
    const value = 'https://example.com/a/b/report.pdf?token=abcdefghijkl';
    const r = cut(value, 30);
    assert.equal(shown(r), 'https://example.…/report.pdf?…');
    assert.equal(r.end, 'token=abcdefghijkl');
    assert.equal(whole(r), value);
    assert.ok(measure(shown(r)) <= 30);
    const hash = cut('https://docs.example.com/guide/install#requirements-for-linux', 30);
    assert.ok(shown(hash).endsWith('/install#…'), shown(hash));
});

test('a path keeps its root and its last segment', () => {
    const posix = cut('/srv/projects/paraframe/brand/guidelines/final.pdf', 30);
    assert.equal(shown(posix), '/srv/projects…elines/final.pdf');
    const windows = cut('C:\\Projects\\Paraframe\\Brand\\guidelines-final.docx', 20);
    assert.ok(shown(windows).startsWith('C') && shown(windows).endsWith('.docx'), shown(windows));
    assert.ok(cut('/var/log/app/', 8).tail.endsWith('/'), 'a trailing separator is kept with the last segment');
});

test('minTail keeps at least that many characters at the end', () => {
    const value = 'paraframe-brand-guidelines.pdf';
    assert.ok(cut(value, 15).tail.length < 10, 'without minTail the head takes the larger share');
    const r = cut(value, 15, { minTail: 10 });
    assert.ok(r.tail.length >= 10, shown(r));
    assert.ok(measure(shown(r)) <= 15);
    assert.equal(cut(value, 15, { minTail: -4 }).tail, cut(value, 15).tail, 'a negative minTail is none');
    assert.equal(cut(value, 15, { minTail: 99 }).head.length >= 1, true, 'a minTail past the value still keeps one head character');
});

test('emoji, flags and combining marks are never split, and wide ones are measured whole', () => {
    const value = '👨‍👩‍👧‍👦👨‍👩‍👧‍👦🇨🇦🇨🇦é́é́é́-family-photo.jpeg';
    const parts = graphemes(value);
    assert.equal(parts[0], '👨‍👩‍👧‍👦', 'a family sequence is one character');
    for (let width = measure(value) - 1; width >= 8; width--) {
        const r = cut(value, width);
        assert.ok(measure(shown(r)) <= width, `${width}: ${shown(r)}`);
        assert.equal(whole(r), value);
        // Every part is made of whole graphemes: re-segmenting the parts gives the original list back.
        assert.deepEqual([...graphemes(r.head), ...graphemes(r.middle), ...graphemes(r.tail)], parts, `${width}: a grapheme was split`);
        assert.ok(r.tail.endsWith('.jpeg'));
    }
    const cjk = cut('二〇二六年度ブランドガイドライン最終版.pdf', 16);
    assert.ok(measure(shown(cjk)) <= 16 && cjk.tail.endsWith('.pdf'), shown(cjk));
});

test('RTL text is cut in logical order: its first letters stay in the head and the extension in the tail', () => {
    const value = 'دليل-العلامة-التجارية-النهائي.pdf';
    const r = cut(value, 15);
    assert.equal(shown(r), 'دليل-ال…ائي.pdf');
    assert.equal(whole(r), value);
    assert.match(source, /dir="auto"/, 'the element takes its direction from the value, so the browser lays the parts out right to left');
});

test('the component: the whole value in title, the cut parts kept in the text at zero size, a CSS ellipsis until mounted, nothing started on the server', () => {
    assert.match(source, /:title="value"/);
    assert.match(source, /data-cut class="[^"]*text-\[0px\]/, 'cut text stays in the DOM, so the accessible name and a copy are the whole value');
    assert.match(source, /aria-hidden="true"\s+data-ellipsis="…"\s+class="before:content-\[attr\(data-ellipsis\)\]"/, 'the ellipsis is drawn from an attribute, so it is not read or copied');
    assert.match(source, /class="block min-w-0 truncate"/, 'before mount (and on the server) the whole value with an end ellipsis');
    assert.match(source, /<template v-else>\{\{ value \}\}<\/template>/);
    const setup = source.slice(source.indexOf('<script'), source.indexOf('onMounted('));
    assert.doesNotMatch(setup, /new ResizeObserver|document\.|window\.|setTimeout|setInterval/, 'no observer, timer or DOM access outside onMounted');
    assert.match(source, /onBeforeUnmount\([\s\S]*observer\?\.disconnect\(\)/);
});
