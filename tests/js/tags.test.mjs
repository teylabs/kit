import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// InputTags: splitting, tidying, duplicates in the locale, the limits and their messages, from the stub (types stripped by node), and the component's wiring.
const dir = mkdtempSync(join(tmpdir(), 'kit-tags-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/tags.ts.stub', import.meta.url), join(dir, 'tags.ts'));
const { addTags, rejectionMessage, splitTags, tagKey, tagsMessages, tidyTag } = await import(pathToFileURL(join(dir, 'tags.ts')).href);
const component = readFileSync(new URL('../../resources/features/core/components/Kit/Form/InputTags.vue.stub', import.meta.url), 'utf8');

test('pasted or typed text splits on the separators, line breaks and tabs; pieces are tidied and empty ones drop out', () => {
    assert.deepEqual(splitTags('red, green;  blue  ,,'), ['red', 'green', 'blue']);
    assert.deepEqual(splitTags('one\ntwo\r\nthree\tfour'), ['one', 'two', 'three', 'four']);
    assert.deepEqual(splitTags('a|b c', { separators: ['|'] }), ['a', 'b c']);
    assert.deepEqual(splitTags('a.b', { separators: ['.'] }), ['a', 'b'], 'a separator is literal, not a regular expression');
    assert.equal(tidyTag('  big   deal '), 'big deal');
    assert.equal(tidyTag(' #Launch ', { normalize: tag => tag.replace(/^#/, '').toLowerCase() }), 'launch');
});

test('duplicates ignore case in the locale; maxLength, pattern and max refuse a tag rather than trim it', () => {
    assert.deepEqual(addTags(['Montréal'], ['MONTRÉAL', 'Laval']), { values: ['Montréal', 'Laval'], added: ['Laval'], rejected: [{ tag: 'MONTRÉAL', reason: 'duplicate' }] });
    assert.deepEqual(addTags([], ['a', 'A']).rejected, [{ tag: 'A', reason: 'duplicate' }], 'within one paste too');
    assert.equal(tagKey('I', 'tr'), 'ı');
    assert.deepEqual(addTags([], ['abcdef'], { maxLength: 5 }).rejected, [{ tag: 'abcdef', reason: 'too_long' }]);
    assert.deepEqual(addTags([], ['🙂🙂🙂'], { maxLength: 3 }).added, ['🙂🙂🙂'], 'length counts characters, not UTF-16 units');
    assert.deepEqual(addTags([], ['ok-1', 'no way'], { pattern: '[a-z0-9-]+' }).rejected, [{ tag: 'no way', reason: 'invalid' }]);
    assert.deepEqual(addTags([], ['ok-1x'], { pattern: 'ok' }).rejected, [{ tag: 'ok-1x', reason: 'invalid' }], 'the pattern must match the whole tag');
    assert.deepEqual(addTags(['a', 'b'], ['c', 'd'], { max: 3 }), { values: ['a', 'b', 'c'], added: ['c'], rejected: [{ tag: 'd', reason: 'too_many' }] });
});

test('messages layer the shared kitUi under the caller\'s, and each reason has a lang key', () => {
    const messages = tagsMessages({ too_many: 'Au plus :count étiquettes.', bogus: 1 }, { duplicate: '« :tag » existe déjà.' });
    assert.equal(rejectionMessage({ tag: 'x', reason: 'duplicate' }, messages), '« x » existe déjà.');
    assert.equal(rejectionMessage({ tag: 'x', reason: 'too_many' }, messages, { max: 3 }), 'Au plus 3 étiquettes.');
    assert.equal(rejectionMessage({ tag: 'xyz', reason: 'too_long' }, tagsMessages(null), { maxLength: 2 }), 'Tags are at most 2 characters.');
    assert.equal(rejectionMessage(undefined, messages), '');
    const lang = readFileSync(new URL('../../lang/en/ui.php', import.meta.url), 'utf8');
    for (const key of Object.keys(tagsMessages(null))) assert.match(lang, new RegExp(`'${key}' =>`), key);
});

test('the component is InputSelect multiple and creatable, adds on separators and paste, and describes its refusal politely', () => {
    assert.match(component, /<InputSelect [^>]*multiple creatable/);
    assert.match(component, /@create="\(tag\) => add\(\[tag\]\)"/);
    assert.match(component, /onKeydown: \[onKeydown/);
    assert.match(component, /onPaste: \[onPaste/);
    assert.match(component, /aria-live="polite"/);
    assert.match(component, /'aria-describedby'/);
    assert.match(component, /event\.isComposing/, 'an IME composition never commits a tag');
});
