import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// InputRichText: the document rules (richText.ts) run here without tiptap or Vue; the editor wiring and the toolbar's accessibility are checked by source,
// comments stripped (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const dir = mkdtempSync(join(tmpdir(), 'kit-rich-text-'));
writeFileSync(join(dir, 'richText.ts'), read('Form/richText.ts.stub'));
const { emptyDocument, isImageSource, isSafeLink, isSafeFontFamily, isSafeFontSize, splitImageFiles, toEditorDocument, fromEditorDocument, fileIntent, RICH_TEXT_TOOLS } = await import(pathToFileURL(join(dir, 'richText.ts')).href);

const text = (value, marks) => (marks ? { type: 'text', text: value, marks } : { type: 'text', text: value });
const doc = (...content) => ({ type: 'doc', content });
const paragraph = (...content) => (content.length ? { type: 'paragraph', content } : { type: 'paragraph' });

test('a document round-trips: inlineCode is the editor\'s code and back, and defaults are normalised', () => {
    const stored = doc(
        { type: 'heading', attrs: { level: 2 }, content: [text('Agenda')] },
        paragraph(text('Run '), text('npm ci', [{ type: 'inlineCode' }, { type: 'bold' }]), { type: 'hardBreak' }, text('see docs', [{ type: 'link', attrs: { href: '/docs' } }])),
        { type: 'orderedList', content: [{ type: 'listItem', content: [paragraph(text('one'))] }] },
        { type: 'blockquote', content: [paragraph(text('quoted', [{ type: 'strike' }, { type: 'italic' }]))] },
    );
    const editor = toEditorDocument(stored);
    assert.deepEqual(editor.content[1].content[1].marks, [{ type: 'code' }, { type: 'bold' }]);
    assert.deepEqual(editor.content[2].attrs, { start: 1 }, 'an ordered list starts at 1 unless it says otherwise');
    const back = fromEditorDocument(editor);
    assert.deepEqual(back.content[1].content[1].marks, [{ type: 'inlineCode' }, { type: 'bold' }]);
    assert.deepEqual(back.content[1].content[3].marks, [{ type: 'link', attrs: { href: '/docs', title: null } }], 'a link\'s title is null when absent');
    assert.deepEqual(fromEditorDocument(toEditorDocument(back)), back, 'normalising is stable');
});

test('null and an empty doc are the empty document', () => {
    assert.deepEqual(emptyDocument(), { type: 'doc', content: [{ type: 'paragraph' }] });
    assert.deepEqual(toEditorDocument(null), { type: 'doc', content: [{ type: 'paragraph' }] });
    assert.deepEqual(toEditorDocument({ type: 'doc', content: [] }), { type: 'doc', content: [{ type: 'paragraph' }] });
});

test('anything outside the subset throws, so the field reports content-error instead of dropping it', () => {
    const bad = {
        'a code block': doc({ type: 'codeBlock', content: [text('x')] }),
        'a level-1 heading': doc({ type: 'heading', attrs: { level: 1 }, content: [text('x')] }),
        'an image': doc(paragraph({ type: 'image', attrs: { src: 'x.png' } })),
        'underline': doc(paragraph(text('x', [{ type: 'underline' }]))),
        'a duplicate mark': doc(paragraph(text('x', [{ type: 'bold' }, { type: 'bold' }]))),
        'an extra attribute': doc({ type: 'paragraph', attrs: { align: 'center' } }),
        'an empty text node': doc(paragraph({ type: 'text', text: '' })),
        'an empty list': doc({ type: 'bulletList', content: [] }),
        'a list item starting with a list': doc({ type: 'bulletList', content: [{ type: 'listItem', content: [{ type: 'bulletList', content: [{ type: 'listItem', content: [paragraph()] }] }] }] }),
        'a start of 0': doc({ type: 'orderedList', attrs: { start: 0 }, content: [{ type: 'listItem', content: [paragraph()] }] }),
        'an unsafe link': doc(paragraph(text('x', [{ type: 'link', attrs: { href: 'javascript:alert(1)' } }]))),
        'not a doc': paragraph(text('x')),
    };
    for (const [name, value] of Object.entries(bad)) {
        assert.throws(() => toEditorDocument(value), Error, name);
    }
    let deep = paragraph(text('x'));
    for (let i = 0; i < 25; i++) deep = { type: 'blockquote', content: [deep] };
    assert.throws(() => toEditorDocument(doc(deep)), /oversized/, 'more than 20 levels');
});

test('isSafeLink: http(s), mailto, tel and relative links; no other scheme, credentials or smuggling', () => {
    for (const ok of ['https://example.com/a?b=1#c', 'http://example.com', 'mailto:ada@example.com', 'tel:+441234', '/docs', 'docs/page', '#section', '?q=1']) {
        assert.equal(isSafeLink(ok), true, ok);
    }
    for (const bad of ['', ' /docs', 'javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,x', 'vbscript:x', '//evil.example', 'https://user:pass@example.com', 'https://a@example.com', 'https://', '/a b', '/a\\b', '/a%0ab', '/a%5cb', 'java\tscript:x', 'app://record/1']) {
        assert.equal(isSafeLink(bad), false, JSON.stringify(bad));
    }
    assert.equal(isSafeLink('app://record/12', /^app:\/\/record\/[0-9]+$/), true, 'the host\'s private pattern, when given');
    assert.equal(isSafeLink('app://other/12', /^app:\/\/record\/[0-9]+$/), false, 'only what the pattern names');
    const sticky = /^app:\/\/record\/[0-9]+$/g;
    assert.deepEqual([isSafeLink('app://record/12', sticky), isSafeLink('app://record/12', sticky), isSafeLink('app://record/12', /^app:\/\/record\/[0-9]+$/y)], [true, true, true], 'a /g or /y pattern gives the same answer every time');
    assert.equal(sticky.lastIndex, 0, 'and the host\'s pattern is left untouched');
});

test('the toolbar offers the documented tools, in order', () => {
    assert.deepEqual([...RICH_TEXT_TOOLS], ['heading2', 'heading3', 'bold', 'italic', 'strike', 'code', 'link', 'bulletList', 'orderedList', 'blockquote', 'image', 'undo', 'redo']);
});

// allow: textAlign and textStyle, opted into per field for mirrored content. Off (the default) the strict subset is unchanged; on, each value is checked strictly.
const styled = (attrs) => doc(paragraph(text('Slide title', [{ type: 'textStyle', attrs }])));
const aligned = (textAlign) => doc({ type: 'paragraph', attrs: { textAlign }, content: [text('Centred')] });
const both = ['textAlign', 'textStyle'];

test('allow off (the default): textAlign and textStyle are refused, so such a document still reports content-error', () => {
    assert.throws(() => toEditorDocument(aligned('center')), /attributes/);
    assert.throws(() => toEditorDocument(doc({ type: 'heading', attrs: { level: 2, textAlign: 'right' }, content: [text('H')] })), /attributes/);
    assert.throws(() => toEditorDocument(styled({ color: '#ff0000' })), /mark/);
    assert.throws(() => toEditorDocument(styled({ color: '#ff0000' }), null, ['textAlign']), /mark/, 'textAlign alone does not admit textStyle');
    assert.throws(() => toEditorDocument(aligned('center'), null, ['textStyle']), /attributes/, 'and textStyle alone does not admit textAlign');
});

test('allow on: each textAlign and textStyle value round-trips', () => {
    for (const align of ['left', 'center', 'right', 'justify']) {
        const stored = aligned(align);
        assert.deepEqual(fromEditorDocument(toEditorDocument(stored, null, both), null, both), stored, align);
    }
    const heading = doc({ type: 'heading', attrs: { level: 3, textAlign: 'center' }, content: [text('H')] });
    assert.deepEqual(fromEditorDocument(toEditorDocument(heading, null, both), null, both), heading, 'a heading keeps its level and alignment');
    for (const attrs of [{ color: '#fff' }, { color: '#1a2B3c' }, { color: 'rgb(255, 0, 12)' }, { color: 'rgba(0,0,0,0.5)' }, { fontFamily: 'Open Sans, Arial, sans-serif' }, { fontFamily: "'Open Sans', Arial" }, { fontFamily: '"Roboto Slab", serif' }, { fontSize: '18pt' }, { fontSize: 2.27 }, { fontSize: 6.25 }, { color: '#172c40', fontFamily: 'sans', fontSize: 1.41 }, { fontFamily: 'display' }, { fontSize: '1.5rem' }, { color: '#000000', fontFamily: 'Roboto', fontSize: '24px' }]) {
        const stored = styled(attrs);
        const editor = toEditorDocument(stored, null, both);
        assert.deepEqual(editor.content[0].content[0].marks[0], { type: 'textStyle', attrs: { color: null, fontFamily: null, fontSize: null, ...attrs } }, 'the editor gets every style attribute, unset ones null');
        assert.deepEqual(fromEditorDocument(editor, null, both), stored, JSON.stringify(attrs));
    }
    const scaled = fromEditorDocument(toEditorDocument(styled({ fontSize: 2.27 }), null, both), null, both);
    assert.equal(scaled.content[0].content[0].marks[0].attrs.fontSize, 2.27, 'a bare number stays a number, not "2.27"');
    const cleared = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'x', marks: [{ type: 'textStyle', attrs: { color: null, fontFamily: null, fontSize: null } }] }] }] };
    assert.deepEqual(fromEditorDocument(cleared, null, both), doc(paragraph(text('x'))), 'a style with every value cleared is dropped, not refused');
});

test('allow on: injection-shaped and out-of-range values are refused (content-error), never passed through as CSS', () => {
    const refusals = {
        color: ['red;background:url(x)', 'red', 'expression(alert(1))', '#ff0000;', '#ff00001', 'rgb(256,0,0)', 'rgba(0,0,0)', 'rgb(0,0,0,0.5)', 'rgba(0,0,0,2)', '}', 'var(--x)', '#fff }', 'url(x)', ''],
        fontFamily: ['"Open Sans\'', "'Arial", '"Open "Sans"', "'Open' Sans", 'Arial; color: red', "'Arial; color: red'", 'url(x)', 'expression(', 'Arial}', 'Arial)', '', ',', 'a,'.repeat(11) + 'b'],
        fontSize: [0, -1, 20.5, 1.2345, Infinity, NaN, '12', '12 px', '12px;', '0px', '500px', '13vh', 'calc(1px)', 'expression(1)', '-1px', '}', '12.12345px'],
    };
    for (const [attr, values] of Object.entries(refusals)) {
        for (const value of values) {
            assert.throws(() => toEditorDocument(styled({ [attr]: value }), null, both), /text style/, `${attr}: ${String(value)} with allow on`);
            assert.throws(() => toEditorDocument(styled({ [attr]: value })), Error, `${attr}: ${JSON.stringify(value)} with allow off`);
        }
    }
    assert.throws(() => toEditorDocument(styled({ color: '#fff', backgroundColor: '#000' }), null, both), /text style/, 'an attribute outside color, fontFamily and fontSize');
    assert.throws(() => toEditorDocument(styled({}), null, both), /text style/, 'an empty style');
    assert.throws(() => toEditorDocument(doc({ type: 'paragraph', attrs: { textAlign: 'center;color:red' }, content: [text('x')] }), null, both), /attributes/);
    assert.throws(() => toEditorDocument(doc({ type: 'paragraph', attrs: { textAlign: 'start' }, content: [text('x')] }), null, both), /attributes/, 'only left, center, right, justify');
    assert.throws(() => toEditorDocument(doc({ type: 'paragraph', attrs: { textAlign: 'left', indent: 2 }, content: [text('x')] }), null, both), /attributes/);
    assert.throws(() => toEditorDocument(doc({ type: 'blockquote', attrs: { textAlign: 'left' }, content: [paragraph(text('x'))] }), null, both), /attributes/, 'only paragraphs and headings align');
});

test('a unitless font size with up to three decimals passes despite floating point (2.03 * 1000 is not exact)', () => {
    // A mirrored deck's real scale values; 2.03 used to fail because 2.03 * 1000 === 2029.9999999999998.
    const real = [1.41, 1.48, 1.56, 1.64, 1.72, 1.8, 1.88, 1.95, 2.03, 2.11, 2.19, 2.27, 2.34, 2.42, 2.5, 2.58, 2.66, 2.73, 2.81, 3.13, 3.28, 3.44, 3.59, 3.75, 3.91, 4.22, 6.09, 6.25];
    for (const size of real) assert.equal(isSafeFontSize(size), true, String(size));
    for (const size of [0.001, 1.005, 19.999, 20]) assert.equal(isSafeFontSize(size), true, String(size));
    for (const size of [0, -1, 20.001, 2.0301, 1.23456, Number.NaN, Number.POSITIVE_INFINITY]) assert.equal(isSafeFontSize(size), false, String(size));
});

// Images: opt-in with allow 'image' and the host's imageSources pattern; a block whose src is the host's own reference, never a pasted URL.
const MEDIA = /^media:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
const UUID = '0b6c5a3e-1f2d-4c8e-9a7b-3d2e1f0a9b8c';
const withImage = (attrs, where = 'doc') => where === 'doc'
    ? { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Before' }] }, { type: 'image', attrs }, { type: 'paragraph' }] }
    : { type: 'doc', content: [{ type: 'blockquote', content: [{ type: 'image', attrs }] }] };

test('an image block round-trips with images allowed and a matching source, at the top level and in a container', () => {
    for (const where of ['doc', 'blockquote']) {
        const stored = withImage({ src: `media:${UUID}`, alt: 'The roof' }, where);
        assert.deepEqual(fromEditorDocument(toEditorDocument(stored, null, ['image'], null, MEDIA), null, ['image'], null, MEDIA), stored, where);
    }
    const noAlt = withImage({ src: `media:${UUID}` });
    assert.deepEqual(toEditorDocument(noAlt, null, ['image'], null, MEDIA).content[1], { type: 'image', attrs: { src: `media:${UUID}`, alt: null } });
});

test('pasted or dropped files split into the images the editor uploads and the rest, still the host\'s file-intent', () => {
    const file = (name, type) => ({ name, type });
    const png = file('a.png', 'image/png');
    const jpg = file('b.jpg', 'image/jpeg');
    const svg = file('c.svg', 'image/svg+xml');
    const pdf = file('d.pdf', 'application/pdf');
    assert.deepEqual(splitImageFiles([png, pdf, jpg, svg]), { images: [png, jpg], rest: [pdf, svg] }, 'SVG is not an image here: it can carry script');
});

test('the editor: images on only with allow, a pattern and an upload callback; a toolbar control; uploads checked before insert', () => {
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /const imagesOn = computed\(\(\) => props\.allow\.includes\('image'\) && props\.imageSources instanceof RegExp && typeof props\.uploadImage === 'function'\);/);
    assert.match(field, /\.\.\.\(props\.allow\.includes\('image'\) \? \[ImageBlock\] : \[\]\),/);
    assert.match(field, /<template v-if="imagesOn">\s*<Button type="button" variant="ghost" size="icon" aria-label="Insert image" title="Insert image"/);
    assert.match(field, /if \(!isImageSource\(image\.src, props\.imageSources\)\) throw new Error/, 'a returned src is checked before it is inserted');
    assert.match(field, /emit\('image-error', error\)/);
    assert.match(field, /if \(rest\.length\) emit\('file-intent', \{ \.\.\.intent, files: rest \}\);/, 'other files stay the host\'s');
    assert.match(field, /tag: 'img\[data-src\]'/, 'only the editor\'s own images parse back; a pasted <img> from elsewhere is dropped');
    assert.match(field, /role="status"[^>]*data-rich-text-uploading>Uploading image…/);
});
