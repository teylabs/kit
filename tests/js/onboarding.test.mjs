import assert from 'node:assert/strict';
import test from 'node:test';
import { copyFileSync, mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// The onboarding UI: the password rules under a new-password field, and the post-sign-up passkey offer on Fortify's registration endpoints.
const kit = new URL('../../resources/features/', import.meta.url);
const source = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-onboarding-'));
copyFileSync(new URL('core/components/Kit/Feedback/passkeyOffer.ts.stub', kit), join(dir, 'passkeyOffer.ts'));
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
test('every kit new-password field shows the rules: invitation sign-up, change password and reset', () => {
    assert.match(source('session-ui/pages/Auth/ResetPassword/Page.vue.stub'), /<PasswordInput id="password" name="password" required autocomplete="new-password" aria-describedby="password-error" rules \/>/);
    assert.match(source('workspace-ui/pages/Auth/Invitation/Show/Page.vue.stub'), /<PasswordInput id="password" name="password" required autocomplete="new-password" aria-describedby="password-error" rules \/>/);
    assert.match(source('workspace-ui/pages/Workspace/Account/Password/Page.vue.stub'), /<PasswordInput id="new-password" name="password" required autocomplete="new-password" aria-describedby="new-password-error" rules \/>/);
    assert.doesNotMatch(source('session-ui/pages/Auth/Login/Page.vue.stub'), /\srules[\s/>]/, 'signing in asks for no rules');
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
    const cancelled = { ...scope, navigator: { credentials: { create: async () => null } } };
    await assert.rejects(() => passkey.registerPasskey(http, { options_url: '/o', store_url: '/s' }, 'x', cancelled), /No passkey was created/);
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

test('a forgotten password is reset on kit pages: the link from sign in, the request, then the new password with its token and address', () => {
    assert.match(source('session-ui/pages/Auth/Login/Page.vue.stub'), /<Link href="\/forgot-password"[^>]*data-forgot-password>Forgot your password\?<\/Link>/);
    const forgot = source('session-ui/pages/Auth/ForgotPassword/Page.vue.stub');
    assert.match(forgot, /<Form v-slot="\{ processing \}" action="\/forgot-password" method="post"/);
    assert.match(forgot, /<Alert v-if="status" variant="success" live="status"/);
    const reset = source('session-ui/pages/Auth/ResetPassword/Page.vue.stub');
    assert.match(reset, /action="\/reset-password" method="post"/);
    assert.match(reset, /<Input type="hidden" name="token" :model-value="token" \/>/);
    assert.match(reset, /<Input type="hidden" name="email" :model-value="email" \/>/);
});
