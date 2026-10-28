import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Kit stubs never render a native <select> or Reka's modal Select: a choice goes through InputSelect (searchable past 8 options), and a time zone through
// TimezoneSelect, which is an InputSelect. The deprecated Select component itself is the one exempt file.
const root = fileURLToPath(new URL('../../resources/features', import.meta.url));
const stubs = readdirSync(root, { recursive: true }).filter((path) => path.endsWith('.vue.stub'));
const exempt = new Set(['core/components/Kit/Form/Select.vue.stub']);

export function selectViolations(source) {
    const template = source.replace(/<script[\s\S]*?<\/script>/g, '');
    const found = [];
    if (/<select[\s>]/.test(template)) found.push('a native <select>');
    if (/<input[^>]*name="timezone"[^>]*>/i.test(template) && !/type="hidden"/i.test(template.match(/<input[^>]*name="timezone"[^>]*>/i)[0])) found.push('a time zone typed into a plain input');
    if (/<Select[\s>]/.test(template) && /name="timezone"/.test(template)) found.push('a time zone on the deprecated Select');

    return found;
}

test('the check fails on a native select and a time zone on the deprecated Select', () => {
    assert.deepEqual(selectViolations('<template><select name="timezone"><option>UTC</option></select></template>'), ['a native <select>']);
    assert.deepEqual(selectViolations('<template><Select id="tz" name="timezone" :options="zones" /></template>'), ['a time zone on the deprecated Select']);
    assert.deepEqual(selectViolations('<template><TimezoneSelect id="tz" name="timezone" /></template>'), []);
});

for (const stub of stubs.filter((path) => !exempt.has(path.replaceAll('\\', '/')))) {
    test(`${stub} renders no native select`, () => assert.deepEqual(selectViolations(readFileSync(join(root, stub), 'utf8')), []));
}

test('TimezoneSelect is a searchable InputSelect with the current zone first', () => {
    const source = readFileSync(join(root, 'core/components/Kit/Form/TimezoneSelect.vue.stub'), 'utf8');
    assert.match(source, /import InputSelect from '\.\/InputSelect\.vue'/);
    assert.match(source, /<InputSelect[^>]*\ssearchable[\s>]/);
    assert.match(source, /chosen-first/);
    assert.doesNotMatch(source, /from '\.\/Select\.vue'/);
});

for (const page of ['settings/pages/Workspace/Settings/General/Page.vue.stub', 'session-ui/pages/Welcome/Timezone/Page.vue.stub', 'session-ui/pages/Welcome/WorkspaceTimezone/Page.vue.stub']) {
    test(`${page} picks its time zone with TimezoneSelect`, () => assert.match(readFileSync(join(root, page), 'utf8'), /<TimezoneSelect[^>]*name="timezone"/));
}
