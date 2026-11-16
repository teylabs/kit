import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// EmailMessage forwards: emailForwards.ts (plain TypeScript) runs here without a DOM on synthetic Gmail, Apple Mail and Outlook bodies. The browser job
// (browser/run.mjs, ?small-pieces) checks the collapsed disclosure and that the forwarded text stays visible.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-email-forwards-'));
writeFileSync(join(dir, 'emailForwards.ts'), read('Text/emailForwards.ts'));
const { splitForwards, forwardSummary, senderName } = await import(pathToFileURL(join(dir, 'emailForwards.ts')).href);
const source = read('Text/EmailMessage.vue');

const gmail = [
    'FYI, see below.',
    '',
    '---------- Forwarded message ---------',
    'From: Ana Lopez <ana@example.test>',
    'Date: Mon, Nov 9, 2026 at 3:14 PM',
    'Subject: Farm order',
    'To: <desk@example.test>',
    '',
    'Hello,',
    'Please see attached.',
].join('\n');

const apple = [
    'Passing this on.',
    '',
    'Begin forwarded message:',
    '',
    'From: Bo Chen <bo@example.test>',
    'Subject: Re: Pickup',
    'Date: November 9, 2026 at 3:14:00 PM AST',
    'To: Ana Lopez <ana@example.test>',
    '',
    'Pickup is Tuesday.',
].join('\n');

const outlook = [
    'See the note from Cy.',
    '',
    '________________________________',
    'From: Cy Doe <cy@example.test>',
    'Sent: Monday, November 9, 2026 3:14 PM',
    'To: Ana Lopez <ana@example.test>',
    'Subject: Invoice',
    '',
    'Invoice attached.',
].join('\r\n');

test('Gmail: the text above the marker is the body; the forward has its sender, date, subject and text', () => {
    const { body, forwards } = splitForwards(gmail);
    assert.equal(body, 'FYI, see below.');
    assert.equal(forwards.length, 1);
    assert.equal(forwards[0].sender, 'Ana Lopez');
    assert.equal(forwards[0].date, 'Mon, Nov 9, 2026 at 3:14 PM');
    assert.equal(forwards[0].subject, 'Farm order');
    assert.deepEqual(forwards[0].headers.map(h => h.name), ['From', 'Date', 'Subject', 'To']);
    assert.equal(forwards[0].body, 'Hello,\nPlease see attached.');
    assert.equal(forwardSummary(forwards[0]), 'Forwarded from Ana Lopez, Mon, Nov 9, 2026 at 3:14 PM');
});

test('Apple Mail: "Begin forwarded message:" with a blank line before the headers', () => {
    const { body, forwards } = splitForwards(apple);
    assert.equal(body, 'Passing this on.');
    assert.equal(forwards[0].sender, 'Bo Chen');
    assert.equal(forwards[0].date, 'November 9, 2026 at 3:14:00 PM AST');
    assert.equal(forwards[0].body, 'Pickup is Tuesday.');
});

test('Outlook: a rule and a From/Sent/To/Subject run (CRLF); Sent is the date; a bare "-----Original Message-----" works too', () => {
    const { body, forwards } = splitForwards(outlook);
    assert.equal(body, 'See the note from Cy.');
    assert.equal(forwards[0].sender, 'Cy Doe');
    assert.equal(forwards[0].date, 'Monday, November 9, 2026 3:14 PM');
    assert.equal(forwards[0].body, 'Invoice attached.');
    const bare = splitForwards('Hi\n\nFrom: Cy Doe\nSent: Monday\nTo: Ana\nSubject: X\n\nText');
    assert.equal(bare.forwards[0].sender, 'Cy Doe');
    assert.equal(bare.forwards[0].body, 'Text');
    assert.equal(splitForwards('-----Original Message-----\nFrom: cy@example.test\nSent: Monday\n\nText').forwards[0].sender, 'cy@example.test');
});

test('nested forwards read outermost first, each with its own text and depth', () => {
    const nested = `${gmail}\n\n${apple.split('\n').slice(2).join('\n')}`;
    const { body, forwards } = splitForwards(nested);
    assert.equal(body, 'FYI, see below.');
    assert.deepEqual(forwards.map(f => [f.depth, f.sender, f.body]), [
        [1, 'Ana Lopez', 'Hello,\nPlease see attached.'],
        [2, 'Bo Chen', 'Pickup is Tuesday.'],
    ]);
});

test('a forward with nothing above it has an empty body', () => {
    const { body, forwards } = splitForwards(gmail.split('\n').slice(2).join('\n'));
    assert.equal(body, '');
    assert.equal(forwards.length, 1);
});

test('no forward means no change: the body comes back byte for byte', () => {
    for (const text of ['Hello,\nPlease see attached.\n', 'From: me, with love\nSee you', 'Line\r\nFrom: Ana <a@example.test>\r\nTo: Bo\r\n', '']) {
        assert.deepEqual(splitForwards(text), { body: text, forwards: [] }, JSON.stringify(text));
    }
    assert.deepEqual(splitForwards(null), { body: '', forwards: [] });
});

test('senderName: display name, bare address, quoted name, mailto brackets', () => {
    assert.equal(senderName('Ana Lopez <ana@example.test>'), 'Ana Lopez');
    assert.equal(senderName('<ana@example.test>'), 'ana@example.test');
    assert.equal(senderName('"Lopez, Ana" <ana@example.test>'), 'Lopez, Ana');
    assert.equal(senderName('Ana Lopez [mailto:ana@example.test]'), 'Ana Lopez');
    assert.equal(senderName(null), null);
    assert.equal(forwardSummary({ sender: null, date: null }), 'Forwarded message');
});

test('EmailMessage splits by default, opts out with :forwarded="false", and folds headers into a Collapsible', () => {
    assert.match(source, /forwarded: true/);
    assert.match(source, /props\.forwarded && props\.body/);
    assert.match(source, /<Collapsible[\s\S]*forwardSummary\(forward\)[\s\S]*data-email-forward-headers/);
    assert.match(source, /data-email-forward-body/);
});
