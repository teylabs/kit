import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// The trail sits above the page's heading (GOV.UK, Polaris), not in a shell row. Checked by source; the heading's position on the home page and a deep page was
// measured in a headless render (see the PR): the same y on both.
const root = new URL('../../resources/features/', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const crumbs = read('core/components/Kit/Layout/Breadcrumbs.vue.stub');
const page = read('core/components/Kit/Layout/Page.vue.stub');
const layout = read('shell/pages/Workspace/Layout.vue.stub');

test('Page draws the trail in the shell, before its header and independent of the heading', () => {
    assert.match(page, /<Breadcrumbs v-if="inShell" \/>\n\s*<div v-if="headingText \|\| \$slots\.actions"/);
    assert.ok(page.indexOf('<Breadcrumbs') < page.indexOf('data-slot="page-header"'));
});

test('the trail lists the ancestors only; with none it is an aria-hidden spacer of the same height, never an empty landmark', () => {
    assert.match(crumbs, /\.filter\(\(crumb\) => !crumb\.current\)/);
    assert.match(crumbs, /<nav v-if="ancestors\.length" aria-label="Breadcrumb" class="mb-2 flex h-5 /);
    assert.match(crumbs, /<div v-else aria-hidden="true" class="mb-2 h-5" data-slot="breadcrumbs-spacer" \/>/);
});

test('the shell has no breadcrumb row any more, and Breadcrumbs is exported for pages without <Page>', () => {
    assert.doesNotMatch(layout, /data-shell-subbar|<Breadcrumbs/);
    assert.match(read('core/components/Kit/index.ts.stub'), /export \{ default as Breadcrumbs \} from '\.\/Layout\/Breadcrumbs\.vue';/);
    assert.match(read('shell/pages/Workspace/Partials/Breadcrumbs.vue.stub'), /import \{ Breadcrumbs \} from '@kit\/ui';/);
});
