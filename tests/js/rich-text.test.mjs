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
const { emptyDocument, isSafeLink, isSafeFontFamily, isSafeFontSize, toEditorDocument, fromEditorDocument, fileIntent, RICH_TEXT_TOOLS } = await import(pathToFileURL(join(dir, 'richText.ts')).href);

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

test('files pasted or dropped become a file-intent, never content; a locked field swallows them', () => {
    const file = { name: 'chart.png' };
    assert.deepEqual(fileIntent('paste', [file], 7, false), { handled: true, intent: { source: 'paste', files: [file], position: 7 } });
    assert.deepEqual(fileIntent('drop', [file], 3, true), { handled: true, intent: null }, 'handled (nothing inserted), but no intent while locked');
    assert.deepEqual(fileIntent('paste', [], 1, false), { handled: false, intent: null }, 'a text paste is the editor\'s');
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /handlePaste: \(view, event\) => files\('paste', event\.clipboardData\?\.files, view\.state\.selection\.from\)/);
    assert.match(field, /if \(handled\) event\.preventDefault\(\);/, 'a drop with files never reaches the browser');
    assert.match(field, /if \(intent\) emit\('file-intent', intent\);/);
});

test('the editor: StarterKit narrowed to the subset, safe links only, and content-error on invalid input', () => {
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /heading: \{ levels: \[2, 3\] \}, codeBlock: false, code: false, horizontalRule: false, underline: false, trailingNode: false,/);
    assert.match(field, /link: \{ openOnClick: false, autolink: false, linkOnPaste: false, isAllowedUri: href => isSafeLink\(href, props\.privateLinks\), HTMLAttributes: \{ target: null, rel: 'noopener noreferrer' \} \}/);
    assert.match(field, /CodeMark\.extend\(\{ excludes: '' \}\)/);
    assert.match(field, /emit\('content-error', \{ code: 'invalid-document', message: contentError\.value \}\);/);
    assert.match(field, /if \(!isSafeLink\(href, props\.privateLinks\)\) \{\s*linkError\.value = /, 'the link popover refuses an unsafe link');
    assert.match(field, /placeholder: 'Start typing…'/);
});

test('toolbar accessibility: a named toolbar, labelled toggle buttons with aria-pressed, and a labelled textbox', () => {
    assert.deepEqual([...RICH_TEXT_TOOLS], ['heading2', 'heading3', 'bold', 'italic', 'strike', 'code', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']);
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /role="toolbar" :aria-label="`\$\{label\} formatting`"/);
    assert.match(field, /:aria-label="controls\[name\]\.label" :title="controls\[name\]\.label" :aria-pressed="pressed\(controls\[name\]\)"/);
    assert.match(field, /aria-label="Edit link"/);
    assert.match(field, /<Popover v-if="name === 'link'" v-model:open="linkOpen" label="Link settings"/);
    assert.match(field, /aria-hidden="true"/, 'icons are decorative');
    for (const attribute of ["role: 'textbox'", "'aria-label': props.label", "'aria-multiline': 'true'", "'aria-readonly':", "'aria-invalid':", "'aria-describedby':"]) {
        assert.ok(field.includes(attribute), attribute);
    }
    assert.match(field, /tabindex: props\.disabled \? '-1' : '0'/, 'read-only stays focusable; disabled leaves the tab order');
    for (const label of ['Heading 2', 'Heading 3', 'Bold', 'Italic', 'Strikethrough', 'Inline code', 'Bullet list', 'Ordered list', 'Blockquote', 'Undo', 'Redo']) {
        assert.ok(field.includes(`label: '${label}'`), label);
    }
});

test('exported from the barrel, and the field imports UI only from the kit', () => {
    const barrel = read('index.ts.stub');
    assert.match(barrel, /export \{ default as InputRichText \} from '\.\/Form\/InputRichText\.vue';/);
    assert.match(barrel, /export \{ emptyDocument, isSafeColor, isSafeFontFamily, isSafeFontSize, isSafeLink, RICH_TEXT_ALLOW, RICH_TEXT_TOOLS,/);
    assert.doesNotMatch(read('Form/InputRichText.vue.stub'), /@\/components\/ui\//);
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

test('the field: allow is a prop (none by default), adds the extensions only when named, passes allow both ways, and adds no toolbar control', () => {
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /allow\?: readonly RichTextAllow\[\]/);
    assert.match(field, /allow: \(\) => \[\]/);
    assert.match(field, /\.\.\.\(props\.allow\.includes\('textAlign'\) \? \[TextAlign\.configure\(\{ types: \['heading', 'paragraph'\], alignments: \['left', 'center', 'right', 'justify'\], defaultAlignment: null \}\)\] : \[\]\),/);
    assert.match(field, /\.\.\.\(props\.allow\.includes\('textStyle'\) \? \[TextStyle, Color, FontFamily, FontSize\] : \[\]\),/);
    assert.match(field, /toEditorDocument\(props\.modelValue, props\.privateLinks, props\.allow, props\.families\)/);
    assert.match(field, /fromEditorDocument\(current\.getJSON\(\), props\.privateLinks, props\.allow, props\.families\)/);
    assert.deepEqual([...RICH_TEXT_TOOLS], ['heading2', 'heading3', 'bold', 'italic', 'strike', 'code', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'], 'no new tools');
});

test('families: with the host\'s list a family must be one of its names; without it the character class stands', () => {
    const tokens = ['sans', 'display'];
    assert.equal(isSafeFontFamily('sans', tokens), true);
    assert.equal(isSafeFontFamily("'display', sans", tokens), true, 'quoted, and several, each listed');
    assert.equal(isSafeFontFamily('serif', tokens), false, 'an unlisted name');
    assert.equal(isSafeFontFamily('sans, Arial', tokens), false, 'every name must be listed');
    assert.equal(isSafeFontFamily('sans;color:red', tokens), false, 'the character class still applies');
    assert.equal(isSafeFontFamily('Open Sans, Arial'), true, 'no list: the character class alone, as before');
    assert.equal(isSafeFontFamily('Open Sans', null), true);
    const both = ['textAlign', 'textStyle'];
    const styled = family => doc(paragraph(text('x', [{ type: 'textStyle', attrs: { fontFamily: family, fontSize: 2.27 } }])));
    assert.deepEqual(fromEditorDocument(toEditorDocument(styled('display'), null, both, tokens), null, both, tokens), styled('display'), 'a listed family round-trips');
    assert.throws(() => toEditorDocument(styled('Comic Sans'), null, both, tokens), /text style/, 'an unlisted one is refused: content-error');
    assert.doesNotThrow(() => toEditorDocument(styled('Comic Sans'), null, both), 'without the list it passes, as before');
    const field = strip(read('Form/InputRichText.vue.stub'));
    assert.match(field, /families\?: readonly string\[\] \| null;/);
    assert.match(field, /families: null \}\);/, 'off by default');
    assert.match(field, /toEditorDocument\(props\.modelValue, props\.privateLinks, props\.allow, props\.families\)/);
    assert.match(field, /fromEditorDocument\(current\.getJSON\(\), props\.privateLinks, props\.allow, props\.families\)/);
});

test('a unitless font size with up to three decimals passes despite floating point (2.03 * 1000 is not exact)', () => {
    // A mirrored deck's real scale values; 2.03 used to fail because 2.03 * 1000 === 2029.9999999999998.
    const real = [1.41, 1.48, 1.56, 1.64, 1.72, 1.8, 1.88, 1.95, 2.03, 2.11, 2.19, 2.27, 2.34, 2.42, 2.5, 2.58, 2.66, 2.73, 2.81, 3.13, 3.28, 3.44, 3.59, 3.75, 3.91, 4.22, 6.09, 6.25];
    for (const size of real) assert.equal(isSafeFontSize(size), true, String(size));
    for (const size of [0.001, 1.005, 19.999, 20]) assert.equal(isSafeFontSize(size), true, String(size));
    for (const size of [0, -1, 20.001, 2.0301, 1.23456, Number.NaN, Number.POSITIVE_INFINITY]) assert.equal(isSafeFontSize(size), false, String(size));
});

test('caller attributes land on the editable textbox: inheritAttrs off, aria-describedby merged, required as aria-required, class and listeners on the frame', () => {
    const source = strip(read('Form/InputRichText.vue.stub'));
    assert.match(source, /defineOptions\(\{ inheritAttrs: false \}\)/);
    assert.match(source, /invalid\?: boolean/);
    assert.match(source, /function attributes\(\)[\s\S]*?\.\.\.caller,[\s\S]*?id: controlId\.value/, 'caller attributes first, the kit\'s own id, role and label after');
    assert.match(source, /new Set\(\[caller\['aria-describedby'\], props\.describedBy, contentError\.value \? `\$\{controlId\.value\}-error` : ''\]/, 'describedby joins caller, described-by and the error, de-duplicated');
    assert.match(source, /own\['aria-required'\] = 'true'/);
    assert.match(source, /'aria-invalid': String\(Boolean\(contentError\.value\) \|\| props\.invalid/);
    assert.match(source, /<div v-bind="frameAttrs"/);
    assert.match(source, /onUpdated\(/, 'changed attributes re-apply to the editable');
});
