import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { flat, members } from './shape.mjs';

// ActionGroup (docs/action-group.md): `direction` row (the default) or column, one 12px gap, a column's actions full-width at every breakpoint, and the kit's
// auth pages stacking their actions in a column with the primary first. Checked by source, comments stripped; the browser job measures the rendered layout.
const root = new URL('../../', import.meta.url);
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const read = path => readFileSync(new URL(path, root), 'utf8');
const group = flat(strip(read('resources/js/components/Kit/Action/ActionGroup.vue')));

test('direction is row or column, row by default, in the component and the contract', () => {
    assert.match(group, /direction\?: 'row' \| 'column';/);
    assert.match(group, /\{as: 'div', direction: 'row', class: undefined\}/);
    assert.equal(members(read('resources/js/components/Kit/contract.ts'), 'ActionGroupProps').get('direction'), "direction?: 'row' | 'column'");
    assert.match(read('resources/js/components/Kit/index.ts'), /^export \{ default as ActionGroup \} from '\.\/Action\/ActionGroup\.vue';$/m);
});

test('one gap for both directions; a column stretches every child to its width; no breakpoint changes either', () => {
    assert.match(group, /'flex min-w-0 gap-3', props\.direction === 'column' \? 'flex-col items-stretch \*:w-full' : 'flex-wrap items-center', props\.class/);
    assert.doesNotMatch(group, /\b(?:sm|md|lg|xl|2xl|max-[a-z0-9]+|@[a-z0-9]+):/, 'the same at every breakpoint');
    assert.match(group, /data-slot="action-group" :data-direction="props\.direction"/);
    assert.doesNotMatch(group, /role=/, 'layout only: no role of its own');
});

// Each auth page's column groups, with the first control in each: the primary.
const pages = {
    'session-ui/pages/Auth/Login/Page.vue.stub': [/^<Button type="submit"[^>]*data-test="login-button">/],
    'session-ui/pages/Auth/TwoFactor/Page.vue.stub': [/^<Button type="submit"[^>]*data-test="two-factor-submit">/],
    'session-ui/pages/Auth/VerifyEmail/Page.vue.stub': [/^<Button type="submit"[^>]*>\{\{ processing \? 'Sending…' : 'Send the link again' \}\}/],
    'workspace-ui/pages/Auth/Invitation/Show/Page.vue.stub': [/^<Button type="submit"[^>]*data-test="accept-invitation">/, /^<Link href="\/switch-account" method="post" as="button" class="[^"]*\bbg-primary\b[^"]*"[^>]*data-test="invitation-switch-account">/],
};

test('Login, TwoFactor, VerifyEmail and the invitation page stack their actions in a column, the primary first, with no width of their own', () => {
    for (const [file, firsts] of Object.entries(pages)) {
        const page = strip(read(`resources/features/${file}`));
        assert.match(page, /import \{ ActionGroup, [^}]*\} from '@kit\/ui';/, `${file}: imports ActionGroup from @kit/ui`);
        const groups = [...page.matchAll(/<ActionGroup\b([^>]*)>([\s\S]*?)<\/ActionGroup>/g)];
        assert.equal(groups.length, firsts.length, `${file}: ${firsts.length} group(s)`);
        groups.forEach(([, attrs, body], index) => {
            assert.match(attrs, /\bdirection="column"/, `${file}: a column`);
            assert.match(body.trim(), firsts[index], `${file}: the primary action first`);
            const children = body.match(/^\s*<(?:Button|Link)\b[^>]*>/gm);
            assert.ok(children.length >= 2, `${file}: a stack of actions`);
            for (const child of children) assert.doesNotMatch(child, /\bw-full\b/, `${file}: the group owns the width`);
        });
    }
});
