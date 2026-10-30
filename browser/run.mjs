// Real-browser checks of the kit Modal's overlays (node browser/run.mjs, after npm ci and npx playwright install chromium). The kit Modal is the package's native
// <dialog> in the top layer; its confirmation, selects and menus must open over it, take clicks, and close on Escape before the Modal does. A zero-item Workbench
// (?workbench) must pass axe with its panes open and collapsed, and keep its keyboard focus. A Workbench of three items with row menus (?workbench-menu) must pass
// axe, keep focusable elements out of its listbox, and open each item's menu from Shift+F10 and right-click. Its row menu with checkbox items
// (?workbench-menu=checkbox) and a host's #navigator-item rows (?workbench-menu=navitem) must pass axe with the listbox semantics kept. A reorderable Workbench
// (?workbench-reorder) over a stand-in for Inertia v3's HTTP client (string bodies) must carry the version token from each move to the next write. A filmstrip
// navigator with thumbnails (?workbench-menu=filmstrip) must keep its images inside the options with their alt, keep the listbox semantics, reorder, filter and
// F6, pass axe, and hold each thumbnail's box at its size before the image loads. Its #navigator-thumbnail (&live) must stay inert inside that box.
// At phone width (?workbench-menu&long) a long footer or inspector tab must scroll inside its row, its last line fully visible above the actions bar.
// A FormStatus mounted before the visit (?form-status) must show after each visit that carries a message and hide only while one is in flight.
// session-ui's guest pages (?auth=) over an Inertia <Form> stand-in: failures, a 429 and a bad reset token stay in the card (no error modal), fields reset and refocus.
// Each kit form control (?form-attrs) must put a caller's aria-describedby, aria-invalid and required on its focusable control, not a wrapper, and pass axe.
// A SegmentedToggle and a PaneTabs (?segmented, light and dark) must move the selection by keyboard, keep a neutral selected surface and pass axe with contrast.
// A FormCard showing 'Saved' and the pale Alerts (?form-saved, light and dark) must pass axe with contrast, checked on the status text and the alerts' text.
// Money and percent inputs and amounts (?money) must pass axe, name their unit in the label, keep the symbol out of the accessible text, submit percentage
// points as typed, and read accounting negatives as "negative".
// An AddressFieldset (?address) must pass axe, be a group named by its title, carry autocomplete tokens, relabel region and postal code with the country, and
// hint (not block) an unlikely postal code.
// RadioCardGroup tiles (?tiles, light and dark) must render the option slot around kit radios, name an image tile by its alt, skip a disabled tile on ArrowRight,
// mark the checked tile with a ring and an aria-hidden check mark, and pass axe with contrast. A Slider in a Field (?slider, light and dark) must be named by the
// Field, carry aria-valuetext and the Field's describedby, emit input live on keys and a drag and one change on release, and pass axe with contrast.
// useAppearance (?appearance) must mount, read 'system' and take a choice when localStorage throws on access (blocked site data, some private modes).
// InputTags (?tags) must pass axe, add a tag on a separator, Enter and a multi-line paste, select then drop the last chip on Backspace, and refuse a duplicate,
// an invalid tag and one past the limit with a described reason.
// A FormCard over useSubmitForm (?form-submit) must submit on Enter with no navigation, disable Save while pending, show a 422's field error and summary
// with focus on the field, then Saved, and pass axe with contrast. A Workbench with :form (?workbench-form=submit|inertia, the latter a stand-in useForm with
// `save`) must save on Ctrl+S but not from a contenteditable that binds it or while pending, focus a 422's field in its collapsed inspector, show a 409's
// conflict with the edits kept, hold a dirty leave with one guard, ignore Enter in the filter, and pass axe with contrast.
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { cpSync, readdirSync, renameSync, rmSync, statSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { chromium } from 'playwright';
import { build, preview } from 'vite';

const here = import.meta.dirname;
const kit = path.join(here, '.kit');
rmSync(kit, { recursive: true, force: true });
cpSync(path.join(here, '../resources/features/core/components/Kit'), path.join(kit, 'Kit'), { recursive: true });
cpSync(path.join(here, '../resources/features/core/css/kit.css.stub'), path.join(kit, 'kit.css'));
// session-ui's guest pages (?auth=), which import the kit barrel as `@kit/ui` and Inertia's <Form> (the stand-in in src/inertia.js).
cpSync(path.join(here, '../resources/features/session-ui/pages'), path.join(kit, 'Pages'), { recursive: true });
const unstub = (dir) => readdirSync(dir).forEach((name) => {
    const file = path.join(dir, name);
    if (statSync(file).isDirectory()) unstub(file);
    else if (name.endsWith('.stub')) renameSync(file, file.slice(0, -5));
});
unstub(kit);
await build({ root: here, configFile: path.join(here, 'vite.config.js') });
const server = await preview({ root: here, configFile: path.join(here, 'vite.config.js'), preview: { port: 0, host: '127.0.0.1' } });
const url = server.resolvedUrls.local[0];
// The session-ui guest pages build on the real @inertiajs/vue3 (vite.auth.config.js), served on their own.
await build({ root: here, configFile: path.join(here, 'vite.auth.config.js') });
const authServer = await preview({ root: here, configFile: path.join(here, 'vite.auth.config.js'), preview: { port: 0, host: '127.0.0.1' } });
const authUrl = authServer.resolvedUrls.local[0];
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
test.after(async () => {
    await browser.close();
    await new Promise((resolve) => server.httpServer.close(resolve));
    await new Promise((resolve) => authServer.httpServer.close(resolve));
});

// The element the browser would hit at the centre of `selector`: proves it paints on top and takes the pointer.
const onTop = (selector) => page.evaluate((s) => {
    const el = document.querySelector(s);
    const r = el.getBoundingClientRect();
    const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return Boolean(hit && (hit === el || el.contains(hit)));
}, selector);
const dialogOpen = () => page.evaluate(() => document.querySelector('[data-package-dialog]').open);
const events = () => page.evaluate(() => window.kitEvents);
async function openModal() {
    await page.goto(url);
    await page.click('[data-open]');
    await page.waitForTimeout(450);
}

test('the unsaved-edits confirmation opens over the modal, inside its <dialog>, and Discard is clickable', async () => {
    await openModal();
    await page.click('[data-modal-close]');
    await page.waitForSelector('[data-confirm-dialog]');
    assert.ok(await page.evaluate(() => Boolean(document.querySelector('[data-confirm-dialog]').closest('dialog[open]'))), 'rendered inside the open dialog');
    assert.ok(await onTop('[data-confirm-action]'), 'Discard is the element hit at its centre');
    await page.click('[data-confirm-action]');
    await page.waitForTimeout(300);
    assert.deepEqual(await events(), ['modal closed', 'dialog close']);
    assert.equal(await dialogOpen(), false);
});

test('Escape closes the confirmation first, and the modal stays open; Escape again asks again', async () => {
    await openModal();
    await page.click('[data-modal-close]');
    await page.waitForSelector('[data-confirm-dialog]');
    await page.keyboard.press('Escape');
    await page.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
    assert.equal(await dialogOpen(), true, 'the modal is still open');
    assert.deepEqual(await events(), []);
    await page.keyboard.press('Escape');
    await page.waitForSelector('[data-confirm-dialog]');
    assert.equal(await dialogOpen(), true);
    // A third Escape on the confirmation: the browser must not close the <dialog> outright after repeated Escapes.
    await page.keyboard.press('Escape');
    await page.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
    assert.equal(await dialogOpen(), true);
    assert.deepEqual(await events(), []);
});

test('a kit Select opens over the modal, its option takes the click, and Escape closes only the select', async () => {
    await openModal();
    await page.click('#status');
    await page.waitForSelector('[role="option"]');
    assert.ok(await page.evaluate(() => Boolean(document.querySelector('[role="listbox"]').closest('dialog[open]'))));
    assert.ok(await onTop('[role="option"]:last-of-type'));
    await page.keyboard.press('Escape');
    await page.waitForSelector('[role="listbox"]', { state: 'detached' });
    assert.equal(await dialogOpen(), true);
    await page.click('#status');
    await page.click('[role="option"]:last-of-type');
    assert.equal(await page.textContent('[data-status]'), 'b');
});

test('a kit menu opens over the modal, its item takes the click, and Escape closes only the menu', async () => {
    await openModal();
    await page.click('[data-menu-trigger]');
    await page.waitForSelector('[data-menu-item]');
    assert.ok(await page.evaluate(() => Boolean(document.querySelector('[data-menu-item]').closest('dialog[open]'))));
    assert.ok(await onTop('[data-menu-item]'));
    await page.keyboard.press('Escape');
    await page.waitForSelector('[data-menu-item]', { state: 'detached' });
    assert.equal(await dialogOpen(), true);
    await page.click('[data-menu-trigger]');
    await page.click('[data-menu-item]');
    assert.deepEqual(await events(), ['menu item']);
});

// ── Workbench with zero items: axe, panes open and collapsed ──────────────────────────────────────────────────────────────────────────────────────────

const axeSource = createRequire(import.meta.url).resolve('axe-core/axe.min.js');
// axe over a zero-item Workbench at xl (three columns) in a fresh context, so no remembered layout leaks between runs. Returns the violations, `{ id, targets }`.
async function axeEmptyWorkbench(query, check = async () => {}) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-workbench-region="stage"]');
        await tab.waitForTimeout(300);
        await check(tab);
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        if (violations.length) console.log(query, JSON.stringify(violations, null, 1));
        return violations;
    } finally {
        await context.close();
    }
}

test('a zero-item Workbench with its panes open has no axe violations (no empty listbox, no unfocusable scroll region)', async () => {
    const violations = await axeEmptyWorkbench('workbench', async tab => {
        assert.equal(await tab.locator('[data-workbench-collapsed]').count(), 0, 'both panes open');
        assert.equal(await tab.locator('[data-workbench-empty]').count(), 1, 'the navigator says it is empty');
    });
    assert.deepEqual(violations, []);
});

test('a zero-item Workbench with its navigator and inspector collapsed has no axe violations', async () => {
    const violations = await axeEmptyWorkbench('workbench=collapsed', async tab => assert.equal(await tab.locator('[data-workbench-collapsed]').count(), 2, 'navigator and inspector collapsed'));
    assert.deepEqual(violations, []);
});

// A stage-only Workbench (no items, no navigator content, no #empty), such as a presenter console, must keep rendering its #stage: the empty state is opt-in.
async function stageOf(query) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const tab = await context.newPage();
    try {
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-workbench-region="stage"]');
        return { stage: await tab.locator('[data-workbench-region="stage"] [data-test-stage]').count(), empty: await tab.locator('[data-workbench-region="stage"] [data-test-empty]').count(), kitDefault: await tab.locator('[data-workbench-stage-empty]').count() };
    } finally {
        await context.close();
    }
}

test('a zero-item Workbench with no #empty slot renders its #stage, with no kit default (a stage-only presenter console keeps its stage)', async () => {
    assert.deepEqual(await stageOf('workbench=stage'), { stage: 1, empty: 0, kitDefault: 0 });
});

test('a zero-item Workbench with an #empty slot renders the slot in place of #stage', async () => {
    assert.deepEqual(await stageOf('workbench=slot'), { stage: 0, empty: 1, kitDefault: 0 });
});

test('a zero-item Workbench: Shift+F6 from the stage lands on the navigator\'s empty message, without an error', async () => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?workbench`);
        await tab.waitForSelector('[data-workbench-region="stage"]');
        await tab.focus('[data-workbench-region="stage"]');
        await tab.keyboard.press('Shift+F6');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-workbench-empty')), true);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── Workbench navigator item menus: axe, no focusable element inside the listbox, Shift+F10 / right-click / Escape ───────────────────────────────────────

// A fresh context at xl (three columns) per test, so no remembered layout or open menu leaks between them.
async function menuWorkbench(query, run, before = async () => {}) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await before(tab);
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-workbench-region="navigator"] [role="option"]');
        await tab.waitForTimeout(300);
        await run(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}
// `skip` turns rules off by name, each with its reason at the call.
const menuAxe = async (tab, skip = []) => {
    if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
    const violations = await tab.evaluate(async (off) => (await window.axe.run(document, { rules: Object.fromEntries(off.map(id => [id, { enabled: false }])) })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), skip);
    if (violations.length) console.log('workbench-menu', JSON.stringify(violations, null, 1));
    return violations;
};
const focusedOption = (tab) => tab.evaluate(() => document.activeElement?.getAttribute('role') === 'option' ? document.activeElement.getAttribute('data-workbench-item') : null);
const openMenus = (tab) => tab.locator('[role="menu"]').count();

test('a Workbench of three items with menus: one labelled menu button per item, none inside the listbox, and no axe violations (closed and open)', async () => {
    await menuWorkbench('workbench-menu', async tab => {
        const buttons = await tab.$$eval('button[aria-haspopup="menu"][aria-label^="Actions for"]', els => els.map(el => el.getAttribute('aria-label')));
        assert.deepEqual(buttons, ['Actions for Slide 1', 'Actions for Slide 2', 'Actions for Slide 3']);
        const inside = await tab.$$eval('[role="listbox"]', lists => lists.flatMap(list => [...list.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(el => el.getAttribute('role') !== 'option').map(el => el.outerHTML.slice(0, 80))));
        assert.deepEqual(inside, [], 'no focusable element but the options inside the listbox');
        // The button column shares the listbox's rows (subgrid): each cell has its option's top and height.
        const rows = await tab.$$eval('[data-workbench-item-menus] > div', cells => cells.map(cell => {
            const id = cell.querySelector('[data-workbench-item-menu]').getAttribute('data-workbench-item-menu');
            const a = document.querySelector(`[role="option"][data-workbench-item="${id}"]`).getBoundingClientRect();
            const b = cell.getBoundingClientRect();
            return Math.abs(a.top - b.top) < 1 && Math.abs(a.height - b.height) < 1 && b.left >= a.right;
        }));
        assert.deepEqual(rows, [true, true, true], 'each button sits on its item\'s row, beside it');
        assert.deepEqual(await menuAxe(tab), []);
        await tab.focus('[role="option"][data-workbench-item="s2"]');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForSelector('[role="menu"]');
        // Open, one rule is off, not about this menu's structure: `region` (every kit menu's panel is portalled to <body>, outside the landmarks).
        // color-contrast runs with the destructive Delete item showing (text-destructive-text on the popover); the menu is non-modal, so axe sees the whole page.
        // nested-interactive, aria-required-children and the ARIA rules all still run.
        assert.equal(await tab.locator('[role="menu"] [data-action="delete"]').count(), 1, 'the open menu shows the destructive item');
        const open = await menuAxe(tab, ['region']);
        assert.deepEqual(open.filter(v => v.id === 'color-contrast'), [], 'no color-contrast violation with a destructive item open');
        assert.deepEqual(open, []);
    });
});

test('Shift+F10 on the focused option opens its menu, a menuitem takes focus, arrows move in it, and Escape returns focus to the option', async () => {
    await menuWorkbench('workbench-menu', async tab => {
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('ArrowDown');
        await tab.waitForTimeout(50);
        assert.equal(await focusedOption(tab), 's2', 'arrows still move between items');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForSelector('[role="menu"]');
        assert.equal(await openMenus(tab), 1);
        assert.deepEqual(await tab.$$eval('[role="menu"] [role="menuitem"]', els => els.map(el => el.textContent.trim())), ['Move up', 'Move down', 'Duplicate', 'Delete']);
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('role')), 'menuitem', 'a menuitem has focus');
        await tab.keyboard.press('ArrowDown');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('role')), 'menuitem');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.waitForFunction(() => document.activeElement?.getAttribute('role') === 'option', null, { timeout: 2000 }).catch(() => {});
        assert.equal(await focusedOption(tab), 's2', 'focus is back on the option');
    });
});

test('right-click on an option opens the same menu (the browser menu is prevented), and choosing Duplicate emits the action with the item', async () => {
    await menuWorkbench('workbench-menu', async tab => {
        const prevented = tab.evaluate(() => new Promise(resolve => document.addEventListener('contextmenu', e => resolve(e.defaultPrevented), { once: true })));
        await tab.click('[role="option"][data-workbench-item="s3"]', { button: 'right' });
        assert.equal(await prevented, true);
        await tab.waitForSelector('[role="menu"]');
        assert.equal(await tab.getAttribute('[data-workbench-item-menu="s3"]', 'aria-expanded'), 'true', 'the row\'s own menu');
        await tab.click('[role="menu"] [role="menuitem"]:has-text("Duplicate")');
        await tab.waitForTimeout(100);
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), ['duplicate s3']);
    });
});

// ── Workbench reorder over Inertia v3's HTTP client: the body is a string, and the version token must carry to the next write ────────────────────────────

test('two moves in a row and then Duplicate: each sends the current version token, so none gets a 409 and the version advances each time', async () => {
    await menuWorkbench('workbench-reorder', async tab => {
        const log = () => tab.evaluate(() => window.kitServer.log);
        const order = () => tab.$$eval('[data-workbench-region="navigator"] [role="option"]', els => els.map(el => el.getAttribute('data-workbench-item')));
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('Alt+ArrowDown');
        await tab.waitForFunction(() => window.kitServer.log.length === 1);
        await tab.waitForTimeout(100);
        await tab.keyboard.press('Alt+ArrowDown');
        await tab.waitForFunction(() => window.kitServer.log.length === 2);
        await tab.waitForTimeout(100);
        assert.deepEqual(await order(), ['s2', 's3', 's1'], 'both moves kept');
        await tab.click('[role="option"][data-workbench-item="s2"]', { button: 'right' });
        await tab.waitForSelector('[role="menu"]');
        await tab.click('[role="menu"] [role="menuitem"]:has-text("Duplicate")');
        await tab.waitForFunction(() => window.kitServer.log.length === 3);
        assert.deepEqual(await log(), [
            { url: '/decks/1/order', sent: 1, status: 200 },
            { url: '/decks/1/order', sent: 2, status: 200 },
            { url: '/slides/duplicate', sent: 3, status: 200 },
        ], 'no 409: each write sent the token the previous one returned');
        assert.equal(await tab.evaluate(() => window.kitServer.version), 4);
    });
});

test('the menu button opens the menu, and Escape returns focus to the button; an item without actions has no button and keeps the browser menu', async () => {
    await menuWorkbench('workbench-menu=partial', async tab => {
        assert.equal(await tab.locator('[data-workbench-item-menu="s3"]').count(), 0);
        const prevented = tab.evaluate(() => new Promise(resolve => document.addEventListener('contextmenu', e => resolve(e.defaultPrevented), { once: true })));
        await tab.click('[role="option"][data-workbench-item="s3"]', { button: 'right' });
        assert.equal(await prevented, false);
        assert.equal(await openMenus(tab), 0);
        await tab.focus('[role="option"][data-workbench-item="s3"]');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForTimeout(100);
        assert.equal(await openMenus(tab), 0);
        await tab.click('[data-workbench-item-menu="s1"]');
        await tab.waitForSelector('[role="menu"]');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.waitForFunction(() => document.activeElement?.hasAttribute('data-workbench-item-menu'), null, { timeout: 2000 }).catch(() => {});
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('data-workbench-item-menu')), 's1');
    });
});

// ── InputTags: axe, separators, Enter, paste, Backspace, refusals ──────────────────────────────────────────────────────────────────────────────────────────
test('InputTags adds tags on a separator, Enter and a multi-line paste, and Backspace on an empty field selects, then removes, the last chip', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?tags`);
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, []);
        const input = tab.getByRole('combobox', { name: 'Tags' });
        const model = () => tab.locator('[data-test-model]').innerText();
        await input.click();
        await input.pressSequentially('Red,');
        assert.equal(await model(), '["red"]', 'a separator adds the typed tag, normalised');
        assert.equal(await input.inputValue(), '', 'and clears the typed text');
        await input.pressSequentially('blue');
        await input.press('Enter');
        assert.equal(await model(), '["red","blue"]', 'Enter adds the typed tag');
        await input.pressSequentially('des');
        await input.press('Enter');
        assert.equal(await model(), '["red","blue","des"]', 'Enter adds what was typed, not the first suggestion that matches');
        await input.press('Backspace');
        await input.press('Backspace');
        assert.equal(await model(), '["red","blue"]');
        await input.pressSequentially('bet');
        await input.press('ArrowDown');
        await input.press('ArrowUp');
        assert.equal(await tab.getByRole('option', { name: 'beta' }).getAttribute('data-highlighted'), '', 'the arrows land on the suggestion');
        await input.press('Enter');
        assert.equal(await model(), '["red","blue","beta"]', 'after the arrow keys, Enter takes the highlighted suggestion');
        await input.press('Backspace');
        await input.press('Backspace');
        await input.press('Backspace');
        assert.equal(await model(), '["red","blue"]', 'the first Backspace on an empty field selects the last chip');
        assert.equal(await tab.locator('[data-chip-highlighted]').count() + await tab.locator('li.ring-2').count() > 0, true);
        await input.press('Backspace');
        assert.equal(await model(), '["red"]', 'the second removes it');
        await input.evaluate((el) => {
            const data = new DataTransfer();
            data.setData('text', 'green\nyellow');
            el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
        });
        assert.equal(await model(), '["red","green","yellow"]', 'a pasted column adds each line');
        assert.deepEqual(await tab.locator('input[type=hidden][name^="tags"]').evaluateAll(els => els.map(el => [el.name, el.value])), [['tags[]', 'red'], ['tags[]', 'green'], ['tags[]', 'yellow']]);
    } finally {
        await tab.close();
    }
});

test('InputTags refuses a duplicate, an invalid tag and one past the limit, with the reason on the input\'s description', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?tags`);
        const input = tab.getByRole('combobox', { name: 'Tags' });
        const problem = tab.locator('[data-tags-problem]');
        await input.click();
        await input.pressSequentially('red,RED,');
        assert.equal(await problem.innerText(), '“red” is already added.');
        assert.ok((await input.getAttribute('aria-describedby')).split(' ').includes(await problem.getAttribute('id')));
        await input.pressSequentially('no_way,');
        assert.equal(await problem.innerText(), '“no_way” is not a valid tag.');
        await input.pressSequentially('a,b,c,');
        assert.equal(await problem.innerText(), 'At most 3 tags.');
        assert.equal(await tab.locator('[data-test-model]').innerText(), '["red","a","b"]');
    } finally {
        await tab.close();
    }
});

// ── Money and percent: axe, the unit in the label, the symbol as decoration, percentage points, accounting negatives ─────────────────────────────────────────
test('money and percent inputs and amounts have no axe violations, and name their unit for assistive technology', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?money`);
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, []);
        assert.equal(await tab.getByRole('textbox', { name: 'Price, CAD' }).count(), 1);
        assert.equal(await tab.getByRole('textbox', { name: 'Rate, percent' }).count(), 1);
        // The symbol sits outside the field, in the locale's position, hidden from assistive technology.
        const prefix = tab.locator('[data-number-prefix]').first();
        assert.equal(await prefix.textContent(), '$');
        assert.equal(await prefix.getAttribute('aria-hidden'), 'true');
        assert.equal((await tab.locator('[data-number-suffix]').first().textContent()).trim(), '$', 'fr-CA puts $ after the number');
    } finally {
        await tab.close();
    }
});

test('a percentage submits percentage points as typed, and an accounting negative reads as "negative"', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?money`);
        const rate = tab.getByRole('textbox', { name: 'Rate, percent' });
        await rate.fill('0.125');
        await rate.blur();
        assert.equal(await tab.locator('input[type=hidden][name=rate]').inputValue(), '0.125', '0.125 stays 0.125 %, not 12.5 %');
        const balance = tab.getByRole('textbox', { name: 'Balance, CAD' });
        await balance.fill('-1234.5');
        await balance.blur();
        assert.equal(await balance.inputValue(), '(1,234.50)');
        assert.equal(await tab.locator('input[type=hidden][name=balance]').inputValue(), '-123450');
        const total = tab.locator('[data-test-total]');
        assert.equal(await total.evaluate(el => [...el.childNodes].map(n => (n.nodeType === 1 && n.classList.contains('sr-only') ? '' : n.textContent)).join('')), '($1,234.56)', 'shown as accounting parentheses');
        assert.equal(await total.evaluate(el => [...el.childNodes].map(n => (n.nodeType === 1 && n.getAttribute('aria-hidden') === 'true' ? '' : n.textContent)).join('')), 'negative $1,234.56');
        assert.match(await tab.locator('[data-test-tax]').innerText(), /^13,00\s%$/u);
    } finally {
        await tab.close();
    }
});

// ── FormStatus mounted before the visit: shown after it, hidden only while a visit is in flight ────────────────────────────────────────────────────────────

// A fresh page on ?form-status. `visit(submit, next)` clicks a submit, records what shows while the visit is in flight, answers it, and waits for it to settle.
async function formStatus(run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?form-status`);
        await tab.waitForSelector('[data-submit-form]');
        const shown = () => tab.evaluate(() => Object.fromEntries(['form', 'plain', 'flash'].map(k => [k, document.querySelector(`[data-fs-${k}]`)?.textContent.trim() ?? null])));
        const visit = async (submit, next) => {
            await tab.click(submit);
            await tab.waitForTimeout(50);
            const during = await shown();
            await tab.evaluate(n => window.kitRespond(n), next);
            await tab.waitForTimeout(50);
            return { during, after: await shown() };
        };
        await run({ tab, shown, visit });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test('FormStatus mounted before the visit (with :form): a submit whose response flashes status shows it, with role=status', async () => {
    await formStatus(async ({ tab, shown, visit }) => {
        assert.deepEqual(await shown(), { form: null, plain: null, flash: null }, 'nothing before the first submit');
        const { after } = await visit('[data-submit-form]', { props: { status: 'We have emailed your password reset link.' } });
        assert.equal(after.form, 'We have emailed your password reset link.');
        assert.equal(after.plain, 'We have emailed your password reset link.', 'without :form too');
        assert.equal(await tab.getAttribute('[data-fs-form]', 'role'), 'status');
    });
});

test('FormStatus: a second submit hides it while in flight, and the new message shows after', async () => {
    await formStatus(async ({ visit }) => {
        await visit('[data-submit-form]', { props: { status: 'First link sent.' } });
        const second = await visit('[data-submit-form]', { props: { status: 'Second link sent.' } });
        assert.equal(second.during.form, null, 'hidden while form.processing');
        assert.equal(second.during.plain, null, 'hidden while the router is navigating');
        assert.equal(second.after.form, 'Second link sent.');
        const third = await visit('[data-submit-plain]', { props: { status: 'Third link sent.' } });
        assert.deepEqual([third.during.form, third.during.plain], [null, null], 'a visit without :form hides both');
        assert.deepEqual([third.after.form, third.after.plain], ['Third link sent.', 'Third link sent.']);
    });
});

test('FormStatus flashKey: the message comes from the Inertia v3 page flash, not page props', async () => {
    await formStatus(async ({ visit }) => {
        const { after } = await visit('[data-submit-plain]', { flash: { notice: 'You are on the list.' } });
        assert.equal(after.flash, 'You are on the list.');
        assert.equal(after.plain, null, 'no status prop, so the default shows nothing');
    });
});

test('FormStatus: a visit whose response carries no message renders nothing', async () => {
    await formStatus(async ({ visit, shown }) => {
        await visit('[data-submit-form]', { props: { status: 'Sent.' }, flash: { notice: 'Noted.' } });
        const { after } = await visit('[data-submit-form]', { props: {}, flash: {} });
        assert.deepEqual(after, { form: null, plain: null, flash: null });
        assert.deepEqual(await shown(), { form: null, plain: null, flash: null });
    });
});

test('FormStatus with :form: processing without a router visit (no start or finish) hides it only while processing', async () => {
    await formStatus(async ({ visit }) => {
        const { during, after } = await visit('[data-submit-http]', { props: { status: 'Saved.' } });
        assert.equal(during.form, null, 'hidden while form.processing');
        assert.equal(after.form, 'Saved.', 'shown once processing ends, though no finish event fired');
    });
});

test('FormStatus flash-key="resetLink" mounted before the visit: a forgot-password submit whose response flashes resetLink (page.flash, not props) shows it', async () => {
    await formStatus(async ({ tab, visit }) => {
        const reset = () => tab.evaluate(() => document.querySelector('[data-fs-reset]')?.textContent.trim() ?? null);
        assert.equal(await reset(), null, 'nothing before the submit');
        await visit('[data-submit-form]', { props: {}, flash: { resetLink: 'We have emailed your password reset link.' } });
        assert.equal(await reset(), 'We have emailed your password reset link.');
        assert.equal(await tab.getAttribute('[data-fs-reset]', 'role'), 'status');
    });
});

// ── Checkbox menu items and #navigator-item ──────────────────────────────────────────────────────────────────────────────────────────────────────────

test('a row menu with a checked and an unchecked item: role=menuitemcheckbox with aria-checked, a check indicator on the checked one only, and no axe violations', async () => {
    await menuWorkbench('workbench-menu=checkbox', async tab => {
        await tab.focus('[role="option"][data-workbench-item="s2"]');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForSelector('[role="menu"]');
        const rows = await tab.$$eval('[role="menu"] [data-action]', els => els.map(el => [el.textContent.trim(), el.getAttribute('role'), el.getAttribute('aria-checked'), el.querySelectorAll('[data-check-indicator]').length]));
        assert.deepEqual(rows, [
            ['Move up', 'menuitem', null, 0], ['Move down', 'menuitem', null, 0], ['Duplicate', 'menuitem', null, 0], ['Delete', 'menuitem', null, 0],
            ['✓Skip slide', 'menuitemcheckbox', 'true', 1], ['Hide notes', 'menuitemcheckbox', 'false', 0],
        ]);
        // Open, `region` is off as in the row-menu test (the panel is portalled to <body>). The menu is non-modal, so color-contrast sees it.
        assert.deepEqual(await menuAxe(tab, ['region']), []);
        await tab.click('[role="menu"] [role="menuitemcheckbox"]:has-text("Hide notes")');
        await tab.waitForTimeout(100);
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), ['hide s2'], 'choosing it emits the action with the item; the host flips `checked`');
    });
});

test('a Workbench with #navigator-item renders the host\'s content inside the kit\'s role=option rows, no focusable element in the listbox, and no axe violations', async () => {
    await menuWorkbench('workbench-menu=navitem', async tab => {
        const rows = await tab.$$eval('[data-host-row]', els => els.map(el => [el.textContent.trim(), el.closest('[role="option"]')?.getAttribute('data-workbench-item') ?? null, el.closest('[role="option"]')?.parentElement?.getAttribute('role') ?? null]));
        assert.deepEqual(rows, [['1. Slide 1', 's1', 'listbox'], ['2. Slide 2', 's2', 'listbox'], ['3. Slide 3', 's3', 'listbox']]);
        const inside = await tab.$$eval('[role="listbox"]', lists => lists.flatMap(list => [...list.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(el => el.getAttribute('role') !== 'option').map(el => el.outerHTML.slice(0, 80))));
        assert.deepEqual(inside, [], 'no focusable element but the options inside the listbox');
        assert.equal(await tab.locator('button[aria-label="Actions for Slide 1"]').count(), 1, 'the row menus are still the kit\'s, outside the listbox');
        assert.deepEqual(await menuAxe(tab), []);
    });
});

// ── Workbench filmstrip navigator: thumbnails inside the options, the listbox kept, reorder, filter, F6, axe, a fixed box before the image loads ───────────

// The filmstrip page with /thumbs/slow.svg held back until `release` is called, so a test can measure the thumbnail box before its image arrives.
async function filmstripWorkbench(run) {
    await menuWorkbench('workbench-menu=filmstrip', run, async tab => {
        let release;
        const held = new Promise(resolve => (release = resolve));
        await tab.route('**/thumbs/slow.svg', async route => {
            await held;
            await route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="#cbd5e1"/></svg>' });
        });
        tab.releaseSlow = release;
    });
}
const filmstripOrder = (tab) => tab.$$eval('[data-workbench-region="navigator"]:not([hidden]) [role="option"]', els => els.map(el => [el.querySelector('[data-workbench-number]')?.textContent.trim() ?? null, el.getAttribute('data-workbench-item')]));
const boxOf = (tab, id) => tab.$eval(`[role="option"][data-workbench-item="${id}"] [data-workbench-thumbnail-box]`, el => { const r = el.getBoundingClientRect(); return [Math.round(r.width * 10) / 10, Math.round(r.height * 10) / 10]; });

test('a filmstrip navigator: each thumbnail is an <img> inside its role=option with the item\'s alt, an item without one gets a placeholder box, nothing focusable in the listbox, and the row menus outside it', async () => {
    await filmstripWorkbench(async tab => {
        const images = await tab.$$eval('[data-workbench-region="navigator"]:not([hidden]) img', els => els.map(el => [el.closest('[role="option"]')?.getAttribute('data-workbench-item') ?? null, el.closest('[role="option"]')?.parentElement?.getAttribute('role') ?? null, el.getAttribute('alt'), el.getAttribute('loading'), el.getAttribute('decoding')]));
        assert.deepEqual(images, [['s1', 'listbox', '', 'lazy', 'async'], ['s2', 'listbox', 'Bar chart', 'lazy', 'async'], ['s4', 'listbox', '', 'lazy', 'async']], 'images inside the options, with their alt; s3 has none');
        assert.equal(await tab.locator('[role="option"][data-workbench-item="s3"] [data-workbench-thumbnail-box]').count(), 1, 'the item without a thumbnail keeps a placeholder box');
        assert.equal(await tab.locator('[role="option"][data-workbench-item="s3"] img').count(), 0);
        assert.deepEqual(await filmstripOrder(tab), [['1', 's1'], ['2', 's2'], ['3', 's3'], ['4', 's4']], 'numbered in order');
        const names = await tab.$$eval('[role="option"]', els => els.map(el => el.textContent.replace(/\s+/g, ' ').trim()));
        assert.deepEqual(names.map(name => name.replace(/^(\d)\s*/, '$1 ')), ['1 Slide 1', '2 Slide 2', '3 Slide 3', '4 Slide 4'], 'each row shows its number and label');
        const inside = await tab.$$eval('[role="listbox"]', lists => lists.flatMap(list => [...list.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(el => el.getAttribute('role') !== 'option').map(el => el.outerHTML.slice(0, 80))));
        assert.deepEqual(inside, [], 'no focusable element but the options inside the listbox');
        const menus = await tab.$$eval('button[aria-label^="Actions for"]', els => els.map(el => [el.getAttribute('aria-label'), el.closest('[role="listbox"]') === null]));
        assert.deepEqual(menus, [['Actions for Slide 1', true], ['Actions for Slide 2', true], ['Actions for Slide 3', true], ['Actions for Slide 4', true]], 'the row menus sit outside the listbox');
        const rows = await tab.$$eval('[data-workbench-item-menus] > div', cells => cells.map(cell => {
            const id = cell.querySelector('[data-workbench-item-menu]').getAttribute('data-workbench-item-menu');
            const a = document.querySelector(`[role="option"][data-workbench-item="${id}"]`).getBoundingClientRect();
            const b = cell.getBoundingClientRect();
            return Math.abs(a.top - b.top) < 1 && Math.abs(a.height - b.height) < 1 && b.left >= a.right;
        }));
        assert.deepEqual(rows, [true, true, true, true], 'each menu cell shares its tall filmstrip row');
        const ratio = await tab.$eval('[role="option"][data-workbench-item="s1"] [data-workbench-thumbnail-box]', el => el.getBoundingClientRect().width / el.getBoundingClientRect().height);
        assert.ok(Math.abs(ratio - 16 / 9) < 0.05, `a 16:9 box (got ${ratio.toFixed(3)})`);
        tab.releaseSlow();
    });
});

test('a filmstrip thumbnail box has its size before the image loads, and keeps it after (no layout shift), whatever the image\'s own shape', async () => {
    await filmstripWorkbench(async tab => {
        assert.equal(await tab.$eval('[data-workbench-item="s4"] img', img => img.complete && img.naturalWidth > 0), false, 'the slow image has not arrived');
        const before = await boxOf(tab, 's4');
        const optionBefore = await tab.$eval('[role="option"][data-workbench-item="s4"]', el => el.getBoundingClientRect().height);
        assert.ok(before[0] > 100 && before[1] > 50, `the box is sized before the image loads (${before})`);
        assert.deepEqual(before, await boxOf(tab, 's3'), 'the same box as the placeholder');
        assert.deepEqual(await boxOf(tab, 's2'), await boxOf(tab, 's3'), 'a square image fills the same 16:9 box');
        tab.releaseSlow();
        await tab.waitForFunction(() => { const img = document.querySelector('[data-workbench-item="s4"] img'); return img.complete && img.naturalWidth === 300; });
        assert.deepEqual(await boxOf(tab, 's4'), before, 'the loaded square image leaves the box as it was');
        assert.equal(await tab.$eval('[role="option"][data-workbench-item="s4"]', el => el.getBoundingClientRect().height), optionBefore, 'and the row height');
    });
});

test('a filmstrip navigator: Alt+↓ reorders and renumbers, the filter keeps each item\'s number, and F6 leaves the navigator', async () => {
    await filmstripWorkbench(async tab => {
        tab.releaseSlow();
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('Alt+ArrowDown');
        await tab.waitForFunction(() => window.kitServer.log.length === 1);
        await tab.waitForTimeout(100);
        assert.deepEqual(await tab.evaluate(() => window.kitServer.log), [['s2', 's1', 's3', 's4']], 'the new order is sent');
        assert.deepEqual(await filmstripOrder(tab), [['1', 's2'], ['2', 's1'], ['3', 's3'], ['4', 's4']], 'moved and renumbered');
        assert.equal(await focusedOption(tab), 's1', 'focus stays on the moved item');
        await tab.keyboard.press('F6');
        assert.equal(await tab.evaluate(() => document.activeElement?.closest('[data-workbench-region]')?.getAttribute('data-workbench-region')), 'stage', 'F6 moves on to the stage');
        await tab.fill('[data-workbench-region="navigator"]:not([hidden]) [data-workbench-filter]', 'Slide 4');
        assert.deepEqual(await filmstripOrder(tab), [['4', 's4']], 'the filtered item keeps its place number');
    });
});

test('a filmstrip navigator has no axe violations (color-contrast included), closed and with a row menu open', async () => {
    await filmstripWorkbench(async tab => {
        tab.releaseSlow();
        await tab.waitForFunction(() => [...document.querySelectorAll('[data-workbench-thumbnail]')].every(img => img.complete));
        assert.deepEqual(await menuAxe(tab), []);
        await tab.focus('[role="option"][data-workbench-item="s2"]');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForSelector('[role="menu"]');
        // Open, `region` is off as in the list layout's check (the menu panel is portalled to <body>); the menu is non-modal, so color-contrast sees the page.
        assert.deepEqual(await menuAxe(tab, ['region']), []);
    });
});

// ── Workbench filmstrip #navigator-thumbnail: a live render inside the box, inert, the box size kept, the label the only name ─────────────────────────

test('a filmstrip #navigator-thumbnail renders inside each 16:9 box, at the image box\'s size, and its button and link are inert: not focusable, not tabbable, not named', async () => {
    let imageBox;
    await filmstripWorkbench(async tab => {
        tab.releaseSlow();
        imageBox = await boxOf(tab, 's1');
    });
    await menuWorkbench('workbench-menu=filmstrip&live', async tab => {
        const placed = await tab.$$eval('[data-live-slide]', els => els.map(el => [el.getAttribute('data-live-slide'), el.closest('[data-workbench-thumbnail-box]')?.closest('[role="option"]')?.getAttribute('data-workbench-item') ?? null, !!el.closest('[inert][aria-hidden="true"]')]));
        assert.deepEqual(placed, [['s1', 's1', true], ['s2', 's2', true], ['s3', 's3', true], ['s4', 's4', true]], 'each live slide in its own box, inside an inert, aria-hidden wrapper');
        assert.equal(await tab.locator('[data-workbench-region="navigator"]:not([hidden]) [role="option"] img').count(), 0, 'the slot wins over thumbnail.src');
        const before = await boxOf(tab, 's1');
        assert.deepEqual(before, imageBox, 'the same box as an image thumbnail');
        for (const id of ['s2', 's3', 's4']) assert.deepEqual(await boxOf(tab, id), before);
        // The 640px mini slide is wider than the box, scaled or not: the box keeps its size.
        await tab.$eval('[data-live-slide="s1"]', el => { el.style.transform = 'none'; });
        assert.deepEqual(await boxOf(tab, 's1'), before, 'unscaled content cannot grow the box');
        const inside = await tab.$$eval('[role="listbox"]', lists => lists.flatMap(list => [...list.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(el => el.getAttribute('role') !== 'option' && !el.closest('[inert][aria-hidden="true"]')).map(el => el.outerHTML.slice(0, 80))));
        assert.deepEqual(inside, [], 'nothing focusable in the listbox outside the inert wrappers');
        const focusable = await tab.$$eval('[data-live-button], [data-live-link]', els => els.map(el => { el.focus(); return el.matches(':focus'); }));
        assert.deepEqual(focusable, [false, false, false, false, false, false, false, false], 'focus() cannot reach the button or the link');
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        const stops = [];
        for (let i = 0; i < 6; i++) {
            await tab.keyboard.press('Tab');
            stops.push(await tab.evaluate(() => document.activeElement?.hasAttribute('data-live-button') || document.activeElement?.hasAttribute('data-live-link') || !!document.activeElement?.closest('[inert]')));
        }
        assert.ok(stops.every(s => !s), 'Tab from the listbox never lands in the live slide');
        for (const label of ['Slide 1', 'Slide 2', 'Slide 3', 'Slide 4']) assert.equal(await tab.getByRole('option', { name: label, exact: true }).count(), 1, `the option is named "${label}" only`);
        assert.equal(await tab.getByRole('button', { name: 'Play' }).count(), 0, 'the live button is not in the accessibility tree');
        assert.equal(await tab.getByRole('link', { name: 'Open' }).count(), 0);
    });
});

test('a filmstrip #navigator-thumbnail: axe is clean (color-contrast included), and Alt+↓ still reorders with the box size unchanged', async () => {
    await menuWorkbench('workbench-menu=filmstrip&live', async tab => {
        assert.deepEqual(await menuAxe(tab), []);
        const before = await boxOf(tab, 's1');
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('Alt+ArrowDown');
        await tab.waitForFunction(() => window.kitServer.log.length === 1);
        await tab.waitForTimeout(100);
        assert.deepEqual(await filmstripOrder(tab), [['1', 's2'], ['2', 's1'], ['3', 's3'], ['4', 's4']]);
        assert.equal(await tab.$eval('[data-live-slide="s1"]', el => el.getAttribute('data-live-index')), '1', 'the slot gets the new index');
        assert.equal(await focusedOption(tab), 's1');
        assert.deepEqual(await boxOf(tab, 's1'), before);
    });
});

// ── session-ui guest pages on the real Inertia <Form>: failures, the limiter (429) and the reset token stay in the card, never Inertia's error modal ──────

// Laravel's default 429 page for a `throttle:` route: plain HTML, no X-Inertia, so Inertia treats it as an HTTP exception and, unless the visit cancels it, shows it in its modal.
const throttledHtml = '<!DOCTYPE html><html lang="en"><head><title>Too Many Requests</title></head><body><div class="code">429</div><div class="message">Too Many Requests</div></body></html>';
const throttled = (seconds) => ({ status: 429, contentType: 'text/html; charset=UTF-8', headers: seconds ? { 'Retry-After': String(seconds) } : {}, body: throttledHtml });
// An Inertia response for the same page carrying validation errors, as Laravel's redirect back renders it.
const invalid = (page, errors) => ({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ ...page, props: { ...page.props, errors } }) });

// A fresh page on auth.html?auth=<name>. `submit(answer)` submits the card's form (the browser's own validation included); `answer(page)` is the response
// Playwright gives the POST, from the page object the app started with.
async function authPage(name, run, query = '') {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        let answer = null;
        let posts = 0;
        await tab.route(/\/(login|forgot-password|reset-password|user\/confirm-password|email\/verification-notification)$/, async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            posts++;
            const page = await tab.evaluate(() => window.kitInitialPage);
            await route.fulfill(answer(page));
        });
        await tab.goto(`${authUrl}auth.html?auth=${name}${query}`);
        await tab.waitForSelector('[data-guest-card] form');
        const submit = async (response) => {
            answer = typeof response === 'function' ? response : () => response;
            const before = posts;
            await tab.click('[data-guest-card] form button[type=submit]');
            await tab.waitForFunction(() => true);
            for (let i = 0; i < 100 && posts === before; i++) await tab.waitForTimeout(20);
            assert.ok(posts > before, 'the form was submitted');
            await tab.waitForTimeout(250);
        };
        const count = (selector) => tab.evaluate(s => document.querySelectorAll(s).length, selector);
        const text = (selector) => tab.evaluate(s => document.querySelector(s)?.textContent.trim() ?? null, selector);
        await run({ tab, submit, count, text });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}
const fieldErrors = '[data-guest-card] [id$="-error"]';
const modal = 'dialog, iframe';
const credentials = (page) => invalid(page, { email: 'These credentials do not match our records.' });

test('Login: email focused on load; each failed sign-in shows the alert and clears the password; the 6th, Laravel\'s 429 HTML, shows Too many requests in the card, no field errors, no modal', async () => {
    await authPage('Login', async ({ tab, submit, count, text }) => {
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'email', 'the email field has focus on first render');
        for (let attempt = 1; attempt <= 5; attempt++) {
            await tab.fill('input[name=email]', 'ada@example.com');
            await tab.fill('input[name=password]', 'wrong-password');
            await submit(credentials);
            assert.ok(await tab.isVisible('[role=alert]'), `attempt ${attempt}: an alert shows`);
            assert.equal(await tab.inputValue('input[name=password]'), '', `attempt ${attempt}: the password is cleared`);
        }
        await tab.fill('input[name=password]', 'wrong-password');
        await submit(throttled(42));
        assert.equal(await count(modal), 0, 'no Inertia error modal');
        assert.match(await text('[data-auth-throttled]'), /Too many requests\. Try again in 42 seconds\./);
        assert.equal(await tab.getAttribute('[data-auth-throttled]', 'role'), 'alert');
        assert.equal(await count(fieldErrors), 0, 'the stale field errors are cleared');
        assert.equal(await tab.inputValue('input[name=password]'), '');
    });
});

test('Login: bad credentials mark the email invalid, keep it, clear the password and refocus the email, on the same page', async () => {
    await authPage('Login', async ({ tab, submit, text }) => {
        await tab.fill('input[name=email]', 'ada@example.com');
        await tab.fill('input[name=password]', 'wrong-password');
        await submit(credentials);
        assert.equal(await text('[role=alert]'), 'These credentials do not match our records.');
        assert.equal(await tab.getAttribute('input[name=email]', 'aria-invalid'), 'true');
        assert.equal(await tab.inputValue('input[name=email]'), 'ada@example.com');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'email');
        assert.equal(new URL(tab.url()).pathname, '/login');
        await tab.click('[aria-label="Show password"]');
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '', 'a re-render (show password) does not bring the old password back');
    });
});

test('VerifyEmail: a resend past the limit (Laravel\'s 429 HTML, no Retry-After) shows Too many requests in the card, no modal; any other HTTP error still gets Inertia\'s modal', async () => {
    await authPage('VerifyEmail', async ({ submit, count, text }) => {
        await submit(throttled());
        assert.equal(await count(modal), 0);
        assert.equal(await text('[data-auth-throttled]'), 'Too many requests. Try again in a moment.');
        await submit({ status: 500, contentType: 'text/html', body: '<!DOCTYPE html><html><body>Server Error</body></html>' });
        assert.equal(await count('[data-auth-throttled]'), 0, 'the alert goes when the next submit starts');
        assert.ok(await count(modal) > 0, 'only a 429 is kept in the card');
    });
});

test('ResetPassword: a bad token shows the alert with a link to request a new one and clears both passwords; then a 429 in place, no modal', async () => {
    await authPage('ResetPassword', async ({ tab, submit, count, text }) => {
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit((page) => invalid(page, { email: 'This password reset token is invalid.' }));
        assert.match(await text('[data-reset-email-error]'), /This password reset token is invalid\./);
        assert.equal(await tab.getAttribute('[data-reset-email-error]', 'role'), 'alert');
        assert.equal(await tab.getAttribute('[data-reset-email-error] a', 'href'), '/forgot-password');
        assert.equal(await text('[data-reset-email-error] a'), 'Request a new link');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.inputValue('input[name=password_confirmation]'), '');
        await tab.click('[aria-label="Show password"] >> nth=0');
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '', 'still cleared after a re-render');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit(throttled(30));
        assert.equal(await count(modal), 0);
        assert.match(await text('[data-auth-throttled]'), /Too many requests/);
        assert.equal(await count('[data-reset-email-error]'), 0, 'the stale token error is cleared');
        assert.equal(await count(fieldErrors), 0);
        assert.equal(await tab.inputValue('input[name=password]'), '');
    });
});

test('ResetPassword: the view model\'s status shows in FormStatus', async () => {
    await authPage('ResetPassword', async ({ text }) => {
        assert.equal(await text('[data-reset-password-status]'), 'Your password has been reset.');
    }, '&status=Your%20password%20has%20been%20reset.');
});

test('ForgotPassword: an invalid address (novalidate, so the server answers) shows its field error each time; the limit shows Too many requests, field errors cleared, no modal', async () => {
    await authPage('ForgotPassword', async ({ tab, submit, count, text }) => {
        assert.equal(await tab.getAttribute('[data-guest-card] form', 'novalidate'), '');
        assert.equal(await tab.getAttribute('input[name=email]', 'type'), 'email');
        for (let attempt = 1; attempt <= 2; attempt++) {
            await tab.fill('input[name=email]', 'nobody');
            await submit((page) => invalid(page, { email: 'The email field must be a valid email address.' }));
            assert.match(await text(fieldErrors), /valid email address/, `attempt ${attempt}`);
            assert.equal(await tab.getAttribute('input[name=email]', 'aria-invalid'), 'true');
        }
        await submit(throttled(60));
        assert.equal(await count(modal), 0);
        assert.match(await text('[data-auth-throttled]'), /Too many requests/);
        assert.equal(await count(fieldErrors), 0);
    });
});

test('ConfirmPassword: the password field has focus on load; a wrong password is cleared, marked invalid and refocused; a 429 stays in the card', async () => {
    await authPage('ConfirmPassword', async ({ tab, submit, count, text }) => {
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'password');
        await tab.fill('input[name=password]', 'wrong-password');
        await submit((page) => invalid(page, { password: 'The provided password was incorrect.' }));
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.getAttribute('input[name=password]', 'aria-invalid'), 'true');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'password');
        await tab.click('[aria-label="Show password"]');
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '', 'still cleared after a re-render');
        await tab.fill('input[name=password]', 'wrong-password');
        await submit(throttled(10));
        assert.equal(await count(modal), 0);
        assert.match(await text('[data-auth-throttled]'), /Too many requests/);
    });
});

test('PasswordInput follows its form\'s reset: a native form reset clears the value it holds', async () => {
    await authPage('ConfirmPassword', async ({ tab }) => {
        await tab.fill('input[name=password]', 'typed-secret');
        await tab.evaluate(() => document.querySelector('[data-guest-card] form').reset());
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '');
        await tab.click('[aria-label="Show password"]');
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '', 'still empty after a re-render (show password)');
    });
});

// ── Workbench #navigator-actions: first in the navigator's DOM, visual and tab order, at every size (phone tabs included) ────────────────────────────────

async function actionsWorkbench(viewport, run) {
    const context = await browser.newContext({ viewport });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?workbench-menu&actions`);
        await tab.waitForSelector('[data-workbench-region="navigator"]', { state: 'attached' });
        await tab.waitForTimeout(300);
        await run(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}
// The navigator region's focusable elements in DOM order, by their first data attribute or role.
const navigatorOrder = (tab) => tab.$eval('[data-workbench-region="navigator"]:not([hidden])', nav => [...nav.querySelectorAll('[data-new-slide], [data-workbench-filter], [role="option"]')].slice(0, 3).map(el => el.hasAttribute('data-new-slide') ? 'action' : el.hasAttribute('data-workbench-filter') ? 'filter' : 'option'));

test('#navigator-actions renders first in the navigator, before the filter and list, at xl', async () => {
    await actionsWorkbench({ width: 1440, height: 900 }, async tab => {
        assert.deepEqual(await navigatorOrder(tab), ['action', 'filter', 'option']);
        await tab.focus('[data-new-slide]');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-workbench-filter')), true, 'Tab goes from the action to the filter');
    });
});

test('at phone width the navigator tab is a flex column: the action is first in DOM, above the filter and list, and not under the actions bar', async () => {
    await actionsWorkbench({ width: 390, height: 844 }, async tab => {
        const name = await tab.$eval('[data-workbench-tabs] [data-workbench-region="navigator"]', nav => nav.getAttribute('aria-label'));
        await tab.locator('[data-workbench-tabs]').getByText(name, { exact: true }).first().click();
        await tab.waitForSelector('[data-workbench-tabs] [data-workbench-region="navigator"] [data-new-slide]', { state: 'visible' });
        const box = await tab.$eval('[data-workbench-tabs] [data-workbench-region="navigator"]', nav => {
            const rect = (sel) => nav.querySelector(sel).getBoundingClientRect();
            const style = getComputedStyle(nav);
            return { display: style.display, direction: style.flexDirection, action: rect('[data-new-slide]').bottom, filter: rect('[data-workbench-filter]').top, list: rect('[role="option"]').top, first: nav.querySelector('[data-new-slide], [data-workbench-filter], [role="listbox"]').hasAttribute('data-new-slide') };
        });
        assert.equal(box.display, 'flex');
        assert.equal(box.direction, 'column');
        assert.equal(box.first, true, 'the action is first in the navigator\'s DOM');
        assert.ok(box.action <= box.filter && box.filter <= box.list, `action above filter above list (${JSON.stringify(box)})`);
        const covered = await tab.$eval('[data-new-slide]', el => { const r = el.getBoundingClientRect(); const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return hit !== el && !el.contains(hit); });
        assert.equal(covered, false, 'nothing (the sticky actions bar included) covers the action');
        await tab.focus('[data-new-slide]');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-workbench-filter')), true, 'Tab goes from the action to the filter');
    });
});

// ── Workbench phone tabs: a long footer or inspector scrolls inside its tab, and its end clears the actions bar ─────────────────────────────────────────

// At 390×844 (tabs mode, the actions bar pinned under the tab row), opens the `region` tab of ?workbench-menu&long, scrolls its own scroll container to the end,
// and reports the container's sizes, where the last line sits against the bar, what the browser hits at that line, and axe's color-contrast results for the region.
async function longTab(region) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?workbench-menu&long`);
        await tab.waitForSelector('[data-workbench-tabs]');
        await tab.waitForTimeout(300);
        const scope = `[data-workbench-tabs] [data-workbench-region="${region}"]`;
        const name = await tab.$eval(scope, el => el.getAttribute('aria-label'));
        await tab.locator('[data-workbench-tabs]').getByText(name, { exact: true }).first().click();
        await tab.waitForSelector(`${scope} [data-long-end]`, { state: 'visible' });
        // The scroll container is the nearest ancestor of the last line, inside the region, that scrolls on its own (none means the tab does not scroll).
        const box = await tab.$eval(scope, nav => {
            const end = nav.querySelector('[data-long-end]');
            let scroller = end.parentElement;
            while (scroller && scroller !== nav.parentElement && !(['auto', 'scroll'].includes(getComputedStyle(scroller).overflowY) && scroller.scrollHeight > scroller.clientHeight)) scroller = scroller.parentElement;
            if (!scroller || scroller === nav.parentElement) return { scrolls: false };
            const sizes = { scrollHeight: scroller.scrollHeight, clientHeight: scroller.clientHeight };
            scroller.scrollTop = scroller.scrollHeight;
            return { scrolls: true, ...sizes, inside: nav.contains(scroller) };
        });
        assert.equal(box.scrolls, true, `the ${region} tab has its own scroll container`);
        assert.equal(box.inside, true, 'the scroll container is inside the region');
        assert.ok(box.scrollHeight > box.clientHeight, `the ${region} overflows its container and scrolls (${JSON.stringify(box)})`);
        await tab.waitForTimeout(150);
        const end = await tab.$eval(`${scope} [data-long-end]`, el => {
            const r = el.getBoundingClientRect();
            const bar = document.querySelector('[data-workbench-actions]').getBoundingClientRect();
            const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
            return { top: r.top, bottom: r.bottom, barTop: bar.top, viewport: window.innerHeight, hit: Boolean(hit && (hit === el || el.contains(hit))) };
        });
        assert.ok(end.top >= 0 && end.bottom <= end.barTop, `the last line is fully above the actions bar (${JSON.stringify(end)})`);
        assert.equal(end.hit, true, 'nothing (the sticky actions bar included) covers the last line');
        await tab.addScriptTag({ path: axeSource });
        const contrast = await tab.evaluate(async (s) => {
            const run = await window.axe.run(document, { runOnly: ['color-contrast'], elementRef: true });
            const mine = (nodes) => nodes.filter(n => n.element?.closest(s));
            return {
                violations: run.violations.flatMap(v => v.nodes).map(n => n.target.join(' ')),
                // Text under the actions bar is not a pass: axe cannot measure it and files it as incomplete ("bgOverlap"). After the fix none is left in the region.
                overlapped: run.incomplete.flatMap(v => mine(v.nodes)).filter(n => [...n.any, ...n.all, ...n.none].some(c => c.data?.messageKey === 'bgOverlap')).map(n => n.target.join(' ')),
            };
        }, scope);
        assert.deepEqual(contrast.violations, [], 'no color-contrast violation on the page');
        assert.deepEqual(contrast.overlapped, [], `no ${region} text sits under another element (the actions bar)`);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test('at phone width a long footer tab scrolls inside its row, and its last line is fully visible above the actions bar; axe color-contrast is clean', async () => {
    await longTab('footer');
});

test('at phone width a long inspector tab scrolls inside its row, and its last line is fully visible above the actions bar; axe color-contrast is clean', async () => {
    await longTab('inspector');
});

// ── Form controls: caller attributes reach the focusable control ────────────────────────────────────────────────────────────────────────────────────────────
// What the control carries: the element's tag, role, and the attributes under test. `selector` picks the focusable control itself.
async function controlAttrs(tab, selector) {
    return tab.evaluate((s) => {
        const el = document.querySelector(s);
        if (!el) return null;
        const pick = (name) => el.getAttribute(name);
        return { tag: el.tagName.toLowerCase(), role: pick('role'), describedby: pick('aria-describedby'), invalid: pick('aria-invalid'), required: el.hasAttribute('required'), ariaRequired: pick('aria-required'), probe: pick('data-probe'), focusable: (el.focus(), document.activeElement === el) };
    }, selector);
}
async function formAttrs(run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const tab = await context.newPage();
    try {
        await tab.goto(`${url}?form-attrs`);
        await tab.waitForSelector('h1');
        await run(tab);
    } finally {
        await context.close();
    }
}
const ids = (value) => (value ?? '').split(/\s+/).filter(Boolean);

test('InputCheckbox: describedby (caller + description), aria-invalid, required and data-* land on the role=checkbox button; class stays on the row', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '#terms');
        assert.equal(c.tag, 'button');
        assert.equal(c.role, 'checkbox');
        assert.equal(await tab.getAttribute('#terms', 'data-slot'), 'checkbox', 'the real shadcn-vue Checkbox (src/shadcn), not a stand-in');
        assert.deepEqual(ids(c.describedby), ['hint', 'terms-description']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.ariaRequired, 'true');
        assert.equal(c.probe, 'checkbox');
        assert.equal(c.focusable, true);
        assert.equal(await tab.locator('#terms-description').textContent(), 'You can withdraw at any time.');
        assert.equal(await tab.locator('label[for="terms"] strong').count(), 1, 'the label slot keeps its markup');
        assert.ok(await tab.evaluate(() => document.querySelector('#terms').closest('.mt-2') !== null), 'class on the wrapper row');
        assert.equal(await tab.evaluate(() => document.querySelector('#terms').classList.contains('mt-2')), false, 'class not on the control');
    });
});

test('Select: describedby, aria-invalid, aria-required and data-* land on the role=combobox trigger', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '#plan');
        assert.equal(c.role, 'combobox');
        assert.deepEqual(ids(c.describedby), ['hint']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.ariaRequired, 'true');
        assert.equal(c.probe, 'select');
        assert.equal(c.focusable, true);
    });
});

test('FileInput: describedby, aria-invalid, required and data-* land on the <input type=file>', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '#upload');
        assert.equal(c.tag, 'input');
        assert.equal(await tab.getAttribute('#upload', 'type'), 'file');
        assert.deepEqual(ids(c.describedby), ['hint']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.required, true);
        assert.equal(c.probe, 'file');
    });
});

test('RadioCardGroup: the caller\'s describedby joins the Field ids on the radiogroup, with aria-invalid and aria-required; the radios are required', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '[role="radiogroup"]');
        assert.deepEqual(ids(c.describedby), ['hint', 'vote-description', 'vote-error']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.ariaRequired, 'true');
        assert.equal(c.probe, 'radio');
        assert.deepEqual(await tab.evaluate(() => [...document.querySelectorAll('#vote input[type=radio]')].map((r) => r.required)), [true, true]);
    });
});

test('InputSelect and TimezoneSelect: describedby, aria-invalid, aria-required and data-* land on the combobox input', async () => {
    await formAttrs(async (tab) => {
        for (const [id, probe] of [['fruit', 'input-select'], ['zone', 'timezone']]) {
            const c = await controlAttrs(tab, `#${id}`);
            assert.equal(c.tag, 'input', id);
            assert.equal(c.role, 'combobox', id);
            assert.deepEqual(ids(c.describedby), ['hint'], id);
            assert.equal(c.invalid, 'true', id);
            assert.equal(c.ariaRequired, 'true', id);
            assert.equal(c.probe, probe, id);
        }
    });
});

test('InputTags: describedby, aria-invalid, aria-required and data-* land on the combobox input, and a refusal\'s reason joins the caller\'s describedby', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '#labels');
        assert.equal(c.tag, 'input');
        assert.equal(c.role, 'combobox');
        assert.deepEqual(ids(c.describedby), ['hint']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.ariaRequired, 'true');
        assert.equal(c.probe, 'tags');
        await tab.locator('#labels').pressSequentially('red,red,');
        const problem = await tab.getAttribute('[data-tags-problem]', 'id');
        assert.deepEqual(ids(await tab.getAttribute('#labels', 'aria-describedby')), ['hint', problem], 'the caller\'s hint stays first');
    });
});

test('InputNumber: the caller\'s describedby joins its error id on the input', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '#qty');
        assert.equal(c.tag, 'input');
        assert.deepEqual(ids(c.describedby), ['hint', 'qty-error']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.required, true);
        assert.equal(c.probe, 'number');
    });
});

test('the form controls page has no axe violations', async () => {
    await formAttrs(async (tab) => {
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, []);
    });
});

// ── A StickyBar pinned by layout: the filling Workbench's actions bar registers with useStickyBars, so a bottom toast sits above it ───────────────────

test('in a filling Workbench, a toast does not cover the actions bar, and Save is clickable right after it', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?sticky-toast`);
        const save = tab.locator('[data-workbench-actions] button', { hasText: 'Save slide' });
        await save.waitFor();
        assert.equal(await tab.getAttribute('[data-workbench-actions]', 'data-pinned'), 'true', 'the bar at the foot by layout is pinned');
        await tab.evaluate(() => window.kitToast('Slide duplicated'));
        await tab.waitForSelector('[data-sonner-toast][data-mounted="true"]');
        await tab.waitForTimeout(500);
        const covered = await tab.evaluate(() => {
            const bar = document.querySelector('[data-workbench-actions]');
            return [...bar.querySelectorAll('button')].filter(button => {
                const r = button.getBoundingClientRect();
                const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
                return !(hit && button.contains(hit));
            }).map(button => button.textContent.trim());
        });
        assert.deepEqual(covered, [], 'no actions-bar button is under the toast');
        const toastBottom = await tab.$eval('[data-sonner-toast]', el => el.getBoundingClientRect().bottom);
        const barTop = await tab.$eval('[data-workbench-actions]', el => el.getBoundingClientRect().top);
        assert.ok(toastBottom <= barTop + 1, `the toast (bottom ${toastBottom}) sits above the bar (top ${barTop})`);
        await save.click({ timeout: 2000 });
        assert.ok((await tab.evaluate(() => window.kitEvents)).includes('save'), 'Save took the click');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── SegmentedToggle and PaneTabs (?segmented): a neutral view switch, a radiogroup driven by the arrows, Home and End; axe with contrast, light and dark ──────

const checked = (tab) => tab.evaluate(() => [...document.querySelectorAll('[data-segmented-toggle] [role="radio"]')].map(el => `${el.dataset.value}:${el.getAttribute('aria-checked')}:${el.tabIndex}`).join(' '));
async function segmented(query, check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 600 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-segmented-toggle]');
        await check(tab);
    } finally {
        await context.close();
    }
}

test('SegmentedToggle: one tab stop on the selection; arrows move and select (skipping the disabled option, wrapping), Home and End; aria-checked follows', async () => {
    await segmented('segmented', async tab => {
        assert.equal(await tab.getAttribute('[data-segmented-toggle]', 'role'), 'radiogroup');
        assert.equal(await tab.getAttribute('[data-segmented-toggle]', 'aria-label'), 'Editor view');
        assert.equal(await checked(tab), 'slides:true:0 outline:false:-1 notes:false:-1 grid:false:-1');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.dataset.value), 'slides', 'Tab lands on the selected segment');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await checked(tab), 'slides:false:-1 outline:true:0 notes:false:-1 grid:false:-1');
        assert.equal(await tab.textContent('[data-view]'), 'outline', 'v-model follows');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await checked(tab), 'slides:false:-1 outline:false:-1 notes:false:-1 grid:true:0', 'the disabled Notes is skipped');
        assert.equal(await tab.evaluate(() => document.activeElement.dataset.value), 'grid');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await checked(tab), 'slides:true:0 outline:false:-1 notes:false:-1 grid:false:-1', 'wraps to the first');
        await tab.keyboard.press('End');
        assert.equal(await checked(tab), 'slides:false:-1 outline:false:-1 notes:false:-1 grid:true:0');
        await tab.keyboard.press('ArrowLeft');
        assert.equal(await checked(tab), 'slides:false:-1 outline:true:0 notes:false:-1 grid:false:-1', 'ArrowLeft skips Notes too');
        await tab.keyboard.press('Home');
        assert.equal(await checked(tab), 'slides:true:0 outline:false:-1 notes:false:-1 grid:false:-1');
        await tab.click('[data-value="notes"]', { force: true });
        assert.equal(await tab.textContent('[data-view]'), 'slides', 'a click on the disabled option changes nothing');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => Boolean(document.activeElement.closest('[data-segmented-toggle]'))), false, 'one tab stop: Tab leaves the group');
    });
});

test('SegmentedToggle and PaneTabs: the selected segment is a bordered surface, not the primary colour, and the focused segment shows an outline', async () => {
    for (const query of ['segmented', 'segmented=dark']) {
        await segmented(query, async tab => {
            const styles = await tab.evaluate(() => {
                const probe = document.createElement('div');
                probe.className = 'bg-primary';
                document.body.append(probe);
                const primary = getComputedStyle(probe).backgroundColor;
                probe.remove();
                const read = el => ({ bg: getComputedStyle(el).backgroundColor, border: getComputedStyle(el).borderTopColor, width: getComputedStyle(el).borderTopWidth });
                return { primary, toggle: read(document.querySelector('[data-segmented-toggle] [aria-checked="true"]')), pane: read(document.querySelector('[data-pane-tabs] [aria-pressed="true"]')), track: getComputedStyle(document.querySelector('[data-segmented-toggle]')).backgroundColor };
            });
            assert.notEqual(styles.primary, 'rgba(0, 0, 0, 0)', `${query}: bg-primary resolves to a colour, so the comparison means something`);
            for (const name of ['toggle', 'pane']) {
                assert.notEqual(styles[name].bg, styles.primary, `${query} ${name}: the selected background isn't primary (${styles.primary})`);
                assert.notEqual(styles[name].bg, styles.track, `${query} ${name}: the selected segment stands out from the track`);
                assert.equal(styles[name].width, '1px', `${query} ${name}: a border`);
                assert.notEqual(styles[name].border, 'rgba(0, 0, 0, 0)', `${query} ${name}: a visible border`);
            }
            await tab.keyboard.press('Tab');
            const outline = await tab.evaluate(() => getComputedStyle(document.activeElement).outlineStyle + ' ' + getComputedStyle(document.activeElement).outlineWidth);
            assert.equal(outline, 'solid 2px', `${query}: the keyboard-focused segment has a 2px outline`);
        });
    }
});

test('SegmentedToggle and PaneTabs have no axe violations, colour contrast included, light and dark (a non-modal surface)', async () => {
    for (const query of ['segmented', 'segmented=dark']) {
        await segmented(query, async tab => {
            await tab.addScriptTag({ path: axeSource });
            const result = await tab.evaluate(async () => {
                const run = await window.axe.run(document, { elementRef: true });
                const checkedHere = run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.closest('[data-segmented-toggle], [data-pane-tabs]'));
                return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), contrast: checkedHere.length };
            });
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1));
            assert.deepEqual(result.violations, []);
            assert.ok(result.contrast >= 5, `${query}: axe checked the segments' contrast (${result.contrast} nodes)`);
        });
    }
});

// ── A saved FormCard (?form-saved): "Saved" in text-success-text, and the pale Alerts; axe with contrast, light and dark ─────────────────────────────────────
test('a FormCard showing "Saved" and the pale Alerts have no axe violations, colour contrast included and checked on the status text, light and dark', async () => {
    for (const query of ['form-saved', 'form-saved=dark']) {
        const context = await browser.newContext({ viewport: { width: 1024, height: 900 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${url}?${query}`);
            await tab.waitForSelector('[data-form-status][data-state="saved"]');
            assert.equal((await tab.textContent('[data-form-status]')).trim(), 'Saved');
            await tab.addScriptTag({ path: axeSource });
            const result = await tab.evaluate(async () => {
                const run = await window.axe.run(document, { elementRef: true });
                const checked = (selector) => run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.matches(selector)).length;
                return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), details: run.violations.flatMap(v => v.nodes.map(n => `${n.target.join(' ')}: ${n.any.map(c => c.message).join('; ')}`)), status: checked('[data-form-status]'), alerts: checked('[data-slot="alert"] *') };
            });
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1), result.details.join('\n'));
            assert.deepEqual(result.violations, []);
            assert.equal(result.status, 1, `${query}: axe checked the "Saved" status text's contrast`);
            assert.ok(result.alerts >= 4, `${query}: axe checked the pale Alerts' text contrast (${result.alerts} nodes)`);
        } finally {
            await context.close();
        }
    }
});

// ── AddressFieldset: axe, the named group, autocomplete, labels that follow the country, the postal hint ──────────────────────────────────────────────────
test('an AddressFieldset has no axe violations, is a group named by its title, and its fields carry autocomplete tokens', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?address`);
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, []);
        assert.equal(await tab.getByRole('group', { name: 'Mailing address' }).count(), 1);
        assert.equal(await tab.getByRole('textbox', { name: 'Street address' }).getAttribute('autocomplete'), 'address-line1');
        assert.equal(await tab.getByRole('textbox', { name: 'City' }).getAttribute('autocomplete'), 'address-level2');
        assert.equal(await tab.locator('input[name="mailing[line1]"]').count(), 1);
        assert.equal((await tab.locator('[data-test-text]').innerText()).split('\n').map(l => l.trim()).filter(Boolean).join(' | '), '24 Sussex Dr | Ottawa, Ontario K1M 1M4 | Canada');
    } finally {
        await tab.close();
    }
});

test('the region and postal-code labels follow the country, and an unlikely postal code is hinted, not blocked', async () => {
    const tab = await browser.newPage();
    try {
        await tab.goto(`${url}?address`);
        assert.equal(await tab.getByText('Province or territory', { exact: true }).count(), 1);
        const postal = tab.getByRole('textbox', { name: 'Postal code' });
        await postal.fill('k1a0b1');
        await postal.blur();
        assert.equal(await postal.inputValue(), 'K1A 0B1');
        assert.equal(await postal.getAttribute('aria-describedby'), null, 'no hint for a good code');
        await postal.fill('12345');
        await postal.blur();
        const described = await postal.getAttribute('aria-describedby');
        assert.ok(described, 'an unlikely code is described by its hint');
        assert.equal(await tab.locator(`#${described}`).innerText(), 'Canadian postal codes look like A1A 1A1.');
        assert.equal(await postal.evaluate(el => el.validity.valid), true, 'a hint never blocks the submit');
        // Change the country to the US: the labels change and the region is cleared.
        await tab.getByRole('combobox', { name: 'Country' }).click();
        await tab.getByRole('option', { name: 'United States' }).click();
        assert.equal(await tab.getByText('State', { exact: true }).count(), 1);
        assert.equal(await tab.getByRole('textbox', { name: 'ZIP code' }).count(), 1);
        assert.match(await tab.locator('[data-test-model]').innerText(), /"country":"US"/);
    } finally {
        await tab.close();
    }
});

// ── DateTime: a <time> with its machine value and full title; a calendar date never shifts between zones; one live ticker ───────────────────────────────

test('DateTime renders <time datetime> with a full title; a calendar date is the same day in any zone, an instant is not; no axe violations', async () => {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?datetime`);
        await tab.waitForSelector('time[data-case="live"]');
        const read = (name) => tab.$eval(`time[data-case="${name}"]`, el => ({ text: el.textContent, datetime: el.getAttribute('datetime'), title: el.getAttribute('title') }));
        const east = await read('date-east');
        const west = await read('date-west');
        assert.deepEqual([east.text, west.text, east.datetime], ['Nov 1, 2026', 'Nov 1, 2026', '2026-11-01'], 'a calendar date never shifts');
        assert.equal(east.title, 'Sunday, November 1, 2026');
        assert.deepEqual([(await read('instant-east')).text, (await read('instant-west')).text], ['2026-10-30', '2026-10-29'], 'an instant is shown in the zone');
        assert.equal((await read('relative')).text, '3 minutes ago');
        assert.equal((await read('live')).text, '2 minutes ago');
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => v.id));
        assert.deepEqual(violations, []);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── Pickers: the APG date grid and month grid keys, a date that never shifts between zones, the phone sheet and the locked state ────────────────────────

const pickersPage = async (options) => {
    const context = await browser.newContext({ locale: 'en-US', ...options });
    const tab = await context.newPage();
    const errors = [];
    tab.on('pageerror', error => errors.push(error.message));
    await tab.goto(`${url}?pickers`);
    await tab.waitForSelector('[data-slot="input-date"]');
    const hidden = (name) => tab.$eval(`form#pickers input[name="${name}"]`, el => el.value);

    return { context, tab, errors, hidden };
};

test('InputDate: the calendar opens on the value, arrows and PageDown move by the APG keys, Enter chooses, and the same date is submitted in any zone', async () => {
    for (const timezoneId of ['Pacific/Kiritimati', 'Pacific/Pago_Pago']) {
        const { context, tab, errors, hidden } = await pickersPage({ timezoneId, viewport: { width: 1024, height: 800 } });
        try {
            assert.equal(await hidden('due'), '2026-11-01', `the default in ${timezoneId}`);
            await tab.click('[data-slot="input-date"] button[aria-label="Choose Due date"]');
            await tab.waitForSelector('[role="dialog"] [data-reka-calendar-cell-trigger]');
            await tab.waitForFunction(() => document.activeElement?.getAttribute('data-value') === '2026-11-01', null, { timeout: 2000 });
            await tab.keyboard.press('ArrowRight');
            await tab.keyboard.press('ArrowDown');
            await tab.keyboard.press('PageDown');
            assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('data-value')), '2026-12-09', 'a day, a week, then a month on');
            await tab.keyboard.press('Enter');
            await tab.waitForSelector('[role="dialog"]', { state: 'detached' });
            assert.equal(await hidden('due'), '2026-12-09', `the chosen date in ${timezoneId}`);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
});

test('InputMonth and InputYear: a 3×4 grid with the APG keys; PageUp turns the year and keeps the month; Escape returns focus to the button', async () => {
    const { context, tab, errors, hidden } = await pickersPage({ viewport: { width: 1024, height: 800 } });
    try {
        await tab.click('[data-slot="input-month"] button');
        await tab.waitForSelector('[role="grid"]');
        await tab.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === 'October 2026', null, { timeout: 2000 });
        await tab.keyboard.press('ArrowDown');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'January 2027', 'down from the last row turns the year, same column');
        await tab.keyboard.press('PageUp');
        await tab.keyboard.press('PageUp');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'January 2025');
        await tab.keyboard.press('End');
        await tab.keyboard.press('Enter');
        await tab.waitForSelector('[role="grid"]', { state: 'detached' });
        assert.equal(await hidden('period'), '2025-03');
        assert.equal(await tab.evaluate(() => document.activeElement?.closest('[data-slot]')?.getAttribute('data-slot')), 'input-month', 'focus is back on the button');
        await tab.click('[data-slot="input-year"] button');
        await tab.waitForSelector('[role="grid"]');
        await tab.keyboard.press('ArrowLeft');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="grid"]', { state: 'detached' });
        assert.equal(await hidden('founded'), '2026', 'Escape chooses nothing');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

test('InputTime and the ranges: wall HH:MM values; a reversed time range blocks the native submit; a half-filled date range is reported', async () => {
    const { context, tab, errors, hidden } = await pickersPage({ viewport: { width: 1024, height: 800 } });
    try {
        assert.equal(await hidden('opens'), '09:30');
        assert.deepEqual([await hidden('hours[start]'), await hidden('hours[end]')], ['09:00', '17:00']);
        assert.equal(await tab.$eval('form#pickers', form => form.checkValidity()), true);
        // Step the end hour down past the start: 17 → 8 with ArrowDown on the end's hour segment.
        const endHour = tab.locator('[data-slot="input-time-range"] [data-reka-time-field-segment="hour"]').nth(1);
        await endHour.focus();
        for (let i = 0; i < 9; i++) await tab.keyboard.press('ArrowDown');
        assert.equal(await hidden('hours[end]'), '08:00');
        assert.equal(await tab.$eval('form#pickers', form => form.checkValidity()), false, 'a reversed span blocks submit');
        assert.ok((await tab.textContent('[data-slot="input-time-range"] ~ [role="alert"], [role="alert"]'))?.includes('before'));
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

test('pickers on a phone open as a bottom sheet; a locked picker has no calendar button; no axe violations closed or open', async () => {
    const { context, tab, errors } = await pickersPage({ viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true });
    try {
        assert.equal(await tab.locator('[data-slot="input-date"][data-locked] button[aria-label^="Choose"]').count(), 0, 'locked: no calendar button');
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        const axe = () => tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => `${v.id}: ${v.nodes.map(n => n.target).join(' ')}`));
        assert.deepEqual(await axe(), [], 'closed');
        await tab.click('[data-slot="input-date"]:not([data-locked]) button[aria-label="Choose Due date"]');
        await tab.waitForSelector('[data-kit-sheet] [data-reka-calendar-cell-trigger]');
        assert.ok((await tab.textContent('[data-kit-sheet]')).includes('Due date, November 2026'), 'the calendar is named by the field');
        const box = await tab.$eval('[data-kit-sheet]', el => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right, bottom: r.bottom }; });
        assert.deepEqual([Math.round(box.left), Math.round(box.right), Math.round(box.bottom)], [0, 390, 780], 'full width, on the bottom edge');
        assert.deepEqual(await axe(), [], 'date sheet open');
        await tab.keyboard.press('Escape');
        await tab.click('[data-slot="input-month"] button');
        await tab.waitForSelector('[data-kit-sheet] [role="grid"]');
        assert.deepEqual(await axe(), [], 'month sheet open');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// Every button in the pinned bar stays clickable under a toast, at a desktop width with bottom-right toasts and at a phone width (bottom-center). The
// shell here is shorter than the Workbench's 8rem allowance, so the filling bar's foot sits above the viewport's. The toaster's inset is the bar's distance from the viewport's bottom.
const pinnedBarClear = (viewport, query) => async () => {
    const context = await browser.newContext({ viewport });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?sticky-toast&${query}`);
        await tab.locator('[data-workbench-actions] button').first().waitFor();
        await tab.evaluate(() => window.kitToast('Slide duplicated.'));
        await tab.waitForSelector('[data-sonner-toast][data-mounted="true"]');
        await tab.waitForTimeout(500);
        const report = await tab.evaluate(() => {
            const bar = document.querySelector('[data-workbench-actions]');
            const toast = document.querySelector('[data-sonner-toast]').getBoundingClientRect();
            const buttons = [...bar.querySelectorAll('button')].filter(button => button.getBoundingClientRect().width > 0);
            const covered = buttons.filter(button => {
                const r = button.getBoundingClientRect();
                return [r.left + 2, r.left + r.width / 2, r.right - 2].some(x => { const hit = document.elementFromPoint(x, r.top + r.height / 2); return !(hit && button.contains(hit)); });
            }).map(button => button.textContent.trim() || button.getAttribute('aria-label'));
            return { covered, count: buttons.length, toastBottom: toast.bottom, barTop: bar.getBoundingClientRect().top, barBottom: bar.getBoundingClientRect().bottom };
        });
        assert.ok(report.count >= 2, `the bar has buttons (${report.count})`);
        assert.ok(report.barBottom < viewport.height - 20, `the bar's foot (${report.barBottom}) sits above the viewport's (${viewport.height})`);
        assert.deepEqual(report.covered, [], `no bar button is under the toast (toast bottom ${report.toastBottom}, bar top ${report.barTop})`);
        assert.ok(report.toastBottom <= report.barTop + 1, `the toast (bottom ${report.toastBottom}) sits above the bar (top ${report.barTop})`);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
test('a pinned Workbench bar: no button is under a toast (1440×900, bottom-right)', pinnedBarClear({ width: 1440, height: 900 }, 'position=bottom-right'));
test('a pinned Workbench bar: no button is under a toast (390×844 phone)', pinnedBarClear({ width: 390, height: 844 }, 'position=auto'));

test('storage that throws on access: the app still mounts, appearance is system, and choosing Dark works without persisting', async () => {
    const context = await browser.newContext();
    try {
        await context.addInitScript(() => {
            const blocked = () => { throw new DOMException('The operation is insecure.', 'SecurityError'); };
            Object.defineProperty(window, 'localStorage', { configurable: true, get: blocked });
            Object.defineProperty(window, 'sessionStorage', { configurable: true, get: blocked });
        });
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?appearance`);
        assert.equal(await tab.evaluate(() => { try { window.localStorage; return 'readable'; } catch { return 'throws'; } }), 'throws', 'the fixture blocks storage');
        await tab.locator('[data-appearance]').waitFor();
        assert.equal(await tab.locator('[data-appearance]').innerText(), 'system');
        await tab.locator('[data-dark]').click();
        assert.equal(await tab.locator('[data-appearance]').innerText(), 'dark');
        assert.equal(await tab.evaluate(() => document.documentElement.classList.contains('dark')), true);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});
// ── The non-visiting form frame: FormCard over useSubmitForm, and a Workbench with :form over each transport ─────────────────────────────────────────

// A fresh context per run, so no remembered Workbench layout (the inspector's collapse) leaks between transports.
async function onPage(query, run, viewport = { width: 1440, height: 900 }) {
    const context = await browser.newContext({ viewport });
    const tab = await context.newPage();
    try {
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('h1');
        await tab.waitForTimeout(300);
        await run(tab);
    } finally {
        await context.close();
    }
}
const axeWithContrast = async (tab) => {
    await tab.addScriptTag({ path: axeSource });
    return tab.evaluate(async () => (await window.axe.run(document, { rules: { 'color-contrast': { enabled: true } } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
};
const focusedName = (tab) => tab.evaluate(() => document.activeElement?.getAttribute('name') ?? document.activeElement?.tagName);

test('a FormCard over useSubmitForm: Enter submits with no navigation, Save is disabled while pending, a 422 shows the field error and summary and focuses the field, then Saved', async () => {
    await onPage('form-submit', async (tab) => {
        assert.equal(await tab.locator('form[data-form-card][novalidate]').count(), 1, 'the card is the <form>, novalidate');
        const before = await tab.evaluate(() => ({ href: location.href, history: history.length }));
        await tab.fill('input[name="title"]', 'Launch');
        await tab.press('input[name="title"]', 'Enter');
        await tab.waitForFunction(() => window.kitSocket.sent.length === 1);
        assert.deepEqual(await tab.evaluate(() => window.kitSocket.sent[0]), { title: 'Launch', summary: '' });
        assert.equal(await tab.locator('[data-form-submit]').isDisabled(), true, 'Save is disabled while the send is pending');
        await tab.press('input[name="title"]', 'Enter');
        assert.equal(await tab.evaluate(() => window.kitSocket.sent.length), 1, 'a second Enter while pending sends nothing');
        await tab.evaluate(() => window.kitSocket.respond('422'));
        await tab.waitForSelector('#title-error');
        assert.equal(await tab.textContent('#title-error'), 'That title is taken.');
        assert.equal(await tab.getAttribute('[data-form-status]', 'data-state'), 'errors');
        assert.match(await tab.textContent('[data-form-status]'), /There are errors in your submission\./);
        assert.equal(await focusedName(tab), 'title', 'the first invalid field has focus');
        assert.equal(await tab.locator('input[name="title"][aria-invalid="true"]').count(), 1);
        await tab.fill('input[name="title"]', 'Launch day');
        await tab.click('[data-form-submit]');
        await tab.waitForFunction(() => window.kitSocket.sent.length === 2);
        await tab.evaluate(() => window.kitSocket.respond('ok'));
        await tab.waitForSelector('[data-form-status][data-state="saved"]');
        assert.equal((await tab.textContent('[data-form-status]')).trim(), 'Saved');
        assert.deepEqual(await tab.evaluate(() => ({ href: location.href, history: history.length })), before, 'no navigation: URL and history unchanged');
    });
});

test('a FormCard over useSubmitForm has no axe violations, color-contrast included, idle and showing a field error (a non-modal surface)', async () => {
    await onPage('form-submit', async (tab) => {
        assert.deepEqual(await axeWithContrast(tab), []);
        await tab.fill('input[name="title"]', 'Launch');
        await tab.press('input[name="title"]', 'Enter');
        await tab.waitForFunction(() => window.kitSocket.sent.length === 1);
        await tab.evaluate(() => window.kitSocket.respond('422'));
        await tab.waitForSelector('#title-error');
        assert.deepEqual(await axeWithContrast(tab), []);
    });
});

// Each Workbench :form check runs per transport (a useSubmitForm, and a stand-in useForm with `save`), as top-level tests so count-check counts them.
const workbenchFormSave = (transport) => async () => {
    await onPage(`workbench-form=${transport}`, async (tab) => {
        assert.equal(await tab.locator('form[data-workbench-form][novalidate]').count(), 1, 'one <form novalidate> around the grid');
        const saveButton = tab.locator('[data-workbench-save]');
        assert.equal(await saveButton.getAttribute('type'), 'submit');
        assert.equal(await saveButton.getAttribute('form'), await tab.getAttribute('form[data-workbench-form]', 'id'));
        assert.equal(await tab.locator('form[data-workbench-form] button[type="submit"]').count(), 1, 'only Save submits');
        await tab.click('[data-test-editor]');
        await tab.keyboard.press('Control+s');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 0, 'the editor bound Mod+S itself: no Workbench save');
        assert.equal(await tab.evaluate(() => window.kitSave.editorSaves), 1);
        await tab.click('[data-workbench-region="stage"] p');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        if (transport === 'inertia') assert.deepEqual(await tab.evaluate(() => ({ method: window.kitSave.calls[0].method, url: window.kitSave.calls[0].url })), { method: 'put', url: '/decks/1' }, 'useForm.submit(save.method, save.url)');
        assert.equal(await saveButton.isDisabled(), true, 'Save is disabled while saving');
        assert.equal(await tab.getAttribute('[data-save-state]', 'data-save-state'), 'saving');
        await tab.keyboard.press('Control+s');
        await tab.keyboard.press('Shift+Control+s');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 1, 'ignored while processing, and Shift is never save');
        await tab.evaluate(() => window.kitSave.respond('ok'));
        await tab.waitForSelector('[data-save-state="saved"]');
    });
};
const workbenchFormSummary = (transport) => async () => {
    await onPage(`workbench-form=${transport}`, async (tab) => {
        assert.equal(await tab.locator('[data-workbench-collapsed]').count() > 0, true, 'the inspector starts collapsed');
        await tab.click('[data-workbench-save]');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('422'));
        await tab.waitForSelector('[data-workbench-form-summary]');
        await tab.waitForFunction(() => document.activeElement?.getAttribute('name') === 'title');
        assert.equal(await tab.getAttribute('[data-save-state]', 'data-save-state'), 'error');
        assert.ok(await tab.locator('input[name="title"]').isVisible(), 'the inspector was expanded to show the field');
        await tab.focus('[data-test-editor]');
        await tab.click('[data-workbench-form-summary]');
        await tab.waitForFunction(() => document.activeElement?.getAttribute('name') === 'title');
        assert.equal(await tab.textContent('#deck-title-error'), 'That title is taken.');
    });
};
const workbenchFormConflict = (transport) => async () => {
    await onPage(`workbench-form=${transport}`, async (tab) => {
        await tab.press('[data-workbench-filter]', 'Enter');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 0, 'Enter in the navigator filter does not submit');
        await tab.click('[data-workbench-save]');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('ok'));
        await tab.waitForSelector('[data-save-state="saved"]');
        await tab.click('[data-workbench-collapsed] button');
        await tab.fill('input[name="title"]', 'My edit');
        await tab.waitForSelector('[data-save-state="unsaved"]');
        assert.equal(await tab.evaluate(() => window.kitBeforeListeners()), 1, 'one leave guard');
        assert.equal(await tab.evaluate(() => window.kitBefore('/elsewhere')), true, 'a visit away while dirty is held');
        await tab.waitForSelector('text=Leave this page?');
        await tab.click('text=Stay');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 2);
        await tab.evaluate(() => window.kitSave.respond('409'));
        await tab.waitForSelector('[data-workbench-conflict]');
        assert.equal(await tab.getAttribute('[data-save-state]', 'data-save-state'), 'conflict');
        assert.equal(await tab.inputValue('input[name="title"]'), 'My edit', 'the edits are kept');
        assert.equal(await tab.locator('[data-workbench-form-summary]').count(), 0, 'the conflict alert, not the errors summary');
    });
};
const workbenchFormAxe = (transport) => async () => {
    await onPage(`workbench-form=${transport}`, async (tab) => {
        assert.deepEqual(await axeWithContrast(tab), []);
        await tab.click('[data-workbench-save]');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('422'));
        await tab.waitForSelector('[data-workbench-form-summary]');
        assert.deepEqual(await axeWithContrast(tab), []);
    });
};
test(`a Workbench with :form (submit): Ctrl+S submits once, not while pending, and not from a contenteditable that binds it; Save is the bar's primary submit`, workbenchFormSave('submit'));
test(`a Workbench with :form (inertia): Ctrl+S submits once, not while pending, and not from a contenteditable that binds it; Save is the bar's primary submit`, workbenchFormSave('inertia'));
test(`a Workbench with :form (submit): a 422 summary focuses the title field inside the collapsed inspector, after expanding it`, workbenchFormSummary('submit'));
test(`a Workbench with :form (inertia): a 422 summary focuses the title field inside the collapsed inspector, after expanding it`, workbenchFormSummary('inertia'));
test(`a Workbench with :form (submit): a 409 stale_write shows the conflict alert and keeps the edits; dirty asks before leaving, with one guard; filter Enter does not submit`, workbenchFormConflict('submit'));
test(`a Workbench with :form (inertia): a 409 stale_write shows the conflict alert and keeps the edits; dirty asks before leaving, with one guard; filter Enter does not submit`, workbenchFormConflict('inertia'));
test(`a Workbench with :form (submit) has no axe violations, color-contrast included, idle and with the errors summary (a non-modal surface)`, workbenchFormAxe('submit'));
test(`a Workbench with :form (inertia) has no axe violations, color-contrast included, idle and with the errors summary (a non-modal surface)`, workbenchFormAxe('inertia'));

// ── RadioCardGroup tiles (?tiles): the option slot, image tiles named by alt, a disabled tile skipped by the arrows, the check mark; axe with contrast ────────

async function scenario(query, ready, check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector(ready);
        await check(tab);
    } finally {
        await context.close();
    }
}
async function axeScopedContrast(tab, within) {
    await tab.addScriptTag({ path: axeSource });
    return tab.evaluate(async (scope) => {
        const run = await window.axe.run(document, { elementRef: true });
        const contrast = run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.closest(scope)).length;
        return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), contrast };
    }, within);
}

test('RadioCardGroup tiles: the option slot renders custom content around kit radios; image tiles are named by alt; a disabled tile is skipped by ArrowRight and not chosen', async () => {
    await scenario('tiles', '[data-tiles="swatch"]', async tab => {
        assert.deepEqual(await tab.locator('[data-tiles="plan"] [data-custom]').allTextContents(), ['Basic plan', 'Plus plan', 'Team plan'], 'the slot replaces each card body');
        assert.equal(await tab.locator('[data-tiles="plan"] input[type="radio"][name="plan"]').count(), 3, 'the native radios stay the kit\'s');
        assert.equal(await tab.getByRole('radio', { name: /Plus plan/ }).isChecked(), true, 'the slot content names its radio');
        assert.equal(await tab.locator('[data-tiles="plan"] [data-value="plus"] [data-radio-card-check]').count(), 1, 'the checked slot card shows the check mark');

        assert.equal(await tab.getByRole('radio', { name: 'Blue swatch' }).count(), 1, 'an image tile is named by its alt');
        const image = tab.locator('[data-tiles="swatch"] [data-value="blue"] img');
        assert.equal(await image.getAttribute('loading'), 'lazy');
        assert.equal(await image.getAttribute('decoding'), 'async');
        const box = await image.evaluate(el => { const r = el.parentElement.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; });
        assert.ok(box[0] > 0 && box[0] === box[1], `the image sits in a square box (${box})`);
        assert.equal(await tab.getByRole('radio', { name: 'Green swatch' }).isDisabled(), true, 'the disabled tile is a native disabled radio');

        await tab.focus('#swatch-option-0');
        await tab.keyboard.press('Space');
        assert.equal(await tab.textContent('[data-swatch]'), 'red');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await tab.textContent('[data-swatch]'), 'blue', 'ArrowRight skips the disabled Green');
        assert.equal(await tab.evaluate(() => document.activeElement.id), 'swatch-option-2');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.dataset.after !== undefined), true, 'one tab stop: Tab leaves the group');
        await tab.click('[data-tiles="swatch"] [data-value="green"]', { force: true });
        assert.equal(await tab.textContent('[data-swatch]'), 'blue', 'a click on the disabled tile changes nothing');

        const marks = await tab.locator('[data-tiles="swatch"] [data-radio-card-check]').evaluateAll(els => els.map(el => `${el.closest('[data-value]').dataset.value}:${el.getAttribute('aria-hidden')}:${Boolean(el.querySelector('svg'))}`));
        assert.deepEqual(marks, ['blue:true:true'], 'one aria-hidden check mark icon, on the checked tile');
        const ring = await tab.evaluate(() => getComputedStyle(document.querySelector('[data-tiles="swatch"] [data-value="blue"]')).boxShadow);
        assert.notEqual(ring, 'none', 'the checked tile has a ring too');
    });
});

test('RadioCardGroup tiles have no axe violations, colour contrast included, light and dark (a non-modal surface)', async () => {
    for (const query of ['tiles', 'tiles=dark']) {
        await scenario(query, '[data-tiles="swatch"]', async tab => {
            await tab.focus('#swatch-option-0');
            await tab.keyboard.press('Space');
            const result = await axeScopedContrast(tab, '[data-tiles]');
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1));
            assert.deepEqual(result.violations, []);
            assert.ok(result.contrast >= 6, `${query}: axe checked the cards' contrast (${result.contrast} nodes)`);
            const outline = await tab.evaluate(() => getComputedStyle(document.querySelector('[data-tiles="swatch"] [data-value="red"]')).outlineStyle);
            assert.equal(outline, 'solid', `${query}: the focused tile shows the focus ring`);
        });
    }
});

// ── Slider (?slider): a native range in a Field; aria-valuetext, live input and change on release, the Field's describedby; axe with contrast ──────────────

const sliderEvents = tab => tab.evaluate(() => [...window.sliderEvents]);

test('Slider: named by its Field, aria-valuetext, live input on keyboard steps and a drag with one change on release, the Field\'s describedby on the input', async () => {
    await scenario('slider', '[data-probe="slider"]', async tab => {
        const slider = tab.getByRole('slider', { name: 'Volume' });
        assert.equal(await slider.count(), 1, 'the Field label names the native range');
        assert.equal(await slider.getAttribute('aria-valuetext'), '40%');
        assert.equal(await slider.getAttribute('name'), 'volume', 'caller attributes land on the input');
        const describedBy = (await slider.getAttribute('aria-describedby')).split(' ');
        assert.ok(describedBy.includes('volume-description'), `the Field description describes the input (${describedBy})`);
        assert.equal(await tab.textContent('#volume-description'), 'How loud alerts play.');
        assert.equal(await tab.getAttribute('[data-slider-value]', 'for'), 'volume', 'the visible value is linked to the input');
        assert.equal(await tab.textContent('[data-slider-value]'), '40%');

        await slider.focus();
        await tab.keyboard.press('ArrowRight');
        await tab.keyboard.press('ArrowRight');
        assert.deepEqual((await sliderEvents(tab)).filter(e => e.startsWith('input')), ['input:45', 'input:50'], 'each keyboard step emits input');
        assert.equal(await slider.getAttribute('aria-valuetext'), '50%');
        assert.equal(await tab.textContent('[data-volume]'), '50', 'v-model follows');

        await tab.evaluate(() => { window.sliderEvents.length = 0; });
        const r = await slider.boundingBox();
        await tab.mouse.move(r.x + r.width * 0.5, r.y + r.height / 2);
        await tab.mouse.down();
        await tab.mouse.move(r.x + r.width * 0.7, r.y + r.height / 2, { steps: 4 });
        await tab.mouse.move(r.x + r.width * 0.9, r.y + r.height / 2, { steps: 4 });
        const during = await sliderEvents(tab);
        assert.ok(during.filter(e => e.startsWith('input')).length >= 2, `input fires live while dragging (${during})`);
        assert.equal(during.filter(e => e.startsWith('change')).length, 0, `no change before release (${during})`);
        await tab.mouse.up();
        const after = await sliderEvents(tab);
        const changes = after.filter(e => e.startsWith('change'));
        assert.equal(changes.length, 1, `one change on release (${after})`);
        assert.equal(changes[0], `change:${await tab.textContent('[data-volume]')}`, 'the change carries the released value');
    });
});

test('Slider has no axe violations, colour contrast included, light and dark, and shows a focus ring (a non-modal surface)', async () => {
    for (const query of ['slider', 'slider=dark']) {
        await scenario(query, '[data-probe="slider"]', async tab => {
            await tab.keyboard.press('Tab');
            assert.equal(await tab.evaluate(() => document.activeElement.dataset.probe), 'slider');
            const outline = await tab.evaluate(() => getComputedStyle(document.activeElement).outlineStyle + ' ' + getComputedStyle(document.activeElement).outlineWidth);
            assert.equal(outline, 'solid 2px', `${query}: the keyboard-focused slider has a 2px outline`);
            const result = await axeScopedContrast(tab, 'main');
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1));
            assert.deepEqual(result.violations, []);
            assert.ok(result.contrast >= 3, `${query}: axe checked the label, description and value contrast (${result.contrast} nodes)`);
        });
    }
});
