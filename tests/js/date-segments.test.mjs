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
