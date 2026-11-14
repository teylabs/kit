import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// DateTime's formatting (Kit/Data/dateTime.ts): Intl only. A calendar date is never converted between zones; an instant shows in the viewer's zone.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-date-time-'));
writeFileSync(join(dir, 'dateTime.ts'), readFileSync(new URL('Data/dateTime.ts', kit), 'utf8'));
const { formatDateTime, fullDateTime, hourCycle, isCalendarDate, todayIn } = await import(pathToFileURL(join(dir, 'dateTime.ts')).href);

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

// The viewer's clock (kitClock): 12h is h12 and 24h is h23 (never h11's 0:30 AM or h24's 24:30); auto sets nothing, so the locale's own clock shows unchanged.
test('a time shows on the viewer\'s clock: 24h in a 12-hour locale, 12h in a 24-hour one, and auto exactly as before', () => {
    const spaced = (value) => value.replace(/\s/gu, ' ');
    const evening = '2026-10-29T23:30:00Z';
    const midnight = '2026-10-30T03:30:00Z';
    const at = (value, format, locale, clock) => spaced(formatDateTime(value, format, { timezone: 'America/Halifax', locale, ...(clock ? { clock } : {}) }));
    assert.equal(at(evening, 'time', 'en-US', '24h'), '20:30');
    assert.equal(at(evening, 'time', 'en-GB', '12h'), '08:30 pm', 'en-GB\'s own 12-hour pattern keeps two digits');
    assert.equal(at(midnight, 'time', 'en-US', '24h'), '00:30', 'h23, not 24:30');
    assert.equal(at(midnight, 'time', 'en-GB', '12h'), '12:30 am', 'h12, not 00:30');
    assert.equal(at(evening, 'local', 'en-US', '24h'), 'Oct 29, 2026, 20:30');
    assert.equal(at(evening, 'datetime', 'en-GB', '12h'), '29 Oct 2026, 08:30 pm');
    for (const [locale, own] of [['en-US', '8:30 PM'], ['en-GB', '20:30']]) {
        assert.equal(at(evening, 'time', locale, 'auto'), own, `${locale}: auto is the locale's own`);
        assert.equal(at(evening, 'time', locale, 'auto'), at(evening, 'time', locale, undefined), `${locale}: auto is byte for byte no clock at all`);
        assert.equal(spaced(fullDateTime(evening, { timezone: 'America/Halifax', locale, clock: 'auto' })), spaced(fullDateTime(evening, { timezone: 'America/Halifax', locale })));
    }
    assert.match(spaced(fullDateTime(evening, { timezone: 'America/Halifax', locale: 'en-US', clock: '24h' })), /at 20:30:00 ADT$/, 'the title follows the clock too');
    assert.equal(at('2026-11-01', 'local', 'en-US', '24h'), 'Nov 1, 2026', 'a calendar date has no time to change');
    assert.deepEqual([hourCycle('12h'), hourCycle('24h'), hourCycle('auto'), hourCycle(undefined)], [{ hourCycle: 'h12' }, { hourCycle: 'h23' }, {}, {}]);
});
