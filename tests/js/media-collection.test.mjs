import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const source = readFileSync(new URL('Media/MediaCollection.vue.stub', kit), 'utf8');

test('MediaCollection: reorders through SortableList with the collection version; uploads go direct', () => {
    assert.match(source, /<SortableList v-else-if="!single" [^>]*:reorder="reorder" :version="collection\.version"/);
    assert.match(source, /<BulkDrop v-if="canAdd && direct" [^\n]*:direct="direct"/);
});

test('a single-file collection offers the dropzone only while empty; move targets exclude the current collection', () => {
    assert.match(source, /const canAdd = computed\(\(\) => !props\.disabled && !!props\.direct && \(!props\.single \|\| items\.value\.length === 0\)\);/);
    assert.match(source, /target\.collection !== props\.collection\.collection/);
});

test('move and remove are emitted for the host, every item has a named menu, and images carry alt text', () => {
    assert.match(source, /emit\('move', item, target\.collection\)/);
    assert.match(source, /emit\('remove', item\)/);
    assert.match(source, /:aria-label="`\$\{text\.actions\} \$\{itemLabel\(item, index\)\}`"/);
    assert.match(source, /<img [^>]*:alt="itemLabel\(item, index\)"/);
});
