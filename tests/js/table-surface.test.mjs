import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// Kit todo 2808: DataTable's card surface, its headers' nowrap and descriptor-declared date columns, and mcp Connections' row actions (only Revoke destructive) and
// dates. Checked by source; browser/run.mjs (?datatable) renders the same in Chromium, light and dark, with axe contrast.
const root = new URL('../../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const table = read(
    'resources/js/components/Kit/Table/DataTable.vue',
);

test('Connections: only Revoke is destructive; Connected and Expires are date columns; the page formats no dates itself', () => {
    const controller = read('src/Features/Mcp/Http/ConnectionController.php');
    const removal = controller.match(
        /new Action\('Remove',[\s\S]*?\), \$held\);/,
    )[0];
    assert.doesNotMatch(
        removal,
        /destructive: true/,
        'removing a grant is not destructive',
    );
    assert.match(controller, /new Action\('Revoke',[^\n]*destructive: true\)/);
    assert.match(
        controller,
        /new TableColumn\('connected', 'Connected', date: 'date'\), new TableColumn\('expires', 'Expires', date: 'date'\)/,
    );
    assert.doesNotMatch(
        read(
            'resources/features/mcp/pages/Workspace/Settings/Connections/Page.vue.stub',
        ),
        /toLocale|new Date\(/,
    );
});
