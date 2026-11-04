import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Pending actions (docs/pending-actions.md): an action shows its request in flight and never fires twice. Checked by source; the Vue and InertiaUI calls are
// typechecked against the supported clients.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');

test('a dialog action sends its method through ModalLink and shows ModalLink\'s loading', () => {
    const segment = read('Action/ActionSegment.vue.stub');
    assert.match(segment, /<ModalLink v-if="action\.intent === 'dialog'[^>]*v-slot="\{ loading \}"[^>]*:method="modalMethod\(action\)"[^>]*:navigate="modalMethod\(action\) === 'get'"/);
    assert.match(segment, /:aria-busy="loading \|\| undefined"><ActionLabel :action="action" :pending="loading" \/>/);
    assert.match(read('Action/ActionMenuItems.vue.stub'), /<ModalLink :href="action\.url" :method="\(action\.method\.toLowerCase\(\)/);
});

test('the runner guards re-entry and marks the running action; the bars block every action while one runs', () => {
    const runner = read('Action/actionRunner.ts.stub');
    assert.match(runner, /function visit\(action: ActionDescriptor, confirmation\?: string\): void \{\s*if \(running\.value !== null\) return;/);
    assert.match(runner, /onStart: \(\) => \{ running\.value = action; \}, onFinish: \(\) => \{ running\.value = null; \}/);
    for (const bar of ['Action/ActionBar.vue.stub', 'Action/HeaderActions.vue.stub']) {
        const source = read(bar);
        assert.match(source, /const blocked = computed\(\(\) => props\.busy \|\| running\.value !== null\);/, bar);
        assert.doesNotMatch(source.replace(/<ActionBar v-if="legacy"[^\n]*/, ''), /<Action(Segment|MenuItems)[^>]*:busy="busy"/, `${bar}: every segment and menu uses blocked`);
        assert.match(source, /:pending="isRunning\(/, bar);
    }
    const segment = read('Action/ActionSegment.vue.stub');
    assert.match(segment, /:aria-busy="pending \|\| undefined"[^>]*><ActionLabel :action="action" :pending="pending" \/><\/button>/);
});

test('the label swaps its icon for a reduced-motion-safe spinner while pending', () => {
    const label = read('Action/ActionLabel.vue.stub');
    assert.match(label, /<LoaderCircle v-if="pending" class="[^"]*animate-spin motion-reduce:animate-none[^"]*" aria-hidden="true" data-action-pending \/><component :is="icon" v-else-if="icon"/);
});

test('useModalVisit: one open at a time, the flag cleared in finally', () => {
    const source = read('Overlay/useModalVisit.ts.stub');
    assert.match(source, /if \(loading\.value\) return null;\s*loading\.value = true;\s*try \{\s*return await visitModal\(url, options\);\s*\} finally \{\s*loading\.value = false;/);
    assert.match(read('index.ts.stub'), /export \{ useModalVisit \} from '\.\/Overlay\/useModalVisit';/);
});
