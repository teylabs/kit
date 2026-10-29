import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// The PublicShell, PublicMeta and the Markdown page, checked by source (no browser runner): the skip link, the footer from the shared shell, the CTA states, the
// phone width, accessibility and no tracking. The server side (sanitizing, the host step) is tests/PublicShellCheck.php.
const read = path => readFileSync(new URL(`../../resources/features/shell/pages/${path}.stub`, import.meta.url), 'utf8');
const shell = read('Workspace/Partials/PublicShell.vue');
const markup = shell.slice(shell.indexOf('<template>'));
/** The source without its comments, for assertions about what the code reads or declares. */
const code = source => source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '').replace(/<!--[\s\S]*?-->/g, '');

test('a skip link goes to #content, the main landmark', () => {
    assert.match(markup, /<a href="#content" class="sr-only focus:not-sr-only[^"]*" data-public-skip>Skip to content<\/a>/);
    assert.match(markup, /<main id="content" tabindex="-1"/, 'main is the target and can take focus');
    assert.ok(markup.indexOf('data-public-skip') < markup.indexOf('<header'), 'it is the first thing in the shell');
});

test('the footer reads the shared shell footer (identity, links, credit): one source of truth', () => {
    assert.match(shell, /const footer = computed<Footer \| null>\(\(\) => page\.props\.shell\?\.footer \?\? null\);/);
    assert.match(markup, /<nav v-if="footer\?\.links\?\.length" aria-label="Footer" data-public-footer-links>/);
    assert.match(markup, /<li v-for="link in footer\.links" :key="link\.url"><a :href="link\.url"/);
    assert.match(markup, /data-public-credit>\{\{ footer\.credit \}\}/);
    assert.doesNotMatch(shell, /kit\.public\.links/, 'no second config for the same links');
});

test('the CTA: "Sign in" for a guest, "Open your workspace" when signed in, from the shared shell\'s kind only', () => {
    assert.match(shell, /const signedIn = computed\(\(\) => Boolean\(page\.props\.shell\?\.kind\) && page\.props\.shell\?\.kind !== 'guest'\);/);
    assert.match(shell, /const ctaUrl = computed\(\(\) => \(signedIn\.value \? props\.workspaceUrl : props\.signInUrl\) \?\? null\);/);
    assert.match(markup, /<Link v-if="cta && ctaUrl" :href="ctaUrl"/, 'shown only with the host\'s URL for that state');
    assert.match(shell, /signInUrl: null, workspaceUrl: null/, 'the shell names no session route itself');
    assert.match(markup, /\{\{ signedIn \? 'Open your workspace' : 'Sign in' \}\}/);
    assert.match(markup, /:data-public-cta="signedIn \? 'workspace' : 'sign-in'"/);
    assert.match(markup, /<slot name="cta">/, 'the host can replace it');
    assert.match(shell, /cta: true/);
    assert.doesNotMatch(code(shell), /\.user\b|\.workspace\b|navigation|permissions?/, 'nothing personal is read');
});

test('phone width (390px): nothing overflows, targets stay 44px', () => {
    assert.match(markup, /flex min-h-16 w-full max-w-7xl min-w-0 flex-wrap items-center/, 'the header wraps instead of overflowing');
    assert.match(markup, /px-4 py-8[^"]*sm:px-6/, 'phone gutters on main');
    assert.match(markup, /flex-col gap-3[^"]*sm:flex-row/, 'the footer stacks on a phone');
    assert.match(markup, /min-h-11 items-center rounded-md px-3/, 'the CTA is a 44px target');
    assert.match(markup, /inline-flex min-h-11 items-center underline-offset-4[^"]*sm:min-h-0/, 'footer links are 44px on a phone');
});

test('accessibility: labelled landmarks, focus rings, and no tracking', () => {
    assert.match(markup, /<nav v-if="\$slots\.nav" aria-label="Site"/);
    assert.match(markup, /<header /);
    assert.match(markup, /<footer /);
    assert.equal((markup.match(/focus-visible:ring-2/g) ?? []).length >= 2, true, 'links and the CTA show a focus ring');
    for (const source of [shell, read('Workspace/Partials/PublicMeta.vue'), read('Public/Markdown/Page.vue')]) assert.doesNotMatch(source, /<script src|gtag|analytics|pixel|plausible|fbq/i, 'no tracking');
    assert.match(shell, /width\?: 'prose' \| 'wide' \| 'full'; tone\?: 'default' \| 'brand'/);
});

test('PublicMeta sets the title, description, canonical, Open Graph and the Twitter card', () => {
    const meta = read('Workspace/Partials/PublicMeta.vue');
    for (const tag of ['head-key="description" name="description"', 'head-key="canonical" rel="canonical"', 'property="og:title"', 'property="og:description"', 'property="og:type"', 'property="og:url"', 'property="og:image"', 'name="twitter:card"']) assert.ok(meta.includes(tag), tag);
    assert.match(meta, /<Head :title="title">/);
});

test('the Markdown page declares no layout (the host routes Public/ pages to the shell) and renders only server-sanitized HTML', () => {
    const page = read('Public/Markdown/Page.vue');
    assert.doesNotMatch(code(page), /layout\s*:|defineOptions\(/);
    assert.equal((code(page).match(/v-html/g) ?? []).length, 1);
    assert.match(page, /v-html="html"/);
    assert.match(page, /<PublicMeta :title="title" :description="description" type="article" \/>/);
});

test('the PublicShell mounts the kit Toaster by default, so a public form\'s flashed toast shows; toaster=false leaves it out', async () => {
    assert.match(code(shell), /toaster\?: boolean/);
    assert.match(code(shell), /toaster: true,/, 'on by default, as in the app shell and the GuestShell');
    assert.match(markup, /<Toaster v-if="toaster" \/>/);
    assert.equal(markup.match(/<Toaster\b/g).length, 1, 'one Toaster');
    // The Toaster renders the page's flashed `toast` on mount and each later flash; a public contact form's redirect toast is an ordinary one.
    const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
    const toaster = code(readFileSync(new URL('Feedback/Toaster.vue.stub', kit), 'utf8'));
    assert.match(toaster, /show\(\(page as unknown as \{ flash\?: Record<string, unknown> \}\)\.flash\?\.toast\);/);
    assert.match(toaster, /router\.on\('flash', /);
    const { mkdtempSync, copyFileSync } = await import('node:fs');
    const { join } = await import('node:path');
    const { tmpdir } = await import('node:os');
    const { pathToFileURL } = await import('node:url');
    const dir = mkdtempSync(join(tmpdir(), 'kit-public-toast-'));
    copyFileSync(new URL('Feedback/toastPlacement.ts.stub', kit), join(dir, 'toastPlacement.ts'));
    const { flashedToast } = await import(pathToFileURL(join(dir, 'toastPlacement.ts')).href);
    const thanks = { id: 't1', type: 'success', message: "Thanks, we'll be in touch", style: 'toast', duration: 4000 };
    assert.deepEqual(flashedToast(thanks), thanks, 'a contact form\'s thank-you toast renders');
});
