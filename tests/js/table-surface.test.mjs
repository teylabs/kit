import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Kit todo 2808: DataTable's card surface, its headers' nowrap and descriptor-declared date columns, and mcp Connections' row actions (only Revoke destructive) and
// dates. Checked by source; browser/run.mjs (?datatable) renders the same in Chromium, light and dark, with axe contrast.
const root = new URL('../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const table = read('resources/features/core/components/Kit/Table/DataTable.vue.stub');

test('the table and both empty states sit on the card surface', () => {
    assert.match(table, /class="[^"]*\bbg-card\b[^"]*\btext-card-foreground\b[^"]*"[^>]*data-table-card/);
    assert.equal((table.match(/class="[^"]*\bbg-card\b[^"]*" data-empty="(first-use|no-results)"/g) ?? []).length, 2);
    assert.doesNotMatch(table, /first:bg-background/, 'the sticky first cell matches the card, not the page');
});

test('column headers never wrap', () => {
    assert.match(table, /<th v-for="header in group\.headers"[^>]*class="[^"]*\bwhitespace-nowrap\b/);
});

test('a column that declares `date` renders its value through DateTime', () => {
    assert.match(table, /import DateTime from '@\/components\/Kit\/Data\/DateTime\.vue'/);
    assert.match(table, /<DateTime v-if="meta\(cell\.column\)\.date[^>]*:format="meta\(cell\.column\)\.date/);
    assert.match(read('src/Features/Core/Data/TableColumn.php'), /public \?string \$date = null/);
});

test('Connections: only Revoke is destructive; Connected and Expires are date columns; the page formats no dates itself', () => {
    const controller = read('src/Features/Mcp/Http/ConnectionController.php');
    const removal = controller.match(/new Action\('Remove '[\s\S]*?\), \$held\);/)[0];
    assert.doesNotMatch(removal, /destructive: true/, 'removing a grant is not destructive');
    assert.match(controller, /new Action\('Revoke',[^\n]*destructive: true\)/);
    assert.match(controller, /new TableColumn\('connected', 'Connected', date: 'date'\), new TableColumn\('expires', 'Expires', date: 'date'\)/);
    assert.doesNotMatch(read('resources/features/mcp/pages/Workspace/Settings/Connections/Page.vue.stub'), /toLocale|new Date\(/);
});

test('People, Security, Notes and activity entries show instants through DateTime, not ad hoc formatting', () => {
    for (const path of ['workspace-ui/pages/Workspace/Settings/People/Page.vue.stub', 'workspace-ui/pages/Workspace/Account/Security/Page.vue.stub', 'notes/pages/Workspace/Notes/Index/Partials/NoteCard.vue.stub', 'core/components/Kit/Feed/ActivityEntryRow.vue.stub']) {
        const source = read(`resources/features/${path}`);
        assert.doesNotMatch(source, /toLocale(Date|)String|new Intl\.DateTimeFormat/, path);
        assert.match(source, /<DateTime /, path);
    }
});
