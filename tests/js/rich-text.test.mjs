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
const { emptyDocument, isSafeLink, toEditorDocument, fromEditorDocument, fileIntent, RICH_TEXT_TOOLS } = await import(pathToFileURL(join(dir, 'richText.ts')).href);

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
    assert.match(barrel, /export \{ emptyDocument, isSafeLink, RICH_TEXT_TOOLS,/);
    assert.doesNotMatch(read('Form/InputRichText.vue.stub'), /@\/components\/ui\//);
});
