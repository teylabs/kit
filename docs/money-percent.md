# Money and percent

`<InputNumber>` takes money and percentages as exact strings, and `<Amount>` shows the same strings back. Both are part of core and exported from the kit barrel. Neither needs a library: the locale's grouping, decimal separator and symbol placement come from the browser's `Intl`.

```vue
<script setup lang="ts">
import { Amount, InputNumber } from '@kit/ui';
</script>

<template>
    <InputNumber name="price" label="Price" kind="money" currency="CAD" />
    <InputNumber name="discount" label="Discount" kind="percent" :scale="1" />
    <Amount :value="invoice.total" kind="money" currency="CAD" negative="parentheses" />
    <Amount :value="invoice.tax_rate" kind="percent" />
</template>
```

- **What is submitted.** A hidden control carries the canonical string. Money is in integer minor units of the currency (`"123450"` is $1,234.50 in CAD; `"1234"` is ¥1,234). A percentage is in **percentage points** at a fixed `scale` (default 2): `"12.50"` is 12.5 %. It is never a fraction, so typing `0.125` means 0.125 %, not 12.5 %. A value with more places than the scale is refused, never rounded.
- **Currency.** Pass `currency` (ISO 4217), or set `kit.currency` (`KIT_CURRENCY`) for the whole app; the kit shares it with every page as `kitNumber`. With neither, money shows no symbol and uses two decimals, and in development the input warns. The kit never guesses a currency.
- **Locale.** `locale`, else the app locale (shared as `kitNumber.locale`), else the browser's language, else en-CA. Typing accepts the locale's decimal separator and `.`. Grouping is applied on blur, never while typing, so the caret doesn't jump. `fr-CA` reads `1 234,56` and puts the symbol after the number.
- **The symbol.** It sits outside the editable text, before or after the number as the locale places it (`$` before in en-CA, `$` after in fr-CA, `%` after). It's decoration: the field's label names the unit for assistive technology ("Price, CAD", "Discount, percent"). A `unit` or `suffix` of your own replaces both.
- **Negatives.** Allowed with `signed`. `negative="parentheses"` shows the accounting form after blur, `(1,234.50)`, and parentheses are accepted when typed or pasted. `Amount` keeps the parentheses visual and says "negative" to screen readers.
- **Amount.** It renders the canonical string with the locale's symbol in tabular figures (`tabular-nums`), so a column of amounts lines up. Empty or null renders nothing. Other attributes (`class`, `data-*`) pass to the span.

`composer test:js` (`tests/js/number.test.mjs`) covers the codec: percentage points, refusing `0.125` at scale 2, locale affixes, accounting negatives, no guessed currency and the `kitNumber` defaults. The adoption inventory's `form.money-percent` surface counts an `InputNumber` only when its `kind` is literally `money` or `percent`, so plain number inputs aren't evidence.
