import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// StageNotice's placement and tone run here without Vue; the component is checked by source (the kit has no browser test runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-stage-notice-'));
// A stand-in for class-variance-authority that keeps the compound class for a variant and tier, as the real cva does.
const cva = join(dir, 'node_modules', 'class-variance-authority');
mkdirSync(cva, { recursive: true });
writeFileSync(join(cva, 'package.json'), JSON.stringify({ name: 'class-variance-authority', type: 'module', main: 'index.js' }));
writeFileSync(join(cva, 'index.js'), 'export const cva = (base, { compoundVariants, defaultVariants }) => (props = {}) => { const p = { ...defaultVariants, ...props }; return [base, ...compoundVariants.filter(c => c.variant === p.variant && c.tier === p.tier).map(c => c.class)].join(" "); };\n');
for (const name of ['alertVariants', 'stageNotice']) {
    writeFileSync(join(dir, `${name}.ts`), readFileSync(new URL(`Feedback/${name}.ts.stub`, kit), 'utf8').replace("from './alertVariants';", "from './alertVariants.ts';"));
}
const notice = await import(pathToFileURL(join(dir, 'stageNotice.ts')).href);
const source = readFileSync(new URL('Feedback/StageNotice.vue.stub', kit), 'utf8');

test('placement: top, bottom and the four corners, inset in container units; an unknown one is top', () => {
    assert.deepEqual(notice.STAGE_NOTICE_PLACEMENTS, ['top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right']);
    assert.match(notice.stageNoticeClass('info', 'top'), /top-\[var\(--stage-notice-inset,3cqw\)\] left-1\/2 -translate-x-1\/2/);
    assert.match(notice.stageNoticeClass('info', 'bottom'), /bottom-\[var\(--stage-notice-inset,3cqw\)\] left-1\/2/);
    assert.match(notice.stageNoticeClass('info', 'top-left'), /top-\[var\(--stage-notice-inset,3cqw\)\] left-\[var\(--stage-notice-inset,3cqw\)\]/);
    assert.match(notice.stageNoticeClass('info', 'bottom-right'), /bottom-\[var\(--stage-notice-inset,3cqw\)\] right-\[var\(--stage-notice-inset,3cqw\)\]/);
    assert.match(notice.stageNoticeClass('info', 'middle'), /^top-\[/);
});
test('tone: the shared alert colours (solid by default, pale on request); an unknown tone is info', () => {
    assert.match(notice.stageNoticeClass('info'), /bg-sky-800 text-white/);
    assert.match(notice.stageNoticeClass('success'), /bg-green-800/);
    assert.match(notice.stageNoticeClass('warning'), /bg-amber-800/);
    assert.match(notice.stageNoticeClass('danger'), /bg-red-800/);
    assert.match(notice.stageNoticeClass('danger', 'top', 'pale'), /bg-red-50 text-foreground/);
    assert.match(notice.stageNoticeClass('brand'), /bg-sky-800/);
    assert.deepEqual(notice.STAGE_NOTICE_TONES, ['info', 'success', 'warning', 'danger']);
});
test('it enters from its own side, and only moves when motion is allowed', () => {
    assert.equal(notice.stageNoticeEnterFrom('top'), 'motion-safe:-translate-y-[1cqw]');
    assert.equal(notice.stageNoticeEnterFrom('bottom-left'), 'motion-safe:translate-y-[1cqw]');
    for (const transition of ['enter-active-class', 'leave-active-class']) {
        const match = source.match(new RegExp(`${transition}="([^"]+)"`));
        assert.ok(match && match[1].split(' ').every(cls => cls.startsWith('motion-safe:')), `${transition} only under motion-safe`);
    }
    assert.match(source, /const enterFrom = computed\(\(\) => `motion-safe:opacity-0 \$\{stageNoticeEnterFrom/);
});
test('the component: slot content, pointer-events none, sized in cq units, positioned in its stage, a polite status unless off', () => {
    assert.match(source, /<slot \/>/);
    assert.match(source, /'pointer-events-none absolute /);
    assert.match(source, /text-\[length:var\(--stage-notice-size,2\.5cqw\)\]/);
    assert.match(source, /max-w-\[90cqw\]/);
    assert.match(source, /:role="live === 'polite' \? 'status' : undefined"/);
    assert.match(source, /:aria-live="live === 'polite' \? 'polite' : undefined"/);
    assert.doesNotMatch(source, /<style/, 'Tailwind utilities and custom properties only');
    assert.doesNotMatch(source, / style=/);
});
test('it is exported from the kit barrel and described in the contract', () => {
    const barrel = readFileSync(new URL('index.ts.stub', kit), 'utf8');
    assert.match(barrel, /export \{ default as StageNotice \} from '\.\/Feedback\/StageNotice\.vue';/);
    assert.match(readFileSync(new URL('contract.ts.stub', kit), 'utf8'), /export interface StageNoticeProps \{ tone\?: 'info' \| 'success' \| 'warning' \| 'danger'/);
});
