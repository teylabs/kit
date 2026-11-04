# Swatch picker

`SwatchPicker` from `@kit/ui` is a colour picker for people who don't think in hex codes. It shows swatches suggested by the server (colours taken from a logo, say), a short palette, and a hex field folded into a Collapsible.

```vue
<SwatchPicker
    v-model="accent"
    name="accent"
    label="Accent colour"
    :suggested="logoColours"
    suggested-label="Suggested from your logo"
    :palette="['#1F2937', '#1D4ED8', '#15803D', '#B91C1C', '#DEB100']"
    default-value="#DEB100"
>
    <template #preview="{ colour }"><ReportPreview :accent="colour" /></template>
</SwatchPicker>
```

- **The value:** `v-model` is `"#RRGGBB"` (upper case), or `''` for "no colour". An empty value means the host's default, and the swatch whose hex is `defaultValue` stands for it. Picking that swatch sets `''`.
- **Posting:** with `name`, a hidden input posts the value. A typed value that isn't a colour is posted as typed, so the save is refused with the field's error rather than quietly keeping the last pick.
- **Keyboard:** all swatches are one `role="radiogroup"` (named by `label`) of `role="radio"` buttons. There is one tab stop, on the picked swatch. The arrow keys move and pick, wrapping at the ends, and Home and End go to the first and last. A suggested colour is not repeated in the palette.
- **Legibility:** the check mark is black or white, whichever contrasts more with the swatch. A colour whose WCAG contrast against `background` (default `#FFFFFF`) is under `minContrast` (default 3) shows `contrastHint`.
- **Helpers:** `contrastRatio`, `luminance`, `markColour` and `normaliseHex` are exported from `@kit/ui` for the server-side twin of the same checks.

Tests: `tests/js/swatch-picker.test.mjs` covers the colour maths and the markup. The browser job (`?swatch`) drives the keys and the hex field, and runs axe in light and dark.
