import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { members } from './shape.mjs';

// RadioCardGroup, EmojiTray and Button's lg/xl sizes and link forms. The tray's throttle and roving keys run here without Vue; the components are checked by source (the kit has
// no browser test runner, so nothing here renders or takes a tap).
const kit = new URL('../../resources/js/components/Kit/', import.meta.url);
const read = path => readFileSync(new URL(path, kit), 'utf8');
const dir = mkdtempSync(join(tmpdir(), 'kit-radio-emoji-'));
writeFileSync(join(dir, 'emojiTray.ts'), read('Form/emojiTray.ts'));
const { DEFAULT_REACTIONS, throttleGate, rovingIndex } = await import(pathToFileURL(join(dir, 'emojiTray.ts')).href);

test('the throttle lets one press through per window and drops the rest', () => {
    let now = 0;
    const gate = throttleGate(1000, () => now);
    assert.equal(gate(), true, 'the first press passes');
    now = 400;
    assert.equal(gate(), false, 'a press inside the window is dropped');
    now = 999;
    assert.equal(gate(), false);
    now = 1000;
    assert.equal(gate(), true, 'the window is measured from the last press let through');
    now = 1500;
    assert.equal(gate(), false);
    assert.equal(throttleGate(0, () => 5)(), true, 'a zero throttle never drops');
});

test('the roving keys move and wrap within the tray', () => {
    assert.equal(rovingIndex('ArrowRight', 0, 6), 1);
    assert.equal(rovingIndex('ArrowRight', 5, 6), 0);
    assert.equal(rovingIndex('ArrowLeft', 0, 6), 5);
    assert.equal(rovingIndex('ArrowDown', 2, 6), 3);
    assert.equal(rovingIndex('Home', 4, 6), 0);
    assert.equal(rovingIndex('End', 1, 6), 5);
    assert.equal(rovingIndex('Enter', 1, 6), null, 'other keys are left to the button');
    assert.equal(rovingIndex('ArrowRight', 0, 0), null);
});

test('every default reaction has a spoken label', () => {
    assert.ok(DEFAULT_REACTIONS.length >= 4);
    for (const reaction of DEFAULT_REACTIONS) assert.ok(reaction.emoji && reaction.label && reaction.label !== reaction.emoji);
});

test('all three are exported, typed in the contract and delivered by core', () => {
    const barrel = read('index.ts');
    for (const line of ["export { default as RadioCardGroup } from './Form/RadioCardGroup.vue';", "export { default as EmojiTray } from './Form/EmojiTray.vue';", "export { DEFAULT_REACTIONS } from './Form/emojiTray';", "export type { EmojiReaction } from './Form/emojiTray';", "export type { RadioCardOption } from './Form/RadioCardGroup.vue';"]) assert.ok(barrel.includes(line), line);
    const contract = read('contract.ts');
    assert.match(contract, /export interface RadioCardGroupProps /);
    assert.match(contract, /export interface EmojiTrayProps /);
    const button = members(contract, 'ButtonProps');
    for (const member of ["size?: 'default' | 'sm' | 'lg' | 'xl' | 'icon'", 'href?: string', "method?: 'get' | 'post' | 'put' | 'patch' | 'delete'", "data?: import('./Form/Button.vue').VisitData", 'preserveScroll?: boolean', 'external?: boolean', 'target?: string']) {
        assert.ok([...button.values()].includes(member), member);
    }
    assert.ok(barrel.includes("export type { VisitData, VisitValue } from './Form/Button.vue';"));
    for (const file of ['Form/RadioCardGroup.vue', 'Form/EmojiTray.vue', 'Form/emojiTray.ts']) assert.ok(existsSync(new URL(file, kit)), file);
});

