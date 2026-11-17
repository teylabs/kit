import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, mkdtempSync, readFileSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// renderMarkdown (Kit/Text/markdown.ts, docs/markdown.md) over an XSS corpus and the GFM features, on micromark, DOMPurify and a jsdom window from
// typecheck/node_modules (installed by the vue-tsc job, which runs this file on its own; the PHP job's `composer test:js` skips it without them).
// Every payload must come out inert: no executable element, no event handler, no style, no script-capable URL. The browser job (browser/run.mjs,
// ?markdown) renders the same kind of payloads with window.pwned set by each and checks nothing ran.
const modules = fileURLToPath(new URL('../../typecheck/node_modules/', import.meta.url));
const installed = ['micromark', 'micromark-extension-gfm', 'dompurify', 'jsdom'].every(name => existsSync(join(modules, name)));
const skip = !installed && 'micromark, DOMPurify and jsdom are installed in typecheck/ by the vue-tsc job';

let render = null;
let parse = null;
if (installed) {
    const dir = mkdtempSync(join(tmpdir(), 'kit-markdown-'));
    symlinkSync(modules, join(dir, 'node_modules'), 'dir');
    writeFileSync(join(dir, 'markdown.ts'), readFileSync(new URL('../../resources/js/components/Kit/Text/markdown.ts', import.meta.url), 'utf8'));
    const { JSDOM } = await import(pathToFileURL(join(modules, 'jsdom/lib/api.js')).href);
    globalThis.window = new JSDOM('<!doctype html><body></body>').window;
    ({ renderMarkdown: render } = await import(pathToFileURL(join(dir, 'markdown.ts')).href));
    parse = html => {
        const template = window.document.createElement('template');
        template.innerHTML = html;
        return template.content;
    };
}

// Everything that could run or load script, checked on the parsed output.
function assertInert(html, payload) {
    const tree = parse(html);
    const bad = tree.querySelectorAll('script, style, iframe, frame, object, embed, form, textarea, select, button, svg, math, link, meta, base, template, video, audio, source, noscript');
    assert.equal(bad.length, 0, `no executable element for ${JSON.stringify(payload)}: ${html}`);
    for (const element of tree.querySelectorAll('*')) {
        for (const { name, value } of element.attributes) {
            assert.doesNotMatch(name, /^on/i, `no event handler for ${JSON.stringify(payload)}: ${html}`);
            assert.notEqual(name, 'style', `no style attribute for ${JSON.stringify(payload)}: ${html}`);
            assert.notEqual(name, 'srcdoc');
            assert.notEqual(name, 'formaction');
            if (['href', 'src', 'xlink:href', 'action', 'background', 'poster'].includes(name)) {
                // eslint-disable-next-line no-control-regex
                assert.doesNotMatch(value.replace(/[\u0000- ]/g, ''), /^(?:javascript|vbscript|data|file):/i, `no script URL for ${JSON.stringify(payload)}: ${html}`);
            }
        }
        if (element.tagName === 'INPUT') {
            assert.equal(element.getAttribute('type'), 'checkbox', 'the only input is a task checkbox');
            assert.equal(element.hasAttribute('disabled'), true, 'and it is disabled');
        }
    }
}

const corpus = [
    '<script>window.pwned = 1</script>',
    '<SCRIPT SRC=//example.test/x.js></SCRIPT>',
    '<img src=x onerror="window.pwned = 1">',
    '<img src="x" onerror=window.pwned=1 />',
    '![x](x" onerror="window.pwned=1)',
    '![x](javascript:window.pwned=1)',
    '[click](javascript:window.pwned=1)',
    '[click](JaVaScRiPt:window.pwned=1)',
    '[click](jav&#x09;ascript:window.pwned=1)',
    '[click](jav\tascript:window.pwned=1)',
    '[click](&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;window.pwned=1)',
    '[click](&#x6A;avascript:window.pwned=1)',
    '[click]( javascript:window.pwned=1)',
    '[click](<javascript:window.pwned=1>)',
    '[click](vbscript:msgbox(1))',
    '[click](data:text/html;base64,PHNjcmlwdD53aW5kb3cucHduZWQ9MTwvc2NyaXB0Pg==)',
    '![x](data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9d2luZG93LnB3bmVkPTE+)',
    '![x](data:image/png;base64,iVBORw0KGgo=)',
    '[click][ref]\n\n[ref]: javascript:window.pwned=1',
    '<javascript:window.pwned=1>',
    'javascript:window.pwned=1',
    '<a href="javascript:window.pwned=1">x</a>',
    '<iframe src="https://example.test"></iframe>',
    '<iframe srcdoc="<script>parent.pwned=1</script>"></iframe>',
    '<object data="javascript:window.pwned=1"></object>',
    '<embed src="javascript:window.pwned=1">',
    '<svg onload="window.pwned=1"><script>window.pwned=1</script></svg>',
    '<svg><a xlink:href="javascript:window.pwned=1"><text>x</text></a></svg>',
    '<math><mtext><table><mglyph><style><img src=x onerror=window.pwned=1></style></mglyph></table></mtext></math>',
    '<math href="javascript:window.pwned=1">x</math>',
    '<p style="background:url(javascript:window.pwned=1)">x</p>',
    '<style>body{background:red}</style>',
    '<div style="position:fixed;inset:0">x</div>',
    '<form action="javascript:window.pwned=1"><button>x</button></form>',
    '<input onfocus="window.pwned=1" autofocus>',
    '<details open ontoggle="window.pwned=1">',
    '<div>\n\n**bold** [x](javascript:window.pwned=1)\n\n</div>',
    '<div>\n\n<img src=x onerror=window.pwned=1>\n\n</div>',
    '> <script>window.pwned=1</script>',
    '- <img src=x onerror=window.pwned=1>',
    '| a |\n| - |\n| <img src=x onerror=window.pwned=1> |',
    '- [ ] <input onfocus=window.pwned=1 autofocus>',
    '`<script>` and\n\n```html\n<script>window.pwned=1</script>\n```',
    '<!-- --><script>window.pwned=1</script>',
    '<![CDATA[<script>window.pwned=1</script>]]>',
    '<base href="javascript:/">',
    '<meta http-equiv="refresh" content="0;url=javascript:window.pwned=1">',
    '<link rel="stylesheet" href="https://example.test/x.css">',
    'x[^1]\n\n[^1]: <img src=x onerror=window.pwned=1>',
    '[x](https://example.test "title\\" onmouseover=\\"window.pwned=1")',
];

test('every XSS payload comes out inert', { skip }, () => {
    for (const payload of corpus) assertInert(render(payload), payload);
});

test('raw HTML is escaped as text, never passed through', { skip }, () => {
    assert.equal(render('<b>bold</b>'), '<p>&lt;b&gt;bold&lt;/b&gt;</p>');
    assert.equal(render('<script>window.pwned = 1</script>'), '&lt;script&gt;window.pwned = 1&lt;/script&gt;');
    assert.equal(parse(render('<img src=x onerror="window.pwned = 1">')).textContent, '<img src=x onerror="window.pwned = 1">', 'the payload stays readable as text');
});

test('script, vbscript and data URLs are dropped; http, https, mailto, anchors and relative paths stay', { skip }, () => {
    for (const href of ['javascript:x', 'jav&#x09;ascript:x', 'vbscript:x', 'data:text/html,x']) {
        assert.equal(parse(render(`[a](${href})`)).querySelector('a').getAttribute('href'), '', href);
    }
    assert.equal(parse(render('![a](data:image/png;base64,iVBORw0KGgo=)')).querySelector('img').getAttribute('src'), '', 'no data: image either');
    for (const href of ['https://example.test/a?b=1', 'http://example.test', 'mailto:someone@example.test', '#section', '/docs/a', 'docs/a']) {
        assert.equal(parse(render(`[a](${href})`)).querySelector('a').getAttribute('href'), href, href);
    }
});

test('links carry rel="noopener noreferrer nofollow"; target only with newTab, never on a footnote link', { skip }, () => {
    const link = parse(render('[a](https://example.test) and https://example.test/b')).querySelectorAll('a');
    assert.deepEqual(Array.from(link, a => [a.getAttribute('rel'), a.hasAttribute('target')]), [['noopener noreferrer nofollow', false], ['noopener noreferrer nofollow', false]]);
    const tab = parse(render('[a](https://example.test)\n\nx[^1]\n\n[^1]: note', { newTab: true }));
    assert.equal(tab.querySelector('a[href^="https"]').getAttribute('target'), '_blank');
    assert.equal(tab.querySelectorAll('a[href^="#"][target]').length, 0);
});

test('headings start at h3 by default, follow with headingLevel and stop at h6', { skip }, () => {
    const levels = (html) => Array.from(parse(html).querySelectorAll('h1, h2, h3, h4, h5, h6'), h => h.tagName);
    assert.deepEqual(levels(render('# a\n## b\n### c\n#### d\n##### e\n###### f')), ['H3', 'H4', 'H5', 'H6', 'H6', 'H6']);
    assert.deepEqual(levels(render('# a\n## b', { headingLevel: 2 })), ['H2', 'H3']);
    assert.deepEqual(levels(render('a\n===\n\nb\n---')), ['H3', 'H4'], 'setext headings too');
});

test('GFM: tables, strikethrough, autolink literals, task lists and footnotes', { skip }, () => {
    const tree = parse(render('| a | b |\n| :- | -: |\n| 1 | 2 |\n\n~~gone~~ www.example.test\n\n- [x] done\n- [ ] todo\n\nnote[^1]\n\n[^1]: the note'));
    assert.deepEqual(Array.from(tree.querySelectorAll('th'), th => [th.textContent, th.getAttribute('align')]), [['a', 'left'], ['b', 'right']]);
    assert.equal(tree.querySelectorAll('tbody td').length, 2);
    assert.equal(tree.querySelector('del').textContent, 'gone');
    assert.equal(tree.querySelector('a[href="http://www.example.test"]').textContent, 'www.example.test');
    const boxes = Array.from(tree.querySelectorAll('input'), i => [i.type, i.checked, i.disabled, i.getAttribute('aria-label')]);
    assert.deepEqual(boxes, [['checkbox', true, true, 'done'], ['checkbox', false, true, 'todo']], 'disabled, and named by the item');
    const ref = tree.querySelector('sup a');
    const note = tree.querySelector(ref.getAttribute('href'));
    assert.match(note.textContent, /the note/);
    assert.equal(tree.getElementById(ref.getAttribute('aria-describedby')).textContent, 'Footnotes');
});

test('footnote ids are unique per render, so two on one page do not collide', { skip }, () => {
    const ids = (html) => Array.from(parse(html).querySelectorAll('[id]'), el => el.id);
    const one = ids(render('a[^1]\n\n[^1]: x'));
    const two = ids(render('a[^1]\n\n[^1]: x'));
    assert.equal(one.length, 3);
    assert.deepEqual(one.filter(id => two.includes(id)), []);
});

test('without a DOM it fails closed: the source as escaped text', { skip }, async () => {
    const saved = globalThis.window;
    delete globalThis.window;
    try {
        const dir = mkdtempSync(join(tmpdir(), 'kit-markdown-ssr-'));
        symlinkSync(modules, join(dir, 'node_modules'), 'dir');
        writeFileSync(join(dir, 'markdown.ts'), readFileSync(new URL('../../resources/js/components/Kit/Text/markdown.ts', import.meta.url), 'utf8'));
        const { renderMarkdown } = await import(pathToFileURL(join(dir, 'markdown.ts')).href);
        assert.equal(renderMarkdown('# hi <script>x</script>'), '<p># hi &lt;script&gt;x&lt;/script&gt;</p>');
        assert.equal(renderMarkdown('  '), '');
    } finally {
        globalThis.window = saved;
    }
});

test('TextMarkdown renders renderMarkdown inside the prose styling, starting headings at h3', () => {
    const source = readFileSync(new URL('../../resources/js/components/Kit/Text/TextMarkdown.vue', import.meta.url), 'utf8');
    assert.match(source, /import \{ renderMarkdown, type MarkdownOptions \} from '\.\/markdown';/);
    assert.match(source, /headingLevel: 3/);
    assert.match(source.replace(/\s+/g, ' '), /PROSE, TABLE, MARKDOWN, \]/);
    assert.match(source, /v-html="html"/);
    assert.match(source, /data-slot="text-markdown"/);
});
