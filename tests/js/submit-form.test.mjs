import assert from 'node:assert/strict';
import test, { mock } from 'node:test';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// useSubmitForm (Kit/Form/submitForm.ts): the non-visiting form. Runs here over a stand-in for vue (reactive is the identity: the form's getters read plain state)
// and the real http.ts, with synthetic handlers: a SubmitError, thrown fetch Responses and useHttp-style errors, a plain Laravel 422 object, a timeout, a sync throw.
// The 409 body is tests/fixtures/stale-write-409.json, the same file the PHP side checks the kit's stale-write refusal against (WorkspaceModelCheck).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-submit-form-'));
const vue = join(dir, 'node_modules', 'vue');
mkdirSync(vue, { recursive: true });
mkdirSync(join(dir, 'Form'));
writeFileSync(join(vue, 'package.json'), JSON.stringify({ name: 'vue', type: 'module', main: 'index.js' }));
writeFileSync(join(vue, 'index.js'), 'export const reactive = (value) => value;\n');
copyFileSync(new URL('http.ts.stub', kit), join(dir, 'http.ts'));
// Node resolves only explicit extensions; a host's bundler resolves the stub's '../http' as written.
writeFileSync(join(dir, 'Form', 'submitForm.ts'), readFileSync(new URL('Form/submitForm.ts.stub', kit), 'utf8').replace("from '../http';", "from '../http.ts';"));
const { useSubmitForm, SubmitError, isSubmitForm, SUBMIT_MESSAGE_MAX, SUBMIT_GENERIC_MESSAGE, SUBMIT_TIMEOUT_MESSAGE } = await import(pathToFileURL(join(dir, 'Form', 'submitForm.ts')).href);
const staleWrite = JSON.parse(readFileSync(new URL('../fixtures/stale-write-409.json', import.meta.url), 'utf8'));

const json = (status, body, type = 'application/json') => new Response(typeof body === 'string' ? body : JSON.stringify(body), { status, headers: { 'content-type': type } });
const httpError = (status, data) => Object.assign(new Error('refused'), { response: { status, data: typeof data === 'string' ? data : JSON.stringify(data), headers: {} } });
const reject = (error) => async () => { throw error; };

test('a success calls the handler with a copy of the data, moves the defaults, and is recentlySuccessful for about 2s', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        const seen = [];
        const form = useSubmitForm({ title: 'Draft', tags: ['a'] }, async (data) => { seen.push(data); });
        assert.equal(isSubmitForm(form), true);
        assert.equal(isSubmitForm({ processing: false, submit() {} }), false, 'an Inertia useForm is not a submit form');
        form.data.title = 'Final';
        form.data.tags.push('b');
        assert.equal(form.isDirty, true);
        assert.equal(await form.submit(), true);
        assert.deepEqual(seen, [{ title: 'Final', tags: ['a', 'b'] }]);
        seen[0].tags.push('mutated');
        assert.deepEqual(form.data.tags, ['a', 'b'], 'the handler gets a copy');
        assert.equal(form.isDirty, false, 'the defaults moved to the submitted data');
        assert.equal(form.recentlySuccessful, true);
        mock.timers.tick(1999);
        assert.equal(form.recentlySuccessful, true);
        mock.timers.tick(1);
        assert.equal(form.recentlySuccessful, false);
        form.data.tags.push('c');
        assert.equal(form.isDirty, true, 'a deep change after the defaults moved is dirty again');
        form.reset();
        assert.deepEqual(form.data, { title: 'Final', tags: ['a', 'b'] }, 'reset goes back to the moved defaults');
    } finally {
        mock.timers.reset();
    }
});

test('a SubmitError maps its fields and summary, with no request', async () => {
    const form = useSubmitForm({ title: '' }, reject(new SubmitError({ title: 'Required' }, 'Fix the title.')));
    assert.equal(await form.submit(), false);
    assert.deepEqual({ ...form.errors }, { title: 'Required', _form: 'Fix the title.' });
    assert.equal(form.hasErrors, true);
    assert.equal(form.processing, false);
});

test('a thrown fetch Response: 422 with Laravel errors maps the first message per field', async () => {
    const form = useSubmitForm({ title: '', body: '' }, reject(json(422, { message: 'The given data was invalid.', errors: { title: ['Too short.', 'Second.'], body: ['Required.'] } })));
    assert.equal(await form.submit(), false);
    assert.deepEqual({ ...form.errors }, { title: 'Too short.', body: 'Required.' });
    assert.equal(form.conflict, false);
});

test('a thrown fetch Response: 409 stale_write (the PHP-checked body) is a conflict, the edits kept', async () => {
    const form = useSubmitForm({ title: 'Old' }, reject(json(staleWrite.status, staleWrite.body)));
    form.data.title = 'Mine';
    assert.equal(await form.submit(), false);
    assert.equal(form.conflict, true);
    assert.equal(form.data.title, 'Mine', 'edits are kept');
    assert.equal(form.isDirty, true);
    assert.ok(form.errors._form);
});

test('a thrown fetch Response with any other status, or a 409 that is not stale_write, is the generic _form message', async () => {
    for (const response of [json(500, '<html>boom</html>', 'text/html'), json(403, { message: 'This action is unauthorized.' }), json(409, { error: 'locked' }), json(404, '')]) {
        const form = useSubmitForm({ title: '' }, reject(response));
        assert.equal(await form.submit(), false);
        assert.deepEqual({ ...form.errors }, { _form: SUBMIT_GENERIC_MESSAGE }, `status ${response.status}`);
        assert.equal(form.conflict, false);
    }
});

test('a useHttp error (a `response` with a string body) reads through errorJson: 422 fields, 409 conflict, other status generic', async () => {
    let form = useSubmitForm({ title: '' }, reject(httpError(422, { errors: { title: ['Taken.'] } })));
    await form.submit();
    assert.deepEqual({ ...form.errors }, { title: 'Taken.' });
    form = useSubmitForm({ title: '' }, reject(httpError(409, staleWrite.body)));
    await form.submit();
    assert.equal(form.conflict, true);
    form = useSubmitForm({ title: '' }, reject(httpError(502, 'Bad gateway')));
    await form.submit();
    assert.deepEqual({ ...form.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
});

test('a 422 whose errors is not exactly Laravel\'s shape is the generic message, never mapped', async () => {
    for (const errors of [{ title: 'not a list' }, { title: [1] }, { title: [] }, {}, ['title'], null, { title: ['ok'], body: 'string' }]) {
        const form = useSubmitForm({ title: '' }, reject(json(422, { errors })));
        await form.submit();
        assert.deepEqual({ ...form.errors }, { _form: SUBMIT_GENERIC_MESSAGE }, JSON.stringify(errors));
    }
});

test('a thrown plain object of Laravel 422 shape maps to the fields; another plain object or an Error is generic', async () => {
    let form = useSubmitForm({ title: '' }, reject({ title: ['Required.'] }));
    await form.submit();
    assert.deepEqual({ ...form.errors }, { title: 'Required.' });
    for (const thrown of [{ title: 'Required.' }, new Error('boom'), 'text', undefined]) {
        form = useSubmitForm({ title: '' }, reject(thrown));
        await form.submit();
        assert.deepEqual({ ...form.errors }, { _form: SUBMIT_GENERIC_MESSAGE }, String(thrown));
    }
});

test('a synchronous throw in the handler (or transform) is a failure, not an exception out of submit()', async () => {
    const form = useSubmitForm({ title: '' }, () => { throw new SubmitError({ title: 'Sync.' }); });
    assert.equal(await form.submit(), false);
    assert.deepEqual({ ...form.errors }, { title: 'Sync.' });
    assert.equal(form.processing, false);
    const transformed = useSubmitForm({ title: '' }, async () => {}, { transform: () => { throw new Error('bad transform'); } });
    assert.equal(await transformed.submit(), false);
    assert.deepEqual({ ...transformed.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
});

test('server text stays text and is capped at SUBMIT_MESSAGE_MAX characters', async () => {
    const long = 'x'.repeat(SUBMIT_MESSAGE_MAX + 50);
    const form = useSubmitForm({ title: '' }, reject(json(422, `{"errors":{"title":["<img src=x onerror=alert(1)>"],"body":["${long}"],"__proto__":["polluted"]}}`)));
    await form.submit();
    assert.equal(form.errors.title, '<img src=x onerror=alert(1)>', 'kept as the literal text (rendered with {{ }}, never v-html)');
    assert.equal(form.errors.body.length, SUBMIT_MESSAGE_MAX);
    assert.ok(form.errors.body.endsWith('…'));
    assert.equal({}.polluted, undefined);
    assert.equal(SUBMIT_MESSAGE_MAX, 300);
});

test('timeout: an unsettled handler fails with _form "Timed out"; off by default', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        const form = useSubmitForm({ title: '' }, () => new Promise(() => {}), { timeout: 5000 });
        const done = form.submit();
        await Promise.resolve();
        assert.equal(form.processing, true);
        mock.timers.tick(5000);
        assert.equal(await done, false);
        assert.deepEqual({ ...form.errors }, { _form: SUBMIT_TIMEOUT_MESSAGE });
        assert.match(SUBMIT_TIMEOUT_MESSAGE, /^Timed out/);
        assert.equal(form.processing, false);
    } finally {
        mock.timers.reset();
    }
});

test('resetOnSuccess true puts every field back to its pre-submit default; a list only those fields', async () => {
    const all = useSubmitForm({ body: '', author: 'Ada' }, async () => {}, { resetOnSuccess: true });
    all.data.body = 'Hello';
    all.data.author = 'Grace';
    await all.submit();
    assert.deepEqual(all.data, { body: '', author: 'Ada' });
    assert.equal(all.isDirty, false);
    const some = useSubmitForm({ body: '', author: 'Ada' }, async () => {}, { resetOnSuccess: ['body'] });
    some.data.body = 'Hello';
    some.data.author = 'Grace';
    await some.submit();
    assert.deepEqual(some.data, { body: '', author: 'Grace' });
    assert.equal(some.isDirty, false, 'the unlisted field\'s default moved to the submitted value');
});

test('a second submit while processing is ignored: one handler call', async () => {
    // Every wait is bounded and every handler call is released, so a missing guard fails here instead of hanging.
    const bounded = (promise) => Promise.race([promise, new Promise((resolve) => setTimeout(() => resolve('pending'), 200))]);
    let calls = 0;
    const releases = [];
    const form = useSubmitForm({ title: '' }, () => { calls++; return new Promise((resolve) => releases.push(resolve)); });
    const first = form.submit();
    const second = form.submit();
    await Promise.resolve();
    await Promise.resolve();
    releases.forEach((release) => release());
    assert.equal(await bounded(second), false, 'the double submit resolves false at once');
    assert.equal(await bounded(first), true);
    assert.equal(calls, 1);
});

test('transform shapes the payload; setDefaults moves the defaults; clearErrors by field; warnOnLeave defaults on, false per form', async () => {
    const seen = [];
    const form = useSubmitForm({ first: 'Ada', last: 'Lovelace' }, async (payload) => { seen.push(payload); }, { transform: (data) => ({ name: `${data.first} ${data.last}` }) });
    await form.submit();
    assert.deepEqual(seen, [{ name: 'Ada Lovelace' }]);
    form.data.first = 'Grace';
    form.setDefaults();
    assert.equal(form.isDirty, false);
    form.setDefaults({ last: 'Hopper' });
    assert.equal(form.isDirty, true);
    form.errors = { first: 'x', last: 'y' };
    form.clearErrors('first');
    assert.deepEqual({ ...form.errors }, { last: 'y' });
    assert.equal(form.warnOnLeave, true);
    assert.equal(useSubmitForm({}, async () => {}, { warnOnLeave: false }).warnOnLeave, false);
});

// httpSubmit over a stand-in for Inertia's useHttp (@inertiajs/vue3 3.9.1, src/useHttp.ts submit()): a 2xx resolves the parsed body (null when empty); a 422
// calls onError(field => first message) and RESOLVES undefined; any other status calls onHttpException and throws an HttpResponseError carrying `response`
// ({ status, data: the raw text, headers }); a network failure calls onNetworkError and throws the client's error.
const { httpSubmit } = await import(pathToFileURL(join(dir, 'Form', 'submitForm.ts')).href);
class HttpResponseError extends Error {
    constructor(message, response) { super(message); this.name = 'HttpResponseError'; this.response = response; }
}
const fakeUseHttp = (reply) => ({
    async post(url, options = {}) {
        try {
            const response = await reply();
            const data = response.data ? JSON.parse(response.data) : null;
            if (response.status >= 200 && response.status < 300) { options.onSuccess?.(data, response); return data; }
            throw new HttpResponseError(`Request failed with status ${response.status}`, response);
        } catch (error) {
            if (error instanceof HttpResponseError) {
                if (error.response.status === 422) {
                    const errors = JSON.parse(error.response.data).errors || {};
                    options.onError?.(Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])));
                    return undefined;
                }
                options.onHttpException?.(error.response);
                throw error;
            }
            options.onNetworkError?.(error);
            throw error;
        }
    },
});
const answer = (status, body) => async () => ({ status, data: body === null ? '' : JSON.stringify(body), headers: { 'content-type': 'application/json' } });
const httpForm = (http, initial = { title: 'Draft' }) => useSubmitForm(initial, httpSubmit((data, hooks) => http.post('/posts', hooks)));

test('a bare useHttp handler reads a 422 as success; httpSubmit refuses it', async () => {
    const http = fakeUseHttp(answer(422, { message: 'The title is required.', errors: { title: ['The title is required.'] } }));
    const bare = useSubmitForm({ title: '' }, async () => { await http.post('/posts'); });
    assert.equal(await bare.submit(), true, 'useHttp resolves undefined on a 422, so awaiting it alone is a false success (the bug httpSubmit exists for)');
    const unhooked = useSubmitForm({ title: '' }, httpSubmit(() => http.post('/posts')));
    assert.equal(await unhooked.submit(), false, 'a resolve of undefined is refused even when the hooks were not passed');
    assert.deepEqual({ ...unhooked.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
    assert.equal(unhooked.recentlySuccessful, false);
});

test('httpSubmit: a 422 rejects with a SubmitError of the field errors', async () => {
    const seen = [];
    const run = httpSubmit((data, hooks) => fakeUseHttp(answer(422, { errors: { title: ['The title is required.', 'second'], body: ['Too long.'] } })).post('/posts', hooks));
    await assert.rejects(run({}), (error) => { seen.push(error); return error instanceof SubmitError; });
    assert.deepEqual(seen[0].errors, { title: 'The title is required.', body: 'Too long.' });
    const form = httpForm(fakeUseHttp(answer(422, { errors: { title: ['The title is required.'] } })));
    form.data.title = '';
    assert.equal(await form.submit(), false);
    assert.deepEqual({ ...form.errors }, { title: 'The title is required.' });
    assert.equal(form.isDirty, true, 'the defaults did not move');
    const listed = httpSubmit((data, hooks) => { hooks.onError({ title: ['First.', 'Second.'] }); return undefined; });
    await assert.rejects(listed({}), (error) => error instanceof SubmitError && error.errors.title === 'First.');
    const empty = httpForm(fakeUseHttp(answer(422, { message: 'Invalid.' })));
    assert.equal(await empty.submit(), false, 'a 422 with no field errors still fails');
    assert.deepEqual({ ...empty.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
});

test('httpSubmit: a 409 stale_write rejects as the conflict', async () => {
    const form = httpForm(fakeUseHttp(answer(staleWrite.status, staleWrite.body)));
    form.data.title = 'Mine';
    assert.equal(await form.submit(), false);
    assert.equal(form.conflict, true);
    assert.equal(form.data.title, 'Mine', 'the edits are kept');
});

test('httpSubmit: a 500 and a network failure reject', async () => {
    const server = httpSubmit((data, hooks) => fakeUseHttp(answer(500, { message: 'Server Error' })).post('/posts', hooks));
    await assert.rejects(server({}), (error) => error.response?.status === 500);
    const failed = httpForm(fakeUseHttp(answer(500, { message: 'Server Error' })));
    assert.equal(await failed.submit(), false);
    assert.deepEqual({ ...failed.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
    assert.equal(failed.conflict, false);
    const network = httpSubmit((data, hooks) => fakeUseHttp(async () => { throw new TypeError('Failed to fetch'); }).post('/posts', hooks));
    await assert.rejects(network({}), TypeError);
    const offline = httpForm(fakeUseHttp(async () => { throw new TypeError('Failed to fetch'); }));
    assert.equal(await offline.submit(), false);
    assert.deepEqual({ ...offline.errors }, { _form: SUBMIT_GENERIC_MESSAGE });
});

test('httpSubmit: a success resolves with the response data, and an empty 2xx body (null) is a success', async () => {
    const seen = [];
    const run = httpSubmit((data, hooks) => { seen.push(data); return fakeUseHttp(answer(201, { id: 7 })).post('/posts', hooks); });
    assert.deepEqual(await run({ title: 'Hi' }), { id: 7 });
    assert.deepEqual(seen, [{ title: 'Hi' }], 'the request gets the data');
    assert.equal(await httpSubmit((data, hooks) => fakeUseHttp(answer(204, null)).post('/posts', hooks))({}), null);
    const form = httpForm(fakeUseHttp(answer(200, { ok: true })));
    form.data.title = 'Final';
    assert.equal(await form.submit(), true);
    assert.equal(form.isDirty, false);
    assert.deepEqual({ ...form.errors }, {});
});
