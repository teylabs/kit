import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stubs are TypeScript with a .stub suffix; node strips types from a plain .ts copy.
const dir = mkdtempSync(join(tmpdir(), 'kit-number-'));
const load = async name => {
    copyFileSync(new URL(`../../resources/features/core/components/Kit/Form/${name}.ts.stub`, import.meta.url), join(dir, `${name}.ts`));
    return import(pathToFileURL(join(dir, `${name}.ts`)).href);
};
const { numberFormat } = await load('number');
const { fieldPath, mappedErrors } = await load('repeaterState');

test('exact native strings, empty, precision rejection, signed paste and huge values', () => {
    const money = numberFormat({ kind: 'money', currency: 'CAD', signed: true });
    for (const [input, canonical, formatted] of [['1234.5','123450','1,234.50'], ['(CA$1,234.56)','-123456','-1,234.56'], ['900719925474099312345.67','90071992547409931234567','900,719,925,474,099,312,345.67'], ['-0','0','0.00'], ['','','']]) {
        assert.deepEqual(money.parse(input), { value: canonical, error: '' });
        assert.equal(money.format(canonical), formatted);
    }
    assert.deepEqual(money.parse('1.001'), { value: '', error: 'Use at most 2 decimal places.' });
    assert.equal(numberFormat({kind:'integer'}).parse('-2').error, 'Enter a positive number.');
    assert.equal(numberFormat({kind:'decimal', scale:4}).parse('.075').value, '0.0750');
    assert.equal(numberFormat({kind:'integer'}).parse('12345678901234567890').value, '12345678901234567890');
});
test('locale parsing/formatting, scale metadata, min/max/step hints', () => {
    const de = numberFormat({ kind: 'money', currency:'EUR', locale:'de-DE', signed:true });
    assert.equal(de.parse('(1.234,56 €)').value, '-123456');
    assert.equal(de.format('-123456'), '-1.234,56');
    assert.equal(numberFormat({kind:'money',currency:'JPY'}).parse('12').value, '12');
    assert.equal(numberFormat({kind:'money',currency:'KWD'}).parse('12.123').value, '12123');
    const int = numberFormat({kind:'integer', min:'0', max:'10', step:'2'});
    assert.equal(int.validate('-1'), 'Minimum: 0.'); assert.equal(int.validate('11'), 'Maximum: 10.'); assert.equal(int.validate('3'), 'Use increments of 2.'); assert.equal(int.validate('4'), '');
});
test('identifier-like integers can opt out of grouping without changing canonical values', () => {
    const year = numberFormat({kind:'integer', grouping:false});
    assert.equal(year.format('2020'), '2020');
    assert.equal(year.parse('2020').value, '2020');
    assert.equal(year.format('12345678901234567890'), '12345678901234567890');
    assert.equal(numberFormat({kind:'integer'}).format('80000'), '80,000');
});

test('repeater error paths map the last server order to the current order', () => {
    assert.equal(fieldPath('lines[2][amount]'), 'lines.2.amount');
    const errors = { 'lines.2.amount': 'Required.', 'lines.0.name': 'Too long.' };
    assert.deepEqual(mappedErrors(errors, 'lines[2]', 'lines[0]'), { 'lines[0][amount]': 'Required.' });
});
