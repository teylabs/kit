import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The pickers' values (Kit/Form/dateInput.ts): a date, month, year or time is a wall value and never shifts between zones; a timed value goes through the viewer's zone and carries its offset.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-date-input-'));
writeFileSync(join(dir, 'dateInput.ts'), readFileSync(new URL('Form/dateInput.ts.stub', kit), 'utf8'));
const m = await import(pathToFileURL(join(dir, 'dateInput.ts')).href);

test('validates dates, months, years and times', () => {
    assert.ok(m.isDate('2028-02-29'));
    assert.ok(!m.isDate('2026-02-29'));
    assert.ok(!m.isDate('2026-13-01'));
    assert.ok(!m.isDate('2026-1-01'));
    assert.ok(m.isMonth('2026-12') && !m.isMonth('2026-00'));
    assert.ok(m.isYear('2026') && !m.isYear('0000') && !m.isYear('26'));
    assert.ok(m.isTime('23:59') && m.isTime('00:00:30') && !m.isTime('24:00') && !m.isTime('9:30'));
    assert.equal(m.normalizeTime('09:30:15'), '09:30');
    assert.equal(m.normalizeTime('nope'), '');
});

test('a date never shifts across zones or DST', () => {
    // A date is a wall value: the same string comes back whatever the viewer's zone, including on DST change days.
    for (const zone of ['UTC', 'America/Halifax', 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Australia/Lord_Howe', 'America/St_Johns']) {
        for (const date of ['2026-03-08', '2026-11-01', '2026-03-29', '2026-10-25', '2026-01-01', '2026-12-31']) {
            const { date: back } = m.fromInstant(m.toInstant(date, '00:00', zone), zone);
            assert.equal(back, date, `${date} in ${zone}`);
            const { date: noon } = m.fromInstant(m.toInstant(date, '12:00', zone), zone);
            assert.equal(noon, date, `${date} noon in ${zone}`);
        }
    }
});

test('a timed value carries the offset in force at that wall time', () => {
    assert.equal(m.toInstant('2026-01-15', '09:30', 'America/Halifax'), '2026-01-15T09:30:00-04:00');
    assert.equal(m.toInstant('2026-07-15', '09:30', 'America/Halifax'), '2026-07-15T09:30:00-03:00');
    assert.equal(m.toInstant('2026-07-15', '09:30', 'America/St_Johns'), '2026-07-15T09:30:00-02:30');
    assert.equal(m.toInstant('2026-07-15', '09:30', 'UTC'), '2026-07-15T09:30:00+00:00');
    assert.equal(m.toInstant('2026-07-15', '09:30', 'Asia/Kolkata'), '2026-07-15T09:30:00+05:30');
});

test('DST gaps move forward and repeated hours take the earlier instant', () => {
    // 2026-03-08 02:30 does not exist in New York; 2026-11-01 01:30 happens twice.
    assert.equal(m.toInstant('2026-03-08', '02:30', 'America/New_York'), '2026-03-08T03:30:00-04:00');
    assert.equal(m.toInstant('2026-11-01', '01:30', 'America/New_York'), '2026-11-01T01:30:00-04:00');
});

test('an instant reads back in the viewer zone; a value without an offset is not an instant', () => {
    assert.deepEqual(m.fromInstant('2026-07-15T12:30:00Z', 'America/Halifax'), { date: '2026-07-15', time: '09:30' });
    assert.deepEqual(m.fromInstant('2026-07-15T02:30:00Z', 'America/Halifax'), { date: '2026-07-14', time: '23:30' });
    assert.deepEqual(m.fromInstant('2026-07-15T12:30:00', 'UTC'), { date: '', time: '' });
    assert.deepEqual(m.fromInstant('2026-07-15', 'UTC'), { date: '', time: '' });
    for (const zone of ['America/Halifax', 'Asia/Kolkata', 'Pacific/Chatham']) {
        const round = m.toInstant('2026-10-29', '18:45', zone);
        assert.deepEqual(m.fromInstant(round, zone), { date: '2026-10-29', time: '18:45' });
    }
});

test('ranges: blank is valid, half-filled and reversed are errors', () => {
    const messages = { incomplete: 'Choose both dates.', order: 'The end is before the start.' };
    assert.equal(m.rangeError({ start: '', end: '' }, m.isDate, messages), '');
    assert.equal(m.rangeError({ start: '2026-01-01', end: '' }, m.isDate, messages), messages.incomplete);
    assert.equal(m.rangeError({ start: '2026-02-01', end: '2026-01-01' }, m.isDate, messages), messages.order);
    assert.equal(m.rangeError({ start: '2026-01-01', end: '2026-01-01' }, m.isDate, messages), '');
    assert.equal(m.rangeError({ start: '09:00', end: '08:00' }, m.isTime, messages), messages.order);
});

test('month names and the year span', () => {
    assert.equal(m.monthNames('en-US')[0], 'January');
    assert.equal(m.monthNames('en-US', 'short')[11], 'Dec');
    const years = m.yearSpan('2026-10-29', 2, 1);
    assert.deepEqual(years, [2027, 2026, 2025, 2024]);
});

test('month and year grids move focus by the APG grid keys', () => {
    // 12 cells, 3 wide: 0 1 2 / 3 4 5 / 6 7 8 / 9 10 11.
    assert.equal(m.gridMove(4, 'ArrowLeft', 3, 12), 3);
    assert.equal(m.gridMove(4, 'ArrowUp', 3, 12), 1);
    assert.equal(m.gridMove(4, 'ArrowDown', 3, 12), 7);
    assert.equal(m.gridMove(0, 'ArrowLeft', 3, 12), -1);
    assert.equal(m.gridMove(1, 'ArrowUp', 3, 12), -2);
    assert.equal(m.gridMove(11, 'ArrowRight', 3, 12), 12);
    assert.equal(m.gridMove(10, 'ArrowDown', 3, 12), 13);
    assert.equal(m.gridMove(4, 'Home', 3, 12), 3);
    assert.equal(m.gridMove(4, 'End', 3, 12), 5);
    assert.equal(m.gridMove(4, 'Home', 3, 12, true), 0);
    assert.equal(m.gridMove(4, 'End', 3, 12, true), 11);
    assert.equal(m.gridMove(4, 'PageUp', 3, 12), -8);
    assert.equal(m.gridMove(4, 'PageDown', 3, 12), 16);
    assert.equal(m.gridMove(4, 'Tab', 3, 12), null);
    // Off the page, the cell modulo 12 keeps the column (Up/Down, PageUp/PageDown) or wraps the row (Left/Right).
    const wrap = n => ((n % 12) + 12) % 12;
    assert.equal(wrap(m.gridMove(1, 'ArrowUp', 3, 12)), 10);
    assert.equal(wrap(m.gridMove(10, 'ArrowDown', 3, 12)), 1);
    assert.equal(wrap(m.gridMove(0, 'ArrowLeft', 3, 12)), 11);
    assert.equal(wrap(m.gridMove(4, 'PageUp', 3, 12)), 4);
});
