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

test('surfaces follow the schema: unique ids, closed groups, known facts, closed signal kinds, rule alone, since when new', () => {
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
        if (s.handRolled.some(signal => signal.kind === 'rule')) assert.equal(s.handRolled.length, 1, `${s.id}: a rule signal stands alone`);
    }
});
