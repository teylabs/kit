import assert from 'node:assert/strict';
import test from 'node:test';
import { existsSync, readdirSync, readFileSync } from 'node:fs';

// The kit's adoption inventory (resources/adoption/surfaces.json, format 1 in surfaces.schema.json), which standards:adoption reads. Everything the kit ships is
// a surface or a listed non-surface, with no exemptions: a new barrel export or feature without an entry fails here.
const root = new URL('../../', import.meta.url);
const read = path => readFileSync(new URL(path, root), 'utf8');
const inventory = JSON.parse(read('resources/adoption/surfaces.json'));
const schema = JSON.parse(read('resources/adoption/surfaces.schema.json'));
const alias = `${inventory.package.alias}:`;

// Runtime exports of the barrel; type-only exports are not surfaces.
function barrelExports() {
    const source = read('resources/features/core/components/Kit/index.ts.stub').replace(/\/\/.*$/gm, '');
    const names = new Set();
    for (const [, body] of source.matchAll(/^export \{([^}]*)\} from/gm)) {
        for (const part of body.split(',').map(p => p.trim()).filter(Boolean)) {
            if (part.startsWith('type ')) continue;
            names.add(part.split(/\s+as\s+/).pop());
        }
    }
    return names;
}

const surfaces = inventory.surfaces;
const imports = surfaces.flatMap(s => s.uses.imports ?? []);
const refs = inventory.nonSurfaces.map(n => n.ref);

test('the inventory is format 1, for this package, with only the schema\'s top-level fields', () => {
    assert.equal(inventory.format, 1);
    assert.equal(inventory.package.composer, JSON.parse(read('composer.json')).name);
    assert.deepEqual(Object.keys(inventory).sort(), Object.keys(schema.properties).sort());
});

test('every runtime barrel export is a surface import or a listed non-surface', () => {
    const missing = [...barrelExports()].filter(name => !imports.includes(alias + name) && !refs.includes(alias + name));
    assert.deepEqual(missing, [], 'add each to a surface\'s uses.imports, or to nonSurfaces with a reason');
});

test('every surface import and non-surface ref names something the kit ships', () => {
    const exported = barrelExports();
    const features = readdirSync(new URL('resources/features/', root), { withFileTypes: true }).filter(d => d.isDirectory()).map(d => d.name);
    for (const ref of [...imports, ...refs]) {
        if (ref.startsWith(alias)) assert.ok(exported.has(ref.slice(alias.length)), `${ref} is not a barrel export`);
        else if (ref.startsWith('feature:')) assert.ok(features.includes(ref.slice(8)), `${ref} is not a feature`);
        else assert.fail(`${ref}: refs are "${alias}Name" or "feature:<id>"`);
    }
});

test('every feature ships a surface or is a listed non-surface', () => {
    const features = readdirSync(new URL('resources/features/', root), { withFileTypes: true }).filter(d => d.isDirectory()).map(d => d.name);
    const covered = new Set([...surfaces.map(s => s.feature), ...refs.filter(r => r.startsWith('feature:')).map(r => r.slice(8))]);
    assert.deepEqual(features.filter(f => !covered.has(f)), []);
    for (const s of surfaces) assert.ok(features.includes(s.feature), `${s.id}: feature ${s.feature} does not exist`);
});

test('uses.files are files a feature installs, and uses.classes exist in src', () => {
    const installed = new Set(readdirSync(new URL('resources/features/', root), { withFileTypes: true }).filter(d => d.isDirectory())
        .flatMap(d => Object.keys(JSON.parse(read(`resources/features/${d.name}/feature.json`)).files ?? {})));
    for (const s of surfaces) {
        for (const file of s.uses.files ?? []) assert.ok(installed.has(file), `${s.id}: ${file} is not installed by any feature`);
        for (const cls of (s.uses.classes ?? []).filter(c => c.startsWith('Tey\\Kit\\'))) {
            assert.ok(existsSync(new URL(`src/${cls.slice(8).replaceAll('\\', '/')}.php`, root)), `${s.id}: ${cls} does not exist`);
        }
    }
});

test('every fact is computed, never assumed: a standards analysis or signals that include the kit\'s own components, and every fact is used', () => {
    const computed = schema.properties.facts.items.properties.computed.oneOf;
    const analyses = computed[0].properties.analysis.enum;
    const kinds = new Map(schema.$defs.signal.oneOf.map(k => [k.properties.kind.const, k]));
    const used = new Set(JSON.stringify(surfaces.map(s => s.applies)).match(/[a-z][a-z0-9-]+/g));
    for (const fact of inventory.facts) {
        assert.ok(fact.computed, `${fact.id}: says how it is computed`);
        const keys = Object.keys(fact.computed);
        assert.equal(keys.length, 1, `${fact.id}: analysis or signals, not both`);
        if (keys[0] === 'analysis') {
            assert.ok(analyses.includes(fact.computed.analysis), `${fact.id}: unknown analysis ${fact.computed.analysis}`);
        } else {
            assert.equal(keys[0], 'signals', `${fact.id}: unknown computation`);
            assert.ok(fact.computed.signals.length > 0, `${fact.id}: at least one signal`);
            for (const signal of fact.computed.signals) {
                const kind = kinds.get(signal.kind);
                assert.ok(kind && kind.properties.kind.const !== 'rule', `${fact.id}: ${signal.kind} cannot compute a fact`);
                assert.deepEqual(kind.required.filter(k => !(k in signal)), [], `${fact.id}: ${signal.kind} misses fields`);
                assert.deepEqual(Object.keys(signal).filter(k => !(k in kind.properties)), [], `${fact.id}: ${signal.kind} has unknown fields`);
            }
            // An app that adopted the surface has the need too, so the kit's own import is evidence; it is a real export.
            const kit = fact.computed.signals.flatMap(s => s.specifiers ?? []).filter(s => s.startsWith('@/components/Kit#'));
            assert.ok(kit.length > 0, `${fact.id}: the kit's own components count as evidence`);
            for (const specifier of kit) assert.ok(barrelExports().has(specifier.split('#')[1]), `${fact.id}: ${specifier} is not a barrel export`);
        }
        assert.ok(used.has(fact.id), `${fact.id}: no surface applies by it`);
    }
});

test('need-based surfaces hang off what the app renders, not merely on having signed-in pages', () => {
    const byId = Object.fromEntries(surfaces.map(s => [s.id, s.applies]));
    for (const [id, fact] of Object.entries({ 'action.menu': 'has-menus', 'overlay.modal': 'has-dialogs', 'data.avatar': 'has-avatars', 'data.badge': 'has-badges', 'feedback.alert': 'has-alerts', 'action.bar': 'has-page-actions', 'layout.blocks': 'has-content-panels' })) {
        assert.equal(byId[id], fact, `${id} applies by ${fact}`);
    }
    // A hidden or search input is not a hand-rolled form control, and only a file input is a hand-rolled upload.
    const tag = id => surfaces.find(s => s.id === id).handRolled.find(signal => signal.kind === 'template-tag');
    assert.deepEqual(tag('form.inputs').except, { type: ['hidden', 'search', 'file'] });
    assert.deepEqual(tag('form.files').where, { type: ['file'] });
    // An app mail view counts as hand-rolled only when it builds on none of the mail layouts the kit brands.
    assert.ok(surfaces.find(s => s.id === 'mail.layout').handRolled.find(signal => signal.kind === 'mail-view').without.length > 0);
});

test('surfaces follow the schema: unique ids, closed groups, known facts, closed signal kinds, since when new', () => {
    const def = schema.$defs.surface;
    const facts = new Set(inventory.facts.map(f => f.id));
    const kinds = new Map(schema.$defs.signal.oneOf.map(k => [k.properties.kind.const, k]));
    const conditionFacts = c => (c === true ? [] : typeof c === 'string' ? [c] : (c.all ?? c.any ?? [c.not]).flatMap(conditionFacts));
    assert.equal(new Set(surfaces.map(s => s.id)).size, surfaces.length, 'surface ids are unique');
    assert.equal(facts.size, inventory.facts.length, 'fact ids are unique');
    for (const s of surfaces) {
        assert.deepEqual(def.required.filter(k => !(k in s)), [], `${s.id}: missing fields`);
        assert.deepEqual(Object.keys(s).filter(k => !(k in def.properties)), [], `${s.id}: unknown fields`);
        assert.ok(def.properties.layer.enum.includes(s.layer), `${s.id}: layer`);
        assert.ok(def.properties.group.enum.includes(s.group), `${s.id}: group`);
        assert.ok(def.properties.status.enum.includes(s.status), `${s.id}: status`);
        if (s.status === 'new') assert.ok(s.since, `${s.id}: a new surface says since when`);
        assert.ok(Object.keys(s.uses).length > 0, `${s.id}: uses needs evidence`);
        for (const fact of conditionFacts(s.applies)) assert.ok(facts.has(fact), `${s.id}: unknown fact ${fact}`);
        for (const signal of s.handRolled) {
            const kind = kinds.get(signal.kind);
            assert.ok(kind, `${s.id}: unknown signal kind ${signal.kind}`);
            assert.deepEqual(kind.required.filter(k => !(k in signal)), [], `${s.id}: ${signal.kind} misses fields`);
            assert.deepEqual(Object.keys(signal).filter(k => !(k in kind.properties)), [], `${s.id}: ${signal.kind} has unknown fields`);
        }
    }
});

test('the adoption fixtures name real surfaces, facts and rows, and each app tree exists', () => {
    const expected = JSON.parse(read('tests/fixtures/adoption/expected.json'));
    const facts = new Set(inventory.facts.map(f => f.id));
    for (const c of expected.cases) {
        assert.ok(surfaces.some(s => s.id === c.surface), `${c.app}: unknown surface ${c.surface}`);
        assert.ok(['adopted', 'hand-rolled', 'missing', 'n/a'].includes(c.row), `${c.app}: unknown row ${c.row}`);
        for (const fact of Object.keys(c.facts)) assert.ok(facts.has(fact), `${c.app}: unknown fact ${fact}`);
        assert.ok(existsSync(new URL(`tests/fixtures/adoption/${c.app}/`, root)), `${c.app}: missing app tree`);
    }
    // mail.layout reads adopted for an app on the kit's mail: no signal fires on a Mailable or on a view built on x-mail::message.
    const mail = surfaces.find(s => s.id === 'mail.layout');
    assert.ok(!mail.handRolled.some(signal => signal.kind === 'php-class'), 'a Mailable alone is not a hand-rolled layout');
    assert.ok(mail.uses.components.includes('x-mail::message'));
    // Passkeys are optional per app (Jasper's ruling): the surface applies only once the app opts in, so it can never fail the zero-exemption gate otherwise.
    assert.deepEqual(surfaces.find(s => s.id === 'auth.passkeys').applies, { all: ['has-auth-screens', 'uses-passkeys'] });
});

test('calibrated against legacy apps: narrow signals stay narrow', () => {
    const byId = Object.fromEntries(surfaces.map(s => [s.id, s]));
    const globs = id => byId[id].handRolled.flatMap(signal => signal.globs ?? []);
    assert.ok(!globs('layout.blocks').some(g => g.includes('Panel')), 'a *Panel is not a content panel');
    assert.ok(!globs('feed.activity').some(g => g.includes('History')), 'a history list is not an activity feed');
    assert.ok(!globs('data.badge').some(g => g.includes('Chip')), 'a chip is not a badge');
    assert.deepEqual(byId.workbench.applies, { all: ['has-sequence-editors', 'has-editor-pages'] }, 'a drag library alone is not a sequence editor');
    assert.ok(!byId['data.table'].handRolled.some(signal => signal.kind === 'template-tag' && signal.tags.includes('table')), 'a table needs a header row');
    // ui.kit-shell is active only once the kit shell is adopted, so a legacy app's own page header needs a signal of its own.
    assert.ok(byId['layout.page'].handRolled.some(signal => signal.kind === 'file-name'), 'a legacy app without the kit shell can read hand-rolled');
    assert.ok(byId['overlay.modal'].replaces.npm.includes('@inertiaui/modal-vue'), 'the library the kit Modal wraps');
});

test('account and settings pages are read wherever they live, and a domain *Settings component is not one', () => {
    const byId = Object.fromEntries(surfaces.map(s => [s.id, s]));
    // An account page at /account (no `settings` URI) still makes account.pages, settings.frame and workspace.settings apply.
    assert.deepEqual(byId['account.pages'].applies, { any: ['has-settings-pages', 'has-account-pages'] });
    assert.deepEqual(byId['workspace.settings'].applies, { all: ['has-workspaces', { any: ['has-settings-pages', 'has-workspace-settings-pages'] }] });
    for (const id of ['settings.frame', 'mcp.connections']) assert.ok(byId[id], `${id} is a surface`);
    assert.deepEqual(byId['settings.frame'].uses.files, ['resources/js/pages/Workspace/Account/Layout.vue', 'resources/js/pages/Workspace/Settings/Layout.vue']);
    assert.deepEqual(byId['mcp.connections'].uses.files, ['resources/js/pages/Workspace/Settings/Connections/Page.vue']);
    // Path globs as standards reads them: `**` crosses folders, `*` and `?` stay in one segment, case-insensitive.
    const matches = (glob, path) => new RegExp(`^${glob.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\/\*\*\/|\*\*|\*|\?/g, t => ({ '/**/': '/(?:.*/)?', '**': '.*', '*': '[^/]*', '?': '[^/]' })[t])}$`, 'i').test(path);
    const fires = (signals, path) => signals.some(s => s.kind === 'file-name' && s.globs.some(g => matches(g, path)) && !(s.except ?? []).some(g => matches(g, path)));
    const signalsOf = id => [...(byId[id]?.handRolled ?? []), ...(inventory.facts.find(f => f.id === id)?.computed.signals ?? [])];
    const hand = ['account.pages', 'settings.frame', 'workspace.settings', 'mcp.connections', 'has-account-pages', 'has-workspace-settings-pages'];
    for (const path of [
        'resources/js/pages/Deck/Edit/Partials/SlideSettings.vue',
        'resources/js/pages/Presentation/Edit/Partials/PresentationSettings.vue',
        'resources/js/components/SlideSettings.vue',
        'resources/js/pages/Workspace/Account/Profile/Page.vue',
        'resources/js/pages/Workspace/Account/Layout.vue',
        'resources/js/pages/Workspace/Settings/General/Page.vue',
        'resources/js/pages/Workspace/Settings/Connections/Page.vue',
        'resources/js/components/ui/settings/Account.vue',
    ]) {
        for (const id of hand) assert.ok(!fires(signalsOf(id), path), `${id} must not flag ${path}`);
    }
    for (const [id, path] of [
        ['account.pages', 'resources/js/pages/Account/Show/Page.vue'],
        ['account.pages', 'resources/js/pages/settings/Profile.vue'],
        ['account.pages', 'resources/js/pages/Settings/Security/Page.vue'],
        ['settings.frame', 'resources/js/pages/Account/Layout.vue'],
        ['settings.frame', 'resources/js/layouts/settings/Layout.vue'],
        ['workspace.settings', 'resources/js/pages/Workspace/Deletion/Page.vue'],
        ['workspace.settings', 'resources/js/pages/Teams/Show.vue'],
        ['mcp.connections', 'resources/js/pages/ConnectedApp/Index/Page.vue'],
        ['mcp.connections', 'resources/js/pages/Settings/ApiTokens/Page.vue'],
        ['mcp.connections', 'resources/js/pages/Account/Assistants/Page.vue'],
    ]) assert.ok(fires(signalsOf(id), path), `${id} flags ${path}`);
});
