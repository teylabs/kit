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
const { layOut, clampLabel, tooLong, actionName, confirmTitle, warnLongLabel } =
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

test('a confirmation without a title asks the label and the record', () => {
    assert.equal(
        confirmTitle(
            { label: 'Delete', confirm: { title: '' } },
            'Northwind Farms',
        ),
        'Delete Northwind Farms?',
    );
    assert.equal(
        confirmTitle({ label: 'Delete', confirm: {} }, null),
        'Delete?',
    );
    assert.equal(
        confirmTitle(
            {
                label: 'Remove',
                confirm: {
                    title: "Remove the 'Files' permission from Acme Assistant?",
                },
            },
            'Acme Assistant',
        ),
        "Remove the 'Files' permission from Acme Assistant?",
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

test('ConnectionController labels grant removals Remove with the grant in the name and the confirm', async () => {
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
    assert.match(
        removal,
        /'title' => "Remove the '"\.\$explicit\[\$scope\]\['label'\]\."' permission from "\.\$assistant\.'\?'/,
    );
    assert.match(
        removal,
        /ariaLabel: 'Remove '\.\$explicit\[\$scope\]\['label'\]/,
    );
    assert.match(controller, /new Action\('Revoke',/);
});
