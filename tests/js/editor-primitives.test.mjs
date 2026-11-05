import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { flat, members } from './shape.mjs';

// Button, Surface, FormActions and InputCheckbox, checked by source (the kit has no Vue runtime in its tests).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const button = read('Form/Button.vue.stub');
const actions = read('Form/FormActions.vue.stub');
const frame = read('Form/formFrame.ts.stub');
const contract = read('contract.ts.stub');

test('Button: type is button by default, submit only inside Inertia\'s <Form> or a FormCard, and an explicit type always wins', () => {
    const source = flat(button);
    assert.match(source, /const inForm = Boolean\(useOptionalFormContext\(\)\) \|\| insideFormSubmit\(\);/);
    assert.match(source, /const kind = computed\(\(\) => props\.type \?\? \(inForm \? 'submit' : 'button'\)\);/);
    assert.match(flat(read('Form/FormCard.vue.stub')), /provideFormSubmit\(\);/);
    assert.match(flat(read('Form/formSubmit.ts.stub')), /return inject\(key, false\)/);
});

test('no kit stub relies on an implicit submit: every Button in a form names its type', () => {
    const root = fileURLToPath(new URL('../../resources/features', import.meta.url));
    const offenders = [];
    for (const path of readdirSync(root, { recursive: true }).filter(p => p.endsWith('.vue.stub'))) {
        const source = readFileSync(join(root, path), 'utf8');
        if (!/<(Form|form)\b/.test(source)) continue;
        for (const tag of source.match(/<Button\b[^>]*>/g) ?? []) if (!/\btype=/.test(tag)) offenders.push(`${path}: ${tag.slice(0, 60)}`);
    }
    assert.deepEqual(offenders, []);
});

test('Surface is exported and typed in the contract', () => {
    assert.match(read('index.ts.stub'), /export \{ default as Surface \} from '\.\/Surface\/Surface\.vue';/);
    const props = members(contract, 'SurfaceProps');
    for (const member of ["tone?: 'default' | 'inverse'", "shape?: 'default' | 'pill'", "padding?: 'none' | 'sm' | 'md'", 'as?: string', 'clip?: boolean', 'translucent?: boolean']) assert.ok([...props.values()].includes(member), member);
});

test('FormActions: every status line is overridable (dirty, errors, up to date, saved), and FormCard passes them through', () => {
    assert.match(flat(actions), /dirtyLabel: 'Unsaved changes', errorsLabel: 'There are errors in your submission\.', upToDateLabel: ''/);
    assert.match(flat(actions), /useFormFrameStatus\(\(\) => source\.value, \{labels: \(\) => props, locked: \(\) => locked\.value, reason: \(\) => props\.submit\?\.reason\}\)/);
    assert.match(flat(frame), /return \{errors: labels\.errorsLabel, dirty: labels\.dirtyLabel, saved: labels\.savedLabel, idle: labels\.upToDateLabel, readonly: options\.reason\?\.\(\) \?\? ''\}\[state\.value\]/);
    const card = flat(read('Form/FormCard.vue.stub'));
    for (const prop of ['dirty-label="dirtyLabel"', 'errors-label="errorsLabel"', 'up-to-date-label="upToDateLabel"']) assert.ok(card.includes(`:${prop}`), prop);
    const props = [...members(contract, 'FormActionsProps').values()];
    for (const member of ['savedLabel?: string', 'dirtyLabel?: string', 'errorsLabel?: string', 'upToDateLabel?: string']) assert.ok(props.includes(member), member);
});

test('FormActions: warnOnLeave false (on the bar, or on a useSubmitForm) registers no router before listener and no beforeunload, so the host owns its guard', () => {
    const bar = flat(actions);
    const shared = flat(frame);
    // The guard is the shared form frame's: FormActions has no router listener or beforeunload of its own.
    assert.doesNotMatch(bar, /router\.on\('before'|addEventListener\('beforeunload'/);
    assert.match(bar, /useLeaveGuard\(\(\) => dirty\.value, \(\) => props\.warnOnLeave && source\.value\?\.warnOnLeave !== false, /);
    // The router listeners, popstate and the Navigation API's navigate sit behind `if (!active()) return;`; the only beforeunload listener is in a watcher on
    // `active() && unsaved()`, and the history fallback's entry needs `active()` too.
    assert.equal((shared.match(/router\.on\('before'/g) ?? []).length, 1);
    assert.match(shared, /if \(!active\(\)\) return; offRouter = \[router\.on\('before'/);
    const mount = shared.slice(shared.indexOf('if (!active()) return;'));
    assert.match(mount, /window\.addEventListener\('popstate', popstate, true\); window\.addEventListener\('scroll', onScroll, \{capture: true, passive: true\}\); navigation\?\.addEventListener\('navigate'/);
    assert.match(shared, /const wantSentinel = \(\) => !navigation && mounted\.value && active\(\) && unsaved\(\)/);
    assert.equal((shared.match(/addEventListener\('beforeunload'/g) ?? []).length, 1);
    assert.match(shared, /watch\(\(\) => active\(\) && unsaved\(\), now => \{if \(now\) window\.addEventListener\('beforeunload', unload\);/);
    // One guard: a frame inside one that already guards (a FormActions inside a Workbench with :form) registers none.
    assert.match(shared, /const outer = inject\(GUARDED, null\); const active = \(\) => !outer\?\.\(\) && enabled\(\); provide\(GUARDED, active\);/);
});

test('InputCheckbox requires an id, and says why', () => {
    const source = read('Form/InputCheckbox.vue.stub');
    assert.match(flat(source), /defineProps<\{id: string; name\?: string;/);
    assert.match(source, /`id` is required: the label points at it/);
    assert.match(contract, /`id` is required: its label and a Field's error point at it\./);
});

test('a menu action reaches the host after the menu has closed and unmounted its rows (gap e)', async () => {
    const { mkdtempSync, writeFileSync } = await import('node:fs');
    const { tmpdir } = await import('node:os');
    const { pathToFileURL } = await import('node:url');
    const dir = mkdtempSync(join(tmpdir(), 'kit-menu-select-'));
    writeFileSync(join(dir, 'menuSelect.ts'), read('Action/menuSelect.ts.stub'));
    const { deferSelect } = await import(pathToFileURL(join(dir, 'menuSelect.ts')).href);

    // ActionBar stays mounted; the row component that chose the action unmounts with the menu before the deferred call.
    const received = [];
    let rowMounted = true;
    const host = action => received.push(action);
    const scheduled = [];
    deferSelect(host, { key: 'archive', label: 'Archive', url: '/x', method: 'post', menu: true }, run => scheduled.push(run));
    rowMounted = false;
    scheduled.forEach(run => run());
    assert.equal(rowMounted, false);
    assert.deepEqual(received.map(action => action.key), ['archive'], 'the host\'s @action runs although the row is gone');
    deferSelect(undefined, { key: 'x', label: 'X', url: '/x', method: 'post' }, run => run());
    assert.equal(received.length, 1, 'no handler: nothing to call');

    const items = flat(read('Action/ActionMenuItems.vue.stub'));
    assert.doesNotMatch(items, /defineEmits|emit\('action'/, 'the rows never emit from a component that is about to unmount');
    assert.match(items, /onAction\?: \(action: ActionDescriptor\) => void/);
    assert.match(items, /const select = \(action: ActionDescriptor\) => deferSelect\(props\.onAction, action\);/);
    const bar = flat(read('Action/ActionBar.vue.stub'));
    assert.equal((bar.match(/<ActionMenuItems :actions="part\.menu" :busy="blocked" :on-action="run" \/>/g) ?? []).length, 2, 'both ActionBar menus hand the rows its own, mounted handler');
    assert.match(bar, /const \{pending, asking, run, visit, running, isRunning\} = useActionRunner\(emit\);/, 'ActionBar runs through the shared runner');
    assert.match(flat(read('Action/actionRunner.ts.stub')), /function run\(action: ActionDescriptor\): void \{if \(hostRuns\(\)\) \{emit\('action', action\);/, 'a host that listens still gets the action from ActionBar\'s own, mounted emit');
});

