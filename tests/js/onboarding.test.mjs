import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The onboarding UI: the password rules under a new-password field, and the post-sign-up passkey offer on Fortify's registration endpoints.
const kit = new URL('../../resources/features/', import.meta.url);
const source = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-onboarding-'));
copyFileSync(new URL('../js/components/Kit/http.ts', kit), join(dir, 'http.ts'));
writeFileSync(join(dir, 'passkeyOffer.ts'), source('../js/components/Kit/Feedback/passkeyOffer.ts').replace("from '../http';", "from './http.ts';"));
const passkey = await import(pathToFileURL(join(dir, 'passkeyOffer.ts')).href);

test('a passkey is offered only where the browser can create one from JSON options', () => {
    assert.equal(passkey.passkeysSupported({}), false);
    assert.equal(passkey.passkeysSupported({ PublicKeyCredential: {}, navigator: { credentials: { create() {} } } }), false);
    assert.equal(passkey.passkeysSupported({ PublicKeyCredential: { parseCreationOptionsFromJSON() {} }, navigator: { credentials: { create() {} } } }), true);
    assert.equal(passkey.passkeyName('macOS'), 'macOS passkey');
    assert.equal(passkey.passkeyName(undefined), 'Passkey');
});
test('registering runs Fortify\'s ceremony: options, the browser\'s create(), then the credential as JSON', async () => {
    const calls = [];
    const http = { request: async config => { calls.push(config); return { data: config.method === 'get' ? { options: { challenge: 'abc' } } : {} }; } };
    const scope = { PublicKeyCredential: { parseCreationOptionsFromJSON: json => ({ parsed: json }) }, navigator: { credentials: { create: async ({ publicKey }) => ({ toJSON: () => ({ id: 'cred', from: publicKey }) }) } } };
    await passkey.registerPasskey(http, { options_url: '/user/passkeys/options', store_url: '/user/passkeys' }, 'macOS passkey', scope);
    assert.deepEqual(calls.map(call => [call.method, call.url]), [['get', '/user/passkeys/options'], ['post', '/user/passkeys']]);
    assert.deepEqual(calls[1].data, { name: 'macOS passkey', credential: { id: 'cred', from: { parsed: { challenge: 'abc' } } } });
    // Inertia v3's XHR client gives the body as the raw responseText string: the options are parsed from it, and a non-JSON answer throws.
    const strings = { request: async config => ({ data: config.method === 'get' ? '{"options":{"challenge":"xyz"}}' : '', headers: { 'content-type': 'application/json' } }) };
    const created = [];
    await passkey.registerPasskey(strings, { options_url: '/o', store_url: '/s' }, 'x', { ...scope, navigator: { credentials: { create: async ({ publicKey }) => { created.push(publicKey); return { toJSON: () => ({}) }; } } } });
    assert.deepEqual(created, [{ parsed: { challenge: 'xyz' } }]);
    await assert.rejects(() => passkey.registerPasskey({ request: async () => ({ data: '<html></html>', headers: { 'content-type': 'text/html' } }) }, { options_url: '/o', store_url: '/s' }, 'x', scope), /not JSON/);
    const cancelled = { ...scope, navigator: { credentials: { create: async () => null } } };
    await assert.rejects(() => passkey.registerPasskey(http, { options_url: '/o', store_url: '/s' }, 'x', cancelled), /No passkey was created/);
});
test('adding a passkey confirms the password first: null once confirmed, the 422\'s password message inline, anything else thrown', async () => {
    const calls = [];
    const ok = { request: async config => { calls.push(config); return { status: 201, data: '""' }; } };
    assert.equal(await passkey.confirmPasskeyPassword(ok, '/user/confirm-password', 'secret'), null);
    assert.deepEqual(calls.map(call => [call.method, call.url, call.data]), [['post', '/user/confirm-password', { password: 'secret' }]]);
    const refused = { request: async () => { throw Object.assign(new Error('422'), { response: { status: 422, data: '{"message":"x","errors":{"password":["The provided password was incorrect."]}}' } }); } };
    assert.equal(await passkey.confirmPasskeyPassword(refused, '/c', 'wrong'), 'The provided password was incorrect.');
    const down = { request: async () => { throw Object.assign(new Error('500'), { response: { status: 500, data: '<html></html>' } }); } };
    await assert.rejects(() => passkey.confirmPasskeyPassword(down, '/c', 'secret'), /500/);
});
test('a client that resolves non-2xx answers: a resolved 422 is the inline message, any other non-2xx (or no status) throws, so WebAuthn is never reached', async () => {
    const resolved = (status, data) => ({ request: async () => ({ status, data }) });
    assert.equal(await passkey.confirmPasskeyPassword(resolved(422, '{"message":"x","errors":{"password":["The provided password was incorrect."]}}'), '/c', 'wrong'), 'The provided password was incorrect.');
    assert.equal(await passkey.confirmPasskeyPassword(resolved(422, '<html></html>'), '/c', 'wrong'), 'That password is not right.');
    await assert.rejects(() => passkey.confirmPasskeyPassword(resolved(500, ''), '/c', 'secret'), /500/);
    await assert.rejects(() => passkey.confirmPasskeyPassword(resolved(419, ''), '/c', 'secret'), /419/);
    await assert.rejects(() => passkey.confirmPasskeyPassword(resolved(302, ''), '/c', 'secret'), /302/);
    await assert.rejects(() => passkey.confirmPasskeyPassword({ request: async () => ({ data: '' }) }, '/c', 'secret'), /no status/);
    assert.equal(await passkey.confirmPasskeyPassword(resolved(200, ''), '/c', 'secret'), null);
});
test('signing in with a passkey runs Fortify\'s ceremony: options, the browser\'s get(), the credential with remember, then the intended URL', async () => {
    assert.equal(passkey.passkeySignInSupported({}), false);
    assert.equal(passkey.passkeySignInSupported({ PublicKeyCredential: { parseRequestOptionsFromJSON() {} }, navigator: { credentials: {} } }), false);
    assert.equal(passkey.passkeySignInSupported({ PublicKeyCredential: { parseRequestOptionsFromJSON() {} }, navigator: { credentials: { get() {} } } }), true);
    const calls = [];
    const http = { request: async config => { calls.push(config); return { data: config.method === 'get' ? '{"options":{"challenge":"abc"}}' : '{"redirect":"https://app.test/@acme"}', headers: { 'content-type': 'application/json' } }; } };
    const scope = { PublicKeyCredential: { parseRequestOptionsFromJSON: json => ({ parsed: json }) }, navigator: { credentials: { get: async ({ publicKey }) => ({ toJSON: () => ({ id: 'cred', from: publicKey }) }) } } };
    const to = await passkey.signInWithPasskey(http, { options_url: '/passkeys/login/options', login_url: '/passkeys/login' }, true, scope);
    assert.equal(to, 'https://app.test/@acme');
    assert.deepEqual(calls.map(call => [call.method, call.url]), [['get', '/passkeys/login/options'], ['post', '/passkeys/login']]);
    assert.deepEqual(calls[1].data, { credential: { id: 'cred', from: { parsed: { challenge: 'abc' } } }, remember: true });
    // No redirect in the answer: home. A cancelled prompt, or a refused passkey (an error status the client throws on), rejects.
    const plain = { request: async config => ({ data: config.method === 'get' ? { options: {} } : {} }) };
    assert.equal(await passkey.signInWithPasskey(plain, { options_url: '/o', login_url: '/l' }, false, scope), '/');
    await assert.rejects(() => passkey.signInWithPasskey(http, { options_url: '/o', login_url: '/l' }, false, { ...scope, navigator: { credentials: { get: async () => null } } }), /No passkey was chosen/);
    await assert.rejects(() => passkey.signInWithPasskey({ request: async config => { if (config.method === 'post') throw new Error('422'); return { data: { options: {} } }; } }, { options_url: '/o', login_url: '/l' }, false, scope), /422/);
});

test('the passkey offer sits at the top of every shell page', () => {
    assert.match(source('shell/pages/Workspace/Layout.vue.stub'), /<PasskeyOffer \/>\n                <slot \/>/);
});
