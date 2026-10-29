import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Typed confirmation: the match that gates Confirm and the `confirmation` a visit sends run here without Vue; Dialog, RowActions, BulkBar and ActionBar are
// checked by source (no browser runner). The server re-check is tests/TypedConfirmationCheck.php.
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-typed-confirm-'));
writeFileSync(join(dir, 'confirmText.ts'), read('Overlay/confirmText.ts.stub'));
const { typedMatches, confirmationData } = await import(pathToFileURL(join(dir, 'confirmText.ts')).href);

test('Confirm is gated on the exact text, case-sensitive by default', () => {
    assert.equal(typedMatches('', 'Acme Ltd'), false, 'empty never matches');
    assert.equal(typedMatches('Acme', 'Acme Ltd'), false, 'a prefix does not match');
    assert.equal(typedMatches('acme ltd', 'Acme Ltd'), false, 'case matters by default');
    assert.equal(typedMatches('Acme Ltd', 'Acme Ltd'), true);
    assert.equal(typedMatches('  Acme Ltd \n', 'Acme Ltd'), true, 'surrounding whitespace from a paste is ignored');
    assert.equal(typedMatches('acme LTD', 'Acme Ltd', false), true, 'case-insensitive when asked');
    assert.equal(typedMatches('Acme  Ltd', 'Acme Ltd'), false, 'inner spacing still has to match');
    assert.equal(typedMatches('anything', ''), false, 'an empty expected text never enables Confirm');
});

test('a confirmed visit sends the typed text as confirmation, and nothing without one', () => {
    assert.deepEqual(confirmationData(' Acme Ltd '), { confirmation: 'Acme Ltd' });
    assert.equal(confirmationData(undefined), undefined);
});

test('Dialog asks for the text with a labelled input, keeps Confirm disabled until it matches, and announces it', () => {
    const dialog = read('Overlay/Dialog.vue.stub');
    assert.match(dialog, /confirmText\?: string \| null; caseSensitive\?: boolean; confirmTextLabel\?: string/);
    assert.match(dialog, /const matches = computed\(\(\) => !asksTyped\.value \|\| typedMatches\(typed\.value, props\.confirmText \?\? '', props\.caseSensitive\)\);/);
    assert.match(dialog, /<Label :for="inputId">/, 'the input is labelled');
    assert.match(dialog, /<BaseInput :id="inputId" v-model="typed"/);
    assert.match(dialog, /:disabled="!matches"/, 'Confirm stays disabled until it matches');
    assert.match(dialog, /<p role="status" class="sr-only">\{\{ matches \? `It matches\. \$\{confirmLabel\} is on\.` : '' \}\}<\/p>/, 'the change is announced politely');
    assert.doesNotMatch(dialog, /@paste|onpaste/, 'pasting is allowed');
    assert.match(dialog, /watch\(open, now => \{ if \(now\) typed\.value = ''; \}\);/, 'each opening starts empty');
    assert.match(dialog, /if \(!matches\.value\) return;/, 'confirm() refuses a mismatch even if called');
    assert.match(dialog, /data: confirmationData\(confirmation\)/, 'the action visit sends confirmation for the server to re-check');
    assert.match(dialog, /emit\('confirm', confirmation\);/);
});

test('descriptors carry confirm.typed, and RowActions, BulkBar and ActionBar render it and send it', () => {
    assert.match(read('Action/actionPlacement.ts.stub'), /confirm\?: \{ title: string; description: string; label: string; typed\?: string \| null \} \| null;/);
    for (const [file, binding] of [['Table/RowActions.vue.stub', 'shown.typed'], ['Table/BulkBar.vue.stub', 'confirm.typed'], ['Action/ActionBar.vue.stub', 'pending.confirm.typed']]) {
        const source = read(file);
        assert.ok(source.includes(`:confirm-text="${binding} ?? null"`), `${file} passes the typed text to its Dialog`);
        assert.match(source, /confirmationData\(confirmation\)/, `${file} sends confirmation with the visit`);
    }
    const bar = read('Action/ActionBar.vue.stub');
    assert.match(bar, /const hostRuns = \(\) => Boolean\(instance\?\.vnode\.props\?\.onAction\);/, 'a host that listens still runs it itself');
    assert.match(bar, /if \(action\.confirm\) \{\n\s+pending\.value = action;/, 'without a host, ActionBar confirms first');
});

test('Dialog keeps the icon gutter by default, and `:icon="false"` drops the icon on any variant and sets the content flush', () => {
    const dialog = read('Overlay/Dialog.vue.stub');
    assert.match(dialog, /confirmTextLabel\?: string; icon\?: boolean \}>\(\), \{[^}]*icon: true \}\);/, 'icon is a boolean prop, on by default');
    assert.match(dialog, /<div :class="icon \? 'flex gap-4' : 'grid gap-4'" :data-confirm-flush="icon \? undefined : ''">/, 'by default the icon column and gutter are as on main; without the icon the content is one flush column');
    assert.match(dialog, /<slot v-if="icon" name="icon"><span v-if="variant === 'danger'" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive\/10 text-destructive" data-confirm-icon>/, 'icon false hides the circle (and the icon slot) on every variant');
    assert.match(dialog, /<div class="grid min-w-0 gap-1">\n\s+<slot name="title">/, 'title, description, typed confirmation and body stay in the one content column');
    assert.match(dialog, /:variant="variant === 'danger' \? 'destructive' : 'primary'"/, 'danger keeps its destructive action whatever icon is');
    assert.doesNotMatch(dialog.slice(dialog.indexOf(':variant="variant ===') - 200, dialog.indexOf(':variant="variant ===')), /icon/, 'the action style does not depend on icon');
    assert.match(read('contract.ts.stub'), /confirmTextLabel\?: string; icon\?: boolean \}/, 'the contract declares icon');
});
