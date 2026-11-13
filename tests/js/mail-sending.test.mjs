import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Settings › Email sending's labels (Kit/Data/mailSending.ts): strings and Intl only.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-mail-sending-'));
writeFileSync(join(dir, 'mailSending.ts'), readFileSync(new URL('Data/mailSending.ts', kit), 'utf8'));
const { checkedAtLabel, fromLine, fromPreview, fullRecordName, recordKind } = await import(pathToFileURL(join(dir, 'mailSending.ts')).href);
const spaced = (value) => value.replace(/\s/gu, ' ');
const now = new Date('2026-11-06T21:00:00Z');

test('the From line names the sender and the address', () => {
    assert.equal(fromLine('Northwind Support', 'news@northwind.example'), 'Northwind Support <news@northwind.example>');
});

test('the preview is the domain itself for root and the send. subdomain otherwise, lowercased, never doubling send.', () => {
    assert.equal(fromPreview('News', 'Northwind.Example', true), 'news@northwind.example');
    assert.equal(fromPreview('News', 'Northwind.Example'), 'news@send.northwind.example');
    assert.equal(fromPreview('news', 'send.northwind.example'), 'news@send.northwind.example');
    assert.equal(fromPreview('news', 'send.northwind.example', true), 'news@northwind.example');
    assert.equal(fromPreview(' ', ' ', true), 'hello@example.com', 'empty fields read as the placeholders');
});

test('records are named by what they are for', () => {
    assert.equal(recordKind({ type: 'TXT', name: 'resend._domainkey.send' }), 'DKIM');
    assert.equal(recordKind({ type: 'TXT', name: 'send.send' }), 'SPF');
    assert.equal(recordKind({ type: 'mx', name: 'send' }), 'MX');
    assert.equal(recordKind({ type: 'CNAME', name: 'bounces' }), 'Return path');
    assert.equal(recordKind({ type: 'AAAA', name: 'x' }), 'AAAA');
});

test('a record\'s full name joins the zone once, for a root domain, a send. domain, a subdomain, and a name already whole', () => {
    assert.equal(fullRecordName('resend._domainkey', 'northwind.example'), 'resend._domainkey.northwind.example');
    assert.equal(fullRecordName('send', 'northwind.example'), 'send.northwind.example');
    assert.equal(fullRecordName('resend._domainkey.send', 'send.northwind.example'), 'resend._domainkey.send.northwind.example');
    assert.equal(fullRecordName('send.send', 'send.northwind.example'), 'send.send.northwind.example');
    assert.equal(fullRecordName('resend._domainkey.send.mail', 'send.mail.northwind.example'), 'resend._domainkey.send.mail.northwind.example');
    assert.equal(fullRecordName('resend._domainkey.send.northwind.example', 'send.northwind.example'), 'resend._domainkey.send.northwind.example');
    assert.equal(fullRecordName('@', 'northwind.example'), 'northwind.example');
});

test('Last checked is in the given timezone, never raw UTC, with the calendar day the timezone\'s', () => {
    assert.equal(spaced(checkedAtLabel('2026-11-06 20:37:08', 'America/Halifax', now, 'en-US')), 'Today 4:37 PM');
    assert.equal(spaced(checkedAtLabel('2026-11-06 20:37:08', 'America/Toronto', now, 'en-US')), 'Today 3:37 PM');
    assert.equal(spaced(checkedAtLabel('2026-11-06 02:00:00', 'America/Toronto', now, 'en-US')), 'Yesterday 9:00 PM');
    assert.equal(spaced(checkedAtLabel('2026-11-03 20:00:00', 'America/Toronto', now, 'en-US')), 'Nov 3, 3:00 PM');
    assert.equal(spaced(checkedAtLabel('2026-11-06T17:37:08-03:00', 'America/Halifax', now, 'en-US')), 'Today 4:37 PM', 'an offset in the value is kept');
    assert.equal(checkedAtLabel('soon', 'UTC', now), 'soon', 'anything else is shown as given');
    // 2026-03-08 is 23 hours long in New York (spring forward): 00:30 on the 9th minus 24 hours is the 7th, yet a check at 10:00 on the 8th is yesterday.
    const afterSpringForward = new Date('2026-03-09T04:30:00Z');
    assert.equal(spaced(checkedAtLabel('2026-03-08 14:00:00', 'America/New_York', afterSpringForward, 'en-US')), 'Yesterday 10:00 AM', 'the day before a 23-hour day is still Yesterday');
});

test('Last checked follows the viewer\'s clock: 24h in a 12-hour locale, 12h in a 24-hour one, auto unchanged', () => {
    assert.equal(spaced(checkedAtLabel('2026-11-06 20:37:08', 'America/Toronto', now, 'en-US', '24h')), 'Today 15:37');
    assert.equal(spaced(checkedAtLabel('2026-11-06 02:00:00', 'America/Toronto', now, 'en-GB', '12h')), 'Yesterday 9:00 pm');
    assert.equal(spaced(checkedAtLabel('2026-11-06 05:05:00', 'America/Toronto', now, 'en-US', '24h')), 'Today 00:05', 'h23, not 24:05');
    assert.equal(spaced(checkedAtLabel('2026-11-06 20:37:08', 'America/Toronto', now, 'en-US', 'auto')), 'Today 3:37 PM');
    assert.equal(spaced(checkedAtLabel('2026-11-06 20:37:08', 'America/Toronto', now, 'en-GB')), 'Today 15:37', 'no clock: the locale\'s own');
});
