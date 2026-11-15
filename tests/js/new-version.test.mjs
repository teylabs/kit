import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The reload after a deploy (Kit/Feedback/newVersion.ts): which hook reloads and which shows the notice, over stand-ins for window, the router and the toast.
// Each test loads its own copy, since installNewVersion installs once per module.
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-new-version-'));
copyFileSync(new URL('Feedback/newVersion.ts', kit), join(dir, 'newVersion.ts'));
let copies = 0;
const load = () => import(`${pathToFileURL(join(dir, 'newVersion.ts')).href}?copy=${++copies}`);

async function page({ unsaved = false } = {}) {
    const { installNewVersion, NEW_VERSION_MESSAGE } = await load();
    const target = new EventTarget();
    const state = { unsaved, reloads: 0, toasts: [], location: null };
    const hooks = {
        target,
        onLocation: (listener) => (state.location = listener),
        unsaved: () => state.unsaved,
        reload: () => state.reloads++,
        toast: (message, options) => state.toasts.push({ message, options }),
    };
    installNewVersion(hooks);
    // As Vite's preload helper dispatches it: cancelable, and thrown on unless its default is prevented.
    const preloadError = () => {
        const event = new Event('vite:preloadError', { cancelable: true });
        target.dispatchEvent(event);
        return event.defaultPrevented;
    };
    const location = (versionChange) => state.location({ detail: { url: new URL('https://app.test/cases'), versionChange } });
    return { state, hooks, installNewVersion, preloadError, location, NEW_VERSION_MESSAGE };
}

test('the decision: a failed chunk reloads only a clean page; a version change always shows the notice', async () => {
    const { newVersionStep } = await load();
    assert.equal(newVersionStep('preload-error', false), 'reload');
    assert.equal(newVersionStep('preload-error', true), 'notice');
    assert.equal(newVersionStep('location', false), 'notice', 'Inertia kept the page: the person reloads when ready');
    assert.equal(newVersionStep('location', true), 'notice');
});

test('vite:preloadError on a clean page: handled, then the page reloads, with no notice', async () => {
    const { state, preloadError } = await page();
    assert.equal(preloadError(), true, 'preventDefault, so Vite does not throw the error');
    assert.equal(state.reloads, 1);
    assert.deepEqual(state.toasts, []);
});

test('vite:preloadError with a form unsaved: handled, no reload, one persistent notice whose Reload reloads', async () => {
    const { state, preloadError, NEW_VERSION_MESSAGE } = await page({ unsaved: true });
    assert.equal(preloadError(), true);
    assert.equal(preloadError(), true, 'a second failed chunk is handled too');
    assert.equal(state.reloads, 0, 'the edits are kept');
    assert.equal(state.toasts.length, 1, 'shown once');
    const [{ message, options }] = state.toasts;
    assert.equal(message, NEW_VERSION_MESSAGE);
    assert.equal(message, 'A new version is available.');
    assert.equal(options.duration, Infinity, 'persistent');
    assert.equal(options.id, 'kit-new-version');
    assert.equal(options.action.label, 'Reload');
    options.action.onClick();
    assert.equal(state.reloads, 1);
});

test('once the form is saved, the next failed chunk reloads', async () => {
    const { state, preloadError } = await page({ unsaved: true });
    preloadError();
    state.unsaved = false;
    preloadError();
    assert.equal(state.reloads, 1);
    assert.equal(state.toasts.length, 1);
});

test("Inertia's location event: a version change shows the notice, clean or not, and never reloads; another location visit is not ours", async () => {
    const clean = await page();
    clean.location(false);
    clean.location(undefined);
    assert.deepEqual(clean.state.toasts, [], 'an external redirect is left to Inertia');
    clean.location(true);
    clean.location(true);
    assert.equal(clean.state.toasts.length, 1, 'shown once');
    assert.equal(clean.state.reloads, 0);
    const dirty = await page({ unsaved: true });
    dirty.location(true);
    assert.equal(dirty.state.toasts.length, 1);
    assert.equal(dirty.state.reloads, 0);
});

test('the notice is one across both hooks, and a second install adds no listeners', async () => {
    const { state, hooks, installNewVersion, preloadError, location } = await page({ unsaved: true });
    location(true);
    preloadError();
    assert.equal(state.toasts.length, 1);
    const target = new EventTarget();
    let listened = false;
    installNewVersion({ ...hooks, target, onLocation: () => (listened = true) });
    const event = new Event('vite:preloadError', { cancelable: true });
    target.dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);
    assert.equal(listened, false);
});
