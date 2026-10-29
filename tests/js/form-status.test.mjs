import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// FormStatus is checked by source (the kit has no browser test runner): a persistent, polite confirmation that composes Alert and never times out.
const root = new URL('../../resources/features/', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const kit = 'core/components/Kit/';
const source = read(`${kit}Form/FormStatus.vue.stub`);
const script = source.split('</script>')[0];
const template = source.split('<template>')[1];

test('it renders Alert as a polite status (role=status, aria-live polite) and nothing of its own styling', () => {
    assert.match(script, /import Alert from '@\/components\/Kit\/Feedback\/Alert\.vue';/);
    assert.match(template, /^\s*<Alert v-if="text !== null && !hidden" :variant="variant" live="status" data-slot="form-status">\{\{ text \}\}<\/Alert>\s*<\/template>\s*$/);
    assert.doesNotMatch(source, /class="|alertVariants\(/);
    // Alert's live="status" is role=status, which is aria-live polite.
    assert.match(read(`${kit}Feedback/Alert.vue.stub`), /:role="live === 'none' \? \(title \? 'region' : undefined\) : live"/);
});

test('tone: success (default), info or warning, mapped to Alert variants of those names', () => {
    assert.match(script, /type FormStatusTone = 'success' \| 'info' \| 'warning';/);
    assert.match(script, /tone: 'success'/);
    assert.match(script, /const variants: Record<FormStatusTone, AlertVariant> = \{ success: 'success', info: 'info', warning: 'warning' \};/);
    const alertVariants = read(`${kit}Feedback/alertVariants.ts.stub`);
    for (const v of ['success', 'info', 'warning']) assert.match(alertVariants, new RegExp(`'${v}'`), `Alert has a ${v} variant`);
});

test('the text is `message` when given (null shows nothing), else the flashed status page prop; blank is nothing', () => {
    assert.match(script, /const page = usePage\(\);/);
    assert.match(script, /props\.message !== undefined \? props\.message : \(page\.props as \{ status\?: unknown \}\)\.status/);
    assert.match(script, /typeof value === 'string' && value\.trim\(\) !== '' \? value : null/);
    assert.match(script, /message: undefined/);
});

test('it hides on the router start and while form.processing, shows again on finish, and removes its listeners on unmount', () => {
    assert.match(script, /watch\(\(\) => props\.form\?\.processing, \(processing\) => \{\n\s+if \(processing\) hidden\.value = true;/);
    assert.match(script, /onMounted\(\(\) => \{\n\s+offStart = router\.on\('start', \(\) => \{\n\s+hidden\.value = true;/);
    assert.match(script, /offFinish = router\.on\('finish', \(\) => \{\n\s+hidden\.value = false;/);
    assert.match(script, /onBeforeUnmount\(\(\) => \{\n\s+offStart\?\.\(\);\n\s+offFinish\?\.\(\);/);
    assert.equal((script.match(/router\.on\(/g) ?? []).length, 2, 'only the two scoped listeners');
});

test('no timers: it never auto-dismisses', () => {
    assert.doesNotMatch(source, /setTimeout|setInterval|useTimeout|duration/);
});

test('it is a barrel export with a contract entry', () => {
    assert.match(read(`${kit}index.ts.stub`), /^export \{ default as FormStatus \} from '\.\/Form\/FormStatus\.vue';$/m);
    assert.match(read(`${kit}contract.ts.stub`), /export interface FormStatusProps \{ message\?: string \| null; tone\?: 'success' \| 'info' \| 'warning'; form\?: \{ processing: boolean \} \| null \}/);
});

test('session-ui shows its after-submit statuses with FormStatus inside the form, not a hand-made Alert', () => {
    const forgot = read('session-ui/pages/Auth/ForgotPassword/Page.vue.stub');
    assert.match(forgot, /method="post" class="mt-6 grid gap-4">\n\s+<FormStatus data-reset-link-sent \/>/);
    const verify = read('session-ui/pages/Auth/VerifyEmail/Page.vue.stub');
    assert.match(verify, /<FormStatus :message="status === 'verification-link-sent' \? 'A new link is on its way\.' : null" data-verification-sent \/>/);
    assert.match(read('session-ui/pages/Auth/Login/Page.vue.stub'), /<FormStatus class="mt-4" data-login-status \/>/);
    for (const page of [forgot, verify, read('session-ui/pages/Auth/Login/Page.vue.stub')]) {
        assert.match(page, /import \{[^}]*\bFormStatus\b[^}]*\} from '@kit\/ui';/);
        assert.doesNotMatch(page, /<Alert|text-green-600/);
    }
});
