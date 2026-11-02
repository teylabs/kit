# Addresses

`<AddressFieldset>` takes a postal address, `<RegionSelect>` picks a province, territory or state, and `<AddressText>` shows an address. They're part of core and exported from the kit barrel. There are no new dependencies: the region names ship as a small table in the kit, and country names come from the browser's `Intl.DisplayNames`.

```vue
<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { AddressFieldset, AddressText, Button, FormCard, type Address } from '@kit/ui';

const address = ref<Address>({ country: 'CA' });
</script>

<template>
    <Form action="/clients/1" method="put">
        <FormCard>
            <AddressFieldset v-model="address" name="mailing" title="Mailing address" required />
        </FormCard>
        <Button type="submit">Save</Button>
    </Form>
    <AddressText :address="client.mailing" :map-url="(a) => `https://maps.example.test/?q=${encodeURIComponent(a.line1 ?? '')}`" />
</template>
```

- **What is submitted.** `<name>[line1]`, `<name>[line2]`, `<name>[city]`, `<name>[region]`, `<name>[postal_code]` and `<name>[country]` (`name` defaults to `address`). Errors are read from the form as `<name>.<part>`, which is how Laravel reports `mailing.city`.
- **The group.** It's a `FormFieldset`, so `title` is the legend that names the group, with `description` and `level` as there. Each field has a label and its standard `autocomplete` token: `address-line1`, `address-line2`, `address-level2`, `address-level1` and `postal-code`. The region and country pickers are the kit's `InputSelect`, a combobox, so browser autofill fills the text fields and not those two.
- **Countries.** `countries` lists the choices, `['CA', 'US']` by default, named in the locale. A fixed `country` hides the country field and submits it as hidden. Changing the country clears the region.
- **Labels follow the country.** Canada shows "Province or territory" and "Postal code", the US "State" and "ZIP code", and anywhere else "Region" and "Postal code". Only the label text changes. Nothing is announced.
- **Regions.** For Canada and the US the region is a searchable select of ISO 3166-2 codes without the prefix (`ON`, `QC`, `NY`, `DC`), named in French when the locale is French ("Québec", "Caroline du Nord"). Any other country takes the region as free text. A stored code that isn't in the list is kept and shown as itself.
- **Postal codes.** A Canadian postal code is written `A1A 1A1` in capitals on blur. A code that doesn't look right for Canada or the US shows a hint under the field, linked with `aria-describedby`. It never blocks the submit, because the server validates. Other countries are never second-guessed.
- **Locale.** `locale`, else the app locale (`kitNumber.locale` when the host shares it), else the browser's language.
- **Strings.** The labels, hints and placeholders are the `address.*` keys in the kit's `ui` lang file, shared as `kitUi`. Override them for the app in `lang/vendor/kit/{locale}/ui.php`, or per use with `messages`.
- **`<AddressText>`.** It renders an `<address>` element with the street, unit, "City, Region Postal" and the country, dropping empty parts. `hideCountry` drops the country line. A map link appears only when you pass `mapUrl`, a function from the address to a URL of your choosing. The kit picks no map provider and makes no outside request.

`composer test:js` (`tests/js/address.test.mjs`) covers the region table, the postal rules, the labels, the messages and the display lines. The browser run (`browser/run.mjs`, `?address`) covers axe, the group name, the labels when the country changes, and the postal hint. The adoption inventory's `form.address` surface doesn't count `EmailAddress` or `IpAddress` components as postal addresses.
