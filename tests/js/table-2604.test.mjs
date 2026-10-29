import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// DataTable's row label, get descriptors as links (RowActions, ActionBar's segments, the ⌄ menu rows) and Badge. The pure parts run here; the components are checked by
// source (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-table-2604-'));
writeFileSync(join(dir, 'rowLabel.ts'), read('Table/rowLabel.ts.stub'));
writeFileSync(join(dir, 'actionPlacement.ts'), read('Action/actionPlacement.ts.stub'));
const { rowName } = await import(pathToFileURL(join(dir, 'rowLabel.ts')).href);
const { rendersAsLink } = await import(pathToFileURL(join(dir, 'actionPlacement.ts')).href);

test('rowLabel names the row by a column key or a function, falling back to the row number', () => {
    const row = { id: 7, name: 'Ana Lopez', email: '' };
    assert.equal(rowName(row, 2, 'name'), 'Ana Lopez');
    assert.equal(rowName(row, 2, r => `${r.name} (#${r.id})`), 'Ana Lopez (#7)');
    assert.equal(rowName(row, 2, 'email'), 'row 3', 'an empty value falls back');
    assert.equal(rowName(row, 2, 'missing'), 'row 3');
    assert.equal(rowName(row, 0, null), 'row 1');
    const table = read('Table/DataTable.vue.stub');
    assert.match(table, /:label="`Actions for \$\{rowName\(row\.original, row\.index, rowLabel\)\}`"/, 'the actions trigger is "Actions for <label>"');
    assert.match(table, /:aria-label="`Select \$\{rowName\(row\.original, row\.index, rowLabel\)\}`"/, 'and the select checkbox names it too');
});

test('a get action without a confirmation renders as a link; post, confirm, dialog and disabled ones stay buttons', () => {
    assert.equal(rendersAsLink({ method: 'get', key: 'view' }), true);
    assert.equal(rendersAsLink({ method: 'GET', key: 'view' }), true);
    assert.equal(rendersAsLink({ method: 'post', key: 'archive' }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', confirm: { title: 't', description: 'd', label: 'l' } }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', intent: 'dialog' }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'x', disabled: true }), false);
    assert.equal(rendersAsLink({ method: 'get', key: 'edit' }, ['edit']), false, 'an intercepted action is the page\'s');
});

test('RowActions: a get action is a link inline (kit Button href) and in the menu (the item is the link)', () => {
    const rows = read('Table/RowActions.vue.stub');
    assert.match(rows, /<Button v-if="rendersAsLink\(action, intercept\)" variant="secondary" :href="action\.url" :external="opensNewTab\(action\)"/, 'inline: a Button with href (an Inertia Link, or an external <a> for a new tab)');
    assert.match(rows, /<Button v-else variant="secondary" type="button"[^>]*@click="perform\(action\)">/, 'a post one stays a button');
    assert.match(rows, /<DropdownMenuItem v-if="rendersAsLink\(action, intercept\)" as-child :data-action="action\.key">\s*<a v-if="opensNewTab\(action\)" :href="action\.url" target="_blank" rel="noopener noreferrer">\{\{ action\.label \}\}<\/a>\s*<Link v-else :href="action\.url">/, 'menu: the item is the link (role=menuitem lands on the <a>)');
});

test('ActionBar segments and the ⌄ menu rows render a get action as a link', () => {
    const segment = read('Action/ActionSegment.vue.stub');
    assert.match(segment, /<a v-else-if="rendersAsLink\(action\) && opensNewTab\(action\) && !busy" :href="action\.url" target="_blank" rel="noopener noreferrer"/);
    assert.match(segment, /<Link v-else-if="rendersAsLink\(action\) && !busy" :href="action\.url"/);
    assert.match(segment, /<button v-else type="button"/, 'anything else is a button');
    const items = read('Action/ActionMenuItems.vue.stub');
    assert.match(items, /<DropdownMenuItem v-else-if="rendersAsLink\(action\)" as-child :disabled="busy" :data-action="action\.key">\s*<a v-if="opensNewTab\(action\)"[^>]*target="_blank" rel="noopener noreferrer">[\s\S]*?<Link v-else :href="action\.url">/);
    assert.match(items, /<DropdownMenuItem v-else :disabled="busy \|\| action\.disabled"[^>]*@select="select\(action\)">/, 'a post row still hands its action to the host');
});

test('Badge: tones on the kit tone tokens, two sizes, text carries the meaning', () => {
    const badge = read('Feedback/Badge.vue.stub');
    assert.match(badge, /tone\?: 'neutral' \| 'info' \| 'success' \| 'warning' \| 'danger'; size\?: 'sm' \| 'md'/);
    for (const [tone, token] of [['info', 'info'], ['success', 'success'], ['warning', 'warning'], ['danger', 'error']]) {
        assert.match(badge, new RegExp(`${tone}: 'border-\\(--kit-tone-${token}-border\\) bg-\\(--kit-tone-${token}-bg\\) text-\\(--kit-tone-${token}-fg\\)'`), tone);
    }
    assert.doesNotMatch(badge, /role="status"|aria-live/, 'not a live region');
    assert.match(read('index.ts.stub'), /export \{ default as Badge \} from '\.\/Feedback\/Badge\.vue';/);
});

test('a confirmed GET asks before it navigates (it is never a bare link that skips the confirmation)', () => {
    const confirmed = { key: 'export', label: 'Export', url: '/people/export', method: 'get', confirm: { title: 'Export everyone?', description: 'This can take a while.', label: 'Export' } };
    assert.equal(rendersAsLink(confirmed), false, 'a GET with a confirm is not rendered as a link');
    // ActionBar's segment: not the link branch, so it reaches the button that emits to ActionBar's run(), which opens the confirmation first.
    const segment = read('Action/ActionSegment.vue.stub');
    assert.doesNotMatch(segment, /isNavigation\(action\)/, 'the old navigation test (which ignored confirm) no longer picks the link');
    const bar = read('Action/ActionBar.vue.stub');
    assert.match(bar, /if \(action\.confirm\) \{\n\s+pending\.value = action;\n[\s\S]*?return;\n\s+\}\n\s+visit\(action\);/, 'ActionBar asks (pending + dialog) before it visits');
    // RowActions: the button calls perform(), which opens the dialog when there is a confirm and visits only from confirmed().
    const rows = read('Table/RowActions.vue.stub');
    assert.match(rows, /function perform\(action: RowAction\) \{[\s\S]*?if \(action\.confirm\) \{[\s\S]*?setTimeout\(\(\) => \(asking\.value = action\), 0\);\n\s+return;\n\s+\}\n\s+run\(action\);/, 'RowActions asks before it visits');
    assert.match(rows, /function confirmed\(confirmation\?: string\) \{\n\s+if \(pending\) run\(pending, confirmation\);/, 'and visits only once confirmed');
    // The ⌄ menu rows: a confirmed GET is not the link row, so it is handed to the host's run (which asks first).
    assert.match(read('Action/ActionMenuItems.vue.stub'), /<DropdownMenuItem v-else-if="rendersAsLink\(action\)"/);
});
