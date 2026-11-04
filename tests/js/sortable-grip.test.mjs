import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// The grip leads a reorderable row in every kit sortable (SortableList and Repeater, cards and the table layout).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');

test('SortableList: the grip is the row\'s first child', () => {
    assert.match(read('Data/SortableList.vue.stub'), /data-sortable-item[^>]*>\s*<SortableHandle /);
});

test('Repeater: one grip, first in the row toolbar, pinned to the leading edge in the table layout with matching room on rows and header', () => {
    const source = read('Form/Repeater.vue.stub');
    const toolbar = source.slice(source.indexOf('data-repeater-toolbar>'));
    assert.match(toolbar, /^data-repeater-toolbar>\s*<!--[^>]*-->\s*<SortableHandle [^\n]*data-repeater-grip/, 'the grip comes before the row title');
    assert.equal((source.match(/<SortableHandle /g) ?? []).length, 1, 'one grip per row');
    assert.match(source, /:class="table && '@lg:absolute @lg:top-2 @lg:left-0'"/);
    assert.match(source, /const gripRoom = computed\(\(\) => \(table\.value && props\.reorderable && props\.draggable && canEdit\.value \? '@lg:pl-9' : ''\)\);/);
    assert.equal((source.match(/gripRoom, gridClass/g) ?? []).length, 2, 'rows and the header get the same leading room');
});

test('Repeater row-header: the host header replaces the title, between the leading grip and the move controls', () => {
    const source = readFileSync(new URL('Form/Repeater.vue.stub', kit), 'utf8');
    const toolbar = source.slice(source.indexOf('data-repeater-toolbar>'));
    const grip = toolbar.indexOf('<SortableHandle ');
    const header = toolbar.indexOf("<div v-if=\"$slots['row-header']\"");
    const moves = toolbar.indexOf(':aria-label="`Move ${itemLabel}');
    assert.ok(grip >= 0 && grip < header && header < moves, 'grip, then the host header, then Move up/down');
    assert.match(toolbar, /<slot name="row-header" v-bind="rowMoves\(index, entry\.row\)" \/><\/div>\s*<h3 v-else /);
});

test('SectionHeader: a #meta slot for a meta line with links, falling back to the meta prop', () => {
    const source = readFileSync(new URL('Layout/SectionHeader.vue.stub', kit), 'utf8');
    assert.match(source, /<p v-if="meta \|\| \$slots\.meta" [^>]*data-section-meta><slot name="meta">\{\{ meta \}\}<\/slot><\/p>/);
});

test('Repeater: order-buttons=false keeps only the grip; row-header and row-menu get moveUp/moveDown and first/last', () => {
    const source = readFileSync(new URL('Form/Repeater.vue.stub', kit), 'utf8');
    assert.match(source, /<template v-if="reorderable && canEdit && \(orderButtons \|\| !draggable\)">/, 'arrows stay when there is no grip to reorder with');
    assert.match(source, /<slot name="row-header" v-bind="rowMoves\(index, entry\.row\)" \/>/);
    assert.match(source, /<slot name="row-menu" v-bind="rowMoves\(index, entry\.row\)" \/>/);
    assert.match(source, /moveUp: movable && index > 0 \? \(\) => move\(index, -1\) : null/);
});
