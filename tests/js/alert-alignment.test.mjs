import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Alert alignment, checked by source (no browser runner): the icon and the dismiss button each sit in a box one text line tall on an items-start row, so
// they line up with the first line of text in every variant, at any length and padding. The title, body and actions share the text column.
const alert = readFileSync(new URL('../../resources/js/components/Kit/Feedback/Alert.vue', import.meta.url), 'utf8');
const variants = readFileSync(new URL('../../resources/js/components/Kit/Feedback/alertVariants.ts', import.meta.url), 'utf8');

test('the title, body and actions share one text column beside the icon', () => {
    const column = alert.match(/<div class="min-w-0 flex-1 space-y-1">([\s\S]*?)\n {8}<\/div>/);
    assert.ok(column, 'one text column');
    for (const slot of ['alert-title', 'alert-body', 'alert-actions']) assert.ok(column[1].includes(`data-slot="${slot}"`), `${slot} is in the text column`);
    assert.ok(alert.indexOf('data-slot="alert-icon"') < alert.indexOf('<div class="min-w-0 flex-1 space-y-1">'), 'the icon comes before the column');
    assert.ok(alert.indexOf('data-slot="alert-dismiss"') > alert.indexOf('data-slot="alert-actions"'), 'the dismiss button comes after it');
});

test('the alert holds its own height in a host\'s stretching grid or flex row', () => {
    // A grid row or flex row stretches its items by default; an alert beside a taller one would otherwise grow and leave space below its text.
    assert.match(alert, /:class="\[alertVariants\(\{ variant, tier \}\), 'self-start'\]"/);
});
