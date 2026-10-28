import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The stub is TypeScript with a .stub suffix; node strips types from a plain .ts copy. The transport is faked: no network.
const dir = mkdtempSync(join(tmpdir(), 'kit-direct-upload-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/directUpload.ts.stub', import.meta.url), join(dir, 'directUpload.ts'));
const { directUpload, mutationFor, DirectUploadError } = await import(pathToFileURL(join(dir, 'directUpload.ts')).href);

const routes = { request: '/uploads', confirm: '/uploads/confirm' };
function fake(ticket, { putStatus = 200, confirmStatus = 200, requestStatus = 200 } = {}) {
    const calls = [];
    return {
        calls,
        json: async (url, body) => { calls.push(['json', url, body]); return url === routes.request ? { status: requestStatus, body: ticket } : { status: confirmStatus, body: { id: body.id, ready: true } }; },
        form: async (url, body) => { calls.push(['form', url, body.get('id'), body.get('file')?.size]); return { status: confirmStatus, body: { id: body.get('id'), ready: true } }; },
        put: async (url, file, headers, progress) => { calls.push(['put', url, headers]); progress(file.size / 2); progress(file.size); return putStatus; },
        digest: async () => 'ab'.repeat(32),
    };
}
const file = () => new File(['hello'], 'a.png', { type: 'image/png' });

test('a PUT ticket uploads straight to the bucket with the signed headers and progress, then confirms', async () => {
    const transport = fake({ id: 'u1', status: 'pending', method: 'PUT', url: 'https://bucket.test/k', headers: { 'Content-Type': 'image/png' } });
    const seen = [];
    const result = await directUpload(file(), routes, fraction => seen.push(fraction), transport);
    assert.deepEqual(transport.calls[0][2], { mime: 'image/png', bytes: 5, sha256: 'ab'.repeat(32), mutation_id: transport.calls[0][2].mutation_id });
    assert.deepEqual(transport.calls[1], ['put', 'https://bucket.test/k', { 'Content-Type': 'image/png' }]);
    assert.deepEqual(transport.calls[2], ['json', routes.confirm, { id: 'u1' }]);
    assert.deepEqual(seen, [0.5, 1, 1]);
    assert.deepEqual(result, { id: 'u1', ready: true });
});

test('a POST ticket sends the file as multipart to the confirm route; a ready ticket only confirms', async () => {
    const post = fake({ id: 'u2', status: 'pending', method: 'POST', url: null, headers: {} });
    await directUpload(file(), routes, undefined, post);
    assert.deepEqual(post.calls[1], ['form', routes.confirm, 'u2', 5]);
    const ready = fake({ id: 'u3', status: 'ready', method: null, url: null, headers: {} });
    await directUpload(file(), routes, undefined, ready);
    assert.deepEqual(ready.calls.map(call => call[0]), ['json', 'json']);
});

test('a retry of the same file replays its mutation id; another file gets its own', () => {
    const one = file();
    assert.equal(mutationFor(one), mutationFor(one));
    assert.notEqual(mutationFor(one), mutationFor(file()));
});

test('a failed step rejects with the step and status, and nothing after it runs', async () => {
    const put = fake({ id: 'u4', status: 'pending', method: 'PUT', url: 'https://bucket.test/k', headers: {} }, { putStatus: 403 });
    await assert.rejects(directUpload(file(), routes, undefined, put), error => error instanceof DirectUploadError && error.step === 'upload' && error.status === 403);
    assert.equal(put.calls.length, 2);
    const request = fake(null, { requestStatus: 422 });
    await assert.rejects(directUpload(file(), routes, undefined, request), error => error.step === 'request');
});
