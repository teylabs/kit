import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// S3 multipart in directUpload: the request offers it only with a complete route, the parts follow the server's layout, at most PART_CONCURRENCY run
// at once, one failed part is retried, a missing ETag fails closed, and the complete payload carries every part's ETag and checksum. The transport
// is faked: no network.
const dir = mkdtempSync(join(tmpdir(), 'kit-direct-multipart-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/Form/directUpload.ts.stub', import.meta.url), join(dir, 'directUpload.ts'));
const { directUpload, partSlices, PART_CONCURRENCY, DirectUploadError } = await import(pathToFileURL(join(dir, 'directUpload.ts')).href);

const routes = { request: '/uploads', confirm: '/uploads/confirm', complete: '/uploads/complete', abort: '/uploads/abort' };
const PARTS = 6;
const bytes = new Uint8Array(6 * 7 + 3).map((_, index) => index);
const file = () => new File([bytes], 'big.png', { type: 'image/png' });
const signed = part => ({ part, url: `https://bucket.test/k?partNumber=${part}`, headers: { 'x-amz-checksum-sha256': `c${part}` } });

function fake({ fail = {}, noEtag = 0, unsignedFirst = true } = {}) {
    const calls = [];
    let inFlight = 0;
    const state = { peak: 0, attempts: {}, received: {} };
    return {
        calls, state,
        json: async (url, body) => {
            calls.push(['json', url, body]);
            if (url === routes.request) {
                const unsigned = unsignedFirst && !body.part_sha256;
                return { status: 200, body: { id: 'u1', status: 'pending', method: 'MULTIPART', url: null, headers: {}, expires_at: null, upload_id: 'mpu-1', parts: Array.from({ length: PARTS }, (_, i) => unsigned ? { part: i + 1, url: null, headers: {} } : signed(i + 1)) } };
            }
            return { status: 200, body: { id: body.id, ready: true } };
        },
        form: async () => assert.fail('no form post'),
        put: async () => assert.fail('no single PUT'),
        digest: async blob => `h${blob.size}`,
        putPart: async (url, part, headers, progress) => {
            const number = Number(new URL(url).searchParams.get('partNumber'));
            state.attempts[number] = (state.attempts[number] ?? 0) + 1;
            inFlight++;
            state.peak = Math.max(state.peak, inFlight);
            await new Promise(resolve => setTimeout(resolve, 5));
            inFlight--;
            if ((fail[number] ?? 0) >= state.attempts[number]) return { status: 500, etag: null };
            progress(part.size);
            state.received[number] = new Uint8Array(await part.arrayBuffer());
            return { status: 200, etag: number === noEtag ? null : `"e${number}"` };
        },
    };
}

test('parts follow the server layout: ceil(size / count) bytes each, the last taking the rest', () => {
    const slices = partSlices(file(), PARTS);
    assert.deepEqual(slices.map(slice => slice.size), [8, 8, 8, 8, 8, 5]);
    assert.equal(slices.reduce((sum, slice) => sum + slice.size, 0), bytes.length);
});

test('a multipart upload hashes the parts, uploads them a few at a time, retries a failed part once, then completes with every ETag', async () => {
    const transport = fake({ fail: { 3: 1 } });
    const seen = [];
    const result = await directUpload(file(), routes, fraction => seen.push(fraction), transport);
    const [first, second, complete] = transport.calls;
    assert.equal(first[2].multipart, true);
    assert.equal(first[2].part_sha256, undefined);
    assert.deepEqual(second[2].part_sha256, ['h8', 'h8', 'h8', 'h8', 'h8', 'h5']);
    assert.equal(second[2].mutation_id, first[2].mutation_id);
    assert.ok(transport.state.peak <= PART_CONCURRENCY && transport.state.peak > 1);
    assert.equal(transport.state.attempts[3], 2);
    assert.deepEqual(Object.values(transport.state.attempts).filter(n => n > 1), [2]);
    const joined = Uint8Array.from(Object.keys(transport.state.received).sort((a, b) => a - b).flatMap(key => [...transport.state.received[key]]));
    assert.deepEqual(joined, bytes);
    assert.deepEqual(complete, ['json', routes.complete, { id: 'u1', parts: Array.from({ length: PARTS }, (_, i) => ({ part: i + 1, etag: `"e${i + 1}"`, checksum: `c${i + 1}` })) }]);
    assert.equal(transport.calls.length, 3);
    assert.equal(seen.at(-1), 1);
    assert.deepEqual(result, { id: 'u1', ready: true });
});

test('with part checksums off the first ticket is already signed, and the complete payload sends a null checksum', async () => {
    const transport = fake({ unsignedFirst: false });
    transport.json = (json => async (url, body) => {
        const response = await json(url, body);
        if (url === routes.request) response.body.parts = response.body.parts.map(part => ({ ...part, headers: {} }));
        return response;
    })(transport.json);
    await directUpload(file(), routes, undefined, transport);
    assert.equal(transport.calls.length, 2);
    assert.ok(transport.calls[1][2].parts.every(part => part.checksum === null));
});

test('a part that keeps failing, or answers without an ETag, fails closed: no complete, and the upload is aborted', async () => {
    const failing = fake({ fail: { 2: 99 } });
    await assert.rejects(directUpload(file(), routes, undefined, failing), error => error instanceof DirectUploadError && error.step === 'upload' && error.status === 500);
    assert.equal(failing.state.attempts[2], 3);
    assert.deepEqual(failing.calls.at(-1), ['json', routes.abort, { id: 'u1' }]);
    assert.ok(!failing.calls.some(call => call[1] === routes.complete));

    const noEtag = fake({ noEtag: 4 });
    await assert.rejects(directUpload(file(), routes, undefined, noEtag), error => error.step === 'upload');
    assert.equal(noEtag.state.attempts[4], 1);
    assert.deepEqual(noEtag.calls.at(-1), ['json', routes.abort, { id: 'u1' }]);
});

test('a cancelled upload stops starting parts and is aborted', async () => {
    const controller = new AbortController();
    controller.abort();
    const transport = fake();
    await assert.rejects(directUpload(file(), routes, undefined, transport, controller.signal), error => error.step === 'upload');
    assert.deepEqual(transport.state.attempts, {});
    assert.deepEqual(transport.calls.at(-1), ['json', routes.abort, { id: 'u1' }]);
});

test('without a complete route the request does not offer multipart, so the body is as before', async () => {
    const calls = [];
    const transport = { json: async (url, body) => { calls.push(body); return { status: 200, body: { id: 'u2', status: 'ready', method: null } }; }, digest: async () => 'ab'.repeat(32) };
    await directUpload(file(), { request: '/uploads', confirm: '/uploads/confirm' }, undefined, transport);
    assert.deepEqual(Object.keys(calls[0]), ['mime', 'bytes', 'sha256', 'mutation_id']);
});
