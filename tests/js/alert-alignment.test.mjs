import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Alert alignment, checked by source (no browser runner): the icon and the dismiss button each sit in a box one text line tall on an items-start row, so
// they line up with the first line of text in every variant, at any length and padding. The title, body and actions share the text column.
const alert = readFileSync(new URL('../../resources/features/core/components/Kit/Feedback/Alert.vue.stub', import.meta.url), 'utf8');
const variants = readFileSync(new URL('../../resources/features/core/components/Kit/Feedback/alertVariants.ts.stub', import.meta.url), 'utf8');

test('the row is items-start and the text is text-sm, whose line height the one-line boxes match', () => {
    assert.match(variants, /cva\('relative flex items-start gap-3 rounded-lg border p-4 text-sm'/);
});

test('the icon is centred in a one-line box, never offset by a margin', () => {
    assert.match(alert, /<span v-if="glyph" class="flex h-5 shrink-0 items-center" data-slot="alert-icon"><component :is="glyph" class="size-5" :class="alertIconClass\(variant, tier\)" aria-hidden="true" \/><\/span>/);
    assert.doesNotMatch(alert, /mt-0\.5/);
});

test('the dismiss button is in the row, centred in a one-line box, not pinned to the corner', () => {
    assert.match(alert, /<span v-if="canDismiss" class="-mr-4 flex h-5 shrink-0 items-center">\n\s+<button type="button" class="flex size-11 items-center justify-center[^"]*"[^>]*data-slot="alert-dismiss"/);
    assert.doesNotMatch(alert, /absolute top-0 right-0/);
    assert.doesNotMatch(alert, /pr-14/, 'no padding reserved for an absolutely placed button');
});

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
