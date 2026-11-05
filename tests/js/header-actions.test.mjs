import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Page header actions: headerSplit runs here without Vue (plain TypeScript); Page, FloatingToolbar and the Workbench's actions row are checked by source, comments
// stripped (no browser runner).
const kit = new URL('../../resources/features/core/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const strip = s => s.replace(/<!--[\s\S]*?-->/g, '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"`])\/\/.*$/gm, '$1');
const dir = mkdtempSync(join(tmpdir(), 'kit-header-actions-'));
writeFileSync(join(dir, 'headerSplit.ts'), read('Action/headerSplit.ts.stub'));
const { headerSplit, keepsActionBar } = await import(pathToFileURL(join(dir, 'headerSplit.ts')).href);

const a = (label, extra = {}) => ({ label, url: `/${label}`, method: 'post', ...extra });
const labels = split => ({ primary: split.primary?.label ?? null, secondary: split.secondary.map(x => x.label), overflow: split.overflow.map(x => x.label) });
const eight = [a('Open', { method: 'get' }), a('Duplicate'), a('Delete', { destructive: true }), a('Export'), a('Share'), a('Archive', { destructive: true }), a('Print'), a('History', { method: 'get' })];

test('one action: it is the primary, and the header keeps the ActionBar path', () => {
    assert.equal(keepsActionBar([a('Save')]), true);
    assert.deepEqual(labels(headerSplit([a('Save')])), { primary: 'Save', secondary: [], overflow: [] });
});

test('three actions without a priority keep the ActionBar path; one priority leaves it', () => {
    const three = [a('Save'), a('Preview', { method: 'get' }), a('Delete', { destructive: true })];
    assert.equal(keepsActionBar(three), true);
    assert.equal(keepsActionBar([...three, a('Copy')]), false);
    assert.equal(keepsActionBar([a('Save', { priority: 'primary' }), a('Copy')]), false);
    assert.equal(keepsActionBar([a('Save', { priority: null })]), true);
});

test('eight actions by default: first non-destructive primary, next two secondary, the rest (destructive too) in More, order kept; GETs count', () => {
    assert.deepEqual(labels(headerSplit(eight)), { primary: 'Open', secondary: ['Duplicate', 'Export'], overflow: ['Delete', 'Share', 'Archive', 'Print', 'History'] });
});

test('destructive never defaults to primary or secondary', () => {
    const split = headerSplit([a('Delete', { destructive: true }), a('Purge', { destructive: true }), a('Void', { destructive: true }), a('Wipe', { destructive: true })]);
    assert.deepEqual(labels(split), { primary: null, secondary: [], overflow: ['Delete', 'Purge', 'Void', 'Wipe'] });
});

test('explicit priorities win, undeclared actions fill what is left', () => {
    const split = headerSplit([a('Open', { method: 'get' }), a('Duplicate', { priority: 'overflow' }), a('Delete', { destructive: true, priority: 'secondary' }), a('Export'), a('Share', { priority: 'primary' }), a('Print')]);
    assert.deepEqual(labels(split), { primary: 'Share', secondary: ['Open', 'Delete'], overflow: ['Duplicate', 'Export', 'Print'] });
});

test('secondaries beyond two go to More in order, explicit ones taking the slots first', () => {
    const split = headerSplit([a('A'), a('B', { priority: 'secondary' }), a('C', { priority: 'secondary' }), a('D', { priority: 'secondary' }), a('E')]);
    assert.deepEqual(labels(split), { primary: 'A', secondary: ['B', 'C'], overflow: ['D', 'E'] });
});

test('more than one primary: the first stays, later ones become secondary, with a warning', () => {
    const warnings = [];
    const split = headerSplit([a('A'), a('B', { priority: 'primary' }), a('C', { priority: 'primary' }), a('D')], { warn: message => warnings.push(message) });
    assert.deepEqual(labels(split), { primary: 'B', secondary: ['A', 'C'], overflow: ['D'] });
    assert.equal(warnings.length, 1);
    assert.match(warnings[0], /2 actions declare priority 'primary'/);
});

test('phone: only the primary stays, secondaries lead the More menu', () => {
    assert.deepEqual(labels(headerSplit(eight, { phone: true })), { primary: 'Open', secondary: [], overflow: ['Duplicate', 'Export', 'Delete', 'Share', 'Archive', 'Print', 'History'] });
    assert.deepEqual(labels(headerSplit([a('Save'), a('Copy')], { phone: true })), { primary: 'Save', secondary: [], overflow: ['Copy'] });
});

test('HeaderActions splits with headerSplit, keeps ActionBar for the default path, and reuses the menu, confirmation and lock pieces', () => {
    const source = strip(read('Action/HeaderActions.vue.stub'));
    assert.match(source, /import \{ headerSplit, keepsActionBar \} from '@\/components\/Kit\/Action\/headerSplit'/);
    assert.match(source, /<ActionBar v-if="legacy" :actions="actions" :busy="busy" :dialog-open="dialogOpen" :primary="primary" v-bind="barListeners" \/>/);
    assert.match(source, /<ActionMenuItems :actions="split\.overflow" :busy="blocked" :on-action="run" \/>/);
    assert.match(source, /:confirm-text="pending\.confirm\.typed \?\? null"/);
    assert.match(source, /withoutEditActions\(props\.actions, lock\.locked\)/);
    assert.match(source, /matchMedia\('\(max-width: 39\.99rem\)'\)/, 'phone is a media query below sm, not the container width');
    assert.match(source, /data-header-more[^>]*>More<ChevronDown/);
});

