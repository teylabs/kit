import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// RowActions' layout and naming rules (rowActionLayout.ts): the split and overflow variants, the default between them, verb labels clamped with the record in the
// accessible name and the confirmation. browser/run.mjs (?row-variants) renders the same in Chromium.
const table = new URL(
    '../../resources/features/core/components/Kit/Table/',
    import.meta.url,
);
const dir = mkdtempSync(join(tmpdir(), 'kit-row-actions-'));
copyFileSync(
    new URL('rowActionLayout.ts.stub', table),
    join(dir, 'rowActionLayout.ts'),
);
const {
    layOut,
    clampLabel,
    tooLong,
    actionName,
    confirmTitle,
    confirmRecord,
    warnLongLabel,
} =
    await import(pathToFileURL(join(dir, 'rowActionLayout.ts')).href);

const plain = (key) => ({ key, label: key, destructive: false });
const danger = (key) => ({ key, label: key, destructive: true });
const keys = (list) => list.map((action) => action.key);
const shape = (laid) => ({
    variant: laid.variant,
    primary: laid.primary?.key ?? null,
    inline: keys(laid.inline),
    lone: keys(laid.lone),
    menu: keys(laid.menu),
});

test('one primary plus menu items defaults to split', () => {
    assert.deepEqual(shape(layOut([plain('open'), danger('delete')])), {
        variant: 'split',
        primary: 'open',
        inline: [],
        lone: [],
        menu: ['delete'],
    });
    assert.deepEqual(
        shape(layOut([plain('a'), plain('b'), plain('c'), danger('d')])),
        {
            variant: 'split',
            primary: 'a',
            inline: [],
            lone: [],
            menu: ['b', 'c', 'd'],
        },
    );
});

test('any other row defaults to overflow as before', () => {
    assert.deepEqual(shape(layOut([plain('a'), plain('b')])), {
        variant: 'overflow',
        primary: null,
        inline: ['a', 'b'],
        lone: [],
        menu: [],
    });
    assert.deepEqual(shape(layOut([plain('a'), plain('b'), danger('d')])), {
        variant: 'overflow',
        primary: null,
        inline: ['a', 'b'],
        lone: [],
        menu: ['d'],
    });
    assert.deepEqual(shape(layOut([plain('a')])), {
        variant: 'overflow',
        primary: null,
        inline: ['a'],
        lone: [],
        menu: [],
    });
    assert.deepEqual(shape(layOut([danger('d')])), {
        variant: 'overflow',
        primary: null,
        inline: [],
        lone: ['d'],
        menu: [],
    });
});

test('an explicit variant always wins', () => {
    assert.deepEqual(
        shape(layOut([plain('open'), danger('delete')], 'overflow')),
        {
            variant: 'overflow',
            primary: null,
            inline: ['open'],
            lone: [],
            menu: ['delete'],
        },
    );
    assert.deepEqual(shape(layOut([plain('a'), plain('b')], 'split')), {
        variant: 'split',
        primary: 'a',
        inline: [],
        lone: [],
        menu: ['b'],
    });
    assert.deepEqual(shape(layOut([plain('a')], 'split')), {
        variant: 'split',
        primary: 'a',
        inline: [],
        lone: [],
        menu: [],
    });
});

test('split with no plain action keeps the lone destructive button', () => {
    assert.deepEqual(shape(layOut([danger('revoke')], 'split')), {
        variant: 'split',
        primary: null,
        inline: [],
        lone: ['revoke'],
        menu: [],
    });
});

test('labels over 24 characters or 3 words are clamped with an ellipsis', () => {
    assert.equal(tooLong('Remove'), false);
    assert.equal(tooLong('Resend the invitation'), false);
    assert.equal(tooLong('Show the full details'), true, 'four words');
    assert.equal(tooLong('Reconcileallsupplierlines'), true, '25 characters');
    assert.equal(clampLabel('Remove'), 'Remove');
    assert.equal(
        clampLabel('Resend the invitation email to Ana Lopez'),
        'Resend the invitation…',
    );
    assert.equal(
        clampLabel('Reconcileallsupplierlines'),
        'Reconcileallsupplierlin…',
    );
    assert.ok(clampLabel('Reconcileallsupplierlines').length <= 24);
});

test('the accessible name is ariaLabel or the label and the record', () => {
    assert.equal(
        actionName(
            { label: 'Remove', ariaLabel: 'Remove Files' },
            'Acme Assistant',
        ),
        'Remove Files',
    );
    assert.equal(
        actionName({ label: 'Revoke' }, 'Acme Assistant'),
        'Revoke Acme Assistant',
    );
    assert.equal(actionName({ label: 'Revoke' }, null), 'Revoke');
});

test('a confirmation title is short and never holds the record; the record goes in the body', () => {
    const untitled = { label: 'Delete', confirm: { title: '' } };
    assert.equal(confirmTitle(untitled), 'Delete this item?');
    assert.equal(confirmTitle({ label: 'Delete', confirm: {} }), 'Delete this item?');
    assert.equal(confirmRecord(untitled, 'Northwind Farms'), 'Northwind Farms');
    assert.equal(confirmRecord(untitled, null), null);
    const titled = {
        label: 'Remove',
        confirm: { title: 'Remove permission?' },
    };
    assert.equal(confirmTitle(titled), 'Remove permission?');
    assert.equal(
        confirmRecord(titled, 'Acme Assistant'),
        null,
        'a server title comes with a description that names the record itself',
    );
});

test('a clamped label warns once per label', () => {
    const warnings = [];
    const warn = (message) => warnings.push(message);
    warnLongLabel('Remove', warn);
    warnLongLabel('Remove the Files grant from Acme', warn);
    warnLongLabel('Remove the Files grant from Acme', warn);
    warnLongLabel('Show the full billing details', warn);
    assert.equal(warnings.length, 2);
    assert.match(
        warnings[0],
        /row action labels are verbs; put the record in the confirm/,
    );
});

test('ConnectionController labels grant removals Remove, describes each by its grant, and keeps the confirm title short', async () => {
    const { readFileSync } = await import('node:fs');
    const controller = readFileSync(
        new URL(
            '../../src/Features/Mcp/Http/ConnectionController.php',
            import.meta.url,
        ),
        'utf8',
    );
    const removal = controller.match(
        /new Action\('Remove',[\s\S]*?\), \$held\);/,
    )[0];
    assert.match(removal, /'title' => 'Remove permission\?'/);
    assert.doesNotMatch(
        removal.match(/'title' => [^,]*,/)[0],
        /\$explicit|\$assistant/,
        'the bold title never holds the grant or the assistant',
    );
    assert.match(
        removal,
        /'description' => "The '"\.\$explicit\[\$scope\]\['label'\]\."' permission is removed from "\.\$assistant/,
    );
    assert.match(removal, /description: \$explicit\[\$scope\]\['label'\]\)/);
    assert.match(
        removal,
        /ariaLabel: 'Remove '\.\$explicit\[\$scope\]\['label'\]/,
    );
    assert.match(controller, /new Action\('Revoke',/);
});

test('RowActions shows a menu item description under its label, truncated, and describes the item by it', async () => {
    const { readFileSync } = await import('node:fs');
    const read = (path) =>
        readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
    const rows = read(
        'resources/features/core/components/Kit/Table/RowActions.vue.stub',
    );
    const shown = rows.match(/<span\s+v-if="action\.description"[\s\S]*?<\/span/g);
    assert.equal(shown.length, 6, 'every menu item shape (button, link and new-tab link, in both menus) shows it');
    for (const span of shown) {
        assert.match(span, /:id="descriptionId\(action\)"/);
        assert.match(span, /text-muted-foreground/);
        assert.match(span, /\btruncate\b/);
    }
    assert.match(rows, /inMenu && action\.description \? descriptionId\(action\)/);
    assert.equal((rows.match(/v-bind="state\(action, true\)"/g) ?? []).length, 2);
    assert.match(rows, /#body/);
    assert.match(read('src/Features/Core/Data/Action.php'), /public \?string \$description = null\)/);
    assert.match(read('types/kit.d.ts'), /ariaLabel: string \| null,\ndescription: string \| null,/);
});

test('ButtonGroup segments share one height and line so split labels align', async () => {
    const { readFileSync } = await import('node:fs');
    const group = readFileSync(
        new URL(
            '../../resources/features/core/components/Kit/Action/ButtonGroup.vue.stub',
            import.meta.url,
        ),
        'utf8',
    );
    const segment = group.match(/'divide-x divide-outline[^']*'/)[0];
    for (const cls of ['[&>*]:h-auto', '[&>*]:items-center', '[&>*]:py-2', '[&>*]:leading-5'])
        assert.ok(segment.includes(cls), cls);
    const trigger = group.match(/'inline-flex items-center gap-1 rounded-md bg-paper[^']*'/)[0];
    for (const cls of ['items-center', 'py-2', 'leading-5'])
        assert.ok(trigger.split(' ').includes(cls), cls);
});
