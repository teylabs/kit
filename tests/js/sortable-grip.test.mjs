import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat } from './shape.mjs';

// The grip leads a reorderable row in every kit sortable (SortableList and Repeater, cards and the table layout).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');

test('Repeater row-header: the host header replaces the title, between the leading grip and the move controls', () => {
    const source = flat(readFileSync(new URL('Form/Repeater.vue', kit), 'utf8'));
    const toolbar = source.slice(source.indexOf('data-repeater-toolbar>'));
    const grip = toolbar.indexOf('<SortableHandle ');
    const header = toolbar.indexOf("<div v-if=\"$slots['row-header']\"");
    const moves = toolbar.indexOf(':aria-label="`Move ${itemLabel}');
    assert.ok(grip >= 0 && grip < header && header < moves, 'grip, then the host header, then Move up/down');
    assert.match(toolbar, /<slot name="row-header" v-bind="rowMoves\(index, entry\.row\)" \/>\s*<\/div>\s*<h3 v-else /);
});
