import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// AddressFieldset, RegionSelect and AddressText: the region table, postal rules, labels, messages and display lines, from the stub (types stripped by node).
const dir = mkdtempSync(join(tmpdir(), 'kit-address-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/address.ts.stub', import.meta.url), join(dir, 'address.ts'));
const { addressAutocomplete, addressLabels, addressLines, addressMessages, countryName, normalizePostal, postalLooksValid, regionName, regionOptions, regions } = await import(pathToFileURL(join(dir, 'address.ts')).href);
const source = name => readFileSync(new URL(`../../resources/features/core/components/Kit/Form/${name}.vue.stub`, import.meta.url), 'utf8');

test('the region table: 13 Canadian provinces and territories and the 50 states plus DC, unique codes, French names for a French locale', () => {
    assert.equal(regions.CA.length, 13);
    assert.equal(regions.US.length, 51);
    for (const list of Object.values(regions)) {
        assert.equal(new Set(list.map(r => r.code)).size, list.length);
        for (const r of list) assert.match(r.code, /^[A-Z]{2}$/);
    }
    assert.equal(regionName('CA', 'QC', 'fr-CA'), 'Québec');
    assert.equal(regionName('CA', 'QC', 'en-CA'), 'Quebec');
    assert.equal(regionName('US', 'NC', 'fr-CA'), 'Caroline du Nord');
    assert.equal(regionOptions('CA', 'fr-CA')[0].label, 'Alberta');
    assert.equal(regionOptions('CA', 'fr-CA').find(o => o.value === 'PE').label, 'Île-du-Prince-Édouard');
    // Any other country: free text, and an unknown code shows as itself.
    assert.equal(regionOptions('FR'), null);
    assert.equal(regionName('FR', 'Bretagne'), 'Bretagne');
});

test('postal codes: Canada written A1A 1A1 on blur, CA and US hints, other countries never second-guessed', () => {
    assert.equal(normalizePostal('CA', ' k1a0b1 '), 'K1A 0B1');
    assert.equal(normalizePostal('CA', 'k1a-0b1'), 'K1A 0B1');
    assert.equal(normalizePostal('CA', 'nope'), 'NOPE');
    assert.equal(normalizePostal('US', ' 12345 '), '12345');
    assert.ok(postalLooksValid('CA', 'k1a 0b1'));
    assert.ok(!postalLooksValid('CA', '12345'));
    assert.ok(postalLooksValid('US', '12345-6789'));
    assert.ok(!postalLooksValid('US', 'K1A 0B1'));
    assert.ok(postalLooksValid('GB', 'SW1A 1AA'));
    assert.ok(postalLooksValid('CA', ''));
});

test('labels follow the country, messages layer the shared kitUi under the caller\'s, and autocomplete tokens are the standard ones', () => {
    const m = addressMessages({ city: 'Ville', region_ca: 'Province ou territoire', bogus: 1 }, { city: 'Municipalité' });
    assert.equal(m.city, 'Municipalité');
    assert.deepEqual(addressLabels('CA', m), { region: 'Province ou territoire', postal_code: 'Postal code', postal_hint: 'Canadian postal codes look like A1A 1A1.' });
    assert.deepEqual(addressLabels('US', addressMessages(null)), { region: 'State', postal_code: 'ZIP code', postal_hint: 'ZIP codes look like 12345 or 12345-6789.' });
    assert.deepEqual(addressLabels('FR', addressMessages(null)), { region: 'Region', postal_code: 'Postal code', postal_hint: null });
    assert.deepEqual(addressAutocomplete, { line1: 'address-line1', line2: 'address-line2', city: 'address-level2', region: 'address-level1', postal_code: 'postal-code', country: 'country' });
    // Every message key has a default in the kit's lang file.
    const lang = readFileSync(new URL('../../lang/en/ui.php', import.meta.url), 'utf8');
    for (const key of Object.keys(addressMessages(null))) assert.match(lang, new RegExp(`'${key}' =>`), key);
});

test('display lines drop empty parts and name the region and country in the locale', () => {
    const address = { line1: '24 Sussex Dr', line2: '', city: 'Ottawa', region: 'ON', postal_code: 'K1M 1M4', country: 'CA' };
    assert.deepEqual(addressLines(address, 'en-CA'), ['24 Sussex Dr', 'Ottawa, Ontario K1M 1M4', 'Canada']);
    assert.deepEqual(addressLines(address, 'en-CA', false), ['24 Sussex Dr', 'Ottawa, Ontario K1M 1M4']);
    assert.equal(addressLines({ city: 'Québec', region: 'QC', country: 'CA' }, 'fr-CA')[0], 'Québec, Québec');
    assert.equal(countryName('US', 'fr-CA'), 'États-Unis');
    assert.deepEqual(addressLines({}), []);
});

test('the components: bracketed names and dotted errors, a fixed country hidden, no map provider by default', () => {
    const fieldset = source('AddressFieldset');
    assert.match(fieldset, /`\$\{props\.name\}\[\$\{part\}\]`/);
    assert.match(fieldset, /errors\?\.\[`\$\{props\.name\}\.\$\{part\}`\]/);
    assert.match(fieldset, /<Field v-if="!props\.country"/);
    assert.match(fieldset, /<FormFieldset /);
    const text = source('AddressText');
    assert.match(text, /mapUrl: null/);
    assert.doesNotMatch(text, /google|openstreetmap|bing|apple\.com/i);
    assert.match(text, /<address /);
});
