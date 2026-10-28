import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// ActionBar's resize handling: the fit has hysteresis, so a width at the boundary cannot flip it back and forth, and the ResizeObserver callback never writes in its
// own frame (the "ResizeObserver loop completed with undelivered notifications" error). Pure logic here; the component is checked by source (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-actionbar-resize-'));
writeFileSync(join(dir, 'actionFit.ts'), read('Action/actionFit.ts.stub'));
const { fitActions, sameMeasure, ACTION_FIT_HYSTERESIS } = await import(pathToFileURL(join(dir, 'actionFit.ts')).href);

const actions = ['publish', 'preview', 'duplicate'].map(key => ({ key }));
const measure = container => ({ container, parts: [120, 100], gap: 12 }); // needs 232

test('a bar at the boundary does not oscillate: compacting shrinks nothing back into fitting', () => {
    // A full bar 1px too narrow compacts. The compact layout is narrower, but the container width is what the next measure sees: with the same width, the bar stays compact.
    let compact = fitActions(actions, measure(231), false).compact;
    assert.equal(compact, true);
    for (let frame = 0; frame < 20; frame++) {
        compact = fitActions(actions, measure(frame % 2 ? 231 : 233), compact).compact;
        assert.equal(compact, true, `frame ${frame}: a 1-2px wobble keeps it compact`);
    }
});

test('a compact bar returns to the full layout only with the hysteresis to spare', () => {
    assert.equal(fitActions(actions, measure(232), true).compact, true, 'just fitting is not enough once compact');
    assert.equal(fitActions(actions, measure(232 + ACTION_FIT_HYSTERESIS - 1), true).compact, true);
    assert.equal(fitActions(actions, measure(232 + ACTION_FIT_HYSTERESIS), true).compact, false);
    assert.equal(fitActions(actions, measure(232), false).compact, false, 'a full bar that exactly fits stays full');
    assert.equal(fitActions(actions, measure(232)).compact, false, 'wasCompact defaults to false, so other callers are unchanged');
});

test('an unchanged measure is recognised, so it is skipped', () => {
    assert.equal(sameMeasure(null, measure(200)), false);
    assert.equal(sameMeasure(measure(200), measure(200)), true);
    assert.equal(sameMeasure(measure(200), measure(201)), false);
    assert.equal(sameMeasure(measure(200), { container: 200, parts: [120, 101], gap: 12 }), false);
    assert.equal(sameMeasure(measure(200), { container: 200, parts: [120, 100], gap: 8 }), false);
});

test('ActionBar measures in an animation frame, never in the observer callback, and cleans up on unmount', () => {
    const bar = read('Action/ActionBar.vue.stub');
    assert.match(bar, /new ResizeObserver\(schedule\)/, 'the observer only schedules');
    assert.doesNotMatch(bar, /new ResizeObserver\(measure\)/);
    const schedule = bar.slice(bar.indexOf('function schedule()'), bar.indexOf('}', bar.indexOf('function schedule()')) + 1);
    assert.match(schedule, /cancelAnimationFrame\(frame\)/, 'a pending frame is replaced, not doubled');
    assert.match(schedule, /requestAnimationFrame\(measure\)/);
    assert.doesNotMatch(schedule, /measured\.value\s*=/, 'the callback writes nothing synchronously');
    assert.match(bar, /if \(!sameMeasure\(measured\.value, next\)\) measured\.value = next;/, 'an unchanged measure writes nothing');
    assert.match(bar, /fitActions\(ordered\.value, measured\.value \?\? \{ container: 0, parts: \[\] \}, wasCompact\.value\)/, 'the fit gets its previous state (hysteresis)');
    const unmount = bar.slice(bar.indexOf('onBeforeUnmount('), bar.indexOf('});', bar.indexOf('onBeforeUnmount(')));
    assert.match(unmount, /observer\?\.disconnect\(\)/);
    assert.match(unmount, /cancelAnimationFrame\(frame\)/);
});
