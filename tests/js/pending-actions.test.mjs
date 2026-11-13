import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat } from './shape.mjs';

// Pending actions (docs/pending-actions.md): an action shows its request in flight and never fires twice. Checked by source; the Vue and InertiaUI calls are
// typechecked against the supported clients.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const flatRead = path => flat(read(path));

test('a dialog action sends its method through ModalLink and shows ModalLink\'s loading', () => {
    const segment = flatRead('Action/ActionSegment.vue');
    assert.match(segment, /<ModalLink v-if="action\.intent === 'dialog'[^>]*v-slot="\{ ?loading ?\}"[^>]*:method="modalMethod\(action\)"[^>]*:navigate="modalMethod\(action\) === 'get'"/);
    assert.match(segment, /:aria-busy="loading \|\| undefined"><ActionLabel :action="action" :pending="loading" \/>/);
    assert.match(flatRead('Action/ActionMenuItems.vue'), /<ModalLink :href="action\.url" :method="\(?action\.method\.toLowerCase\(\)/);
});

