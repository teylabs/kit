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
copyFileSync(new URL('core/components/Kit/http.ts.stub', kit), join(dir, 'http.ts'));
writeFileSync(join(dir, 'passkeyOffer.ts'), source('core/components/Kit/Feedback/passkeyOffer.ts.stub').replace("from '../http';", "from './http.ts';"));
const passkey = await import(pathToFileURL(join(dir, 'passkeyOffer.ts')).href);

test('the rules show before typing: the length (ticked once met, from the rule in force) and the breach check on submit', () => {
    const rules = source('core/components/Kit/Form/PasswordRules.vue.stub');
    assert.match(rules, /At least \{\{ policy\.min \}\} characters/);
    assert.match(rules, /Not found in a known data breach \(checked when you submit\)/);
    assert.match(rules, /const long = computed\(\(\) => \[\.\.\.\(props\.value \?\? ''\)\]\.length >= policy\.value\.min\);/, 'the length ticks as it is met, counting characters, not bytes');
    assert.match(rules, /:data-met="long \|\| undefined"/);
    assert.match(rules, /shared\?\.min \?\? 8, uncompromised: shared\?\.uncompromised \?\? true/, 'from kitPassword, the kit default without it');
    assert.match(rules, /<li v-if="policy\.uncompromised"/);
    const input = source('core/components/Kit/Form/PasswordInput.vue.stub');
    assert.match(input, /<PasswordRules v-if="rules" :id="rulesId" :value="model" \/>/);
    assert.match(input, /:aria-describedby="describedBy"/, 'the rules join the field\'s description');
});
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

test('the offer is dismissible, says when it failed, and sits at the top of every shell page', () => {
    const offer = source('core/components/Kit/Feedback/PasskeyOffer.vue.stub');
    assert.match(offer, /Add a passkey for next time/);
    assert.match(offer, /data-passkey-dismiss @click="close">Not now</);
    assert.match(offer, /aria-label="Dismiss the passkey offer"/);
    assert.match(offer, /router\.delete\(offer\.value\.dismiss_url/);
    assert.match(offer, /role="alert"[^>]*data-passkey-failed/);
    assert.match(offer, /const shown = computed\(\(\) => offer\.value !== null && supported\.value\);/);
    assert.match(source('shell/pages/Workspace/Layout.vue.stub'), /<PasskeyOffer \/>\n                <slot \/>/);
});
