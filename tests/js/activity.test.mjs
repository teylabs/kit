import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// ActivityStream and useCursorFeed: the day sections, the trusted-html path and the cursor paging run here without Vue (activity.ts is plain TypeScript); the
// components' markup is checked by source, comments stripped (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const dir = mkdtempSync(join(tmpdir(), 'kit-activity-'));
writeFileSync(join(dir, 'activity.ts'), read('Feed/activity.ts.stub'));
const { activitySections, trustedHtml, headlineText, createCursorFeed, activityTone } = await import(pathToFileURL(join(dir, 'activity.ts')).href);

const entry = (id, day = null, headline = { text: `entry ${id}` }) => ({ id, kind: 'activity', headline, occurredAt: null, timestamp: null, timestampTitle: null, intent: null, meta: [], count: null, truncated: false, children: [], day });
const today = { date: '2026-10-29', label: 'Today' };
const yesterday = { date: '2026-10-28', label: 'Yesterday' };

test('entries group into consecutive day sections, labelled by the day', () => {
    const sections = activitySections([entry('a', today), entry('b', today), entry('c', yesterday)], true);
    assert.deepEqual(sections.map(s => [s.day?.label, s.entries.map(e => e.id)]), [['Today', ['a', 'b']], ['Yesterday', ['c']]]);
});

test('an entry with no day joins the section before it', () => {
    const sections = activitySections([entry('a', today), entry('b'), entry('c', yesterday)], true);
    assert.deepEqual(sections.map(s => s.entries.map(e => e.id)), [['a', 'b'], ['c']]);
});

test('with days off, one unlabelled section holds every entry', () => {
    const sections = activitySections([entry('a', today), entry('b', yesterday)], false);
    assert.equal(sections.length, 1);
    assert.equal(sections[0].day, null);
    assert.deepEqual(sections[0].entries.map(e => e.id), ['a', 'b']);
});

test('html reaches v-html only through trustedHtml; text never does', () => {
    assert.equal(trustedHtml({ html: '<strong>Ada</strong> signed' }), '<strong>Ada</strong> signed');
    assert.equal(trustedHtml({ text: '<strong>Ada</strong> signed' }), null, 'a text headline is never treated as markup');
    assert.equal(headlineText({ text: '<b>x</b>' }), '<b>x</b>');
    const row = strip(read('Feed/ActivityEntryRow.vue.stub'));
    assert.equal(row.match(/v-html=/g).length, 1, 'one v-html in the row');
    assert.match(row, /const html = computed\(\(\) => trustedHtml\(props\.entry\.headline\)\);/);
    assert.match(row, /v-if="html !== null"[^>]*v-html="html"/);
    assert.match(row, /const text = computed\(\(\) => headlineText\(props\.entry\.headline\)\);/);
    assert.match(row, /v-else-if="text"[^>]*>\{\{ text \}\}<\/p>/, 'text renders as interpolated text');
    assert.doesNotMatch(strip(read('Feed/activity.ts.stub')), /escape|sanitize|DOMPurify|replace\(/i, 'the kit neither escapes nor sanitizes html');
});

test('the stream shows the empty state with the host\'s text, and no live region unless asked', () => {
    const stream = strip(read('Feed/ActivityStream.vue.stub'));
    assert.match(stream, /<EmptyState v-if="!entries\.length" :title="empty" compact \/>/);
    assert.match(stream, /empty: 'Nothing has happened yet\.'/);
    assert.match(stream, /:aria-live="live \? 'polite' : undefined"/);
    assert.match(stream, /live: false/);
    assert.match(stream, /<section v-for="section in sections" :key="section\.key" :aria-label="section\.day\?\.label"/);
    assert.match(stream, /<Button type="button" variant="secondary" :disabled="loading" data-load-more @click="loadMore">/);
});

function feed(first) {
    const fetched = [];
    const controller = createCursorFeed({ first, cursorKey: 'next_cursor', syncKey: 'sync_token', fetch: cursor => fetched.push(cursor) });
    return { controller, fetched };
}
const page = (ids, next_cursor, sync_token = 's1') => ({ entries: ids.map(id => entry(id)), next_cursor, sync_token });

test('pages accumulate, skipping entries already held, and a null cursor ends the feed', () => {
    const { controller, fetched } = feed(page(['a', 'b'], 'c1'));
    assert.equal(controller.loadMore(), true);
    assert.deepEqual(fetched, ['c1']);
    assert.equal(controller.state.loading, true);
    assert.equal(controller.loadMore(), false, 'no second request while one is in flight');
    assert.equal(controller.receive(page(['b', 'c'], null)), 1);
    assert.deepEqual(controller.state.entries.map(e => e.id), ['a', 'b', 'c']);
    assert.equal(controller.state.cursor, null);
    assert.equal(controller.state.loading, false);
    assert.equal(controller.loadMore(), false, 'nothing more to load');
});

test('an empty page that carries a cursor keeps following it', () => {
    const { controller, fetched } = feed(page(['a'], 'c1'));
    controller.loadMore();
    controller.receive(page([], 'c2'));
    assert.deepEqual(fetched, ['c1', 'c2']);
    controller.receive(page(['b'], null));
    assert.deepEqual(controller.state.entries.map(e => e.id), ['a', 'b']);
});

test('a changed sync token drops the held entries and reads the head page again', () => {
    const { controller, fetched } = feed(page(['a', 'b'], 'c1'));
    controller.loadMore();
    assert.equal(controller.receive(page(['x'], 'c9', 's2')), 0, 'the rewritten page is not appended');
    assert.deepEqual(fetched, ['c1', null], 'the head page is requested');
    assert.deepEqual(controller.state.entries.map(e => e.id), ['a', 'b'], 'held until the head arrives');
    controller.receive(page(['n', 'a'], 'c5', 's2'));
    assert.deepEqual(controller.state.entries.map(e => e.id), ['n', 'a']);
    assert.equal(controller.state.cursor, 'c5');
    assert.equal(controller.state.resynced, true);
    controller.loadMore();
    controller.receive(page(['b'], null, 's2'));
    assert.deepEqual(controller.state.entries.map(e => e.id), ['n', 'a', 'b'], 'paging continues under the new token');
});

test('a failed request stops loading and keeps what is held', () => {
    const { controller } = feed(page(['a'], 'c1'));
    controller.loadMore();
    controller.failed();
    assert.equal(controller.state.loading, false);
    assert.deepEqual(controller.state.entries.map(e => e.id), ['a']);
    assert.equal(controller.loadMore(), true, 'it can try again');
});

test('useCursorFeed reloads only its prop with the cursor, keeping the URL, and makes the state reactive', () => {
    const composable = strip(read('Feed/useCursorFeed.ts.stub'));
    assert.match(composable, /router\.reload\(\{ only: \[prop\], data: \{ \[param\]: cursor \}, preserveUrl: true,/);
    assert.match(composable, /const \{ prop, cursorKey = 'next_cursor', syncKey = 'sync_token', param = 'cursor' \} = config;/);
    assert.match(composable, /wrap: <S extends object>\(value: S\) => reactive\(value\) as S,/);
});

test('a null headline renders the row with its time and meta only, and no empty paragraph', () => {
    assert.equal(trustedHtml(null), null);
    assert.equal(headlineText(null), '');
    const row = strip(read('Feed/ActivityEntryRow.vue.stub'));
    assert.match(row, /<p v-else-if="text"/, 'no text paragraph without text');
    assert.match(row, /\(html !== null \|\| text\) && 'mt-0\.5'/, 'the meta line closes up without a headline above it');
    assert.match(strip(read('Feed/activity.ts.stub')), /headline: ActivityHeadline \| null;/);
});

test('an intent outside the kit tones shows as neutral, with a warning in development, never an error', () => {
    const warned = [];
    assert.equal(activityTone('success'), 'success');
    assert.equal(activityTone(null), 'neutral');
    assert.equal(activityTone('paid', intent => warned.push(intent)), 'neutral');
    assert.deepEqual(warned, ['paid']);
    const row = strip(read('Feed/ActivityEntryRow.vue.stub'));
    assert.match(row, /if \(!import\.meta\.env\?\.DEV \|\| warned\.has\(intent\)\) return;/);
    assert.match(row, /:data-tone="tone"/);
    assert.match(row, /dots\[tone\]/);
});

test('effectiveOn: "effective <label> · recorded <timestamp>"', () => {
    const row = strip(read('Feed/ActivityEntryRow.vue.stub'));
    assert.match(row, /<span v-if="entry\.effectiveOnLabel" data-activity-effective>effective <time :datetime="entry\.effectiveOn \?\? undefined">\{\{ entry\.effectiveOnLabel \}\}<\/time><template v-if="when"> · recorded<\/template><\/span>/);
    assert.match(strip(read('Feed/activity.ts.stub')), /effectiveOn\?: string \| null;\s*effectiveOnLabel\?: string \| null;/);
});
