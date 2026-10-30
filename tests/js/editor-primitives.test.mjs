import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

// Button, Surface, FormActions and InputCheckbox, checked by source (the kit has no Vue runtime in its tests).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const button = read('Form/Button.vue.stub');
const actions = read('Form/FormActions.vue.stub');
const frame = read('Form/formFrame.ts.stub');
const contract = read('contract.ts.stub');

test('Button: ghost and link map to the host button\'s own variants; size passes through as the host size', () => {
    assert.match(button, /variant\?: 'primary' \| 'secondary' \| 'destructive' \| 'success' \| 'ghost' \| 'link' \| 'bar'/);
    assert.match(button, /props\.variant === 'success' && successClass/, 'success is filled green on the host default button');
    assert.match(button, /var\(--color-success,#15803d\)/, 'success falls back to green-700 (5.0:1 on white, AA)');
    assert.match(button, /props\.variant === 'destructive' && destructiveClass/, 'destructive gets a solid hover');
    assert.match(button, /hover:bg-\[var\(--color-destructive-hover,#b91c1c\)\]/, 'the destructive hover is solid red-700 (6.5:1), not the host\'s translucent /90');
    assert.match(button, /const base = computed\(\(\) => \(props\.variant === 'primary' \|\| props\.variant === 'success' \? 'default' : props\.variant === 'bar' \? 'ghost' : props\.variant\)\);/, 'ghost and link are the host variants of the same name');
    assert.match(button, /size\?: 'default' \| 'sm' \| 'lg' \| 'xl' \| 'icon'/);
    assert.match(button, /<BaseButton v-if="!href" :variant="base" :size="hostSize" :type="kind"/);
});

test('Button: type is button by default, submit only inside Inertia\'s <Form> or a FormCard, and an explicit type always wins', () => {
    assert.match(button, /type: undefined, href: undefined/);
    assert.match(button, /const inForm = Boolean\(useFormContext\(\)\) \|\| insideFormSubmit\(\);/);
    assert.match(button, /const kind = computed\(\(\) => props\.type \?\? \(inForm \? 'submit' : 'button'\)\);/);
    assert.match(read('Form/FormCard.vue.stub'), /provideFormSubmit\(\);/);
    assert.match(read('Form/formSubmit.ts.stub'), /return inject\(key, false\);/);
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

test('Surface: a card-like container with the card tokens that provides its tone and shape', () => {
    const surface = read('Surface/Surface.vue.stub');
    assert.match(surface, /provideSurface\(\(\) => \(\{ tone: props\.tone, shape: props\.shape \}\)\);/);
    assert.match(surface, /'bg-card text-card-foreground shadow-xs'/);
    assert.match(surface, /'border-foreground bg-foreground text-background'/);
    assert.match(surface, /\{ none: 'p-0', sm: 'p-3', md: 'p-6' \}\[props\.padding\]/);
    assert.match(surface, /props\.shape === 'pill' \? 'rounded-full' : 'rounded-xl'/);
    assert.match(read('index.ts.stub'), /export \{ default as Surface \} from '\.\/Surface\/Surface\.vue';/);
    assert.match(contract, /export interface SurfaceProps \{ tone\?: 'default' \| 'inverse'; shape\?: 'default' \| 'pill'; padding\?: 'none' \| 'sm' \| 'md'; as\?: string; clip\?: boolean; translucent\?: boolean \}/);
});

test('FormActions: every status line is overridable (dirty, errors, up to date, saved), and FormCard passes them through', () => {
    assert.match(actions, /dirtyLabel: 'Unsaved changes', errorsLabel: 'There are errors in your submission\.', upToDateLabel: '', /);
    // The status text is the shared form frame's (formFrame.ts), fed FormActions' own label props and the submit's reason.
    assert.match(actions, /useFormFrameStatus\(\(\) => source\.value, \{ labels: \(\) => props, locked: \(\) => locked\.value, reason: \(\) => props\.submit\?\.reason \}\)/);
    assert.match(frame, /return \{ errors: labels\.errorsLabel, dirty: labels\.dirtyLabel, saved: labels\.savedLabel, idle: labels\.upToDateLabel, readonly: options\.reason\?\.\(\) \?\? '' \}\[state\.value\];/);
    assert.match(read('Form/FormCard.vue.stub'), /:dirty-label="dirtyLabel" :errors-label="errorsLabel" :up-to-date-label="upToDateLabel"/);
    assert.match(contract, /savedLabel\?: string; dirtyLabel\?: string; errorsLabel\?: string; upToDateLabel\?: string;/);
});

test('FormActions: warnOnLeave false (on the bar, or on a useSubmitForm) registers no router before listener and no beforeunload, so the host owns its guard', () => {
    // The guard is the shared form frame's: FormActions has no router listener or beforeunload of its own.
    assert.doesNotMatch(actions, /router\.on\('before'|addEventListener\('beforeunload'/);
    assert.match(actions, /useLeaveGuard\(\(\) => dirty\.value, \(\) => props\.warnOnLeave && source\.value\?\.warnOnLeave !== false, /);
    // The router listeners, popstate and the Navigation API's navigate sit behind `if (!active()) return;`; the only beforeunload listener is in a watcher on
    // `active() && unsaved()`, and the history fallback's entry needs `active()` too.
    assert.equal((frame.match(/router\.on\('before'/g) ?? []).length, 1);
    assert.match(frame, /if \(!active\(\)\) return;\s*offRouter = \[\s*router\.on\('before'/);
    const mount = frame.slice(frame.indexOf('if (!active()) return;'));
    assert.match(mount, /window\.addEventListener\('popstate', popstate, true\);\s*window\.addEventListener\('scroll', onScroll, \{ capture: true, passive: true \}\);\s*navigation\?\.addEventListener\('navigate', navigate\);/);
    assert.match(frame, /const wantSentinel = \(\) => !navigation && mounted\.value && active\(\) && unsaved\(\)/);
    assert.equal((frame.match(/addEventListener\('beforeunload'/g) ?? []).length, 1);
    assert.match(frame, /watch\(\(\) => active\(\) && unsaved\(\), \(now\) => \{\s*if \(now\) window\.addEventListener\('beforeunload', unload\);/);
    // One guard: a frame inside one that already guards (a FormActions inside a Workbench with :form) registers none.
    assert.match(frame, /const outer = inject\(GUARDED, null\);\s*const active = \(\) => !outer\?\.\(\) && enabled\(\);\s*provide\(GUARDED, active\);/);
});

test('InputCheckbox requires an id, and says why', () => {
    assert.match(read('Form/InputCheckbox.vue.stub'), /defineProps<\{ id: string; name\?: string;/);
    assert.match(read('Form/InputCheckbox.vue.stub'), /`id` is required: the label points at it/);
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

    const items = read('Action/ActionMenuItems.vue.stub');
    assert.doesNotMatch(items, /defineEmits|emit\('action'/, 'the rows never emit from a component that is about to unmount');
    assert.match(items, /onAction\?: \(action: ActionDescriptor\) => void/);
    assert.match(items, /const select = \(action: ActionDescriptor\) => deferSelect\(props\.onAction, action\);/);
    const bar = read('Action/ActionBar.vue.stub');
    assert.equal((bar.match(/<ActionMenuItems :actions="part\.menu" :busy="busy" :on-action="run" \/>/g) ?? []).length, 2, 'both ActionBar menus hand the rows its own, mounted handler');
    assert.match(bar, /const \{ pending, asking, run, visit \} = useActionRunner\(emit\);/, 'ActionBar runs through the shared runner');
    assert.match(read('Action/actionRunner.ts.stub'), /function run\(action: ActionDescriptor\): void \{\n\s+if \(hostRuns\(\)\) \{\n\s+emit\('action', action\);/, 'a host that listens still gets the action from ActionBar\'s own, mounted emit');
});

test('Surface: clip (overflow-hidden by default, off for a sticky child) and translucent (legible and calm under every preference)', () => {
    const surface = read('Surface/Surface.vue.stub');
    assert.match(surface, /clip: true, translucent: false \}\);/);
    assert.match(surface, /props\.clip && 'overflow-hidden'/);
    assert.match(surface, /props\.translucent && \(props\.tone === 'inverse' \? translucentInverse : translucentDefault\)/);
    // Translucency only where backdrop-filter works; solid under more contrast and reduced transparency; no blur under reduced motion.
    assert.match(surface, /supports-\[backdrop-filter\]:bg-card\/80 supports-\[backdrop-filter\]:backdrop-blur-md/);
    assert.match(surface, /contrast-more:supports-\[backdrop-filter\]:bg-card /);
    assert.match(surface, /\[@media\(prefers-reduced-transparency:reduce\)\]:supports-\[backdrop-filter\]:bg-card /);
    assert.match(surface, /motion-reduce:backdrop-blur-none contrast-more:backdrop-blur-none \[@media\(prefers-reduced-transparency:reduce\)\]:backdrop-blur-none/);
    assert.match(surface, /supports-\[backdrop-filter\]:bg-foreground\/80/);
    const docs = readFileSync(new URL('../../docs/surface.md', import.meta.url), 'utf8');
    assert.match(docs, /\| `clip` \| `true` \|/);
    assert.match(docs, /\| `translucent` \| `false` \|/);
});
