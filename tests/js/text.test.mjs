import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The Text family (docs/text.md). The pure helpers (Text/text.ts, Text/copy.ts) run here without a DOM; useCopy runs on Vue from typecheck/ when the vue-tsc
// job installed it. Each component's markup contract is checked by source, one test per component. The browser job (browser/run.mjs, ?text) renders them:
// copying, truncation and clamping, scrolling, number and address formats, axe, and Vue's server renderer.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = (path) => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-text-'));
mkdirSync(join(dir, 'Data'));
mkdirSync(join(dir, 'Text'));
writeFileSync(join(dir, 'Data/middleTruncate.ts'), read('Data/middleTruncate.ts'));
writeFileSync(join(dir, 'Text/text.ts'), read('Text/text.ts').replace("from '../Data/middleTruncate';", "from '../Data/middleTruncate.ts';"));
writeFileSync(join(dir, 'Text/copy.ts'), read('Text/copy.ts'));
const { badgeItems, formatNumeric, isNumeric, longTextCut, textLines, CLAMP_LINES } = await import(pathToFileURL(join(dir, 'Text/text.ts')).href);
const { copyText } = await import(pathToFileURL(join(dir, 'Text/copy.ts')).href);
const source = (name) => read(`Text/${name}.vue`);
const template = (text) => text.slice(text.indexOf('<template>'));

// navigator and document as a test sets them, restored after.
async function withGlobals(globals, run) {
    const saved = Object.fromEntries(Object.keys(globals).map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(globals)) Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    try {
        return await run();
    } finally {
        for (const [key, descriptor] of Object.entries(saved)) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    }
}
// A document whose execCommand('copy') copies the selected textarea's value into `clipboard`, or refuses.
function fakeDocument({ refuse = false } = {}) {
    const log = { clipboard: null, focused: 0, appended: 0, removed: 0, restored: 0 };
    const active = { focus: () => log.focused++ };
    const range = {};
    let selected = null;
    const doc = {
        activeElement: active,
        body: { append: () => log.appended++ },
        getSelection: () => ({ rangeCount: 1, getRangeAt: () => range, removeAllRanges() {}, addRange: (r) => r === range && log.restored++ }),
        createElement: () => ({ value: '', style: {}, setAttribute() {}, focus() {}, select() { selected = this; }, remove: () => log.removed++ }),
        execCommand: (command) => {
            if (refuse || command !== 'copy') return false;
            log.clipboard = selected?.value ?? null;
            return true;
        },
    };
    return { doc, log };
}

test('copyText: the async Clipboard API when the browser has one, and a refusal from it is the answer, never retried another way', async () => {
    const written = [];
    await withGlobals({ navigator: { clipboard: { writeText: async (text) => written.push(text) } }, document: undefined }, () => copyText('abc-123'));
    assert.deepEqual(written, ['abc-123']);
    const { doc, log } = fakeDocument();
    await withGlobals({ navigator: { clipboard: { writeText: async () => { throw new Error('NotAllowedError'); } } }, document: doc }, async () => {
        await assert.rejects(copyText('secret'), /NotAllowedError/);
    });
    assert.equal(log.clipboard, null, 'a refused write does not fall back to execCommand');
});

test('copyText: without the Clipboard API it copies through a hidden textarea and puts back the focus and the selection; it rejects when that fails too', async () => {
    const { doc, log } = fakeDocument();
    await withGlobals({ navigator: {}, document: doc }, () => copyText('line one\nline two'));
    assert.equal(log.clipboard, 'line one\nline two', 'the exact text, line breaks kept');
    assert.deepEqual([log.appended, log.removed, log.focused, log.restored], [1, 1, 1, 1], 'the textarea is removed, focus and the selection restored');
    const refused = fakeDocument({ refuse: true });
    await withGlobals({ navigator: {}, document: refused.doc }, async () => {
        await assert.rejects(copyText('x'), /clipboard unavailable/);
    });
    assert.equal(refused.log.removed, 1, 'the textarea is removed even when the copy fails');
    await withGlobals({ navigator: {}, document: undefined }, async () => {
        await assert.rejects(copyText('x'), /clipboard unavailable/, 'no DOM (the server): rejects, never throws a ReferenceError');
    });
});

const vueDir = new URL('../../typecheck/node_modules/vue/', import.meta.url);
test('useCopy: copied only once the write resolved, failed on a refusal, one write at a time, and a changed value claims nothing', { skip: !existsSync(vueDir) && 'Vue is installed in typecheck/ by the vue-tsc job' }, async () => {
    const require = createRequire(new URL('../../typecheck/package.json', import.meta.url));
    const vue = require('vue');
    writeFileSync(join(dir, 'Text/useCopy.ts'), read('Text/useCopy.ts').replace("from 'vue'", `from '${pathToFileURL(require.resolve('vue')).href}'`).replace("from './copy';", "from './copy.ts';"));
    const { useCopy } = await import(pathToFileURL(join(dir, 'Text/useCopy.ts')).href);
    const pending = [];
    const writes = [];
    await withGlobals({ navigator: { clipboard: { writeText: (text) => new Promise((resolve, reject) => { writes.push(text); pending.push({ resolve, reject }); }) } } }, async () => {
        const value = vue.ref('first');
        const scope = vue.effectScope();
        const { state, copy } = scope.run(() => useCopy(() => value.value));
        const press = copy();
        assert.equal(state.value, 'copying', 'not copied before the write resolves');
        copy();
        assert.deepEqual(writes, ['first'], 'a press while one is in flight is ignored');
        pending.shift().resolve();
        await press;
        assert.equal(state.value, 'copied');
        const second = copy();
        value.value = 'second';
        await vue.nextTick();
        assert.equal(state.value, 'idle', 'a changed value goes back to idle');
        pending.shift().resolve();
        await second;
        assert.equal(state.value, 'idle', 'the old write resolving claims nothing about the new value');
        const third = copy();
        pending.shift().reject(new Error('NotAllowedError'));
        await third;
        assert.equal(state.value, 'failed');
        scope.stop();
    });
});

test('formatNumeric: the locale\'s grouping and decimal, fixed or up to three places, compact, and nothing for what is not a number', () => {
    assert.equal(formatNumeric(1234567, { locale: 'en-CA' }), '1,234,567');
    assert.equal(formatNumeric('1234.5', { locale: 'en-CA', decimals: 2 }), '1,234.50');
    assert.equal(formatNumeric(0.1 + 0.2, { locale: 'en-CA' }), '0.3', 'up to three places by default, so float noise is not shown');
    assert.equal(formatNumeric(2.5, { locale: 'en-CA', decimals: 0 }), '3', 'half rounds away from zero');
    assert.equal(formatNumeric(-2.5, { locale: 'en-CA', decimals: 0 }), '-3');
    assert.equal(formatNumeric('1234.5', { locale: 'fr-CA', decimals: 2 }).replace(/\s/g, ' '), '1 234,50', 'fr-CA groups with a space and a comma decimal');
    assert.equal(formatNumeric('1234.5', { locale: 'de-DE', decimals: 1 }), '1.234,5');
    assert.equal(formatNumeric(1234, { locale: 'en-CA', notation: 'compact' }), '1.2K');
    assert.equal(formatNumeric('9007199254740993', { locale: 'en-CA' }), '9,007,199,254,740,993', 'a string past 2^53 keeps every digit');
    for (const value of [null, undefined, '', '  ', 'n/a', '1,234', NaN, Infinity, {}, [], true]) assert.equal(formatNumeric(value), null, `${JSON.stringify(value)} is not a number`);
    assert.equal(formatNumeric(-1234.5, { locale: 'en-CA', decimals: 2 }), '-1,234.50');
    assert.equal(formatNumeric('-1234.5', { locale: 'en-CA', decimals: 2, negative: 'parentheses' }), '(1,234.50)', 'the accounting form');
    assert.equal(formatNumeric(1234.5, { locale: 'en-CA', decimals: 2, negative: 'parentheses' }), '1,234.50', 'a positive has no parentheses');
    assert.equal(formatNumeric(-0.0004, { locale: 'en-CA', decimals: 0, negative: 'parentheses' }), '0', 'what rounds to zero is not negative: never (0) or -0');
    assert.equal(formatNumeric(-0, { locale: 'en-CA' }), '0');
    assert.equal(isNumeric(' 42 '), true);
    assert.equal(isNumeric('1e3'), true);
    assert.equal(isNumeric('0x10'), false, 'hex is not a decimal');
});

test('textLines: an array\'s non-empty lines, or a string split on line breaks, else on commas', () => {
    assert.deepEqual(textLines(['1 Main St', '', null, ' Ottawa ON ', 0]), ['1 Main St', 'Ottawa ON', '0']);
    assert.deepEqual(textLines('1 Main St, Unit 4, Ottawa ON'), ['1 Main St', 'Unit 4', 'Ottawa ON']);
    assert.deepEqual(textLines('Suite 200, 1 Main St\nOttawa, ON K1A 0B1'), ['Suite 200, 1 Main St', 'Ottawa, ON K1A 0B1'], 'line breaks win: a comma inside a line stays');
    assert.deepEqual(textLines(null), []);
    assert.deepEqual(textLines(' , '), []);
});

test('longTextCut: at most `limit` characters as a reader counts them, trailing space dropped, an ellipsis added; no cut at or under the limit', () => {
    assert.deepEqual(longTextCut('short', 5), { shown: 'short', truncated: false }, 'exactly the limit is whole');
    assert.deepEqual(longTextCut('one two three', 4), { shown: 'one…', truncated: true }, 'the space before the cut is dropped');
    const family = '👨‍👩‍👧‍👦';
    assert.deepEqual(longTextCut(`${family}${family}${family}`, 2), { shown: `${family}${family}…`, truncated: true }, 'an emoji sequence is one character, never split');
    assert.equal(longTextCut('éééé', 2).shown, 'éé…');
    assert.equal(longTextCut('abc', 0).shown, 'a…', 'a limit under one is one');
});

test('badgeItems: strings and label-value objects, blanks dropped, a tone each, and a repeated item keeps a key of its own', () => {
    const items = badgeItems(['Draft', ' ', { label: 'Plan', value: 'Pro', tone: 'info' }, { label: 'Seats', value: 0 }, { label: 'Note', value: '' }, 'Draft', { label: '' }]);
    assert.deepEqual(items.map(({ label, value, tone }) => [label, value, tone]), [['Draft', null, 'neutral'], ['Plan', 'Pro', 'info'], ['Seats', '0', 'neutral'], ['Note', null, 'neutral'], ['Draft', null, 'neutral']]);
    assert.equal(new Set(items.map((item) => item.key)).size, items.length, 'no two keys alike');
    assert.notEqual(badgeItems([{ label: 'a', value: 'b' }])[0].key, badgeItems(['a b'])[0].key);
    assert.deepEqual(badgeItems(null), []);
});

test('TextProse: plain text in a pre-wrap paragraph, the app\'s own markup in the slot, styled from tokens, never v-html', () => {
    const vue = source('TextProse');
    assert.doesNotMatch(vue, /v-html/);
    assert.match(template(vue), /<p v-if="text !== null" class="whitespace-pre-wrap">\{\{ text \}\}<\/p>\s*<slot v-else \/>/);
    assert.doesNotMatch(read('Text/text.ts').match(/export const PROSE =[^;]*/)[0], /\b(gray|slate|zinc|neutral)-\d|prose\b/, 'tokens only, no typography plugin and no palette colour');
});

test('TextCode: content kept as written in a pre, a focusable named region with a bounded height, wrap toggled with aria-pressed, Copy through useCopy', () => {
    const vue = source('TextCode');
    assert.doesNotMatch(vue, /v-html/);
    assert.match(vue, /<template v-if="content !== null && content !== undefined">\{\{ content \}\}<\/template>/, 'text interpolation: markup in content is shown, never parsed');
    assert.match(vue, /<pre\s+ref="pre"\s+role="region"\s+:aria-label="title \?\? 'Code'"\s+tabindex="0"/);
    assert.match(vue, /md: 'max-h-80'/);
    assert.match(vue, /:aria-pressed="wrapped"/);
    assert.match(vue, /useCopy\(/);
    assert.match(read('Text/RawOutputCard.vue'), /<TextCode card :title="title" :content="content"><slot \/><\/TextCode>/, 'RawOutputCard is TextCode with card');
});

test('TextTable: the app\'s table in a box that scrolls sideways, focusable, and a named region only when it has a label', () => {
    const vue = source('TextTable');
    assert.match(vue, /:role="label \? 'region' : undefined"/);
    assert.match(vue, /tabindex="0"/);
    assert.match(vue, /overflow-x-auto/);
});

test('TextLong: a real button with aria-expanded and aria-controls after the text, the text never HTML', () => {
    const vue = source('TextLong');
    assert.doesNotMatch(vue, /v-html/);
    assert.match(vue, /<span :id="id" data-text-long-content>\{\{ visible \}\}<\/span>/);
    assert.match(vue, /<button type="button"[^>]*:aria-expanded="expanded" :aria-controls="id"/);
    assert.match(vue, /defineModel<boolean>\('expanded'/);
});

test('TextTruncate: CSS truncation or a line clamp from whole class strings, the shared content-box measure, nothing on the server', () => {
    const vue = source('TextTruncate');
    assert.deepEqual(Object.keys(CLAMP_LINES), ['1', '2', '3', '4', '5', '6']);
    assert.equal(CLAMP_LINES[1], 'truncate');
    assert.match(vue, /lines > 1 \? CLAMP_LINES\[lines\] : 'block truncate'/, 'a clamp never carries block, which would undo its display');
    assert.match(vue, /import \{ contentWidth \} from '@\/components\/Kit\/Data\/middleTruncate'/, 'the measure MiddleTruncate uses, not a copy');
    assert.match(read('Data/MiddleTruncate.vue'), /contentWidth,\n/, 'MiddleTruncate uses the same one');
    const setup = vue.slice(vue.indexOf('<script'), vue.indexOf('onMounted('));
    assert.doesNotMatch(setup.replace(/function measure[\s\S]*?\n}\n/, ''), /new ResizeObserver|document\.|window\.|setTimeout|setInterval/, 'no observer, timer or DOM access outside onMounted');
    assert.match(vue, /:title="cut \? full : undefined"/, 'a title only once the end was cut');
    assert.match(vue, /max-w-\(--kit-text-max-width\)/, 'a width through a custom property, so the style rule holds');
});

test('TextCopy: inline, a real button named by the text it shows and described by its tooltip; the button form is CopyButton\'s markup', () => {
    const vue = source('TextCopy');
    assert.match(vue, /<button\s+type="button"\s+:title="tooltip"/);
    assert.match(vue, /<slot>\{\{ text \}\}<\/slot><\/button/, 'the visible text is the whole name (label in name)');
    assert.match(vue, /role="status" aria-live="polite"/);
    assert.match(vue, /data-copy-button/);
    assert.match(read('Action/CopyButton.vue'), /<TextCopy\s+button/, 'CopyButton renders TextCopy\'s button form');
    for (const name of ['TextCopy.vue', 'CopyField.vue', 'TextCode.vue']) assert.doesNotMatch(read(`Text/${name}`), /navigator\.clipboard/, `${name} writes through copyText only`);
    assert.doesNotMatch(read('Action/CopyButton.vue'), /navigator\.clipboard/);
});

test('CopyField: a labelled read-only field that selects on focus, a Copy button named for the label, a polite status, the field focused on failure', () => {
    const vue = source('CopyField');
    assert.match(vue, /<Field :id="fieldId" :label="label"/);
    assert.equal((vue.match(/readonly\n/g) ?? []).length, 2, 'both the input and the textarea are read-only');
    assert.match(vue, /Copy<span class="sr-only"> \{\{ label \}\}<\/span>/);
    assert.match(vue, /state\.value !== 'failed'\) return;[\s\S]*document\.getElementById\(fieldId\)\?\.focus\(\)/);
});

test('TextNumeric: tabular figures from formatNumeric, the host\'s locale, and nothing when it is not a number', () => {
    const vue = source('TextNumeric');
    assert.match(vue, /v-if="shown !== null"/);
    assert.match(vue, /class="tabular-nums"/);
    assert.match(vue, /numberDefaults\(/, 'the locale resolves as Amount\'s does');
    assert.match(vue, /<span class="sr-only">negative <\/span\s*><span aria-hidden="true">\(<\/span>/, 'accounting parentheses are visual; a screen reader hears negative');
    assert.match(read('Text/TextDollars.vue'), /<TextNumeric[\s\S]*:decimals="2"/);
});

test('TextAddress: an <address> from the kit Address or from lines, and AddressText is it (the barrel) or renders it (the path)', () => {
    const vue = source('TextAddress');
    assert.match(vue, /addressLines\(props\.address, locale\.value, !props\.hideCountry\)/);
    assert.match(vue, /: textLines\(props\.lines\)/);
    assert.match(vue, /<address[\s\S]*data-address-text/, 'the marker AddressText always had');
    assert.match(read('Form/AddressText.vue'), /<TextAddress[\s\S]*:hide-country="hideCountry"/);
});

test('TextChips and TextTags: a real list of kit Badges, no hand-drawn chip', () => {
    for (const name of ['TextChips', 'TextTags']) {
        const vue = source(name);
        assert.match(vue, /import Badge from '@\/components\/Kit\/Feedback\/Badge\.vue'/, name);
        assert.match(vue, /<ul[\s\S]*:aria-label="label"[\s\S]*<li v-for/, name);
        assert.match(vue, /<Badge /, name);
        assert.doesNotMatch(template(vue), /\b(bg|text|ring)-(gray|slate|zinc)-\d/, `${name}: tokens only`);
    }
});

test('the barrel: every Text name, each deprecated alias on the component it names, and every old export kept', () => {
    const barrel = read('index.ts');
    const exported = (name) => barrel.match(new RegExp(`export \\{ default as ${name} \\} from '([^']+)'`))?.[1];
    for (const name of ['TextProse', 'TextCode', 'TextTable', 'TextLong', 'TextTruncate', 'TextCopy', 'TextNumeric', 'TextAddress', 'TextChips', 'TextTags', 'CopyField']) assert.ok(exported(name), name);
    for (const [alias, target] of [['ProseCode', 'TextCode'], ['ProseTable', 'TextTable'], ['LongText', 'TextLong'], ['ClickToCopy', 'TextCopy'], ['AddressText', 'TextAddress']]) {
        assert.equal(exported(alias), exported(target), `${alias} is ${target}`);
        assert.match(barrel, new RegExp(`@deprecated Use ${target}[^\\n]*\\n(?:\\/\\*\\*[^\\n]*\\n)?export \\{ default as ${alias} \\}`), `${alias} is marked deprecated`);
    }
    assert.equal(exported('CopyButton'), './Action/CopyButton.vue', 'CopyButton keeps its own name and default label');
    assert.equal(exported('RawOutputCard'), './Text/RawOutputCard.vue');
});
