import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';

// The Error page's message and its recovery link are one group: a tight Stack inside the Page, so the Page's child gap never splits them.
const source = readFileSync(new URL('../../resources/features/workspace-ui/pages/Workspace/Error/Page.vue.stub', import.meta.url), 'utf8');

test('the Error page groups its message and recovery link in one tight Stack', () => {
    assert.match(source, /import \{ Page, Stack \} from '@kit\/ui';/);
    assert.match(source, /<Page xs>\s*<Stack class="items-start gap-0">\s*<p role="alert">\{\{ message \}\}<\/p>\s*<Link :href="recovery_url">\{\{ recovery_label \}\}<\/Link>\s*<\/Stack>\s*<\/Page>/);
});
