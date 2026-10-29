import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Overlays take the host's theme: the panels, headers and footers are on the semantic tokens, and every scrim is the kit's --kit-scrim (deeper in dark mode).
// Checked by source; the rendered colours (a host theme, dark mode) were measured in a headless render (see the PR).
const kit = new URL('../../resources/features/core/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const css = read('css/kit.css.stub');
const raw = /\b(?:bg|text|border|ring)-(?:white|black|gray|zinc|neutral|slate|stone)(?:-\d+)?(?:\/\d+)?\b/;

test('Modal, its panel and Dialog use no fixed colours', () => {
    for (const file of ['Modal.vue.stub', 'ModalPanel.vue.stub', 'Dialog.vue.stub']) assert.doesNotMatch(read(`components/Kit/Overlay/${file}`), raw, file);
});

test('the panels are on the semantic surface tokens', () => {
    assert.match(read('components/Kit/Overlay/Modal.vue.stub'), /border border-border bg-background text-foreground/);
    assert.match(read('components/Kit/Overlay/Dialog.vue.stub'), /border border-border bg-background p-6 text-foreground/);
    assert.match(read('components/Kit/Overlay/ModalPanel.vue.stub'), /border-t border-border/);
});

test('one scrim token, light and dark, behind the Dialog, the modal package\'s backdrop and the host Sheet overlay', () => {
    assert.match(css, /:root \{[^}]*--kit-scrim: rgb\(0 0 0 \/ 0\.5\);/);
    assert.match(css, /\.dark \{[^}]*--kit-scrim: rgb\(0 0 0 \/ 0\.7\);/);
    assert.match(css, /--color-scrim: var\(--kit-scrim\);/);
    assert.match(read('components/Kit/Overlay/Dialog.vue.stub'), /fixed inset-0 z-\(--z-dialog\) bg-scrim/);
    assert.match(css, /\.im-modal-dialog:not\(\.backdrop\\:bg-transparent\)::backdrop,\n\.im-backdrop,\n\[data-slot='sheet-overlay'\] \{\n    background-color: var\(--kit-scrim\);/);
    // Unlayered: the rule must sit outside every @layer, or the package's utilities would outrank it.
    const before = css.slice(0, css.indexOf('.im-modal-dialog'));
    assert.equal((before.match(/\{/g) ?? []).length, (before.match(/\}/g) ?? []).length, 'the scrim rule is at the top level');
});
