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
