import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-tag-list-'));
copyFileSync(new URL('Text/tagList.ts', kit), join(dir, 'tagList.ts'));
const { shownTags } = await import(pathToFileURL(join(dir, 'tagList.ts')).href);

const flag = (key, tone = 'warning') => ({ key, label: key, kind: 'flag', tone });
const tag = (key) => ({ key: `tag:${key}`, label: key, kind: 'tag', tone: 'neutral' });

test('every tag shows up to the limit: they wrap, nothing folds', () => {
    const tags = [flag('a'), flag('b'), tag('c'), tag('d'), tag('e'), tag('f'), tag('g'), tag('h')];
    assert.deepEqual(shownTags(tags).shown.map((t) => t.key), tags.map((t) => t.key));
    assert.equal(shownTags(tags).hidden.length, 0);
});

test('past 8 the rest fold into "+N more", and flags never fold before tags', () => {
    const tags = [tag('t1'), tag('t2'), tag('t3'), tag('t4'), tag('t5'), tag('t6'), tag('t7'), tag('t8'), flag('late', 'danger')];
    const { shown, hidden } = shownTags(tags);
    assert.equal(shown.length, 8);
    assert.equal(shown[0].key, 'late');
    assert.deepEqual(hidden.map((t) => t.key), ['tag:t8']);
});

test('max null never folds; a custom max folds at it', () => {
    const tags = Array.from({ length: 12 }, (_, i) => tag(`t${i}`));
    assert.equal(shownTags(tags, null).hidden.length, 0);
    assert.equal(shownTags(tags, 3).shown.length, 3);
    assert.equal(shownTags(tags, 3).hidden.length, 9);
});

test('blank labels and repeated keys are dropped; nothing in, nothing out', () => {
    assert.deepEqual(shownTags([flag('a'), { ...flag('a') }, { ...tag('x'), label: '  ' }]).shown.map((t) => t.key), ['a']);
    assert.deepEqual(shownTags(null), { shown: [], hidden: [] });
});
