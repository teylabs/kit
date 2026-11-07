import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// FormStatus is checked by source here, and in a real browser by browser/run.mjs (?form-status): a persistent, polite confirmation that composes Alert and never times out.
const root = new URL('../../resources/features/', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const kit = 'core/components/Kit/';
const source = read(`${kit}Form/FormStatus.vue.stub`);
const script = source.split('</script>')[0];
const template = source.split('<template>')[1];

test('session-ui shows its after-submit statuses with FormStatus inside the form, not a hand-made Alert', () => {
    const forgot = read('session-ui/pages/Auth/ForgotPassword/Page.vue.stub');
    assert.match(forgot, /method="post" class="grid gap-4"[^>]*>\n\s+<FormStatus data-reset-link-sent \/>/);
    const verify = read('session-ui/pages/Auth/VerifyEmail/Page.vue.stub');
    assert.match(verify, /<FormStatus :message="status === 'verification-link-sent' \? 'A new link is on its way\.' : null" data-verification-sent \/>/);
    assert.match(read('session-ui/pages/Auth/Login/Page.vue.stub'), /<FormStatus data-login-status \/>/);
    // ResetPassword matches ForgotPassword: the status prop, first inside the form, before the hidden token and address.
    const reset = read('session-ui/pages/Auth/ResetPassword/Page.vue.stub');
    assert.match(reset, /method="post" :reset-on-success="\['password', 'password_confirmation'\]"[^>]* class="grid gap-4"[^>]*>\n\s+<FormStatus data-reset-password-status \/>\n/);
    assert.ok(reset.indexOf('<FormStatus data-reset-password-status />') < reset.indexOf('<Input type="hidden" name="token"'), 'the status comes before the hidden token and address');
    for (const page of [forgot, verify, read('session-ui/pages/Auth/Login/Page.vue.stub'), reset]) {
        assert.match(page, /import \{[^}]*\bFormStatus\b[^}]*\} from '@kit\/ui';/);
        // An Alert here is only an error (a 429, a bad reset token): assertive and danger. A status is never a hand-made Alert.
        assert.doesNotMatch(page, /<Alert(?![^>]*variant="danger" live="alert")|text-green-600/);
    }
});
