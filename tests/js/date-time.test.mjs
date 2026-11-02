import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// DateTime's formatting (Kit/Data/dateTime.ts): Intl only. A calendar date is never converted between zones; an instant shows in the viewer's zone.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-date-time-'));
writeFileSync(join(dir, 'dateTime.ts'), readFileSync(new URL('Data/dateTime.ts.stub', kit), 'utf8'));
const { formatDateTime, fullDateTime, isCalendarDate, todayIn } = await import(pathToFileURL(join(dir, 'dateTime.ts')).href);

// The zones furthest apart (UTC+14 and UTC-11), and one with a DST change on 2026-11-01.
const zones = ['Pacific/Kiritimati', 'Pacific/Pago_Pago', 'America/New_York', 'America/Halifax', 'Asia/Kolkata', 'UTC'];

test('a calendar date never shifts across zones or a DST change, in every format', () => {
    for (const value of ['2026-11-01', '2026-03-08', '2026-03-29', '2026-01-01', '2026-12-31']) {
        for (const format of ['local', 'date', 'datetime', 'time', 'ymd']) {
            const seen = new Set(zones.map(timezone => formatDateTime(value, format, { timezone, locale: 'en-US' })));
            assert.equal(seen.size, 1, `${value} ${format} is the same in every zone: ${[...seen].join(' | ')}`);
        }
        assert.equal(formatDateTime(value, 'ymd', { timezone: 'Pacific/Kiritimati' }), value);
        assert.equal(new Set(zones.map(timezone => fullDateTime(value, { timezone, locale: 'en-US' }))).size, 1, `${value}'s title never shifts`);
    }
    assert.equal(formatDateTime('2026-11-01', 'date', { timezone: 'Pacific/Pago_Pago', locale: 'en-US' }), 'Nov 1, 2026');
});

test('an instant shows in the viewer\'s zone: the same moment is a different day east and west of the date line', () => {
    const moment = '2026-10-29T23:30:00Z';
    assert.equal(formatDateTime(moment, 'ymd', { timezone: 'Pacific/Kiritimati' }), '2026-10-30');
    assert.equal(formatDateTime(moment, 'ymd', { timezone: 'Pacific/Pago_Pago' }), '2026-10-29');
    assert.equal(formatDateTime(moment, 'time', { timezone: 'America/Halifax', locale: 'en-US' }), '8:30 PM');
    assert.equal(formatDateTime('2026-10-29T20:30:00-03:00', 'time', { timezone: 'America/Halifax', locale: 'en-US' }), '8:30 PM', 'the offset in the value is honoured');
    assert.match(fullDateTime(moment, { timezone: 'America/Halifax', locale: 'en-US' }), /Thursday, October 29, 2026 at 8:30:00 PM ADT/);
});

test('an instant without an offset, or anything else, is shown as given, never guessed', () => {
    for (const value of ['2026-10-29T20:30:00', 'yesterday', '', '2026-02-30', '29/10/2026']) assert.equal(formatDateTime(value, 'local', { timezone: 'UTC' }), value);
    assert.equal(isCalendarDate('2026-02-30'), false);
});

test('relative: seconds, minutes, hours and days, then the absolute date past the threshold', () => {
    const now = Date.parse('2026-10-29T12:00:00Z');
    const at = (seconds) => new Date(now + seconds * 1000).toISOString();
    const rel = (seconds, extra = {}) => formatDateTime(at(seconds), 'relative', { timezone: 'UTC', locale: 'en-US', now, ...extra });
    assert.equal(rel(-10), 'now');
    assert.equal(rel(-180), '3 minutes ago');
    assert.equal(rel(2 * 3600), 'in 2 hours');
    assert.equal(rel(-2 * 86400), '2 days ago');
    assert.equal(rel(-8 * 86400), 'Oct 21, 2026, 12:00 PM', 'past 7 days it is the date and time');
    assert.equal(rel(-8 * 86400, { relativeUntilDays: 30 }), '8 days ago');
    // A calendar date's relative day is counted on the viewer's calendar.
    assert.equal(formatDateTime('2026-10-28', 'relative', { timezone: 'UTC', locale: 'en-US', now }), 'yesterday');
    assert.equal(formatDateTime('2026-10-30', 'relative', { timezone: 'Pacific/Kiritimati', locale: 'en-US', now }), 'today', 'it is already the 30th in Kiritimati');
    assert.equal(todayIn('Pacific/Pago_Pago', now), '2026-10-29');
});

test('DateTime renders a <time> with the machine value and the full title, and a live relative time shares one ticker', () => {
    const view = readFileSync(new URL('Data/DateTime.vue.stub', kit), 'utf8');
    assert.match(view, /<time :datetime="value" :title="title" data-slot="date-time">\{\{ text \}\}<\/time>/);
    const timezone = readFileSync(new URL('Data/useTimezone.ts.stub', kit), 'utf8');
    assert.match(timezone, /page\?\.props\.kitTimezone/);
    assert.match(timezone, /browserTimezone\(\) \?\? 'UTC'/);
    assert.match(timezone, /setInterval\(\(\) => \(tick\.value = Date\.now\(\)\), 60_000\)/);
    assert.match(readFileSync(new URL('index.ts.stub', kit), 'utf8'), /export \{ default as DateTime \} from '\.\/Data\/DateTime\.vue';/);
});
