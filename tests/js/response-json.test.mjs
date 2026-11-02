import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// responseJson: Inertia v3's XHR client resolves `data` as the raw responseText string, so kit code parses bodies through this helper, never `response.data.x`.
const dir = mkdtempSync(join(tmpdir(), 'kit-http-'));
copyFileSync(new URL('../../resources/features/core/components/Kit/http.ts.stub', import.meta.url), join(dir, 'http.ts'));
const { responseJson, errorJson, ResponseJsonError } = await import(pathToFileURL(join(dir, 'http.ts')).href);

test('a string JSON body with a JSON content type parses to the object', () => {
    assert.deepEqual(responseJson({ data: '{"version":7}', headers: { 'content-type': 'application/json' } }), { version: 7 });
    assert.deepEqual(responseJson({ data: '{"version":"v2"}', headers: { 'Content-Type': 'application/json; charset=utf-8' } }), { version: 'v2' });
});
test('a string JSON body with no content type parses when it is JSON', () => {
    assert.deepEqual(responseJson({ data: '{"version":3}' }), { version: 3 });
    assert.deepEqual(responseJson({ data: '[1,2]', headers: {} }), [1, 2]);
});
test('an object body comes back as the same object', () => {
    const body = { version: 4 };
    assert.equal(responseJson({ data: body }), body);
});
test('a non-JSON string throws, naming the content type, never a silent null', () => {
    assert.throws(() => responseJson({ data: '<html>Server Error</html>', headers: { 'content-type': 'text/html' } }), error => error instanceof ResponseJsonError && /not JSON \(content type: text\/html\)/.test(error.message));
    assert.throws(() => responseJson({ data: '{"version":', headers: { 'content-type': 'application/json' } }), ResponseJsonError, 'a JSON content type with a broken body still throws');
    assert.throws(() => responseJson({ data: 'ok' }), /content type: none/);
});
test('an empty body throws unless the caller allows one, which reads as null', () => {
    for (const data of ['', '   ', undefined, null]) {
        assert.throws(() => responseJson({ data }), /empty body/, JSON.stringify(data));
        assert.equal(responseJson({ data }, { allowEmpty: true }), null);
    }
});
test('errorJson reads a refusal body, and an empty or non-JSON one as null with its status', () => {
    assert.deepEqual(errorJson({ response: { status: 409, data: '{"error":"stale_write"}' } }), { status: 409, body: { error: 'stale_write' } });
    assert.deepEqual(errorJson({ response: { status: 500, data: '<html></html>' } }), { status: 500, body: null });
    assert.deepEqual(errorJson({ response: { status: 409, data: '' } }), { status: 409, body: null });
    assert.deepEqual(errorJson(new Error('offline')), { status: undefined, body: null });
});
