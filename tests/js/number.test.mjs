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
const { numberDefaults, numberFormat } = await load('number');
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

test('percent is canonical in percentage points, never a fraction', () => {
    const percent = numberFormat({ kind: 'percent' });
    assert.deepEqual(percent.parse('12.5'), { value: '12.50', error: '' });
    assert.deepEqual(percent.parse('12.5 %'), { value: '12.50', error: '' });
    assert.equal(percent.format('12.50'), '12.50');
    // 0.125 is 0.125 %, not 12.5 %: it is formatted as given, or refused when it needs more places than the scale.
    const fine = numberFormat({ kind: 'percent', scale: 3 });
    assert.deepEqual(fine.parse('0.125'), { value: '0.125', error: '' });
    assert.equal(fine.format('0.125'), '0.125');
    assert.deepEqual(percent.parse('0.125'), { value: '', error: 'Use at most 2 decimal places.' });
    assert.equal(numberFormat({ kind: 'decimal' }).parse('12%').error, 'Enter a valid number.');
    const fr = numberFormat({ kind: 'percent', locale: 'fr-CA', scale: 1 });
    assert.equal(fr.parse('12,5 %').value, '12.5');
    assert.equal(fr.format('12.5'), '12,5');
    assert.deepEqual({ prefix: fr.affix.prefix, suffix: fr.affix.suffix.trim() }, { prefix: '', suffix: '%' });
    assert.deepEqual(percent.affix, { prefix: '', suffix: '%' });
});

test('money affixes follow the locale, accounting negatives and no guessed currency', () => {
    assert.deepEqual(numberFormat({ kind: 'money', currency: 'CAD', locale: 'en-CA' }).affix, { prefix: '$', suffix: '' });
    const fr = numberFormat({ kind: 'money', currency: 'CAD', locale: 'fr-CA' }).affix;
    assert.equal(fr.prefix, '');
    assert.equal(fr.suffix.trim(), '$');
    const accounting = numberFormat({ kind: 'money', currency: 'CAD', signed: true, negative: 'parentheses' });
    assert.equal(accounting.format('-123456'), '(1,234.56)');
    assert.equal(accounting.format('123456'), '1,234.56');
    assert.equal(accounting.parse('(1,234.56)').value, '-123456');
    // Without a currency nothing is guessed: no symbol, two decimals, the same minor-unit canonical form.
    const bare = numberFormat({ kind: 'money' });
    assert.deepEqual(bare.affix, { prefix: '', suffix: '' });
    assert.equal(bare.parse('$12.34').value, '1234');
    assert.equal(numberFormat({ kind: 'money', currency: 'JPY' }).scale, 0);
});

test('number defaults: the shared kitNumber, then the browser, then en-CA; a currency only when configured', () => {
    assert.deepEqual(numberDefaults({ locale: 'fr-CA', currency: 'CAD' }, 'en-US'), { locale: 'fr-CA', currency: 'CAD' });
    assert.deepEqual(numberDefaults({ locale: null, currency: null }, 'en-US'), { locale: 'en-US', currency: undefined });
    assert.deepEqual(numberDefaults(null, 'not a locale!'), { locale: 'en-CA', currency: undefined });
    assert.deepEqual(numberDefaults(undefined, undefined), { locale: 'en-CA', currency: undefined });
});
