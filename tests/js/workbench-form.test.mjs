import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The form frame: FormCard's own <form> for a useSubmitForm, the Workbench's `:form` (Save, ⌘S, summary, leave guard, 409 conflict), the keymap's `save`, and the
// shared formFrame composables. The keymap runs here; the components are checked by source, and in a real browser by browser/run.mjs (?form-submit,
// ?workbench-form).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-workbench-form-'));
writeFileSync(join(dir, 'workbench.ts'), read('Workbench/workbench.ts.stub'));
const wb = await import(pathToFileURL(join(dir, 'workbench.ts')).href);
const workbench = read('Workbench/Workbench.vue.stub');
const card = read('Form/FormCard.vue.stub');
const frame = read('Form/formFrame.ts.stub');

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

test('Workbench: one <form novalidate> around the grid only with :form, and no wrapper without it', () => {
    assert.match(workbench, /const FormFrame: FunctionalComponent<\{ enabled: boolean \}> = \(frameProps, \{ slots \}\) => \(frameProps\.enabled\s*\? h\('form', \{ id: formId, novalidate: true, class: 'contents', 'data-workbench-form': '', onSubmit: \(event: Event\) => \{ event\.preventDefault\(\); void runSave\(\); \} \}, slots\.default\?\.\(\)\)\s*: slots\.default\?\.\(\)\);/);
    assert.match(workbench, /<template>\s*<FormFrame :enabled="hasForm">\s*<div ref="root" v-bind="\$attrs" role="group"/);
    assert.match(workbench, /defineOptions\(\{ inheritAttrs: false \}\);/, 'the host\'s class and data-* still land on the grid, never on the optional form');
    assert.doesNotMatch(workbench, /provideFormSubmit/, 'kit Buttons inside stay type=button: only Save submits');
});

test('Workbench: Save is the bar\'s primary submit (form=id), disabled while processing; the app\'s actions go secondary', () => {
    assert.match(workbench, /<template v-if="hasForm" #save>\s*<Button type="submit" :form="formId" variant="primary" :disabled="frame\.processing\.value"/);
    assert.match(workbench, /<WorkbenchActions [^>]*:primary="!hasForm"/);
    assert.match(read('Workbench/WorkbenchActions.vue.stub'), /<HeaderActions [^>]*:primary="primary"[^>]*\/>\s*<slot name="save" \/>/);
});

test('Workbench: a useSubmitForm submits itself; a useForm submits to `save` with the 409 read through onHttpException + errorJson; @submit replaces both', () => {
    assert.match(workbench, /if \(!form \|\| form\.processing\) return;\s*if \(props\.onSubmit\) \{ await props\.onSubmit\(\); return; \}\s*if \(isSubmitForm\(form\)\) \{\s*const ok = await form\.submit\(\);\s*settle\(ok, form\.conflict\);/);
    assert.match(workbench, /form\.submit\(props\.save\.method\.toLowerCase\(\) as Method, props\.save\.url, \{/);
    assert.match(workbench, /onHttpException: \(response[^)]*\) => \{\s*const \{ status, body \} = errorJson\(\{ response \}\);\s*settle\(false, isConflict\(status, \(body as \{ error\?: unknown \} \| null\)\?\.error\)\);\s*return false;/);
    assert.match(workbench, /function settle\(ok: boolean, conflict: boolean\): void \{\s*if \(ok\) wb\.markSaved\(\);\s*else if \(conflict\) wb\.markConflict\(\);\s*else \{\s*wb\.markFailed\(\);\s*if \(props\.form\?\.hasErrors\) void revealFirstInvalid\(\);/);
    assert.doesNotMatch(workbench, /form\.reset|resetAndClearErrors/, 'a conflict keeps the edits');
});

test('Workbench: ⌘S requests the form\'s submit, ignored when already handled, while processing, or while another modal is open', () => {
    assert.match(workbench, /if \(command === 'save'\) \{ requestSave\(event\); return; \}/);
    assert.match(workbench, /if \(event\.defaultPrevented \|\| !form\) return;\s*event\.preventDefault\(\);\s*if \(frame\.processing\.value \|\| document\.querySelector\('dialog\[open\]:not\(\[data-workbench-drawer\]\), \[role="dialog"\]\[aria-modal="true"\]:not\(\[data-workbench-drawer\]\)'\)\) return;\s*form\.requestSubmit\(/);
    assert.match(workbench, /\['\[', '\]', '=', '\+', '-', '_', '0', 's', 'S'\]\.includes\(event\.key\)/, 'from focus outside the Workbench too');
    assert.match(workbench, /save: hasForm\.value \}\);/);
});

test('Workbench: the summary focuses the first invalid field after revealing its pane; the leave guard and status are the shared frame\'s', () => {
    assert.match(workbench, /data-workbench-form-summary @click="revealFirstInvalid"/);
    assert.match(workbench, /if \(wb\.mode === 'tabs' && region && wb\.tab !== region\) wb\.tab = region;\s*if \(region === 'inspector' && wb\.collapsed\.inspector\) inspectorPanel\.value\?\.expand\(\);/);
    assert.match(workbench, /if \(wb\.mode === 'drawer'\) wb\.drawerOpen = true;/);
    assert.match(workbench, /useLeaveGuard\(\(\) => hasForm\.value && frame\.dirty\.value, \(\) => hasForm\.value && props\.warnOnLeave && props\.form\?\.warnOnLeave !== false, formEl, \(\) => hasForm\.value && frame\.processing\.value\)/);
    assert.match(workbench, /<Dialog v-if="hasForm" v-model:open="askLeave"/);
    assert.match(workbench, /watch\(\(\) => hasForm\.value && frame\.processing\.value, \(busy\) => \{ if \(busy\) wb\.markSaving\(\); \}\);/);
    assert.match(workbench, /watch\(\(\) => hasForm\.value && !!props\.form\?\.isDirty, \(dirty\) => \{ if \(dirty && !frame\.processing\.value && wb\.saveState !== 'conflict'\) wb\.markDirty\(\); \}\);/);
});

test('the navigator filter\'s Enter never submits the Workbench\'s form', () => {
    assert.match(read('Workbench/WorkbenchNavigator.vue.stub'), /data-workbench-filter @keydown\.enter\.prevent \/>/);
});

test('FormCard: its own <form novalidate> only for a useSubmitForm (branded, never "has submit()") with no Inertia <Form>; submit stays the descriptor', () => {
    assert.match(card, /const own = computed\(\(\) => !context && isSubmitForm\(props\.form\)\);/);
    assert.match(card, /<component :is="own \? 'form' : 'section'" ref="card" :novalidate="own \|\| undefined"[^>]*@submit="onSubmit">/);
    assert.match(card, /if \(!own\.value \|\| !isSubmitForm\(props\.form\)\) return;\s*event\.preventDefault\(\);/);
    assert.match(card, /submit\?: \{ label: string; disabled\?: boolean; reason\?: string \| null \}/);
    assert.match(card, /role="alert"[^>]*data-form-error>\{\{ formError \}\}<\/p>/, 'the _form message is text, never v-html');
    assert.doesNotMatch(card + workbench, /v-html/);
});

test('formFrame: one leave guard, from the outermost frame that is guarding', () => {
    assert.match(frame, /const outer = inject\(GUARDED, null\);\s*const active = \(\) => !outer\?\.\(\) && enabled\(\);\s*provide\(GUARDED, active\);/);
});
