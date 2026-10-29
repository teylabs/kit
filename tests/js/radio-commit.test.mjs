import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// RadioCardGroup commit="click": the controller the component runs is driven here without Vue (arrows move focus, a press commits once); the markup and aria are
// checked by source (no browser runner). Enter and Space reach `press` as the button's own click, as a tap does.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-radio-commit-'));
writeFileSync(join(dir, 'radioCommit.ts'), read('Form/radioCommit.ts.stub'));
const { createCommitter, moveFocus, commitChoice } = await import(pathToFileURL(join(dir, 'radioCommit.ts')).href);

function poll(options = [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }, { value: 'c', label: 'C' }], start = null) {
    const state = { value: start, focused: 0, commits: [] };
    const committer = createCommitter({
        options: () => options,
        disabled: () => false,
        value: () => state.value,
        commit: (value, label) => { state.value = value; state.commits.push([value, label]); },
        focus: index => { state.focused = index; },
    });
    return { state, committer };
}

test('arrowing from A to C moves focus and emits nothing', () => {
    const { state, committer } = poll();
    assert.equal(committer.key('ArrowDown', 0), true);
    assert.equal(committer.key('ArrowDown', state.focused), true);
    assert.equal(state.focused, 2, 'focus is on C');
    assert.deepEqual(state.commits, [], 'no commit while arrowing');
    assert.equal(state.value, null, 'nothing is selected');
});

test('Enter on C (the button\'s click) commits once', () => {
    const { state, committer } = poll();
    committer.key('End', 0);
    committer.press(state.focused);
    assert.deepEqual(state.commits, [['c', 'C']]);
    assert.equal(state.value, 'c');
});

test('a tap commits once, and pressing the chosen option again emits nothing', () => {
    const { state, committer } = poll();
    committer.press(1);
    assert.deepEqual(state.commits, [['b', 'B']]);
    committer.press(1);
    assert.deepEqual(state.commits, [['b', 'B']], 're-selecting the current option emits nothing');
    committer.press(0);
    assert.deepEqual(state.commits.map(([value]) => value), ['b', 'a'], 'a different option commits again');
});

test('focus skips disabled options and wraps; other keys are left to the button', () => {
    const options = [{ value: 'a' }, { value: 'b', disabled: true }, { value: 'c' }];
    assert.equal(moveFocus('ArrowDown', 0, options), 2);
    assert.equal(moveFocus('ArrowDown', 2, options), 0);
    assert.equal(moveFocus('ArrowUp', 0, options), 2);
    assert.equal(moveFocus('Home', 2, options), 0);
    assert.equal(moveFocus('End', 0, options), 2);
    assert.equal(moveFocus('Enter', 0, options), null);
    assert.equal(moveFocus(' ', 0, options), null);
    assert.equal(commitChoice(null, { value: 'b', disabled: true }), null, 'a disabled option commits nothing');
    assert.equal(commitChoice(null, { value: 'a' }, true), null, 'a disabled group commits nothing');
});

test('the component: role="radio" buttons with a roving tabindex in click mode, a polite announcement, the native group by default', () => {
    const source = read('Form/RadioCardGroup.vue.stub');
    assert.match(source, /commit\?: 'change' \| 'click'/);
    assert.match(source, /commit: 'change'/, 'change (the native, arrow-selects group) is the default');
    assert.match(source, /:is="clickMode \? 'button' : 'label'"/);
    assert.match(source, /:role="clickMode \? 'radio' : undefined"/);
    assert.match(source, /:aria-checked="clickMode \? \(model === option\.value \? 'true' : 'false'\) : undefined"/);
    assert.match(source, /:tabindex="clickMode \? \(index === focused \? 0 : -1\) : undefined"/);
    assert.match(source, /@click="clickMode && committer\.press\(index\)"/, 'Space, Enter, a click and a tap commit through the button\'s click');
    assert.match(source, /@keydown="clickMode && move\(\$event, index\)"/, 'keys only move focus');
    assert.match(source, /emit\('commit', value\);/);
    assert.match(source, /<p role="status" class="sr-only" data-radio-card-announce>\{\{ announced \}\}<\/p>/, 'the committed choice is announced politely');
    assert.match(source, /<input type="hidden" :name="groupName" :value="model \?\? ''" data-radio-card-value \/>/, 'it still submits with a form');
    assert.match(source, /<input v-if="!clickMode" :id="optionId\(index\)" v-model="model" type="radio"/, 'change mode keeps the native radios');
    assert.match(source, /deviates from the APG radio group on purpose/, 'the deviation is documented where it is made');
});

test('each card carries its option value as data-value, for hosts and tests to find it', () => {
    const group = read('Form/RadioCardGroup.vue.stub').replace(/<!--[\s\S]*?-->/g, '');
    assert.match(group, /:data-value="String\(option\.value\)"/);
});
