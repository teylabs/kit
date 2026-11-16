import test from 'node:test';
import assert from 'node:assert/strict';
import { createFileDropGuard } from '../../resources/js/components/Kit/Form/fileDropGuard.ts';

class Target extends EventTarget {
    added = 0;
    addEventListener(...args) {
        this.added++;
        super.addEventListener(...args);
    }
    removeEventListener(...args) {
        this.added--;
        super.removeEventListener(...args);
    }
}
const drag = (type, types = ['Files']) => {
    const event = new Event(type, { cancelable: true });
    event.dataTransfer = { types, dropEffect: 'copy' };

    return event;
};

test('listens only while at least one zone holds the guard, however many are mounted', () => {
    const target = new Target();
    const guard = createFileDropGuard(target);
    const first = guard.acquire();
    const second = guard.acquire();
    assert.equal(guard.holders(), 2);
    assert.equal(target.added, 6);
    first();
    first();
    assert.equal(guard.holders(), 1, 'releasing twice counts once');
    assert.equal(target.added, 6);
    second();
    assert.equal(guard.holders(), 0);
    assert.equal(target.added, 0);
});

test('a file dragged or dropped anywhere on the page is kept from the browser; other drags are left alone', () => {
    const target = new Target();
    const release = createFileDropGuard(target).acquire();
    const over = drag('dragover');
    target.dispatchEvent(over);
    assert.equal(over.defaultPrevented, true);
    assert.equal(
        over.dataTransfer.dropEffect,
        'none',
        'outside a zone the cursor says no drop',
    );
    const drop = drag('drop');
    target.dispatchEvent(drop);
    assert.equal(drop.defaultPrevented, true);
    const text = drag('drop', ['text/plain']);
    target.dispatchEvent(text);
    assert.equal(text.defaultPrevented, false);
    release();
    const after = drag('drop');
    target.dispatchEvent(after);
    assert.equal(
        after.defaultPrevented,
        false,
        'no guard once every zone is gone',
    );
});

test('reports a page drag from the first enter until the drag leaves, drops or is released', () => {
    const target = new Target();
    const changes = [];
    const guard = createFileDropGuard(target, (dragging) =>
        changes.push(dragging),
    );
    const release = guard.acquire();
    target.dispatchEvent(drag('dragenter'));
    target.dispatchEvent(drag('dragenter'));
    target.dispatchEvent(drag('dragleave'));
    assert.equal(
        guard.dragging(),
        true,
        'moving between children keeps the drag',
    );
    target.dispatchEvent(drag('dragleave'));
    assert.equal(guard.dragging(), false);
    target.dispatchEvent(drag('dragenter'));
    target.dispatchEvent(drag('drop'));
    assert.equal(guard.dragging(), false);
    target.dispatchEvent(drag('dragenter'));
    release();
    assert.equal(guard.dragging(), false);
    assert.deepEqual(changes, [true, false, true, false, true, false]);
});
