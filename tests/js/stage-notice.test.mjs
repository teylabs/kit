import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// StageNotice's placement and tone run here without Vue; the component is checked by source (the kit has no browser test runner).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const dir = mkdtempSync(join(tmpdir(), 'kit-stage-notice-'));
// A stand-in for class-variance-authority that keeps the compound class for a variant and tier, as the real cva does.
const cva = join(dir, 'node_modules', 'class-variance-authority');
mkdirSync(cva, { recursive: true });
writeFileSync(join(cva, 'package.json'), JSON.stringify({ name: 'class-variance-authority', type: 'module', main: 'index.js' }));
writeFileSync(join(cva, 'index.js'), 'export const cva = (base, { compoundVariants, defaultVariants }) => (props = {}) => { const p = { ...defaultVariants, ...props }; return [base, ...compoundVariants.filter(c => c.variant === p.variant && c.tier === p.tier).map(c => c.class)].join(" "); };\n');
for (const name of ['alertVariants', 'stageNotice']) {
    writeFileSync(join(dir, `${name}.ts`), readFileSync(new URL(`Feedback/${name}.ts`, kit), 'utf8').replace("from './alertVariants';", "from './alertVariants.ts';"));
}
const notice = await import(pathToFileURL(join(dir, 'stageNotice.ts')).href);
const source = readFileSync(new URL('Feedback/StageNotice.vue', kit), 'utf8');

test('placement: top, bottom and the four corners, inset in container units; an unknown one is top', () => {
    assert.deepEqual(notice.STAGE_NOTICE_PLACEMENTS, ['top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right']);
    assert.match(notice.stageNoticeClass('info', 'top'), /top-\[var\(--stage-notice-inset,3cqw\)\] left-1\/2 -translate-x-1\/2/);
    assert.match(notice.stageNoticeClass('info', 'bottom'), /bottom-\[var\(--stage-notice-inset,3cqw\)\] left-1\/2/);
    assert.match(notice.stageNoticeClass('info', 'top-left'), /top-\[var\(--stage-notice-inset,3cqw\)\] left-\[var\(--stage-notice-inset,3cqw\)\]/);
    assert.match(notice.stageNoticeClass('info', 'bottom-right'), /bottom-\[var\(--stage-notice-inset,3cqw\)\] right-\[var\(--stage-notice-inset,3cqw\)\]/);
    assert.match(notice.stageNoticeClass('info', 'middle'), /^top-\[/);
});
