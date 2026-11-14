# Read-only text: the Text family

The Text family (from `@kit/ui`) shows text on a page that nobody edits: running prose, raw code or output, a plain table, a long body with Show more, a title cut to fit, a value to copy, a number, an address, chips and tags. Every name starts with `Text`, so typing `Text` lists them, apart from `CopyField`, the copy beside a labelled field. They use the kit's tokens (light and dark), need no typography plugin and no number or clipboard library, and render on the server without touching the DOM.

```vue
<script setup lang="ts">
import { CopyField, TextAddress, TextChips, TextCode, TextCopy, TextLong, TextNumeric, TextProse, TextTags, TextTruncate } from '@kit/ui';
</script>

<template>
    <TextTruncate :text="report.title" />
    <TextProse :text="report.summary" />
    <TextLong :text="note.body" :limit="280" />
    <TextCode :content="delivery.rawBody" title="Message source" card max-height="lg" />
    <p>Reference <TextCopy :value="invoice.number" /></p>
    <CopyField label="Webhook URL" :value="webhook.url" />
    <TextNumeric :value="report.rows" /> rows
    <TextNumeric :value="balance" :decimals="2" negative="parentheses" />
    <TextAddress :address="client.mailing" />
    <TextChips :items="[{ label: 'Plan', value: 'Pro', tone: 'info' }, 'Overdue']" label="Details" />
    <TextTags :items="report.tags" label="Tags" />
</template>
```

| Component | What it shows |
| --- | --- |
| `TextProse` | Running text. `text` is plain text with its line breaks kept; the default slot takes markup the app renders itself (paragraphs, lists, headings, links, quotes, code, tables), styled from tokens with no outer margin. `size` sm (default), base or lg. It never renders HTML from a string. |
| `TextCode` | Raw text in a monospaced `<pre>`, exactly as written: code, JSON, a log, an email's source. See [below](#textcode). |
| `TextTable` | A plain `<table>` the app writes, styled for reading: full width, rules between rows, padded cells. It scrolls sideways in its own box, which takes focus, and is a region named by `label`. For sorting or selection, use `DataTable`. |
| `TextLong` | A long plain-text body cut to `limit` characters (default 360) with a Show more button after it. See [below](#textlong). |
| `TextTruncate` | Text cut at its end: one line with an ellipsis, or `lines` 2 to 6 clamped. See [below](#texttruncate). For a file name, path or URL, use [`MiddleTruncate`](middle-truncate.md). |
| `TextCopy` | The copy primitive: inline text that copies its `value` when pressed, or (`button`, `icon`) a copy button. See [Copy](#copy). |
| `CopyField` | A value to copy in a labelled read-only field, with a Copy button beside it. See [Copy](#copy). |
| `TextNumeric` | A plain number in the locale's grouping and decimal separator, in tabular figures. See [below](#textnumeric). For money with a currency or a percentage, use [`Amount`](money-percent.md). |
| `TextAddress` | A postal address in an `<address>` element, one line per row. See [below](#textaddress). |
| `TextChips` | A list of kit `Badge`s, each a label or a label with its value (`Plan: Pro`), each with its own `tone`. |
| `TextTags` | A list of short labels as neutral `Badge`s (`tone` for all). To edit tags, use `InputTags`. |

`TextChips` and `TextTags` are real lists (`<ul>`), named by `label`, so a screen reader counts them. Blank items are dropped, a repeated one is kept, and nothing renders without an item. The words carry the meaning, never the tone alone.

## TextCode

```vue
<TextCode :content="payload" />
<TextCode :content="log" title="Import log" toolbar :wrap="false" />
<TextCode :content="message.source" title="Message source" card :level="2" max-height="lg" />
```

- **Kept as written.** Pass `content` (a string or number), or the default slot. It is text: `<b>` in it is shown, never parsed, and whitespace is kept byte for byte.
- **Bounded and scrolling.** `max-height` is `sm` (12rem), `md` (20rem, the default), `lg` (32rem) or `none`. It scrolls inside, as a region named by `title` (else "Code") that takes focus, so a keyboard scrolls it with the arrow keys, Page Down and End. It never widens the column it sits in.
- **Wrapping.** Long lines wrap by default; `:wrap="false"` scrolls them sideways.
- **Card and toolbar.** `card` frames it in a kit `Card` with `title` as its heading (at `level`, 3 by default) and a toolbar: Copy, which copies the whole text, also the part scrolled out of view, and Wrap, a toggle with `aria-pressed`. `toolbar` shows the same toolbar without the card. Copy takes slot text from the rendered `<pre>`, kept current on each update and by a `MutationObserver` (a child that writes its own DOM, such as a highlighter, included), so a slot that changes while a copy is in flight makes that copy claim nothing, and the next press copies what is shown. An email body, a webhook payload and a job's raw output are this, with `card`.

## TextLong

- The cut counts characters as a reader does: an emoji sequence or an accented letter is one, never split. Trailing space before the cut is dropped and `…` added. Text no longer than `limit` is shown whole, with no button.
- The button follows the text, says Show more or Show less (`more-label`, `less-label`), and carries `aria-expanded` and `aria-controls`. `v-model:expanded` starts it open or reads it. `:collapsible="false"` always shows the whole text.
- Line breaks are kept (`white-space: pre-wrap`). It is plain text, never HTML.

## TextTruncate

```vue
<TextTruncate :text="row.title" />
<TextTruncate :text="card.summary" :lines="3" />
<TextTruncate max-width="16rem">{{ person.name }} · {{ person.email }}</TextTruncate>
```

- `lines` 1 (the default) is one line with an end ellipsis; 2 to 6 clamp to that many lines. `max-width` (a CSS length) bounds it; otherwise it takes the width it is given, so a flex child needs `min-w-0`.
- The whole text stays in the element, so its accessible name, a selection and a copy are all of it. Once mounted it measures whether the end was cut, with the same content-box measure as `MiddleTruncate` (`contentWidth`), and only then puts the whole text in `title`, verbatim (leading and trailing whitespace kept), and marks `data-truncated`. It measures again when its box resizes.
- On the server it is the CSS truncation alone: no title, observer or timer.

## Copy

One helper writes to the clipboard for every kit copy: `copyText(text)` uses the async Clipboard API, and where the browser has none (a page served over plain http, an older browser) a hidden textarea and `execCommand('copy')`, putting back the focus and selection it moved. A write the browser refuses is not retried another way. `useCopy(() => value)` wraps it with the shared state (`idle`, `copying`, `copied`, `failed`): copied only once the write resolved, and one write at a time: a press while one is in flight is ignored, also after the value changed, so writes never overlap. A changed value goes back to `idle` once no write is in flight, and a write that settles after the change claims nothing about the new value. `TextCopy`, `CopyField`, `CopyButton` and `TextCode`'s Copy all use it.

- **`TextCopy`** inline is a real button styled as text with a dotted underline: Enter and Space copy too. It shows the default slot, else `value`, so its accessible name is what it shows; `tooltip` ("Copy") is its title. Once copied it shows a check and a polite status says "Copied." once.
- **`TextCopy button`** is a labelled kit `Button` (`label`, `copied-label`, `variant` secondary or ghost); `icon` a small icon button named by `label`. A refused or missing clipboard says so and shows the value in a read-only input, selected on focus, to copy by hand. **`CopyButton`** is this, under its own name and with the same props.
- **`CopyField`** is a `Field` with `label` and `description` around a read-only input (`multiline`: a textarea) in a monospaced face, which selects its whole value on focus, and a Copy button named "Copy {label}". A polite status says "Copied {label}." On a refusal it says so and focuses the field with the value selected.

## TextNumeric

- The locale's grouping and decimal (`1,234,567`; fr-CA `1 234,50`) from the browser's `Intl`, in tabular figures. The locale is `locale`, else the host's `kitNumber.locale`, else the browser's, else en-CA, as `Amount` resolves it.
- `decimals` fixes the places (half rounds away from zero); unset keeps up to three, so float noise is not shown. `notation="compact"` writes `1.2K`. A numeric string is formatted as written, so a value past 2^53 keeps every digit.
- `negative="parentheses"` is the accounting form, `(1,234.50)`: the parentheses are visual and a screen reader hears "negative". What rounds to zero is never negative. A locale's own digits (Arabic-Indic, Persian, Devanagari) and the bidi mark it writes before its minus are kept. A negative carries `data-negative` for the app's own colour.
- `prefix` and `suffix` (or the `prefix` and `suffix` slots) sit outside the number.
- A value that is not a number (null, empty, `"n/a"`, `"1,234"` with its grouping) renders nothing, never `NaN`.

## TextAddress

```vue
<TextAddress :address="client.mailing" hide-country />
<TextAddress :lines="order.shipping_lines" size="base" />
<TextAddress lines="200 Sample Ave, Suite 3, Springfield IL 62701" />
```

- From `address`, the kit's `Address` ([address.md](address.md)): street, unit, "City, Region Postal" and the country, empty parts dropped, the region named in the locale's language (`Québec` in fr-CA). `hide-country` drops the country line; `map-url` adds a map link of the app's choosing.
- From `lines`: lines already written, an array (empties dropped) or a string split on line breaks, else on commas. With neither, the default slot.
- `size` sm, base or lg; unset, it takes the surrounding text's. It renders nothing when there is nothing to show.

## Renamed and merged

Each old name is still exported, for one release, and is marked `@deprecated` in the barrel:

| Was | Now | Kept as |
| --- | --- | --- |
| `ProseCode` | `TextCode` | `ProseCode` (the same component) |
| `ProseTable` | `TextTable` | `ProseTable` (the same component) |
| `LongText` | `TextLong` | `LongText` (the same component) |
| `ClickToCopy` | `TextCopy` | `ClickToCopy` (the same component) |
| `RawOutputCard` | `TextCode card` | `RawOutputCard` (renders `<TextCode card>`) |
| `AddressText` | `TextAddress` | `AddressText` (the same component in the barrel; `Form/AddressText.vue` renders it for path imports) |
| `CopyButton` | `TextCopy button` | `CopyButton` (renders it, same props and markup) |
| `TextDollars` | `TextNumeric :decimals="2" prefix="$"`, or `Amount` for money | `TextDollars` (renders `TextNumeric`) |

Props changed on the way in: `LongText`'s `max-chars` is `limit`; `ClickToCopy`'s `mime` is gone (it copies text); `TextNumeric`'s `format` pattern string (`0,0.00`) is `decimals`, `notation` and `negative`; `ProseCode`'s and `RawOutputCard`'s `content` keep their name.

Tests: `tests/js/text.test.mjs` (`composer test:js`) covers `copyText` with and without the Clipboard API and on a refusal, `useCopy`'s states, number formats and negatives, address lines, the long-text cut on graphemes, badge items, and each component's markup contract. The browser job (`?text`) copies by pointer and keyboard and through the fallback, reads a refused copy, truncates, clamps and resizes, expands long text, scrolls and unwraps a code card and copies all of it, reads number and address formats, runs axe in light and dark, and renders the family under Vue's server renderer.
