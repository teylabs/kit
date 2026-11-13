import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The form frame: FormCard's own <form> for a useSubmitForm, the Workbench's `:form` (Save, ⌘S, summary, leave guard, 409 conflict), the keymap's `save`, and the
// shared formFrame composables. The keymap runs here; the components are checked by source, and in a real browser by browser/run.mjs (?form-submit,
// ?workbench-form).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-workbench-form-'));
writeFileSync(join(dir, 'workbench.ts'), read('Workbench/workbench.ts'));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);
const workbench = read('Workbench/Workbench.vue');
const card = read('Form/FormCard.vue');
const frame = read('Form/formFrame.ts');

test('⌘S / Ctrl+S is `save` only with a form; never with Shift (a rich-text strikethrough) or Alt; even while typing', () => {
    const cmd = (event, context = {}) => wb.workbenchCommand({ key: 's', ...event }, { inNavigator: false, typing: true, save: true, ...context });
    assert.equal(cmd({ metaKey: true }), 'save');
    assert.equal(cmd({ ctrlKey: true, key: 'S' }), 'save');
    assert.equal(cmd({ metaKey: true }, { save: false }), null, 'no :form, no binding: the browser keeps ⌘S');
    assert.equal(cmd({ metaKey: true }, { save: undefined }), null);
    assert.equal(cmd({ metaKey: true, shiftKey: true }), null);
    assert.equal(cmd({ metaKey: true, altKey: true }), null);
    assert.equal(cmd({}), null, 'a plain s is typing');
    assert.equal(wb.enabledCommand(false, { key: 's', metaKey: true }, { inNavigator: false, typing: false, save: true }), null, 'keys off: the transport owns ⌘S too');
});

test('formFrame: one leave guard, from the outermost frame that is guarding', () => {
    assert.match(frame, /const outer = inject\(GUARDED, null\);\s*const active = \(\) => !outer\?\.\(\) && enabled\(\);\s*provide\(GUARDED, active\);/);
});

test('editedSaveState: a failed save ("Not saved") is "Unsaved changes" again after an edit that leaves the form dirty; nothing else moves', () => {
    assert.equal(wb.SAVE_LABELS.error, 'Not saved');
    assert.equal(wb.editedSaveState('error', true), 'unsaved');
    assert.equal(wb.editedSaveState('error', false), 'error', 'an edit back to the saved values is not unsaved changes');
    for (const state of ['idle', 'saving', 'saved', 'unsaved', 'conflict']) assert.equal(wb.editedSaveState(state, true), state, `${state} is kept`);
});

// The Workbench :form and FormCard behaviour (submit, ⌘S, the 422 summary, the 409 conflict, the leave guard, the submit gate) is checked in a browser
// (browser/run.mjs, ?workbench-form and ?form-submit). Not covered there: ⌘S ignored while another modal is open.
test('FormCard and Workbench render no v-html: a form message (`_form`) is text', () => {
    assert.doesNotMatch(card + workbench, /v-html/);
});
