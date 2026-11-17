import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Each typed date or time segment (reka-ui's role="spinbutton" part) is a WCAG 2.2 target: at least 24×24 CSS px, its text centred. The browser job
// (?date-targets) measures the boxes with axe; this keeps every segmented stub on the class that sets them.
const form = new URL('../../resources/js/components/Kit/Form/', import.meta.url);

for (const stub of ['InputDate', 'InputDateRange', 'InputTime', 'InputTimeRange']) {
    test(`${stub}: its segments are at least 24×24 with centred text`, () => {
        const source = readFileSync(new URL(`${stub}.vue`, form), 'utf8');
        const segment = source.match(/const segment =\s*'([^']+)'/);
        assert.ok(segment, `${stub} declares its segment classes`);
        const classes = segment[1].split(/\s+/);
        for (const name of ['inline-flex', 'min-h-6', 'min-w-6', 'items-center', 'justify-center']) assert.ok(classes.includes(name), `${stub} segment has ${name}`);
        assert.ok(source.includes(':class="segment"'), `${stub} puts the classes on its segments`);
    });
}

// Every literal ("/", "-", ":") sits the same distance from the glyphs either side: each segment pads its text symmetrically (px-2, so a one-digit
// segment already fills its 24px and centring adds no uneven slack) and each literal tucks in by the same -mx-0.5. The browser job (?date-clear) measures
// the gaps in en-US, fr-CA and en-GB.
for (const stub of ['InputDate', 'InputDateRange', 'InputTime', 'InputTimeRange']) {
    test(`${stub}: its literals are evenly spaced`, () => {
        const source = readFileSync(new URL(`${stub}.vue`, form), 'utf8');
        assert.ok(source.match(/const segment =\s*'([^']+)'/)[1].split(/\s+/).includes('px-2'), `${stub} segment pads its text with px-2`);
        const literals = source.match(/item\.part === 'literal'"[^>]*?class="([^"]+)"/gs) ?? [];
        assert.ok(literals.length >= 1, `${stub} renders its literals`);
        for (const literal of literals) assert.match(literal, /class="[^"]*-mx-0\.5[^"]*"/, `${stub} literal tucks in with -mx-0.5`);
    });
}
