// Real-browser checks of the kit Modal's overlays (node browser/run.mjs, after npm ci and npx playwright install chromium). The kit Modal is the package's native
// <dialog> in the top layer; its confirmation, selects and menus must open over it, take clicks, and close on Escape before the Modal does. A zero-item Workbench
// (?workbench) must pass axe with its panes open and collapsed, and keep its keyboard focus. A Workbench of three items with row menus (?workbench-menu) must pass
// axe, keep focusable elements out of its listbox, and open each item's menu from Shift+F10 and right-click. Its row menu with checkbox items
// (?workbench-menu=checkbox) and a host's #navigator-item rows (?workbench-menu=navitem) must pass axe with the listbox semantics kept. A reorderable Workbench
// (?workbench-reorder) over a stand-in for Inertia v3's HTTP client (string bodies) must carry the version token from each move to the next write.
// A filmstrip navigator with thumbnails (?workbench-menu=filmstrip) must keep its images inside the options with their alt, keep the listbox semantics,
// reorder, filter and F6, pass axe, and hold each thumbnail's box at its size before the image loads.
// A FormStatus mounted before the visit (?form-status) must show after each visit that carries a message and hide only while one is in flight.
// Each kit form control (?form-attrs) must put a caller's aria-describedby, aria-invalid and required on its focusable control, not a wrapper, and pass axe.
// A FormCard showing 'Saved' and the pale Alerts (?form-saved, light and dark) must pass axe with contrast, checked on the status text and the alerts' text.
// session-ui's guest pages (?auth=) over an Inertia <Form> stand-in: failures, a 429 and a bad reset token stay in the card (no error modal), fields reset and refocus.
// At phone width (?workbench-menu&long) a long footer or inspector tab must scroll inside its row, its last line fully visible above the actions bar.
// A FormCard over useSubmitForm (?form-submit) must submit on Enter with no navigation, disable Save while pending, show a 422's field error and summary
// with focus on the field, then Saved, and pass axe with contrast. A Workbench with :form (?workbench-form=submit|inertia, the latter a stand-in useForm with
// `save`) must save on Ctrl+S but not from a contenteditable that binds it or while pending, focus a 422's field in its collapsed inspector, show a 409's
// conflict with the edits kept, hold a dirty leave with one guard, ignore Enter in the filter, and pass axe with contrast.
// The leave-page guard (leave.html, on the real @inertiajs/vue3) must hold Back from a dirty form (Stay keeps it, Leave goes) and not a clean one, with the
// Navigation API and with window.navigation deleted (the history fallback, leaving no entry behind), count a BulkDrop upload in flight as unsaved, and let a
// save's onSuccess visit through.
// A SegmentedToggle and a PaneTabs (?segmented, light and dark) must move the selection by keyboard, keep a neutral selected surface and pass axe with contrast.
// RadioCardGroup tiles (?tiles, light and dark) must render the option slot around kit radios, name an image tile by its alt, skip a disabled tile on ArrowRight,
// mark the checked tile with a ring and an aria-hidden check mark, and pass axe with contrast. A Slider in a Field (?slider, light and dark) must be named by the
// Field, carry aria-valuetext and the Field's describedby, emit input live on keys and a drag and one change on release, and pass axe with contrast.
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
// The shell's partials (Wordmark, FocusShell), which import the kit barrel as `@kit/ui`.
cpSync(path.join(here, '../resources/features/shell/pages/Workspace/Partials'), path.join(kit, 'Shell'), { recursive: true });
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
        // color-contrast runs with the destructive Delete item showing (text-destructive-text on the popover), as do nested-interactive, aria-required-children
        // and the ARIA rules.
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
        let body = null;
        await tab.route(/\/(login|register|invitations\/token\/register|invite\/token\/register|forgot-password|reset-password|user\/confirm-password|email\/verification-notification)$/, async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            posts++;
            body = route.request().postDataJSON();
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
        await run({ tab, submit, count, text, posted: () => body });
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

test('Register (Fortify\'s /register): name focused on load, novalidate, the four fields; a failed attempt shows each field error in the card, clears both passwords, refocuses the first invalid field and keeps the rest', async () => {
    await authPage('Register', async ({ tab, submit, count, text, posted }) => {
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'name', 'the name field has focus on first render');
        assert.equal(await tab.getAttribute('[data-guest-card] form', 'novalidate'), '');
        assert.equal(await tab.getAttribute('[data-guest-card] form', 'action'), '/register');
        assert.equal(await tab.getAttribute('input[name=email]', 'type'), 'email');
        assert.equal(await text('h1'), 'Create an account');
        assert.equal(await tab.getAttribute('[data-sign-in]', 'href'), '/login');
        assert.equal(await text('[data-sign-in]'), 'Sign in');
        await tab.fill('input[name=name]', 'Ada');
        await tab.fill('input[name=email]', 'nobody');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-2');
        await submit((page) => invalid(page, { email: 'The email field must be a valid email address.', password: 'The password field confirmation does not match.' }));
        assert.deepEqual(posted(), { name: 'Ada', email: 'nobody', password: 'a-new-password-1', password_confirmation: 'a-new-password-2' });
        assert.equal(await count(fieldErrors), 2, 'both field errors show in the card');
        assert.match(await text('#email-error'), /valid email address/);
        assert.equal(await tab.getAttribute('input[name=email]', 'aria-invalid'), 'true');
        assert.equal(await tab.getAttribute('input[name=password]', 'aria-invalid'), 'true');
        assert.equal(await count('[data-register-error]'), 0, 'no top alert: every error has its field');
        assert.equal(await tab.inputValue('input[name=name]'), 'Ada');
        assert.equal(await tab.inputValue('input[name=email]'), 'nobody');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.inputValue('input[name=password_confirmation]'), '');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'email', 'focus moves to the first invalid field');
        await tab.click('[aria-label="Show password"] >> nth=0');
        await tab.waitForTimeout(50);
        assert.equal(await tab.inputValue('input[name=password]'), '', 'still cleared after a re-render');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit(throttled(42));
        assert.equal(await count(modal), 0, 'no Inertia error modal');
        assert.match(await text('[data-auth-throttled]'), /Too many requests\. Try again in 42 seconds\./);
        assert.equal(await tab.getAttribute('[data-auth-throttled]', 'role'), 'alert');
        assert.equal(await count(fieldErrors), 0, 'the stale field errors are cleared');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.inputValue('input[name=password_confirmation]'), '');
    });
});

test('Register, an invited account (view model): the address is text with its hint, no email input, posted through `submit`; the heading and lead are the view model\'s; a name error refocuses name; an `email` or `invitation` error is one top alert', async () => {
    await authPage('Register', async ({ tab, submit, count, text, posted }) => {
        assert.equal(await count('input[name=email], input[type=email]'), 0, 'no email input');
        assert.equal(await text('[data-register-email]'), 'ada@example.com');
        assert.equal(await text('#register-email-hint'), 'The invitation was sent to this address.');
        assert.equal(await text('h1'), 'Create your account');
        assert.equal(await text('[data-register-lead]'), 'You have been invited to join Northwind Farms.');
        assert.equal(await tab.getAttribute('[data-guest-card] form', 'action'), '/invitations/token/register');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'name');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit((page) => invalid(page, { name: 'The name field is required.' }));
        assert.deepEqual(posted(), { name: '', password: 'a-new-password-1', password_confirmation: 'a-new-password-1', email: 'ada@example.com' }, 'the shown address is posted from the view model');
        assert.match(await text('#name-error'), /name field is required/);
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'name', 'focus moves to name');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.inputValue('input[name=password_confirmation]'), '');
        await tab.fill('input[name=name]', 'Ada');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit((page) => invalid(page, { email: 'This invitation is for a different address.' }));
        assert.equal(await text('[data-register-error]'), 'This invitation is for a different address.');
        assert.equal(await tab.getAttribute('[data-register-error]', 'role'), 'alert');
        assert.equal(await tab.inputValue('input[name=password]'), '');
        await submit((page) => invalid(page, { invitation: 'This invitation has expired.' }));
        assert.equal(await text('[data-register-error]'), 'This invitation has expired.');
        await submit(throttled(42));
        assert.equal(await count(modal), 0);
        assert.equal(await text('[data-auth-throttled]'), 'Too many attempts with this invitation. Wait a minute, then try again.', 'the view model\'s 429 copy');
        assert.equal(await count('[data-register-error]'), 0, 'the stale top alert is cleared');
        await submit({ status: 419, contentType: 'text/html', body: '<!DOCTYPE html><html><body>Page Expired</body></html>' });
        assert.equal(await count(modal), 0, 'a 419 stays in the card');
        assert.equal(await text('[data-auth-throttled]'), 'Your session expired. Reload the page and try again.');
    }, '&flow=invited');
});

test('Register, an invite that creates the invitee\'s workspace: the address is shown and not posted; the button is the view model\'s; a network failure is in the card', async () => {
    await authPage('Register', async ({ tab, submit, count, text, posted }) => {
        assert.equal(await count('input[name=email], input[type=email]'), 0);
        assert.equal(await text('[data-register-email]'), 'ada@example.com');
        assert.equal(await text('[data-guest-card] form button[type=submit]'), 'Create account and workspace');
        assert.equal(await count('[data-register-lead]'), 0, 'no lead line when the view model gives none');
        await tab.fill('input[name=name]', 'Ada');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await submit((page) => invalid(page, { password: 'The password field must be at least 12 characters.' }));
        assert.deepEqual(posted(), { name: 'Ada', password: 'a-new-password-1', password_confirmation: 'a-new-password-1' }, 'no email is posted');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('name')), 'password');
        await tab.route(/\/invite\/token\/register$/, (route) => route.abort('internetdisconnected'));
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        await tab.click('[data-guest-card] form button[type=submit]');
        await tab.waitForSelector('[data-auth-throttled]');
        assert.equal(await text('[data-auth-throttled]'), 'We could not reach the server. Check your connection and try again.');
        assert.equal(await count(modal), 0);
        assert.equal(await count(fieldErrors), 0);
        assert.equal(await tab.inputValue('input[name=password]'), '');
        assert.equal(await tab.inputValue('input[name=password_confirmation]'), '');
    }, '&flow=workspace');
});

test('Register, an invitation revoked mid-form: the server\'s redirect back to the invitation page is followed, with its flashed message', async () => {
    await authPage('Register', async ({ tab, submit, count }) => {
        await tab.fill('input[name=name]', 'Ada');
        await tab.fill('input[name=password]', 'a-new-password-1');
        await tab.fill('input[name=password_confirmation]', 'a-new-password-1');
        // Laravel's 302 back to the invitation is followed by the browser's own request, so the visit's answer is the invitation page (Inertia JSON, its url).
        await submit({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ component: 'Auth/Invitation/Show/Page', url: '/invitations/token', version: null, flash: {}, clearHistory: false, encryptHistory: false, props: { title: 'Invitation', errors: {}, status: 'This invitation is no longer valid.' } }) });
        assert.equal(await tab.evaluate(() => window.location.pathname), '/invitations/token');
        assert.equal(await count('input[name=password_confirmation]'), 0, 'the register form is gone');
        assert.equal(await tab.evaluate(() => document.querySelector('[data-elsewhere]')?.textContent), 'This invitation is no longer valid.', 'the invitation page\'s flashed message');
        assert.equal(await count(modal), 0);
    }, '&flow=invited');
});

test('Login links to Register only while the view model passes the link', async () => {
    await authPage('Login', async ({ tab, count }) => {
        assert.equal(await count('[data-register-link]'), 0);
    });
    await authPage('Login', async ({ tab, text }) => {
        assert.equal(await tab.getAttribute('[data-register-link]', 'href'), '/register');
        assert.equal(await text('[data-register-link]'), 'Create an account');
    }, '&register');
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
// A host that lays the Workbench out itself (`:fill="false"` in its own full-height flex column) puts the bar at the viewport's foot: it counts as pinned.
test('a non-filling Workbench docked at the viewport\'s foot: its bar is pinned and no button is under a toast', async () => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?sticky-toast&docked&position=bottom-right`);
        await tab.locator('[data-workbench-actions] button').first().waitFor();
        const bar = await tab.$eval('[data-workbench-actions]', el => ({ bottom: el.getBoundingClientRect().bottom, pinned: el.getAttribute('data-pinned') }));
        assert.ok(Math.abs(bar.bottom - 900) <= 12, `the bar ends at the viewport's foot, give or take its container's gap (${bar.bottom})`);
        await tab.waitForFunction(() => document.querySelector('[data-workbench-actions]')?.getAttribute('data-pinned') === 'true', null, { timeout: 2000 }).catch(() => {});
        assert.equal(await tab.getAttribute('[data-workbench-actions]', 'data-pinned'), 'true', 'a bar at the viewport\'s foot counts as pinned');
        await tab.evaluate(() => window.kitToast('Slide duplicated.'));
        await tab.waitForSelector('[data-sonner-toast][data-mounted="true"]');
        await tab.waitForTimeout(500);
        const covered = await tab.evaluate(() => [...document.querySelectorAll('[data-workbench-actions] button')].filter(button => button.getBoundingClientRect().width > 0).filter(button => {
            const r = button.getBoundingClientRect();
            return [r.left + 2, r.left + r.width / 2, r.right - 2].some(x => { const hit = document.elementFromPoint(x, r.top + r.height / 2); return !(hit && button.contains(hit)); });
        }).map(button => button.textContent.trim()));
        assert.deepEqual(covered, [], 'no bar button is under the toast');
    } finally {
        await context.close();
    }
});

// ── The shell's mark: with no --shell-mark-* tokens a huge logomark stays in its 2rem box; FocusShell's #mark slot replaces the symbol ─────────────

test('the shell mark keeps a default height with no --shell-mark tokens, and FocusShell renders its #mark slot', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?shell-mark`);
        await tab.waitForSelector('[data-case="plain"] [data-shell-wordmark] img');
        const box = await tab.$eval('[data-case="plain"] [data-shell-wordmark]', el => { const img = el.querySelector('img').getBoundingClientRect(); const r = el.getBoundingClientRect(); return { height: r.height, imgHeight: img.height, imgWidth: img.width }; });
        assert.equal(Math.round(box.height), 32, `the mark's box is 2rem high (${box.height})`);
        assert.ok(box.imgHeight <= 32 && box.imgWidth <= 160, `a 1000px SVG stays inside it (${box.imgWidth}×${box.imgHeight})`);
        const bar = await tab.$eval('[data-case="focus"] [data-shell-bar]', el => el.getBoundingClientRect().height);
        assert.ok(bar <= 80, `the focus bar keeps its height (${bar})`);
        assert.equal(await tab.locator('[data-case="focus"] [data-shell-wordmark] [data-host-mark]').count(), 1, 'FocusShell renders its #mark slot inside the home link');
        assert.equal(await tab.locator('[data-case="focus"] [data-shell-wordmark] img').count(), 0, 'the slot replaces the default symbol');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
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
                return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), status: checked('[data-form-status]'), alerts: checked('[data-slot="alert"] *') };
            });
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1));
            assert.deepEqual(result.violations, []);
            assert.equal(result.status, 1, `${query}: axe checked the "Saved" status text's contrast`);
            assert.ok(result.alerts >= 4, `${query}: axe checked the pale Alerts' text contrast (${result.alerts} nodes)`);
        } finally {
            await context.close();
        }
    }
});

// ── Blocked storage (?appearance): useAppearance must mount, read 'system' and take a choice when localStorage throws on access ──
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

// ── The leave-page guard on the real Inertia (leave.html): Back and Forward, the history fallback, pending uploads, the save's own follow-up ──────────────

const inertiaPage = (component, url, props = {}) => ({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ component, props: { errors: {}, ...props }, url, version: null, flash: {}, clearHistory: false, encryptHistory: false }) });

// A fresh context on leave.html, which starts on Leave/Other (/other); `run` gets the tab on Leave/Edit (/edit), reached by an Inertia visit, so Back has a
// same-document entry to go to. `navigation: false` deletes window.navigation before any script runs (a browser without the Navigation API). `then` is where
// the save's onSuccess visits. `upload.release()` answers a held BulkDrop confirm.
async function leavePage(run, { navigation = true, then = null } = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    if (!navigation) await context.addInitScript(() => { delete window.navigation; });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        const upload = { held: [], release() { this.held.splice(0).forEach(answer => answer()); } };
        let saves = 0;
        await tab.route(/\/(edit|other)$/, (route) => {
            const request = route.request();
            if (!request.headers()['x-inertia']) return route.continue();
            if (request.url().endsWith('/other')) return route.fulfill(inertiaPage('Leave/Other', '/other'));
            if (request.method() === 'PUT') {
                saves++;
                // Laravel's redirect back to the saved page, as Inertia receives it.
                return route.fulfill(inertiaPage('Leave/Edit', '/edit', { title: JSON.parse(request.postData()).title, then }));
            }
            return route.fulfill(inertiaPage('Leave/Edit', '/edit', { title: 'Opening', then }));
        });
        await tab.route(/\/uploads\/request$/, route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ id: 'u1', status: 'pending', method: null, url: null, headers: {}, expires_at: null }) }));
        await tab.route(/\/uploads\/confirm$/, route => new Promise((resolve) => upload.held.push(() => resolve(route.fulfill({ status: 200, contentType: 'application/json', body: '{"id":"u1"}' })))));
        await tab.goto(`${authUrl}leave.html`);
        await tab.waitForSelector('[data-page="other"]');
        assert.equal(await tab.evaluate(() => 'navigation' in window), navigation, navigation ? 'the Navigation API is there' : 'window.navigation is deleted');
        await tab.click('[data-to-edit]');
        await tab.waitForSelector('[data-page="edit"]');
        const at = () => tab.evaluate(() => document.querySelector('[data-page]')?.getAttribute('data-page'));
        const asking = () => tab.locator('text=Leave this page?').count();
        // Back as the browser's button does it, without waiting for a load that a held traverse never has.
        const back = async () => { await tab.goBack({ waitUntil: 'commit', timeout: 1000 }).catch(() => {}); await tab.waitForTimeout(300); };
        await run({ tab, at, asking, back, upload, saves: () => saves });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

const backWhileDirty = (navigation) => async () => {
    await leavePage(async ({ tab, at, asking, back }) => {
        const length = await tab.evaluate(() => history.length);
        await tab.fill('#title', 'My edit');
        await tab.waitForSelector('[data-form-status][data-state="dirty"]');
        await back();
        await tab.waitForSelector('text=Leave this page?');
        await tab.click('text=Stay');
        await tab.waitForTimeout(300);
        assert.equal(await at(), 'edit', 'Stay keeps the page');
        assert.equal(await tab.inputValue('#title'), 'My edit', 'and the edit');
        assert.equal(new URL(tab.url()).pathname, '/edit');
        await back();
        await tab.waitForSelector('text=Leave this page?');
        await tab.click('button:has-text("Leave")');
        await tab.waitForSelector('[data-page="other"]');
        assert.equal(new URL(tab.url()).pathname, '/other');
        assert.equal(await asking(), 0);
        if (!navigation) assert.equal(await tab.evaluate(() => history.length), length + 1, 'one guard entry at most, never one per Back');
        // Forward comes back to the form page, not to a blank or another page's entry.
        await tab.goForward({ waitUntil: 'commit' });
        await tab.waitForSelector('[data-page="edit"]');
        assert.equal(new URL(tab.url()).pathname, '/edit');
    }, { navigation });
};
const backWhileClean = (navigation) => async () => {
    await leavePage(async ({ tab, at, asking, back }) => {
        const length = await tab.evaluate(() => history.length);
        // Dirty and held once (Stay), then clean again (Cancel): the guard lets go, and its entry, if any, is gone, so one Back leaves.
        await tab.fill('#title', 'My edit');
        await tab.waitForSelector('[data-form-status][data-state="dirty"]');
        await back();
        await tab.waitForSelector('text=Leave this page?');
        await tab.click('text=Stay');
        await tab.waitForTimeout(300);
        await tab.click('[data-form-cancel]');
        await tab.waitForTimeout(300);
        assert.equal(await tab.inputValue('#title'), 'Opening');
        await back();
        await tab.waitForSelector('[data-page="other"]');
        assert.equal(await asking(), 0, 'a clean form does not ask');
        assert.equal(await at(), 'other', 'one Back leaves: no guard entry is left behind');
        // Forward comes back to the form page itself, then Back leaves again at once.
        await tab.goForward({ waitUntil: 'commit' });
        await tab.waitForSelector('[data-page="edit"]');
        await back();
        await tab.waitForSelector('[data-page="other"]');
        assert.equal(await asking(), 0);
        if (!navigation) assert.ok(await tab.evaluate(() => history.length) <= length + 1, 'no entries pile up');
    }, { navigation });
};
test('leave guard (Navigation API): Back with a dirty form asks; Stay keeps the page and the edit, Leave goes back', backWhileDirty(true));
test('leave guard (Navigation API): once the form is clean again, Back does not ask', backWhileClean(true));
test('leave guard (no Navigation API, history fallback): Back with a dirty form asks; Stay keeps the page, Leave goes back, with one guard entry at most', backWhileDirty(false));
test('leave guard (no Navigation API, history fallback): once the form is clean again, Back does not ask, and one Back leaves (no guard entry left behind)', backWhileClean(false));

test('leave guard: an upload in flight (BulkDrop direct) counts as unsaved on a clean form; once it ends, Back leaves', async () => {
    await leavePage(async ({ tab, at, asking, back, upload }) => {
        await tab.setInputFiles('[data-dropzone-input]', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('synthetic notes') });
        await tab.waitForFunction(() => document.body.textContent.includes('Uploading'));
        assert.equal(await tab.getAttribute('[data-form-status]', 'data-state'), 'idle', 'the form itself is clean');
        await back();
        await tab.waitForSelector('text=Leave this page?');
        await tab.click('text=Stay');
        assert.equal(await at(), 'edit');
        upload.release();
        await tab.waitForFunction(() => !document.body.textContent.includes('Uploading'));
        await back();
        await tab.waitForSelector('[data-page="other"]');
        assert.equal(await asking(), 0);
    });
});

test('leave guard: a successful save\'s follow-up visit (from onSuccess, the form still dirty) is not held', async () => {
    await leavePage(async ({ tab, asking, saves }) => {
        await tab.fill('#title', 'Saved title');
        await tab.waitForSelector('[data-form-status][data-state="dirty"]');
        await tab.click('[data-form-submit]');
        await tab.waitForSelector('[data-page="other"]', { timeout: 3000 });
        assert.equal(saves(), 1);
        assert.equal(await asking(), 0, 'the save\'s own visit is let through');
        assert.equal(new URL(tab.url()).pathname, '/other');
    }, { then: '/other' });
});
