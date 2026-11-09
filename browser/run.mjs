// Real-browser checks of the kit Modal's overlays (node browser/run.mjs, after npm ci and npx playwright install chromium). The kit Modal is the package's native
// <dialog> in the top layer; its confirmation, selects and menus must open over it, take clicks, and close on Escape before the Modal does. A zero-item Workbench
// (?workbench) must pass axe with its panes open and collapsed, and keep its keyboard focus. A Workbench of three items with row menus (?workbench-menu) must pass
// axe, keep focusable elements out of its listbox, and open each item's menu from Shift+F10 and right-click. Its row menu with checkbox items
// (?workbench-menu=checkbox) and a host's #navigator-item rows (?workbench-menu=navitem) must pass axe with the listbox semantics kept. A reorderable Workbench
// (?workbench-reorder) over a stand-in for Inertia v3's HTTP client (string bodies) must carry the version token from each move to the next write. A filmstrip
// navigator with thumbnails (?workbench-menu=filmstrip) must keep its images inside the options with their alt, keep the listbox semantics, reorder, filter and
// F6, pass axe, and hold each thumbnail's box at its size before the image loads. Its #navigator-thumbnail (&live) must stay inert inside that box.
// At phone width (?workbench-menu&long) a long footer or inspector tab must scroll inside its row, its last line fully visible above the actions bar.
// The page rhythm (?rhythm): a <Page> spaces its in-flow children with one equal gap and gives out-of-flow ones none, <Page flush> none at all, and a PageGrid lays
// out its presets and spans in 12 columns at 1280px and one below md. PageEnd (?page-end) measures from the last in-flow sibling before it, past a hidden,
// fixed or absolute helper.
// A FormStatus mounted before the visit (?form-status) must show after each visit that carries a message and hide only while one is in flight.
// session-ui's guest pages (?auth=) over an Inertia <Form> stand-in: failures, a 429 and a bad reset token stay in the card (no error modal), fields reset and refocus.
// Each kit form control (?form-attrs) must put a caller's aria-describedby, aria-invalid and required on its focusable control, not a wrapper, and pass axe.
// The leave-page guard (leave.html, on the real @inertiajs/vue3) must hold Back from a dirty form (Stay keeps it, Leave goes) and not a clean one, with the
// Navigation API and with window.navigation deleted (the history fallback, leaving no entry behind), count a BulkDrop upload in flight as unsaved, and let a
// save's onSuccess visit through.
// A SwatchPicker (?swatch, light and dark) must pick by keyboard, post an empty value for its default swatch and pass axe.
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
// Page grounds (?tinted, light and dark, at 1280px): on the sample tinted ground a DataTable, FormCard, EmptyState and PageSection each sit on one card with a
// --border edge and a background other than the ground, none nested, the chrome on the ground, and axe passes; on the white ground (?tinted=white) no card is added.
// The kit's mail (mail/, from tests/MailRenderCheck.php) must pass axe contrast in light and dark, break no button label inside a word from 320 to 640px, and
// show the lockup on its opaque plate over the dark header.
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { cpSync, readFileSync, readdirSync, renameSync, rmSync, statSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { chromium } from 'playwright';
import { build, createServer, preview } from 'vite';

const here = import.meta.dirname;
const kit = path.join(here, '.kit');
rmSync(kit, { recursive: true, force: true });
cpSync(path.join(here, '../resources/features/core/components/Kit'), path.join(kit, 'Kit'), { recursive: true });
cpSync(path.join(here, '../resources/features/core/css/kit.css.stub'), path.join(kit, 'kit.css'));
// session-ui's guest pages (?auth=), which import the kit barrel as `@kit/ui` and Inertia's <Form> (the stand-in in src/inertia.js).
cpSync(path.join(here, '../resources/features/session-ui/pages'), path.join(kit, 'Pages'), { recursive: true });
// The shell's public pages (?public): PublicShell and the Markdown page.
cpSync(path.join(here, '../resources/features/shell/pages'), path.join(kit, 'Pages'), { recursive: true });
// The notes composer (notes.html).
cpSync(path.join(here, '../resources/features/notes/pages'), path.join(kit, 'Pages'), { recursive: true });
// The dashboard grid (dashboard.html).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Dashboard'), path.join(kit, 'Pages/Workspace/Dashboard'), { recursive: true });
// The workspace list (workspaces.html).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Index'), path.join(kit, 'Pages/Workspace/Index'), { recursive: true });
// mcp's consent page (auth.html?auth=Mcp/Authorize), a compact card like the guest pages.
cpSync(path.join(here, '../resources/features/mcp/pages'), path.join(kit, 'Pages'), { recursive: true });
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

// ── Modal initial focus (?modal-focus=<ms>): the package's trap focuses its first focusable (Close) a frame after its enter animation, however slow ──────
// The fixture stands in for the package's ModalContent and createFocusTrap; the panel must end on its first field once the trap has had its turn.
async function openFocusModal(query, { cpu, beforeTrap } = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const tab = await context.newPage();
    if (cpu) await (await context.newCDPSession(tab)).send('Emulation.setCPUThrottlingRate', { rate: cpu });
    await tab.goto(`${url}?${query}`);
    await tab.click('[data-open]');
    if (beforeTrap) await beforeTrap(tab);
    await tab.waitForFunction(() => window.trapFocused === true, null, { timeout: 30000 });
    await tab.waitForTimeout(150);
    const focused = await tab.evaluate(() => {
        const el = document.activeElement;
        return el?.hasAttribute('data-first-field') ? 'first' : el?.hasAttribute('data-second-field') ? 'second' : el?.hasAttribute('data-modal-close') ? 'close' : el?.tagName;
    });
    await context.close();
    return focused;
}

test('a modal whose enter animation outlasts 400ms (2s, CPU throttled 6x) ends with focus on its first field, not the trap\'s Close button', async () => {
    assert.equal(await openFocusModal('modal-focus=2000', { cpu: 6 }), 'first');
});

test('a modal at normal speed (300ms enter) ends with focus on its first field', async () => {
    assert.equal(await openFocusModal('modal-focus=300'), 'first');
});

test('a modal with no field leaves focus where the trap put it (Close)', async () => {
    assert.equal(await openFocusModal('modal-focus=1000&nofield'), 'close');
});

test('a modal already marked entered when the panel mounts and whose trap takes no focus closes its opening window: later programmatic focus on Close is left alone', async () => {
    const focused = await openFocusModal('modal-focus=0&entered&notrap', {
        beforeTrap: async (tab) => {
            await tab.waitForFunction(() => window.trapFocused === true);
            await tab.waitForTimeout(300);
            await tab.evaluate(() => document.querySelector('[data-modal-close]').focus());
        },
    });
    assert.equal(focused, 'close');
});

test('closing the panel while it opens leaves none of its document listeners behind', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?modal-focus=3000`);
        const before = await tab.evaluate(() => window.openingListeners());
        await tab.evaluate(() => document.querySelector('[data-open]').click());
        await tab.waitForFunction(() => document.activeElement?.hasAttribute('data-first-field'));
        assert.ok(await tab.evaluate(() => window.openingListeners()) > before, 'the opening window is listening');
        await tab.evaluate(() => document.querySelector('[data-modal-close]').click());
        await tab.waitForSelector('[data-modal-close]', { state: 'detached' });
        assert.equal(await tab.evaluate(() => window.openingListeners()), before);
    } finally {
        await context.close();
    }
});

test('with no entered marker on the modal, a field focused by script and the page\'s own input take the typing right away', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?modal-focus=3000&nomarker`);
        const before = await tab.evaluate(() => window.openingListeners());
        await tab.evaluate(() => document.querySelector('[data-open]').click());
        await tab.waitForFunction(() => document.activeElement?.hasAttribute('data-first-field'));
        // A programmatic focus (a script, or a test driver's focus()), then typing: nothing pulls it back to the first field.
        await tab.evaluate(() => document.querySelector('[data-second-field]').focus());
        await tab.keyboard.type('quarterly', { delay: 20 });
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-second-field')), true, 'a programmatic focus inside the modal is not pulled back');
        await tab.click('[data-page-input]', { timeout: 1000 });
        await tab.keyboard.type('north', { delay: 20 });
        assert.equal(await tab.inputValue('[data-page-input]'), 'north');
        assert.equal(await tab.inputValue('[data-second-field]'), 'quarterly');
        assert.equal(await tab.evaluate(() => window.openingListeners()), before, 'input ended the window');
    } finally {
        await context.close();
    }
});

test('with no entered marker and no input, the opening window closes within about a second', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?modal-focus=5000&nomarker`);
        const before = await tab.evaluate(() => window.openingListeners());
        await tab.evaluate(() => document.querySelector('[data-open]').click());
        await tab.waitForTimeout(1300);
        assert.equal(await tab.evaluate(() => window.openingListeners()), before);
    } finally {
        await context.close();
    }
});

test('a viewer who tabs before the enter animation ends keeps their focus after the trap fires', async () => {
    const focused = await openFocusModal('modal-focus=1500', {
        beforeTrap: async (tab) => {
            await tab.waitForFunction(() => document.activeElement?.hasAttribute('data-first-field'));
            await tab.keyboard.press('Tab');
        },
    });
    assert.equal(focused, 'second');
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

test('a write made while a move\'s reply is still on its way waits for it: the row menu is busy meanwhile, and a host write after whenIdle() sends the new token', async () => {
    await menuWorkbench('workbench-reorder=slow', async tab => {
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('Alt+ArrowDown');
        await tab.waitForFunction(() => window.kitServer.log.length === 1);
        // The server has the move and its reply is still on its way: the version token hasn't moved yet.
        await tab.click('[role="option"][data-workbench-item="s3"]', { button: 'right' });
        await tab.waitForSelector('[role="menu"]');
        assert.equal(await tab.getAttribute('[role="menu"] [role="menuitem"]:has-text("Duplicate")', 'data-disabled'), '', 'the row menu is busy while the move is in flight');
        await tab.keyboard.press('Escape');
        await tab.evaluate(() => window.kitNewSlide());
        assert.deepEqual(await tab.evaluate(() => window.kitServer.log), [
            { url: '/decks/1/order', sent: 1, status: 200 },
            { url: '/slides/new', sent: 2, status: 200 },
        ], 'the host write waited for the move\'s reply and sent its token: no 409');
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

// Compact auth cards (docs/page-rhythm.md): Page's gap-6 between blocks, with deliberate tighter groups. Measured on the real pages, light and dark at 1280px.
const compactAuthCards = async (scheme) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, colorScheme: scheme });
    try {
        const tab = await context.newPage();
        const box = (selector) => tab.$eval(selector, (el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, width: r.width }; });
        for (const [name, status] of [['Login', '[data-login-status]'], ['Register', '[data-register-status]']]) {
            await tab.goto(`${authUrl}auth.html?auth=${name}&status=${encodeURIComponent('Your password has been reset.')}`);
            await tab.waitForSelector(status);
            const heading = await tab.$eval('h1', (el) => el.parentElement.getBoundingClientRect().bottom);
            const [alert, form] = [await box(status), await box('form[novalidate]')];
            assert.equal(Math.round(alert.top - heading), 16, `${name}: the status is 16px under the heading`);
            assert.equal(Math.round(alert.width), Math.round(form.width), `${name}: the status spans the card`);
        }
        await tab.goto(`${authUrl}auth.html?auth=Mcp/Authorize`);
        await tab.waitForSelector('[data-test="consent-deny"]');
        const [approve, deny, summary, scopes] = [await box('[data-kit-consent="approve"]'), await box('[data-kit-consent="deny"]'), await box('[data-consent-title]'), await box('[data-consent-scopes]')];
        assert.equal(Math.round(deny.top - approve.bottom), 12, 'Deny is 12px under Approve: one decision');
        const intro = await tab.$eval('[data-consent-title]', (el) => el.parentElement.getBoundingClientRect().bottom);
        assert.equal(Math.round(scopes.top - intro), 16, 'the scopes are 16px under the request');
        assert.equal(Math.round(approve.top - scopes.bottom), 24, 'the decision is the page gap (24px) under the request');
        assert.ok(summary.top >= 0);
    } finally {
        await context.close();
    }
};

test('Compact auth cards (light): a flashed status sits 16px under the heading and spans the card; Deny sits 12px under Approve', () => compactAuthCards('light'));
test('Compact auth cards (dark): a flashed status sits 16px under the heading and spans the card; Deny sits 12px under Approve', () => compactAuthCards('dark'));

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
        assert.deepEqual(ids(c.describedby), ['hint', 'terms-hint']);
        assert.equal(c.invalid, 'true');
        assert.equal(c.ariaRequired, 'true');
        assert.equal(c.probe, 'checkbox');
        assert.equal(c.focusable, true);
        assert.equal(await tab.locator('#terms-hint').textContent(), 'You can withdraw at any time.');
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

test('RadioCardGroup: the caller\'s describedby lands on the radiogroup (outside a Field, no field ids that don\'t exist), with aria-invalid and aria-required; the radios are required', async () => {
    await formAttrs(async (tab) => {
        const c = await controlAttrs(tab, '[role="radiogroup"]');
        assert.deepEqual(ids(c.describedby), ['hint']);
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

test('a scroll-pinned StickyBar reveals a control that takes focus under it, without moving focus', async () => {
    const context = await browser.newContext({ viewport: { width: 390, height: 600 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?sticky-toast&form`);
        await tab.waitForSelector('[data-sticky-bar][data-pinned="true"]');
        const overlap = id => tab.evaluate(id => {
            const field = document.getElementById(id).getBoundingClientRect();
            const bar = document.querySelector('[data-sticky-bar]').getBoundingClientRect();
            return field.bottom > bar.top && field.top < bar.bottom;
        }, id);
        await tab.evaluate(() => {
            const field = document.getElementById('field-8').getBoundingClientRect();
            const bar = document.querySelector('[data-sticky-bar]').getBoundingClientRect();
            window.scrollBy(0, field.bottom - bar.top - 12);
        });
        assert.equal(await overlap('field-8'), true, 'field 8 starts under the pinned bar');
        await tab.focus('#field-7');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.id), 'field-8', 'focus moved by Tab only');
        assert.equal(await overlap('field-8'), false, 'the focused field is scrolled clear of the bar');
        await tab.keyboard.press('Shift+Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.id), 'field-7');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── Overlays opened from a FloatingToolbar (?surface-overlay): the toolbar's pill/inverse surface stops at the overlay ─────────────────────────────

test('a Dialog or Popover opened from a FloatingToolbar renders ordinary buttons; its trigger keeps the pill', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?surface-overlay`);
        const radius = selector => tab.$eval(selector, el => getComputedStyle(el).borderTopLeftRadius);
        const pill = await radius('[data-test-toolbar-button]');
        assert.ok(parseFloat(pill) >= 18, `the toolbar's own button is a pill (${pill})`);
        assert.equal(await radius('[data-test-dialog-trigger]'), pill, 'the Dialog trigger keeps the pill');
        assert.equal(await radius('[data-test-popover-trigger]'), pill, 'the Popover trigger keeps the pill');

        await tab.click('[data-test-dialog-trigger]');
        await tab.waitForSelector('[data-confirm-dialog]');
        const dialogButtons = await tab.$$eval('[data-confirm-dialog] button', els => els.map(el => [el.textContent.trim(), getComputedStyle(el).borderTopLeftRadius, el.className.includes('rounded-full')]));
        assert.ok(dialogButtons.length >= 2, 'the dialog has its cancel and confirm buttons');
        for (const [label, r, full] of dialogButtons) {
            assert.ok(parseFloat(r) < 18 && !full, `dialog button "${label}" is not a pill (${r})`);
        }
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[data-confirm-dialog]', { state: 'detached' });

        await tab.click('[data-test-popover-trigger]');
        await tab.waitForSelector('[data-test-popover-button]');
        const apply = await radius('[data-test-popover-button]');
        assert.ok(parseFloat(apply) < 18, `the popover's button is not a pill (${apply})`);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── A finalize gate (?finalize): step errors and descriptor actions, the server's progress, the certify control and the finalized banner ──────────

async function finalizeGate(width, scheme) {
    {
        const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: scheme });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?finalize${scheme === 'dark' ? '=dark' : ''}`);
            const gate = '[data-test-gate]';
            const errorsOf = key => tab.$$eval(`${gate} [data-checklist-item]:has([data-checklist-errors]) li[data-field], ${gate} [data-checklist-errors] li`, els => els.map(el => el.textContent.trim()));
            const all = await errorsOf();
            assert.ok(all.includes('Comparable 2 has no adjusted price.') && all.includes('Comparable 3 has no sale date.'), 'a step shows each of its errors');
            assert.equal(await tab.$eval(`${gate} [data-field="comparables.1.price"] a`, a => a.getAttribute('href')), '#comparables', 'an error with a url links to its fix and names its field');
            assert.deepEqual(await tab.$$eval(`${gate} [data-checklist-actions] button, ${gate} [data-checklist-actions] a`, els => els.map(el => el.textContent.trim())), ['Open comparables', 'Confirm'], 'each step\'s descriptor actions are drawn as buttons');
            assert.equal(await tab.$$eval(`${gate} [data-state="done"] [data-checklist-errors]`, els => els.length), 0, 'a done step shows no errors');
            assert.equal(await tab.$$eval(`${gate} [data-checklist-turn]`, els => els.length), 1, 'with active="turn" the active step is a still ring');
            assert.equal(await tab.$$eval(`${gate} .animate-spin, ${gate} [class*="animate-spin"]`, els => els.length), 0, 'and nothing spins');
            assert.deepEqual(await tab.$$eval(`${gate} [data-step-action]`, els => els.map(el => el.getAttribute('data-step-action'))), ['comparables:fix-comparables', 'review:confirm-review', 'details:details'], 'every fix carries data-step-action="<step>:<action>"');
            const look = sel => tab.$eval(`${gate} [data-step-action="${sel}"] > *`, el => [el.tagName, getComputedStyle(el).height, getComputedStyle(el).textDecorationLine]);
            assert.deepEqual(await look('details:details'), await look('comparables:fix-comparables'), 'a plain item action and a descriptor fix look the same');
            assert.equal(await tab.textContent('[data-test-complete]'), 'incomplete', 'the server\'s progress decides completion, though no step failed');
            assert.equal(await tab.getAttribute(`${gate} [data-checklist]`, 'data-complete'), null);

            const blocked = '[data-test-blocked] [data-finalize-control]';
            assert.ok(await tab.isDisabled(`${blocked} button[type=submit]`) && await tab.isDisabled(`${blocked} [role=checkbox], ${blocked} input[type=checkbox]`), 'while the server sends Finalize disabled, the box and the button are disabled');
            assert.equal((await tab.textContent(`${blocked} [data-finalize-reason]`)).trim(), 'Every required step must be done first.');
            const ready = '[data-test-ready] [data-finalize-control]';
            assert.match(await tab.textContent(`${ready} [data-finalize-statement]`), /Placeholder until the client supplies the wording/, 'the statement is shown as sent');
            assert.ok(await tab.isDisabled(`${ready} button[type=submit]`), 'Finalize waits for the box');
            await tab.click(`[data-test-ready] label`);
            assert.ok(!(await tab.isDisabled(`${ready} button[type=submit]`)), 'ticking the box enables Finalize');

            const banner = '[data-test-banner] [data-finalized-banner]';
            assert.match(await tab.textContent(banner), /Finalized by Ada Lovelace on .*through an assistant\./s, 'the banner names who, when, and the assistant channel');
            assert.doesNotMatch(await tab.textContent('[data-test-banner-plain]'), /assistant/, 'a web finalization does not mention an assistant');
            assert.equal(await tab.$$eval('[data-test-banner-plain] [data-finalized-unfinalize]', els => els.length), 0, 'without the action there is no Un-finalize');
            await tab.click(`${banner} [data-finalized-unfinalize] button`);
            const submit = `${banner} form button[type=submit]`;
            assert.ok(await tab.isDisabled(submit), 'Un-finalize asks for a reason before it can be sent');
            await tab.fill(`${banner} textarea`, 'Wrong figure');
            assert.ok(!(await tab.isDisabled(submit)), 'a reason enables it');
            const overflow = await tab.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            assert.equal(overflow, false, 'nothing overflows the viewport');
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
}

test('a finalize gate draws its errors, fixes, certify control and banner (390px, light)', () => finalizeGate(390, 'light'));
test('a finalize gate draws its errors, fixes, certify control and banner (1440px, dark)', () => finalizeGate(1440, 'dark'));

async function finalizeSteps(width, scheme) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: scheme });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?finalize${scheme === 'dark' ? '=dark' : ''}`);
        const rail = '[data-test-steps]';
        assert.equal(await tab.getAttribute(`${rail} [data-checklist-items]`, 'data-variant'), 'steps');
        const markers = await tab.$$eval(`${rail} [data-checklist-marker]`, els => els.map(el => [Math.round(el.getBoundingClientRect().width), el.textContent.trim(), !!el.querySelector('svg')]));
        assert.equal(markers.length, 6, 'one marker per step, and one for the finalize control at the end of the rail');
        assert.ok(markers.every(([w]) => w === 32), 'markers are 32px circles');
        assert.deepEqual(markers.map(([, text, svg]) => svg ? 'icon' : text), ['icon', '2', '3', '4', 'icon', 'icon'], 'done, skipped and the final step show icons; the others their number');
        const rails = await tab.$$eval(`${rail} [data-checklist-rail]`, els => els.map(el => [el.className.includes('bg-primary'), Math.round(el.getBoundingClientRect().height)]));
        assert.equal(rails.length, 5, 'every step joins the next, the last one joins the finalize control');
        assert.deepEqual(rails.map(([on]) => on), [true, false, false, false, false], 'the rail is filled only while every step so far is settled: a skipped step after open ones does not fill it');
        assert.ok(rails.every(([, h]) => h > 24), 'the rail runs between markers without gaps');
        const label = await tab.$eval(`${rail} [data-state="active"] [data-checklist-label]`, el => [parseFloat(getComputedStyle(el).fontSize), getComputedStyle(el).fontWeight]);
        assert.ok(label[0] >= 18 && Number(label[1]) >= 600, 'step titles are large (text-lg) and semibold at every width');
        assert.equal((await tab.textContent(`${rail} [data-checklist-final] [data-checklist-label]`)).trim(), 'Finalize', 'the last rail step is titled like the others');
        const gaps = await tab.$$eval(`${rail} [data-checklist-item]:not([data-checklist-final])`, els => els.map(el => getComputedStyle(el).paddingBottom));
        assert.ok(gaps.every(gap => gap === '80px'), 'each step body has generous room below it (80px)');
        assert.deepEqual(await tab.$$eval(`${rail} [data-step-action]`, els => els.map(el => el.getAttribute('data-step-action'))), ['comparables:fix-comparables', 'review:confirm-review', 'details:details'], 'each step body keeps its fixes');
        assert.equal(await tab.$$eval(`${rail} [data-checklist-final] [data-finalize-control]`, els => els.length), 1, 'the finalize control is the last rail step');
        assert.deepEqual(await tab.$$eval(`${rail} [data-checklist-warnings] li`, els => els.map(el => [el.textContent.trim(), el.closest('[role=alert]') === null])), [['No subject photos.', true]], 'a warning is drawn in its step, not as an alert');
        assert.equal(await tab.$$eval(`${rail} [data-step-action="review:confirm-review"]`, els => els.length), 1, 'and the step keeps its Confirm');
        assert.deepEqual(await tab.$$eval(`${rail} [data-checklist-body] [data-test-editor]`, els => els.map(el => [el.closest('[data-checklist-item]').getAttribute('data-state') && el.closest('[data-checklist-item]').querySelector('[data-checklist-label]').textContent.trim(), el.textContent.trim()])), [['Comparables adjusted', 'comparables editor (3 comparables)'], ['Printed figures reviewed', 'review editor (printed figures)']], 'the body slot puts each step\'s mapped editor under that step, with its props');
        assert.equal(await tab.$$eval(`${rail} [data-checklist-body]:empty`, els => els.length), 3, 'steps without an editor have an empty body');
        const order = await tab.$eval(`${rail} [data-checklist-item][aria-current="step"]`, li => [...li.querySelectorAll('[data-checklist-label], [data-checklist-errors], [data-checklist-body], [data-checklist-actions]')].map(el => [...el.attributes].map(a => a.name).find(n => n.startsWith('data-checklist-'))));
        assert.deepEqual(order, ['data-checklist-label', 'data-checklist-errors', 'data-checklist-body', 'data-checklist-actions'], 'label, then errors, then the body, then the fixes');
        assert.equal(await tab.$$eval(`${rail} [class*="animate-spin"]`, els => els.length), 0, 'with active="turn" nothing spins');
        assert.equal(await tab.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'nothing overflows the viewport');
        if (process.env.SHOTS) await tab.evaluate(() => { document.body.style.background = getComputedStyle(document.querySelector("main")).backgroundColor; }); await tab.locator(rail).screenshot({ path: `${process.env.SHOTS}/steps-${width}-${scheme}.png` });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test('a finalize gate as a vertical stepper: markers, rail, titles, fixes and the finalize step (390px, light)', () => finalizeSteps(390, 'light'));
test('a finalize gate as a vertical stepper: markers, rail, titles, fixes and the finalize step (1440px, dark)', () => finalizeSteps(1440, 'dark'));

// ── A deliver panel (?deliver): recipients from live contacts only, internal copies as Bcc, a sandboxed preview, schedule, test, resend and history ─────

async function deliverPanel(width, scheme) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: scheme });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.addInitScript(() => {
            window.kitPreviewRequests = [];
            window.kitHttpClient = { request: async (config) => {
                window.kitPreviewRequests.push(config);
                return { status: 200, data: JSON.stringify({ subject: config.data.subject, html: '<html><body><p>' + config.data.message.replace(/</g, '&lt;') + '</p><script>parent.kitPwned = true</' + 'script><a href="https://evil.test">x</a></body></html>', attachment: 'attached', attachmentName: 'report-7.pdf', attachmentBytes: 20480 }) };
            } };
        });
        await tab.goto(`${url}?deliver${scheme === 'dark' ? '=dark' : ''}`);
        const panel = '[data-deliver-panel]';
        const labels = { to: 'To', cc: 'Cc', none: 'Not on this send', now: 'Send now', morning: 'Tomorrow morning (8:00)', pick: 'Pick a date and time' };
        const choose = async (trigger, value) => {
            await tab.click(trigger);
            await tab.getByRole('option', { name: labels[value], exact: true }).click();
        };
        const confirm = async () => {
            await tab.waitForSelector('[data-confirm-dialog]');
            await tab.click('[data-confirm-dialog] [data-confirm-action]');
            await tab.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
        };
        const roles = () => tab.$$eval(`${panel} [data-deliver-option]`, els => els.map(el => el.getAttribute('data-deliver-choice')));
        assert.deepEqual(await roles(), ['to', 'cc'], 'each live contact starts in its own role');
        assert.equal(await tab.inputValue('[data-deliver-subject]'), 'Your report: Main St', 'the workspace default subject');
        assert.equal(await tab.inputValue('[data-deliver-message]'), 'Here is your report.', 'and message');

        // Preview: the exact draft goes to the server; the email is drawn in a sandbox that runs no script.
        await tab.fill('[data-deliver-message]', 'Hello <b>Robin</b>');
        await tab.click('[data-deliver-preview-button]');
        await tab.waitForSelector('[data-deliver-preview-frame]');
        const [request] = await tab.evaluate(() => window.kitPreviewRequests);
        assert.deepEqual([request.url, request.data.to, request.data.cc, request.data.bcc, request.data.message], ['/@acme/deliver/report.deliver/7/preview', ['robin@client.test'], ['sam@client.test'], ['office@example.test'], 'Hello <b>Robin</b>'], 'the preview posts the draft as it stands');
        assert.equal(await tab.getAttribute('[data-deliver-preview-frame]', 'sandbox'), '', 'the preview iframe is fully sandboxed');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => window.kitPwned ?? false), false, 'script in the email never runs');
        assert.match(await tab.textContent('[data-deliver-preview-attachment]'), /report-7\.pdf.*20 KB.*attached/s, 'the preview says the document is attached, with its size');

        // Send now: To, Cc, Bcc (internal copy plus typed), subject, message, a claim key; no send_at.
        await choose('[data-deliver-option=\"sam@client.test\"] button', 'none');
        await tab.fill('[data-deliver-extra-bcc]', 'Boss@Example.test, ');
        await tab.click('[data-deliver-send]');
        await tab.waitForSelector('[data-confirm-dialog]');
        assert.equal(await tab.evaluate(() => (window.kitVisits ?? []).length), 0, 'a real send asks first: nothing is posted yet');
        assert.match(await tab.textContent('[data-confirm-dialog]'), /Send to Robin Vale\?.*3 recipients, including 2 Bcc\. It sends now\./s, 'the confirm names who it goes to, how many, and when');
        await tab.getByRole('button', { name: 'Cancel', exact: true }).click();
        await tab.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
        assert.equal(await tab.evaluate(() => (window.kitVisits ?? []).length), 0, 'Cancel sends nothing');
        await tab.click('[data-deliver-send]');
        await confirm();
        const [sent] = await tab.evaluate(() => window.kitVisits);
        assert.equal(sent.url, '/@acme/deliver/report.deliver/7/send?revision=r1', 'Send posts to the server\'s action, which carries the revision');
        assert.deepEqual([sent.data.to, sent.data.cc, sent.data.bcc, sent.data.send_at], [['robin@client.test'], [], ['office@example.test', 'boss@example.test'], undefined], 'a contact left off is not sent to; typed Bcc is added, lowercased');
        assert.match(sent.data.claim_key, /^[0-9a-f-]{16,}$/, 'with a claim key');

        // Tomorrow morning: Schedule, with send_at at 8:00 tomorrow local time.
        await choose('[data-deliver-when]', 'morning');
        assert.equal((await tab.textContent('[data-deliver-send]')).trim(), 'Schedule');
        assert.match(await tab.textContent('[data-deliver-morning]'), /8:00/, 'it says when');
        await tab.click('[data-deliver-send]');
        await tab.waitForSelector('[data-confirm-dialog]');
        assert.match(await tab.textContent('[data-confirm-dialog]'), /Scheduled for .*8:00/s, 'a scheduled send says when in the confirm');
        await confirm();
        const scheduled = (await tab.evaluate(() => window.kitVisits)).at(-1);
        const at = await tab.evaluate(iso => { const d = new Date(iso); const t = new Date(); t.setDate(t.getDate() + 1); return [d.getHours(), d.getMinutes(), d.getDate() === t.getDate()]; }, scheduled.data.send_at);
        assert.deepEqual(at, [8, 0, true], 'send_at is 8:00 tomorrow in the person\'s time');
        assert.notEqual(scheduled.data.claim_key, sent.data.claim_key, 'a new send has a new claim key');

        // A picked time in the past cannot be sent.
        await choose('[data-deliver-when]', 'pick');
        await tab.fill('[data-deliver-at]', '2020-01-01T09:00');
        assert.equal(await tab.isDisabled('[data-deliver-send]'), true, 'a time in the past is refused here too');
        await choose('[data-deliver-when]', 'now');

        // A typed To (a customer with no contact email) counts as a To, and is named in the confirm.
        await choose('[data-deliver-option="robin@client.test"] button', 'none');
        await tab.fill('[data-deliver-extra-to]', 'Pat@Elsewhere.test');
        assert.equal(await tab.isDisabled('[data-deliver-send]'), false, 'a typed To is enough');
        await tab.click('[data-deliver-send]');
        await tab.waitForSelector('[data-confirm-dialog]');
        assert.match(await tab.textContent('[data-confirm-dialog]'), /Send to pat@elsewhere\.test\?/, 'the confirm names the typed address');
        await confirm();
        const typed = (await tab.evaluate(() => window.kitVisits)).at(-1);
        assert.deepEqual([typed.data.to, typed.data.to_other], [[], ['pat@elsewhere.test']], 'typed To goes as to_other, lowercased, apart from picked contacts');
        await tab.fill('[data-deliver-extra-to]', '');
        await choose('[data-deliver-option="robin@client.test"] button', 'to');

        // No To: Send is off.
        await choose('[data-deliver-option=\"robin@client.test\"] button', 'cc');
        assert.equal(await tab.isDisabled('[data-deliver-send]'), true, 'at least one To');
        assert.ok(await tab.isVisible('[data-deliver-needs-to]'));
        await choose('[data-deliver-option=\"robin@client.test\"] button', 'to');

        // Test: to the sender only; the server decides that, the panel posts only the wording.
        const before = await tab.evaluate(() => window.kitVisits.length);
        await tab.click('[data-deliver-test]');
        assert.equal(await tab.$$eval('[data-confirm-dialog]', els => els.length), 0, 'the test send does not ask');
        assert.equal(await tab.evaluate(() => window.kitVisits.length), before + 1);
        const test = (await tab.evaluate(() => window.kitVisits)).at(-1);
        assert.deepEqual([test.url, Object.keys(test.data).sort()], ['/@acme/deliver/report.deliver/7/test?revision=r1', ['claim_key', 'message', 'subject']], 'a test posts no recipients');

        // History: states, statuses, notes and actions.
        assert.deepEqual(await tab.$$eval('[data-deliver-entry]', els => els.map(el => el.getAttribute('data-deliver-state'))), ['scheduled', 'unknown', 'sent', 'sent']);
        assert.match(await tab.textContent('[data-deliver-entry="3"] [data-deliver-note]'), /did not confirm/, 'an unconfirmed send says so');
        assert.equal(await tab.getAttribute('[data-deliver-entry="1"] [data-deliver-recipient-status="bounced"]', 'data-deliver-recipient-status'), 'bounced', 'a bounce shows on its address');
        assert.equal(await tab.$$eval('[data-deliver-entry="2"] [data-deliver-action]', els => els.length), 0, 'a test has no actions');
        await tab.click('[data-deliver-action="cancel-4"]');
        assert.equal((await tab.evaluate(() => window.kitVisits)).at(-1).url, '/@acme/deliver/report.deliver/7/deliveries/4/cancel?revision=r1', 'Cancel posts its action');

        // Resend fills the form from that delivery and posts to its own action with revoke_previous.
        await tab.click('[data-deliver-action="resend-1"]');
        assert.ok(await tab.isVisible('[data-deliver-resending]'));
        assert.deepEqual(await roles(), ['to', 'cc'], 'the earlier recipients are chosen again');
        assert.equal((await tab.textContent('[data-deliver-send]')).trim(), 'Send again');
        await tab.click('[data-deliver-send]');
        await confirm();
        const resend = (await tab.evaluate(() => window.kitVisits)).at(-1);
        assert.deepEqual([resend.url, resend.data.revoke_previous], ['/@acme/deliver/report.deliver/7/deliveries/1/resend?revision=r1', true], 'resend posts to its action and turns off the earlier link by default');

        if (scheme === 'dark') assert.equal(await tab.evaluate(() => document.documentElement.classList.contains('dark')), true);
        if (process.env.SHOTS) await tab.screenshot({ path: `${process.env.SHOTS}/deliver-${width}-${scheme}.png`, fullPage: true });
        assert.deepEqual(errors, []);

        // Blocked: Send shows disabled with the gate's reason.
        await tab.goto(`${url}?deliver=blocked`);
        assert.equal(await tab.isDisabled('[data-deliver-send]'), true);
        assert.equal((await tab.textContent('[data-deliver-reason]')).trim(), 'Finalize it first.', 'the server\'s reason is shown');
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run('[data-deliver-panel]')).violations.map(v => `${v.id}: ${v.nodes.map(n => n.target).join(' | ')}`));
        assert.deepEqual(violations, [], 'the panel passes axe');
    } finally {
        await context.close();
    }
}

test('a deliver panel sends, schedules, previews safely and shows its history (390px, light)', () => deliverPanel(390, 'light'));
test('a deliver panel sends, schedules, previews safely and shows its history (1440px, dark)', () => deliverPanel(1440, 'dark'));

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

// ── SwatchPicker (?swatch): suggested swatches and a palette in one radiogroup, the arrows, Home and End; the default swatch is an empty value; axe ──────

const swatches = (tab) => tab.evaluate(() => [...document.querySelectorAll('[data-swatch-picker] [role="radio"]')].map(el => `${el.dataset.swatch}:${el.getAttribute('aria-checked')}:${el.tabIndex}`).join(' '));
async function swatch(query, check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-swatch-picker]');
        await check(tab);
    } finally {
        await context.close();
    }
}

test('SwatchPicker: suggested first and once each; the default swatch stands for an empty value; one tab stop, the arrows wrap, Home and End; the hex field and the posted value', async () => {
    await swatch('swatch', async tab => {
        assert.equal(await tab.getAttribute('[data-swatch-picker] [role="radiogroup"]', 'aria-label'), 'Accent colour');
        assert.equal(await swatches(tab), '#0E7490:false:-1 #B91C1C:false:-1 #1F2937:false:-1 #1D4ED8:false:-1 #15803D:false:-1 #DEB100:true:0', 'the suggested teal is not repeated in the palette; empty picks the default');
        assert.equal(await tab.getAttribute('[data-preview]', 'data-colour'), '#DEB100');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.dataset.swatch), '#DEB100', 'Tab lands on the picked swatch');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await tab.textContent('[data-value]'), '#0E7490', 'wraps to the first and picks it');
        assert.equal(await tab.inputValue('input[type="hidden"][name="accent"]'), '#0E7490');
        await tab.keyboard.press('End');
        assert.equal(await tab.textContent('[data-value]'), '', 'the default swatch posts an empty value');
        assert.ok(await tab.isVisible('[data-swatch-contrast]'), 'the light gold on white shows the contrast hint');
        await tab.keyboard.press('Home');
        await tab.keyboard.press('ArrowLeft');
        assert.equal(await tab.textContent('[data-value]'), '', 'ArrowLeft from the first wraps to the last (the default)');
        await tab.keyboard.press('ArrowUp');
        assert.equal(await tab.textContent('[data-value]'), '#15803D');
        assert.equal(await tab.isVisible('[data-swatch-contrast]'), false, 'a dark green needs no hint');
        const marks = await tab.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-swatch]')].map(el => [el.dataset.swatch, getComputedStyle(el).color])));
        assert.equal(marks['#DEB100'], 'rgb(0, 0, 0)', 'a black mark on the light gold');
        assert.equal(marks['#1F2937'], 'rgb(255, 255, 255)', 'a white mark on the dark slate');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => Boolean(document.activeElement.closest('[role="radiogroup"]'))), false, 'one tab stop: Tab leaves the group');
        await tab.click('[data-swatch-picker] button[aria-expanded]');
        await tab.fill('[data-swatch-hex]', '7c3aed');
        assert.equal(await tab.textContent('[data-value]'), '#7C3AED', 'a typed hex picks that colour');
        assert.equal(await swatches(tab).then(s => s.includes(':true:')), false, 'no swatch is checked for a custom colour');
        await tab.fill('[data-swatch-hex]', 'purple');
        assert.equal(await tab.textContent('[data-value]'), '#7C3AED', 'a non-colour keeps the last colour in v-model');
        assert.equal(await tab.inputValue('input[type="hidden"][name="accent"]'), 'purple', 'but is posted as typed, so the save is refused with the field error');
        await tab.fill('[data-swatch-hex]', '');
        assert.equal(await tab.textContent('[data-value]'), '', 'an empty hex is the default');
    });
});

test('SwatchPicker has no axe violations, light and dark (a non-modal surface)', async () => {
    for (const query of ['swatch', 'swatch=dark']) {
        await swatch(query, async tab => {
            await tab.addScriptTag({ path: axeSource });
            const violations = await tab.evaluate(async () => (await window.axe.run(document.querySelector('[data-swatch-picker]'))).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
            if (violations.length) console.log(query, JSON.stringify(violations, null, 1));
            assert.deepEqual(violations, []);
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

// ── DataTable (?datatable): a card surface on a ground of another colour, headers that never wrap, a descriptor-declared date column, plain grant removal ──

for (const scheme of ['light', 'dark']) {
    test(`DataTable (${scheme}): the table sits on the card surface, th never wraps, a date column renders through DateTime, and axe passes with contrast`, async () => {
        const context = await browser.newContext({ viewport: { width: 1024, height: 600 }, timezoneId: 'UTC', locale: 'en-US' });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?datatable${scheme === 'dark' ? '=dark' : ''}`);
            await tab.waitForSelector('[data-table-card]');
            const surface = await tab.evaluate(() => {
                const probe = document.createElement('div');
                probe.className = 'bg-card';
                document.body.append(probe);
                const card = getComputedStyle(probe).backgroundColor;
                probe.remove();
                return { card, table: getComputedStyle(document.querySelector('[data-table-card]')).backgroundColor, ground: getComputedStyle(document.querySelector('main')).backgroundColor };
            });
            assert.equal(surface.table, surface.card, 'the table card paints the card colour');
            assert.notEqual(surface.table, surface.ground, 'not the page ground');
            assert.deepEqual(await tab.$$eval('[data-table] thead th', els => [...new Set(els.map(el => getComputedStyle(el).whiteSpace))]), ['nowrap']);
            assert.equal(await tab.$eval('[data-column="connectedBy"]', el => el.getBoundingClientRect().height < 40), true, '"Connected by" stays on one line');
            const time = await tab.$eval('[data-cell="connected"] time', el => ({ text: el.textContent, datetime: el.getAttribute('datetime'), title: el.getAttribute('title') }));
            assert.deepEqual([time.text, time.datetime], ['Oct 30, 2026', '2026-10-30T17:28:52+00:00'], 'the instant is a local date, not the raw ISO value');
            assert.match(time.title, /2026/);
            assert.equal(await tab.$eval('[data-action="remove-grant:files"]', el => /(^| )bg-destructive/.test(el.className)), false, 'removing a grant is not destructive');
            await tab.click('button[aria-label="Actions for Acme Assistant"]');
            await tab.waitForSelector('[data-action="revoke"]');
            assert.equal(await tab.$eval('[data-action="revoke"]', el => /destructive/.test(el.className + (el.getAttribute('data-variant') ?? ''))), true, 'Revoke stays destructive');
            await tab.keyboard.press('Escape');
            if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
            const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ') + ' ' + n.any.map(a => a.message).join()) })));
            assert.deepEqual(violations, [], JSON.stringify(violations));
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    });
}

// ── Page grounds (?tinted, ?tinted=dark, ?tinted=white): on a tinted ground the content components draw one card each, never nested; on white, none ─────────

const groundShot = process.env.KIT_TINTED_SCREENSHOT;
for (const scheme of ['light', 'dark']) {
    test(`Tinted ground (${scheme}, 1280px): DataTable, FormCard, EmptyState and PageSection each sit on a card with an edge, none nested, chrome on the ground, and axe passes`, async () => {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, timezoneId: 'UTC', locale: 'en-US' });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?tinted${scheme === 'dark' ? '=dark' : ''}`);
            await tab.waitForSelector('[data-test="table"] [data-table]');
            const cards = await tab.evaluate(() => {
                const ground = getComputedStyle(document.querySelector('[data-test-ground]')).backgroundColor;
                const look = (el) => {
                    const style = getComputedStyle(el);
                    return { background: style.backgroundColor, border: style.borderTopWidth, borderStyle: style.borderTopStyle, borderColor: style.borderTopColor };
                };
                const probe = document.createElement('div');
                probe.className = 'border border-border';
                document.body.append(probe);
                const edge = getComputedStyle(probe).borderTopColor;
                probe.remove();
                return {
                    ground,
                    edge,
                    table: look(document.querySelector('[data-test="table"]')),
                    form: look(document.querySelector('[data-test="form"]')),
                    empty: look(document.querySelector('[data-test="empty"]')),
                    section: look(document.querySelector('[data-test="section"] > [data-ground-surface]')),
                    workbench: look(document.querySelector('[data-test="workbench"]')),
                };
            });
            for (const name of ['table', 'form', 'empty', 'section', 'workbench']) {
                assert.notEqual(cards[name].background, cards.ground, `${name}: its background differs from the ground`);
                assert.equal(cards[name].borderStyle, 'solid', `${name}: a card edge`);
                assert.equal(cards[name].border, '1px', `${name}: a 1px edge`);
                assert.equal(cards[name].borderColor, cards.edge, `${name}: the edge is --border`);
            }
            assert.equal(await tab.$$eval('[data-ground-surface] [data-ground-surface], [data-form-card] [data-ground-surface], [data-card] [data-ground-surface]', els => els.length), 0, 'never a card inside a card');
            assert.equal(await tab.$eval('[data-test="nested-table"]', el => el.hasAttribute('data-ground-surface')), false, 'a DataTable in a PageSection card draws none');
            assert.equal(await tab.$eval('[data-test="workbench"]', el => el.hasAttribute('data-ground-surface')), true, 'the Workbench draws its card');
            assert.equal(await tab.$eval('[data-test="widget-empty"]', el => el.hasAttribute('data-ground-surface')), false, 'an EmptyState in a dashboard WidgetCard draws none');
            assert.equal(await tab.$eval('[data-test="nested-empty"]', el => el.hasAttribute('data-ground-surface')), false, 'an EmptyState in a Card draws none');
            assert.equal(await tab.$eval('[data-test="nested-table"] [data-table-card]', el => /(^| )(bg-card|ring-1|rounded-lg)( |$)/.test(el.className)), false, 'its rows are flat on that card');
            assert.equal(await tab.$eval('[data-test="section-none"]', el => el.querySelector('[data-ground-surface]') === null), true, 'surface="none" opts out');
            assert.equal(await tab.$eval('[data-slot="page-heading"]', el => el.closest('[data-ground-surface]') === null), true, 'the page heading stays on the ground');
            assert.equal(await tab.$eval('[data-test="section"] h2', el => el.closest('[data-ground-surface]') === null), true, 'the section heading stays on the ground');
            assert.equal(await tab.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'no horizontal scroll at 1280px');
            if (scheme === 'light' && groundShot) await tab.screenshot({ path: groundShot, fullPage: true });
            if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
            const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ') + ' ' + n.any.map(a => a.message).join()) })));
            assert.deepEqual(violations, [], JSON.stringify(violations));
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    });
}

test('White ground (?tinted=white, 1280px): the default draws no ground card and keeps each component as it was', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?tinted=white`);
        await tab.waitForSelector('[data-test="table"] [data-table]');
        assert.equal(await tab.$$eval('[data-ground-surface]', els => els.length), 0, 'no ground card anywhere');
        assert.equal(await tab.$eval('[data-test="table"]', el => el.className), 'grid gap-4', 'the DataTable section is unchanged');
        assert.equal(await tab.$eval('[data-test="table"] [data-table-card]', el => /(^| )bg-card( |$)/.test(el.className) && /(^| )ring-1( |$)/.test(el.className)), true, 'the rows keep their own card');
        assert.equal(await tab.$eval('[data-test="empty"]', el => /(^| )(bg-card|border|rounded-xl)( |$)/.test(el.className)), false, 'the EmptyState draws no card');
        assert.deepEqual(await tab.$eval('[data-test="section"]', el => [...el.children].map(child => child.tagName)), ['HEADER', 'P'], 'the PageSection holds its header and content directly');
        const backgrounds = await tab.evaluate(() => ['[data-test="empty"]', '[data-test="section"]'].map(selector => getComputedStyle(document.querySelector(selector)).backgroundColor));
        assert.deepEqual(backgrounds, ['rgba(0, 0, 0, 0)', 'rgba(0, 0, 0, 0)'], 'transparent on the ground, as before');
    } finally {
        await context.close();
    }
});

// ── Row actions (?datatable, ?datatable=people): quiet inline actions, an icon menu trigger with a name, the danger tone on destructive actions only ──────

const rowActionsCheck = async (query, expectations) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 700 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-row-menu]');
        const trigger = await tab.$eval('[data-row-menu]', el => ({ text: el.textContent.trim(), name: el.getAttribute('aria-label'), icon: Boolean(el.querySelector('svg[aria-hidden="true"]')), filled: /(^| )bg-(secondary|primary)( |$)/.test(el.className) }));
        assert.deepEqual(trigger, { text: '', name: 'Actions for Acme Assistant', icon: true, filled: false }, 'the trigger is a quiet icon with a name, not "…" text');
        for (const key of expectations.inline) {
            assert.equal(await tab.$eval(`[data-row-actions] > [data-action="${key}"]`, el => /(^| )bg-(secondary|primary|destructive)( |$)/.test(el.className)), false, `${key} inline is quiet (no filled background)`);
        }
        await tab.click('[data-row-menu]');
        await tab.waitForSelector('[role="menu"]');
        for (const [key, danger] of Object.entries(expectations.menu)) {
            assert.equal(await tab.$eval(`[role="menu"] [data-action="${key}"]`, el => /text-destructive/.test(el.className)), danger, `${key} ${danger ? 'has' : 'has not'} the danger tone`);
        }
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-actions-${query.replace(/\W/g, '-')}.png` });
        // The row menu is modal (it hides the page from axe), so axe runs with it closed, as in the DataTable test above.
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document, { rules: { region: { enabled: false } } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, [], JSON.stringify(violations));
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};

test('Row actions (overflow, any DataTable): inline actions are quiet, the menu trigger is a named icon, and only the destructive Revoke is danger-toned; axe passes', () =>
    rowActionsCheck('datatable&rows=overflow', { inline: ['remove-grant:files'], menu: { revoke: true } }));

test('Row actions (overflow, People): Change role inline and quiet, Make owner and Suspend in the menu without the danger tone, Remove with it; axe passes', () =>
    rowActionsCheck('datatable=people&rows=overflow', { inline: ['change-role'], menu: { suspend: false, 'transfer-ownership': false, remove: true } }));

test('Row actions (?datatable=lone): a lone destructive Revoke is a quiet ghost button, not a danger one; its Dialog\'s commit is danger', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 700 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?datatable=lone`);
        const trigger = await tab.waitForSelector('[data-row-actions] > [data-action="revoke"]');
        const look = await trigger.evaluate(el => ({ danger: /(^| )(bg|text|border)-destructive/.test(el.className), ghost: /(^| )hover:bg-accent( |$)/.test(el.className), }));
        assert.equal(look.danger, false, 'the lone Revoke has no danger tone');
        assert.equal(look.ghost, true, 'the lone Revoke is the ghost button the other row actions use');
        assert.equal(await tab.locator('[data-row-menu]').count(), 0, 'one action: no menu');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-actions-lone.png` });
        await trigger.click();
        const dialog = await tab.waitForSelector('[role="alertdialog"]');
        assert.equal(await dialog.evaluate(el => Boolean(el.querySelector('[data-confirm-icon]'))), true, 'Revoke asks in a danger Dialog');
        assert.equal(await tab.$eval('[data-confirm-action]', el => /(^| )bg-destructive( |$)/.test(el.className)), true, 'the Dialog\'s commit is danger');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-actions-lone-dialog.png` });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

test('Row actions (People): Make owner asks in a neutral Dialog, Remove in a danger one', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?datatable=people`);
        const tone = async (key) => {
            await tab.click('button[aria-label="Actions for Acme Assistant"]');
            await tab.click(`[role="menu"] [data-action="${key}"]`);
            const dialog = await tab.waitForSelector('[role="alertdialog"]');
            const danger = await dialog.evaluate(el => Boolean(el.querySelector('[data-confirm-icon]')));
            await tab.keyboard.press('Escape');
            await tab.waitForSelector('[role="alertdialog"]', { state: 'detached' });
            return danger;
        };
        assert.equal(await tone('transfer-ownership'), false, 'Make owner: neutral');
        assert.equal(await tone('remove'), true, 'Remove: danger');
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
const blockedReason = (tab) => tab.evaluate(() => document.getElementById(document.querySelector('[data-workbench-save]').getAttribute('aria-describedby') ?? '')?.textContent ?? null);
test('a Workbench with :form (submit): Save is disabled, described by the reason, and Ctrl+S and Enter are ignored while an upload is pending; then it saves', async () => {
    await onPage('workbench-form=submit', async (tab) => {
        await tab.click('[data-workbench-collapsed] button');
        await tab.fill('input[name="title"]', 'Pending');
        await tab.evaluate(() => { window.kitUpload = window.kitGate.begin(); });
        await tab.waitForSelector('[data-workbench-save][data-blocked="pending"]');
        assert.equal(await tab.locator('[data-workbench-save]').isDisabled(), true);
        assert.equal(await blockedReason(tab), 'Waiting for uploads to finish');
        await tab.keyboard.press('Control+s');
        await tab.press('input[name="title"]', 'Enter');
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 0, 'no save while the upload is in flight');
        await tab.evaluate(() => window.kitUpload());
        await tab.waitForFunction(() => !document.querySelector('[data-workbench-save]').disabled);
        assert.equal(await tab.getAttribute('[data-workbench-save]', 'aria-describedby'), null, 'no description once it can save');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
    });
});
test('a Workbench with :form (submit): can-submit false disables Save with its reason and ignores Ctrl+S; an open 409 conflict does too', async () => {
    await onPage('workbench-form=submit', async (tab) => {
        await tab.evaluate(() => { window.kitGate.canSubmit.value = false; });
        await tab.waitForSelector('[data-workbench-save][data-blocked="host"]');
        assert.equal(await tab.locator('[data-workbench-save]').isDisabled(), true);
        assert.equal(await blockedReason(tab), 'This can\'t be saved yet');
        await tab.click('[data-workbench-region="stage"] p');
        await tab.keyboard.press('Control+s');
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 0);
        await tab.evaluate(() => { window.kitGate.canSubmit.value = true; });
        await tab.waitForFunction(() => !document.querySelector('[data-workbench-save]').disabled);
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('409'));
        await tab.waitForSelector('[data-workbench-save][data-blocked="conflict"]');
        assert.equal(await blockedReason(tab), 'Reload to see the latest version before you save');
        await tab.keyboard.press('Control+s');
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 1, 'no save over an open conflict');
    });
});
test('a Workbench with :form (inertia): a visit refused with 409 (errors.stale_write, no JSON) blocks Save until reload, and the edits stay', async () => {
    await onPage('workbench-form=inertia', async (tab) => {
        await tab.click('[data-workbench-collapsed] button');
        await tab.fill('input[name="title"]', 'Mine');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('409'));
        await tab.waitForSelector('[data-workbench-save][data-blocked="conflict"]');
        assert.equal(await blockedReason(tab), 'Reload to see the latest version before you save');
        assert.equal(await tab.inputValue('input[name="title"]'), 'Mine', 'the edits are kept');
        await tab.keyboard.press('Control+s');
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => window.kitSave.calls.length), 1, 'no save over an open conflict');
    });
});
const workbenchFormFailThenEdit = (transport) => async () => {
    await onPage(`workbench-form=${transport}`, async (tab) => {
        await tab.click('[data-workbench-collapsed] button');
        await tab.fill('input[name="title"]', 'First try');
        await tab.waitForSelector('[data-save-state="unsaved"]');
        await tab.click('[data-workbench-save]');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('422'));
        await tab.waitForSelector('[data-save-state="error"]');
        assert.match(await tab.textContent('[data-save-state]'), /Not saved/);
        await tab.fill('input[name="title"]', 'Second try');
        await tab.waitForSelector('[data-save-state="unsaved"]', { timeout: 2000 });
        assert.match(await tab.textContent('[data-save-state]'), /Unsaved changes/);
    });
};
test(`a Workbench with :form (submit): after a failed save, the next edit is "Unsaved changes" again, not "Not saved"`, workbenchFormFailThenEdit('submit'));
test(`a Workbench with :form (inertia): after a failed save, the next edit is "Unsaved changes" again, not "Not saved"`, workbenchFormFailThenEdit('inertia'));

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

// ── FloatingToolbar in a Workbench stage: wider items than the stage ───────────────────────────────────────────────────────────────────────────────────

test('a stage FloatingToolbar wider than its stage fits inside the stage\'s insets and folds its overflow, instead of running past the edge', async () => {
    for (const width of [1280, 900]) {
        const context = await browser.newContext({ viewport: { width, height: 800 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${url}?stage-toolbar`);
            await tab.waitForSelector('[data-floating-toolbar][data-mode="stage"][data-placement]');
            await tab.waitForTimeout(300);
            const box = await tab.evaluate(() => {
                const wrapper = document.querySelector('[data-floating-toolbar][data-mode="stage"]');
                const viewport = wrapper.parentElement.getBoundingClientRect();
                const pill = wrapper.querySelector('[role="toolbar"]').getBoundingClientRect();
                return { left: pill.left - viewport.left, right: viewport.right - pill.right, overflowing: !!wrapper.querySelector('[data-toolbar-items][data-overflowing]') };
            });
            assert.ok(box.left >= 7.5 && box.right >= 7.5, `at ${width}px the pill stays inside the stage's 8px insets (left ${box.left}, right ${box.right})`);
            assert.equal(box.overflowing, true, `at ${width}px the items that don't fit fold into the menu`);
        } finally {
            await context.close();
        }
    }
});

// ── The breadcrumb trail in the shell: drawn once, by <Page> or by the shell's bar ─────────────────────────────────────────────────────────────────────

test('a <Page> nested in another draws no second trail, and with the shell\'s bar no <Page> draws one: the bar has the whole trail, the current page marked', async () => {
    for (const mode of ['page', 'bar']) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${url}?trail=${mode}`);
            await tab.waitForSelector('[data-slot="page"]');
            const trails = await tab.evaluate(() => ({
                lines: document.querySelectorAll('[data-slot="breadcrumbs"], [data-slot="breadcrumbs-spacer"]').length,
                line: [...document.querySelectorAll('[data-slot="breadcrumbs"] li')].map(li => li.textContent.trim() || li.querySelector('a')?.getAttribute('aria-label')),
                bar: [...document.querySelectorAll('[data-slot="breadcrumbs-bar"] li')].map(li => li.textContent.trim() || li.querySelector('a')?.getAttribute('aria-label')),
                current: document.querySelector('[data-slot="breadcrumbs-bar"] [aria-current="page"]')?.textContent.trim() ?? null,
            }));
            if (mode === 'page') {
                assert.equal(trails.lines, 1, 'one trail line, from the outer <Page>');
                assert.deepEqual(trails.line, ['Home', 'Assignments'], 'ancestors only');
                assert.deepEqual(trails.bar, []);
            } else {
                assert.equal(trails.lines, 0, 'no <Page> draws a line under the bar');
                assert.deepEqual(trails.bar, ['Home', 'Assignments', 'A-0001'], 'the whole trail, the current page last');
                assert.equal(trails.current, 'A-0001', 'the current page is marked aria-current');
            }
        } finally {
            await context.close();
        }
    }
});

test('a breakout page (a "Back to <parent>" strip) draws no trail line: the strip replaces the trail', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?trail=page&strip=1`);
        await tab.waitForSelector('[data-slot="page"]');
        const lines = await tab.evaluate(() => document.querySelectorAll('[data-slot="breadcrumbs"], [data-slot="breadcrumbs-spacer"]').length);
        assert.equal(lines, 0, 'no trail line and no spacer on a page with a strip');
        await tab.goto(`${url}?trail=page`);
        await tab.waitForSelector('[data-slot="page"]');
        assert.equal(await tab.evaluate(() => document.querySelectorAll('[data-slot="breadcrumbs"]').length), 1, 'the same page without a strip still draws its trail');
    } finally {
        await context.close();
    }
});

// ── FormLink and useNewTabForm: a native hidden form on <body>, which outlives the modal that fired it ─────────────────────────────────────────────────

test('FormLink and useNewTabForm submit a hidden form from <body> that still goes out after the modal unmounts, with Laravel field names', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const sent = [];
        await tab.route(/\/exports(\/|\?|$)/, async route => {
            const request = route.request();
            sent.push({ method: request.method(), url: request.url().replace(/^https?:\/\/[^/]+/, ''), body: request.postData() });
            await route.fulfill({ status: 200, headers: { 'content-type': 'text/csv', 'content-disposition': 'attachment; filename="export.csv"' }, body: 'a,b\n' });
        });
        const load = async () => {
            await tab.goto(`${url}?form-link`);
            await tab.waitForSelector('[data-test-modal]');
        };
        const fire = async selector => {
            const download = tab.waitForEvent('download');
            await tab.click(selector);
            await download;
            await tab.waitForSelector('[data-test-closed]');
            assert.equal(await tab.locator('form[data-hidden-form]').count(), 0, 'the hidden form is removed once submitted');
        };

        await load();
        await fire('[data-test-get]');
        await load();
        await fire('[data-test-delete]');
        await load();
        await fire('[data-test-validate]');

        assert.deepEqual(sent[0], { method: 'GET', url: '/exports/download?format=csv&ids%5B0%5D=3&ids%5B1%5D=4&filters%5Bopen%5D=1&filters%5Bowner%5D=', body: null }, 'GET keeps the URL\'s own query and flattens nested data');
        assert.equal(sent[1].method, 'POST');
        assert.equal(sent[1].url, '/exports');
        assert.equal(sent[1].body, 'id=9&_method=DELETE', 'a DELETE is a POST with _method, as Laravel reads it');
        assert.deepEqual(sent[2], { method: 'GET', url: '/exports/e-17?as=pdf', body: null }, 'useNewTabForm opens the URL built from the validated page, after the modal is gone');
        assert.equal(sent.length, 3);
        assert.equal(tab.url(), `${url}?form-link`, 'a download leaves the page where it is');
    } finally {
        await context.close();
    }
});

// ── FloatingToolbar under the modal layer: a modal opened from the toolbar's own slot covers the toolbar ───────────────────────────────────────────────

test('a floating toolbar sits under a z-40 modal layer mounted before it, and the modal it opens from its slot opens and closes over it', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?toolbar-modal`);
        await tab.click('[data-test-open-modal]');
        await tab.waitForSelector('[data-test-modal]');
        const top = await tab.evaluate(() => {
            const pill = document.querySelector('[data-test-open-modal]').getBoundingClientRect();
            const atToolbar = document.elementFromPoint(pill.left + pill.width / 2, pill.top + pill.height / 2);
            const dialog = document.querySelector('[data-test-modal]').getBoundingClientRect();
            const atModal = document.elementFromPoint(dialog.left + dialog.width / 2, dialog.top + dialog.height / 2);
            return { toolbarCovered: Boolean(atToolbar?.closest('[data-test-modal-layer]')), modalOnTop: Boolean(atModal?.closest('[data-test-modal]')) };
        });
        const layer = await tab.evaluate(() => getComputedStyle(document.querySelector('[data-test-open-modal]').closest('[data-floating-toolbar]') ?? document.querySelector('[data-test-open-modal]').closest('.fixed')).zIndex);
        assert.equal(layer, '35', 'the floating pill is at z-35: above StickyBar (30), under the modal layer (40)');
        assert.equal(top.modalOnTop, true, 'the modal is the top element at its centre');
        assert.equal(top.toolbarCovered, true, 'the modal layer covers the toolbar, not the other way round');
        await tab.click('[data-test-modal-close]');
        await tab.waitForSelector('[data-test-modal]', { state: 'detached' });
        await tab.click('[data-test-open-modal]');
        await tab.waitForSelector('[data-test-modal]');
        assert.equal(await tab.locator('[data-test-open-modal]').count(), 1, 'the toolbar stays mounted across the modal opening and closing');
    } finally {
        await context.close();
    }
});

// ── The kit's shared sortable: SortableList's grip keys, Move buttons, live announcement, link rows, pointer drag; its reorder action's conflict; the Repeater's drag ──

async function sortableTab(query, check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-sortable-list] [data-sortable-item]');
        await check(tab);
    } finally {
        await context.close();
    }
}
const sortOrder = (tab) => tab.$$eval('[data-sortable-fixture="slides"] [data-sortable-item]', els => els.map(el => el.getAttribute('data-sortable-id')));
const sortStatus = (tab) => tab.locator('[data-sortable-fixture="slides"] [data-sortable-status]').innerText();

test('SortableList: the grip moves its row with ↓/↑ and End, keeps focus, announces "moved to position N of M", and emits the order', async () => {
    await sortableTab('sortable', async tab => {
        const grip = tab.getByRole('button', { name: 'Reorder Slide Intro' });
        await grip.focus();
        await tab.keyboard.press('ArrowDown');
        await tab.waitForFunction(() => window.kitEvents.length === 1);
        assert.deepEqual(await sortOrder(tab), ['s2', 's1', 's3', 's4'], '↓ on the grip moves the row one place down');
        assert.equal(await sortStatus(tab), 'Slide Intro moved to position 2 of 4');
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Reorder Slide Intro', 'the moved row\'s grip keeps focus');
        await tab.keyboard.press('End');
        await tab.waitForFunction(() => window.kitEvents.length === 2);
        assert.deepEqual(await sortOrder(tab), ['s2', 's3', 's4', 's1'], 'End moves it to the end');
        assert.equal(await sortStatus(tab), 'Slide Intro moved to position 4 of 4');
        await tab.keyboard.press('ArrowDown');
        await tab.waitForTimeout(100);
        assert.equal(await tab.evaluate(() => window.kitEvents.length), 2, '↓ on the last row does nothing');
        await tab.keyboard.press('ArrowUp');
        await tab.waitForFunction(() => window.kitEvents.length === 3);
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), ['update s2,s1,s3,s4', 'update s2,s3,s4,s1', 'update s2,s3,s1,s4'], 'each move emits the whole new order');
    });
});

test('SortableList: Move up / Move down buttons reorder without a drag, the first row\'s Move up is disabled, and rows holding links and buttons pass axe', async () => {
    await sortableTab('sortable', async tab => {
        assert.equal(await tab.getByRole('button', { name: 'Move Slide Intro up' }).getAttribute('aria-disabled'), 'true');
        await tab.getByRole('button', { name: 'Move Slide Problem up' }).click();
        await tab.waitForFunction(() => window.kitEvents.length === 1);
        assert.deepEqual(await sortOrder(tab), ['s2', 's1', 's3', 's4']);
        assert.equal(await sortStatus(tab), 'Slide Problem moved to position 1 of 4');
        await tab.getByRole('button', { name: 'Move Slide Plan down' }).click();
        await tab.waitForFunction(() => window.kitEvents.length === 2);
        assert.deepEqual(await sortOrder(tab), ['s2', 's1', 's4', 's3']);
        assert.equal(await tab.locator('[data-sortable-fixture="slides"] ul [role="listbox"], [data-sortable-fixture="slides"] [role="option"]').count(), 0, 'a plain list, never a listbox');
        assert.deepEqual(await axeWithContrast(tab), [], 'no nested-interactive or other violations with links and buttons in the rows');
    });
});

test('SortableList: a pointer drag by the grip moves the row and emits the order', async () => {
    await sortableTab('sortable', async tab => {
        const grip = await tab.getByRole('button', { name: 'Reorder Slide Intro' }).boundingBox();
        const target = await tab.locator('[data-sortable-fixture="slides"] [data-sortable-item]').nth(2).boundingBox();
        await tab.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2);
        await tab.mouse.down();
        for (let step = 1; step <= 30; step++) { await tab.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2 + (target.y + target.height * 0.9 - grip.y - grip.height / 2) * step / 30); await tab.waitForTimeout(16); }
        await tab.waitForTimeout(200);
        await tab.mouse.up();
        await tab.waitForFunction(() => window.kitEvents.length === 1, null, { timeout: 3000 });
        assert.deepEqual(await sortOrder(tab), ['s2', 's3', 's1', 's4'], 'dropped below the third row');
        assert.equal(await sortStatus(tab), 'Slide Intro moved to position 3 of 4');
    });
});

test('SortableList on a reorder action: a move sends { order, version } and keeps the new token; a stale token rolls the order back and reports the conflict', async () => {
    await sortableTab('sortable=reorder', async tab => {
        await tab.getByRole('button', { name: 'Reorder Slide Intro' }).focus();
        await tab.keyboard.press('ArrowDown');
        await tab.waitForFunction(() => window.kitEvents.includes('saved 2'));
        assert.deepEqual(await tab.evaluate(() => window.kitServer.log), [{ url: '/decks/1/order', sent: 1, status: 200, order: ['s2', 's1', 's3', 's4'] }]);
        await tab.keyboard.press('ArrowDown');
        await tab.waitForFunction(() => window.kitEvents.includes('saved 3'));
        assert.equal(await tab.evaluate(() => window.kitServer.log[1].sent), 2, 'the second move sent the token the first one got back');
        await tab.evaluate(() => window.kitServer.stale());
        await tab.keyboard.press('ArrowDown');
        await tab.waitForFunction(() => window.kitEvents.some(event => event.startsWith('conflict')));
        assert.deepEqual(await sortOrder(tab), ['s2', 's3', 's1', 's4'], 'the refused move is rolled back');
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), ['saved 2', 'saved 3', 'conflict s2,s3,s1,s4']);
        assert.equal(await sortStatus(tab), 'The order changed elsewhere, so the move was not saved.');
    });
});

test('Repeater with draggable: each row gets a grip whose ↓ moves the row (the same announcement and change event as Move down), and a pointer drag reorders', async () => {
    await sortableTab('sortable', async tab => {
        const names = () => tab.$$eval('[data-sortable-fixture="repeater"] [data-repeater-row] input[name$="[name]"]', els => els.map(el => el.value));
        await tab.getByRole('button', { name: 'Reorder Row 1' }).focus();
        await tab.keyboard.press('ArrowDown');
        await tab.waitForFunction(() => window.kitEvents.some(event => event.startsWith('repeater')));
        assert.deepEqual(await names(), ['Beta', 'Alpha', 'Gamma']);
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), ['repeater r2,r1,r3']);
        assert.equal(await tab.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Reorder Row 2', 'focus stays on the moved row\'s grip (now row 2)');
        const grip = await tab.getByRole('button', { name: 'Reorder Row 3' }).boundingBox();
        const first = await tab.locator('[data-sortable-fixture="repeater"] [data-repeater-row]').first().boundingBox();
        await tab.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2);
        await tab.mouse.down();
        for (let step = 1; step <= 30; step++) { await tab.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2 - (grip.y + grip.height / 2 - first.y - 4) * step / 30); await tab.waitForTimeout(16); }
        await tab.waitForTimeout(200);
        await tab.mouse.up();
        await tab.waitForFunction(() => window.kitEvents.length === 2, null, { timeout: 3000 });
        assert.deepEqual(await names(), ['Gamma', 'Beta', 'Alpha'], 'the last row dragged to the top');
    });
});

// ── Typed confirmation (?confirm): Confirm stays off until the text matches, the match is announced, pasting works, each opening starts empty, and the
// action visit carries the typed text as `confirmation` for the server to re-check; an ActionBar action with confirm.typed asks the same; axe ──────────────

async function confirmPage(check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?confirm`);
        await tab.waitForSelector('[data-open-confirm]');
        await check(tab);
    } finally {
        await context.close();
    }
}
const confirmButton = '[data-confirm-dialog] [data-confirm-action]';

test('typed confirmation: Confirm is off until the text matches, the match is announced, and the visit sends the typed text as confirmation', async () => {
    await confirmPage(async tab => {
        await tab.click('[data-open-confirm]');
        await tab.waitForSelector('[data-confirm-dialog]');
        const input = tab.getByRole('textbox', { name: /Acme Ltd/ });
        assert.ok(await input.isVisible(), 'the input is labelled with the text to type');
        assert.ok(await tab.isDisabled(confirmButton), 'off while empty');
        await input.fill('acme ltd');
        assert.ok(await tab.isDisabled(confirmButton), 'off on a case mismatch');
        assert.equal(await tab.textContent('[data-confirm-dialog] [role="status"]'), '', 'nothing announced yet');
        await input.fill(' Acme Ltd ');
        assert.ok(await tab.isEnabled(confirmButton), 'on once it matches (surrounding spaces ignored)');
        assert.match(await tab.textContent('[data-confirm-dialog] [role="status"]'), /It matches/);
        await tab.evaluate(() => document.querySelector('[data-confirm-dialog] [data-confirm-action]').removeAttribute('disabled'));
        await input.fill('Acme');
        await tab.click(confirmButton, { force: true });
        assert.deepEqual(await tab.evaluate(() => window.kitVisits ?? []), [], 'a forced click on a mismatch sends nothing');
        await input.fill('Acme Ltd');
        await tab.click(confirmButton);
        assert.deepEqual(await tab.evaluate(() => window.kitVisits), [{ url: '/clients/1', method: 'delete', data: { confirmation: 'Acme Ltd' } }]);
        assert.equal(await tab.textContent('[data-confirmed]'), 'Acme Ltd');
    });
});

test('typed confirmation: pasting is allowed, and reopening starts empty', async () => {
    await confirmPage(async tab => {
        await tab.click('[data-open-confirm]');
        const input = tab.getByRole('textbox', { name: /Acme Ltd/ });
        await input.focus();
        await tab.evaluate(() => {
            const field = document.activeElement;
            const data = new DataTransfer();
            data.setData('text/plain', 'Acme Ltd');
            const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
            if (field.dispatchEvent(event)) { field.value = 'Acme Ltd'; field.dispatchEvent(new Event('input', { bubbles: true })); }
        });
        assert.ok(await tab.isEnabled(confirmButton), 'a paste is not blocked');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
        await tab.click('[data-open-confirm]');
        assert.equal(await tab.getByRole('textbox', { name: /Acme Ltd/ }).inputValue(), '', 'a match never carries over');
        assert.ok(await tab.isDisabled(confirmButton));
    });
});

test('typed confirmation: an ActionBar action with confirm.typed asks for the text and sends it; the open dialog has no axe violations', async () => {
    await confirmPage(async tab => {
        await tab.click('[data-bar] button:has-text("Delete workspace")');
        await tab.waitForSelector('[data-confirm-dialog]');
        assert.ok(await tab.isDisabled(confirmButton), 'off until typed');
        await tab.addScriptTag({ path: axeSource });
        const { violations } = await tab.evaluate(() => window.axe.run(document.querySelector('[data-confirm-dialog]')));
        assert.deepEqual(violations.map(v => v.id), []);
        await tab.getByRole('textbox', { name: /Acme Ltd/ }).fill('Acme Ltd');
        await tab.click(confirmButton);
        await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
        const [visit] = await tab.evaluate(() => window.kitVisits);
        assert.equal(visit.url, '/workspaces/1');
        assert.deepEqual(visit.data, { confirmation: 'Acme Ltd' });
    });
});

// ── Safety guards (?guards): a confirmed GET asks before it navigates in ActionBar and RowActions; activity text never becomes markup ──────────────────────

async function guardsPage(check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?guards`);
        await tab.waitForSelector('[data-bar]');
        await check(tab);
    } finally {
        await context.close();
    }
}

for (const [where, scope] of [['ActionBar', '[data-bar]'], ['RowActions', '[data-rows]']]) {
    test(`a confirmed GET in ${where} is not a bare link: it asks first and visits only once confirmed`, async () => {
        await guardsPage(async tab => {
            assert.equal(await tab.locator(`${scope} a[href="/people/export"]`).count(), 0, 'no link that would skip the confirmation');
            const trigger = tab.locator(`${scope} button:has-text("Export")`).first();
            if (await trigger.count() === 0) {
                await tab.locator(`${scope} button`).first().click();
                await tab.locator('[role="menuitem"]:has-text("Export")').click();
            } else {
                await trigger.click();
            }
            await tab.waitForSelector('[data-confirm-dialog]');
            assert.deepEqual(await tab.evaluate(() => window.kitVisits ?? []), [], 'nothing visited while asking');
            await tab.click('[data-confirm-dialog] [data-confirm-action]');
            await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
            assert.equal((await tab.evaluate(() => window.kitVisits))[0].url, '/people/export');
        });
    });
}

test('an activity text headline renders as text, never markup; an html headline renders its markup', async () => {
    await guardsPage(async tab => {
        await tab.waitForTimeout(100);
        assert.equal(await tab.locator('[data-text-entry] img, [data-text-entry] b').count(), 0, 'no elements from a text headline');
        assert.match(await tab.textContent('[data-text-entry]'), /<b>Ada<\/b> signed/, 'shown literally');
        assert.equal(await tab.evaluate(() => window.kitPwned), undefined, 'its handler never ran');
        assert.equal(await tab.locator('[data-html-entry] strong').textContent(), 'Ada');
    });
});

// ── Workbench landmarks and collapsed bars (?workbench, ?workbench=collapsed); a checkbox row menu's first item takes focus (?workbench-menu=checkbox) ─────

test('Workbench regions are labelled landmarks (nav, section, aside) the keyboard can focus, and the navigator is a listbox of options', async () => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?workbench-menu`);
        await tab.waitForSelector('[role="option"]');
        const regions = await tab.$$eval('[data-workbench-region]', els => Object.fromEntries(els.map(el => [el.dataset.workbenchRegion, `${el.tagName.toLowerCase()}:${Boolean(el.getAttribute('aria-label'))}:${el.tabIndex}`])));
        assert.equal(regions.navigator, 'nav:true:-1');
        assert.equal(regions.stage, 'section:true:-1');
        assert.equal(regions.inspector, 'aside:true:-1');
        assert.ok(await tab.locator('[data-workbench-region="navigator"] [role="listbox"] [role="option"][aria-selected]').count() >= 3);
        await tab.focus('[data-workbench-region="stage"]');
        await tab.keyboard.press('F6');
        assert.notEqual(await tab.evaluate(() => document.activeElement.closest('[data-workbench-region]')?.dataset.workbenchRegion), 'stage', 'F6 moves to another region');
    } finally {
        await context.close();
    }
});

test('a collapsed pane keeps a slim bar with a real Expand button in the tab order: aria-expanded false, aria-controls naming its region, and it expands', async () => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?workbench=collapsed`);
        await tab.waitForSelector('[data-workbench-collapsed]');
        const toggles = tab.locator('[data-workbench-collapsed] button[aria-expanded="false"]');
        assert.equal(await toggles.count(), 2);
        for (const toggle of await toggles.all()) {
            assert.match(await toggle.getAttribute('aria-label') ?? await toggle.textContent(), /^Expand .+ pane$/i);
            assert.notEqual(await toggle.evaluate(el => el.tabIndex), -1, 'in the tab order');
            const controls = await toggle.getAttribute('aria-controls');
            assert.ok(controls && await tab.locator(`[id="${controls}"]`).count() === 1, 'aria-controls names an element on the page');
        }
        await toggles.first().click();
        await tab.waitForFunction(() => document.querySelectorAll('[data-workbench-collapsed]').length === 1);
    } finally {
        await context.close();
    }
});

test('a checkbox row menu opened from the item focuses its first item', async () => {
    await menuWorkbench('workbench-menu=checkbox', async tab => {
        await tab.focus('[role="option"][data-workbench-item="s1"]');
        await tab.keyboard.press('Shift+F10');
        await tab.waitForSelector('[role="menu"]');
        await tab.waitForTimeout(50);
        const first = await tab.$eval('[role="menu"]', menu => menu.querySelector(':is([role="menuitem"], [role="menuitemcheckbox"]):not([data-disabled])') === document.activeElement);
        assert.ok(first, 'the first enabled item, checkbox items included, has focus (Move up is off on the first slide)');
    });
});

// ── Action rendering (?guards): a plain GET is a link, a new-tab GET opens safely, a POST stays a button; while one action runs the others are blocked ──────

for (const [where, scope] of [['ActionBar', '[data-link-bar]'], ['RowActions', '[data-link-rows]']]) {
    test(`${where}: a GET action is a link, a new-tab GET has rel="noopener noreferrer", and a POST stays a button`, async () => {
        await guardsPage(async tab => {
            const all = async (selector) => tab.locator(`${scope} ${selector}`).count();
            const inMenu = async (selector) => {
                const trigger = tab.locator(`${scope} button[aria-haspopup="menu"]`);
                if (await trigger.count() === 0) return 0;
                await trigger.first().click();
                await tab.waitForSelector('[role="menu"]');
                const n = await tab.locator(`[role="menu"] ${selector}`).count();
                await tab.keyboard.press('Escape');
                return n;
            };
            const view = await all('a[href="/people/7"]') + await inMenu('a[href="/people/7"]');
            assert.ok(view >= 1, 'the GET renders as a link');
            const docs = [...await tab.$$eval(`${scope} a[href="https://example.com/docs"]`, els => els.map(el => `${el.target}|${el.rel}`))];
            if (docs.length === 0) {
                await tab.locator(`${scope} button[aria-haspopup="menu"]`).first().click();
                docs.push(...await tab.$$eval('[role="menu"] a[href="https://example.com/docs"]', els => els.map(el => `${el.target}|${el.rel}`)));
                await tab.keyboard.press('Escape');
            }
            assert.deepEqual(docs, ['_blank|noopener noreferrer'], 'a new tab, without opener access');
            assert.equal(await all('a[href="/people/7/archive"]') + await inMenu('a[href="/people/7/archive"]'), 0, 'a POST is never a link');
        });
    });
}

test('ActionBar: while an action runs it is marked busy with a spinner and every other action is blocked; after it finishes they work again', async () => {
    await guardsPage(async tab => {
        await tab.evaluate(() => { window.kitHold = new Promise(resolve => { window.kitRelease = resolve; }); });
        const archive = tab.locator('[data-link-bar] button:has-text("Archive")');
        await archive.click();
        await tab.waitForFunction(() => (window.kitVisits ?? []).length === 1);
        assert.equal(await archive.getAttribute('aria-busy'), 'true', 'the running action is busy');
        assert.equal(await tab.locator('[data-link-bar] [data-action-pending]').count(), 1, 'its icon is a spinner');
        assert.equal(await tab.locator('[data-link-bar] [data-action-pending]').evaluate(el => getComputedStyle(el).animationName !== 'none' || matchMedia('(prefers-reduced-motion: reduce)').matches), true);
        await archive.click({ force: true });
        assert.equal(await tab.evaluate(() => window.kitVisits.length), 1, 'a second press while running sends nothing');
        await tab.evaluate(() => window.kitRelease({}));
        await tab.waitForFunction(() => !document.querySelector('[data-link-bar] [aria-busy="true"]'));
        await archive.click();
        await tab.waitForFunction(() => window.kitVisits.length === 2);
    });
});

// ── Rendered accessibility contracts (?a11y): Field ids, DescriptionList copy, EmptyState, HeaderStatus ───────────────────────────────────────────────────

async function a11yPage(check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 900 }, permissions: ['clipboard-read', 'clipboard-write'] });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?a11y`);
        await tab.waitForSelector('[data-details]');
        await check(tab);
    } finally {
        await context.close();
    }
}
const describedIds = (tab, selector) => tab.$eval(selector, el => (el.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean).map(id => `${id}:${Boolean(document.getElementById(id))}`));

test('Field: a control names only the description and error ids the Field renders, every one of them on the page, and aria-invalid follows the error', async () => {
    await a11yPage(async tab => {
        assert.deepEqual(await describedIds(tab, '#plain'), [], 'no description, no error: no describedby');
        assert.equal(await tab.getAttribute('#plain', 'aria-invalid'), null);
        assert.deepEqual(await describedIds(tab, '#described'), ['described-description:true', 'described-error:true']);
        assert.equal(await tab.getAttribute('#described', 'aria-invalid'), 'true');
        assert.deepEqual(await describedIds(tab, '#notes'), ['notes-error:true']);
        assert.equal(await tab.getAttribute('#notes', 'aria-invalid'), 'true');
        assert.deepEqual(await describedIds(tab, '#agree'), ['agree-error:true', 'agree-hint:true'], 'the checkbox joins its own description after the Field\'s ids');
    });
});

test('DescriptionList: a value copies through a button named for its term and the result is announced; a blank value has no copy button', async () => {
    await a11yPage(async tab => {
        const copy = tab.getByRole('button', { name: 'Copy Client ID' });
        assert.equal(await copy.count(), 1);
        assert.equal(await tab.getByRole('button', { name: 'Copy Secret' }).count(), 0, 'never a blank value');
        await copy.click();
        assert.equal(await tab.evaluate(() => navigator.clipboard.readText()), 'abc-123');
        await tab.waitForFunction(() => document.querySelector('[data-details] [role="status"]')?.textContent.trim() !== '');
    });
});

test('EmptyState is a region named by its heading, never a live region, with a decorative icon; HeaderStatus is a polite status, not a button', async () => {
    await a11yPage(async tab => {
        const region = tab.getByRole('region', { name: 'No invoices yet' });
        assert.equal(await region.count(), 1);
        assert.equal(await region.locator('[role="status"], [role="alert"], [role="log"], [aria-live]').count(), 0);
        assert.equal(await region.evaluate(el => el.hasAttribute('aria-live') || ['status', 'alert', 'log'].includes(el.getAttribute('role'))), false);
        assert.equal(await region.locator('[data-empty-state-icon]:not([aria-hidden="true"])').count(), 0);
        const status = tab.locator('[data-status] [role="status"]');
        assert.equal(await status.getAttribute('aria-live'), 'polite');
        assert.equal(await tab.locator('[data-status] button').count(), 0);
        await tab.addScriptTag({ path: axeSource });
        const { violations } = await tab.evaluate(() => window.axe.run(document, { rules: { 'color-contrast': { enabled: false } } }));
        assert.deepEqual(violations.map(v => v.id), []);
    });
});

// ── Workbench layout options (?workbench-menu&…): save-status="none", headings off, the overlay navigator ─────────────────────────────────────────────────

async function layoutWorkbench(query, check) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?workbench-menu&${query}`);
        await tab.waitForSelector('[data-workbench-region="stage"]');
        await tab.waitForTimeout(150);
        await check(tab);
    } finally {
        await context.close();
    }
}
const saveRegion = tab => tab.locator('[data-workbench-actions] [role="status"][data-save-state]').count();

test('save-status="kit" (the default) renders the kit save status region; "none" renders no kit status region or conflict alert', async () => {
    await layoutWorkbench('x', async tab => assert.equal(await saveRegion(tab), 1));
    await layoutWorkbench('save-status=none', async tab => {
        assert.equal(await saveRegion(tab), 0);
        assert.equal(await tab.locator('[data-workbench-actions] [role="alert"]').count(), 0);
    });
});

test('headings off: no region h2s, the landmarks keep their names, and the pane toggles are still reachable', async () => {
    await layoutWorkbench('headings=0', async tab => {
        assert.equal(await tab.locator('[data-workbench-region] h2').count(), 0, 'no pane headings');
        for (const region of ['navigator', 'inspector']) assert.ok(await tab.getAttribute(`[data-workbench-region="${region}"]`, 'aria-label'), `${region} keeps its name`);
        assert.ok(await tab.locator('button[aria-expanded]:is([aria-label*="navigator" i], :text-matches("navigator", "i"))').count() >= 1, 'a navigator toggle remains');
    });
});

test('navigator-mode="overlay": no navigator column; its toggle opens the sheet (aria-expanded) and the stage keeps its width (the sheet itself is the Modal package, a stand-in here)', async () => {
    await layoutWorkbench('overlay', async tab => {
        const stage = () => tab.$eval('[data-workbench-region="stage"]', el => Math.round(el.getBoundingClientRect().width));
        const before = await stage();
        assert.equal(await tab.locator('[data-workbench-region="navigator"]:visible').count(), 0, 'no navigator column');
        const toggle = tab.getByRole('button', { name: /navigator pane/i });
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        await toggle.click();
        await tab.waitForFunction(() => [...document.querySelectorAll('button[aria-expanded="true"]')].some(el => /navigator/i.test(el.getAttribute('aria-label') ?? '')));
        assert.equal(await stage(), before, 'the stage never resizes');
    });
});

test('Checklist: an ordered list named by its label, aria-current="step" on the active step only, a failure reason as an alert, a url action is a link and a named action emits', async () => {
    await a11yPage(async tab => {
        const list = tab.getByRole('list', { name: 'Setup' });
        assert.equal(await list.evaluate(el => el.tagName), 'OL');
        assert.deepEqual(await tab.$$eval('[data-checklist] [aria-current]', els => els.map(el => `${el.getAttribute('aria-current')}:${el.textContent.includes('Connect bank')}`)), ['step:true']);
        assert.match(await tab.locator('[data-checklist] [role="alert"]').textContent(), /The file was empty/);
        assert.equal(await tab.locator('[data-checklist] a[href="/bank"]').count(), 1);
        await tab.locator('[data-checklist] button:has-text("Retry")').click();
        assert.equal(await tab.textContent('[data-steps]'), 'c');
        assert.equal(await tab.locator('[data-checklist] [role="status"]').getAttribute('aria-live'), 'polite');
    });
});

test('EmojiTray: a toolbar named by its label with one tab stop, arrows move, each button is named by its label (the emoji is hidden), and presses are throttled', async () => {
    await a11yPage(async tab => {
        const bar = tab.getByRole('toolbar', { name: 'Reactions' });
        assert.deepEqual(await bar.locator('button').evaluateAll(els => els.map(el => `${el.getAttribute('aria-label')}:${el.tabIndex}:${el.type}`)), ['Like:0:button', 'Celebrate:-1:button', 'Love:-1:button']);
        assert.equal(await bar.locator('button span:not([aria-hidden="true"])').count(), 0, 'the emoji is not read out');
        await bar.getByRole('button', { name: 'Like' }).focus();
        await tab.keyboard.press('ArrowRight');
        assert.equal(await tab.evaluate(() => document.activeElement.getAttribute('aria-label')), 'Celebrate');
        assert.equal(await tab.evaluate(() => document.activeElement.tabIndex), 0, 'the tab stop moves with focus');
        await tab.keyboard.press('Enter');
        await tab.keyboard.press('Enter');
        assert.equal(await tab.textContent('[data-reactions]'), 'Celebrate', 'a second press inside the throttle is dropped');
    });
});

test('Button with href is one link styled as a button (never a button inside a link); a new-tab link has rel="noopener noreferrer" (a non-GET visit\'s as="button" is Inertia\'s Link, a stand-in here)', async () => {
    await a11yPage(async tab => {
        assert.equal(await tab.locator('[data-buttons] a button, [data-buttons] button a').count(), 0, 'no nesting');
        assert.equal(await tab.locator('[data-buttons] a[href="/people"]').count(), 1);
        assert.equal(await tab.getAttribute('[data-buttons] a[href="https://example.com"]', 'rel'), 'noopener noreferrer');
    });
});

test('Collapsible: a real button whose aria-expanded and aria-controls follow the content region, which is hidden when closed; Enter and Space toggle it', async () => {
    await a11yPage(async tab => {
        const trigger = tab.getByRole('button', { name: 'Advanced' });
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
        const controls = await trigger.getAttribute('aria-controls');
        assert.ok(controls);
        assert.equal(await tab.locator(`[id="${controls}"]`).isVisible(), false, 'hidden when closed');
        await trigger.focus();
        await tab.keyboard.press('Enter');
        assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
        const region = tab.getByRole('region', { name: 'Advanced' });
        assert.equal(await region.getAttribute('id'), controls, 'aria-controls names the content region, labelled by its trigger');
        assert.ok(await tab.locator('[data-collapsible-body]').isVisible());
        await tab.keyboard.press('Space');
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
    });
});

// ── More rendered accessibility contracts (?a11y2) ─────────────────────────────────────────────────────────────────────────────────────────────────────────

async function a11y2(check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?a11y2`);
        await tab.waitForSelector('[data-vote]');
        await tab.waitForTimeout(100);
        await check(tab);
    } finally {
        await context.close();
    }
}

test('RadioCardGroup commit="click": role=radio buttons with one tab stop; arrows only move focus; Space commits and the choice is announced', async () => {
    await a11y2(async tab => {
        const radios = tab.locator('[data-vote] [role="radio"]');
        assert.deepEqual(await radios.evaluateAll(els => els.map(el => `${el.tagName}:${el.getAttribute('aria-checked')}:${el.tabIndex}`)), ['BUTTON:false:0', 'BUTTON:false:-1', 'BUTTON:false:-1']);
        await radios.first().focus();
        await tab.keyboard.press('ArrowRight');
        assert.equal(await tab.evaluate(() => document.activeElement.textContent.trim()), 'Beta');
        assert.equal(await tab.textContent('[data-commits]'), '', 'moving focus commits nothing');
        await tab.keyboard.press('Space');
        assert.equal(await tab.textContent('[data-commits]'), 'b');
        assert.equal(await radios.nth(1).getAttribute('aria-checked'), 'true');
        await tab.waitForFunction(() => document.querySelector('[data-vote] [data-radio-card-announce]')?.textContent.trim() !== '');
        assert.equal(await tab.getAttribute('[data-vote] [data-radio-card-announce]', 'role'), 'status');
    });
});

test('Slider in a locked LockedForm is read-only: aria-readonly, and a key does not change the value', async () => {
    await a11y2(async tab => {
        const slider = tab.locator('[data-locked] input[type="range"]');
        assert.equal(await slider.getAttribute('aria-readonly'), 'true');
        assert.equal(await slider.isDisabled(), false, 'read-only, not disabled: it still takes focus');
        await slider.focus();
        await tab.keyboard.press('ArrowRight');
        await tab.keyboard.press('ArrowRight');
        assert.equal(await tab.textContent('[data-volume]'), '40');
        assert.equal(await slider.inputValue(), '40', 'the value is put back');
    });
});

test('StageNotice is a polite status unless live="off", and never takes the pointer', async () => {
    await a11y2(async tab => {
        const loud = tab.locator('[data-stage] > *').first();
        assert.equal(await loud.getAttribute('role'), 'status');
        assert.equal(await loud.getAttribute('aria-live'), 'polite');
        assert.equal(await loud.evaluate(el => getComputedStyle(el).pointerEvents), 'none');
        const quiet = tab.locator('[data-stage-quiet] > *').first();
        assert.equal(await quiet.getAttribute('role'), null);
        assert.equal(await quiet.getAttribute('aria-live'), null);
    });
});

test('an empty ActivityStream shows the host\'s empty text and is not a live region by default', async () => {
    await a11y2(async tab => {
        assert.match(await tab.textContent('[data-activity]'), /No activity on this case\./);
        assert.equal(await tab.locator('[data-activity] [aria-live]').count(), 0);
    });
});

test('MediaCollection: every photo has alt text and a menu button named for it; Move and Remove reach the host', async () => {
    await a11y2(async tab => {
        assert.deepEqual(await tab.$$eval('[data-media] img', els => els.map(el => el.alt)), ['Photo 1 (photo-1.jpg)', 'Photo 2 (photo-2.jpg)']);
        const menu = tab.getByRole('button', { name: 'Actions for Photo 2 (photo-2.jpg)' });
        assert.equal(await menu.count(), 1);
        await menu.click();
        await tab.getByRole('menuitem', { name: /Move to Floor plans/ }).click();
        await tab.getByRole('button', { name: 'Actions for Photo 1 (photo-1.jpg)' }).click();
        await tab.getByRole('menuitem', { name: 'Remove' }).click();
        assert.equal(await tab.textContent('[data-moved]'), 'p2>floorplans,p1x');
    });
});

test('FloatingToolbar: an icon-only item is a focusable button named by its label (also its tooltip); a labelled item shows its text; a press reaches the host', async () => {
    await a11y2(async tab => {
        const bold = tab.locator('[data-tools] button[aria-label="Bold"]').first();
        assert.equal(await bold.count(), 1);
        assert.equal(await bold.getAttribute('title'), 'Bold');
        assert.notEqual(await bold.evaluate(el => el.tabIndex), -1);
        assert.equal((await bold.textContent()).trim(), '', 'no visible text');
        await bold.click();
        await tab.locator('[data-tools] button:has-text("Link")').first().click();
        assert.equal(await tab.textContent('[data-used]'), 'bold,link');
    });
});

// ── InputRichText (?rich-text): toolbar and textbox semantics, caller attributes, unsafe links, an invalid document ──────────────────────────────────────

async function richText(check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?rich-text`);
        await tab.waitForSelector('[data-rich-text-content]');
        await tab.waitForTimeout(150);
        await check(tab);
    } finally {
        await context.close();
    }
}

test('InputRichText: a toolbar named "<label> formatting" of labelled toggle buttons with aria-pressed, and a labelled multi-line textbox that takes the caller\'s attributes', async () => {
    await richText(async tab => {
        const toolbar = tab.getByRole('toolbar', { name: 'Notes formatting' });
        assert.equal(await toolbar.count(), 1);
        for (const name of ['Bold', 'Italic', 'Bullet list']) {
            const button = toolbar.getByRole('button', { name, exact: true });
            assert.equal(await button.getAttribute('aria-pressed'), 'false', name);
        }
        const box = tab.getByRole('textbox', { name: 'Notes' });
        assert.equal(await box.getAttribute('aria-multiline'), 'true');
        assert.equal(await box.getAttribute('aria-required'), 'true', 'required arrives as aria-required');
        const ids = (await box.getAttribute('aria-describedby')).split(' ');
        assert.ok(ids.includes('caller-hint') && ids.includes('notes-description'), `caller and Field ids joined: ${ids}`);
        assert.equal(await box.evaluate(el => el.classList.contains('mt-2')), false, 'class stays on the frame');
        await box.click();
        await tab.keyboard.press('ControlOrMeta+b');
        await tab.keyboard.type('x');
        assert.equal(await toolbar.getByRole('button', { name: 'Bold', exact: true }).getAttribute('aria-pressed'), 'true');
    });
});

test('InputRichText: a stored javascript: link locks the field with content-error, is never rendered as a link and never runs; a safe link renders without opener access', async () => {
    await richText(async tab => {
        await tab.waitForFunction(() => document.querySelector('[data-unsafe-errors]').textContent.includes('invalid-document'));
        const unsafe = tab.getByRole('textbox', { name: 'Unsafe' });
        assert.equal(await unsafe.getAttribute('aria-invalid'), 'true');
        assert.equal(await unsafe.getAttribute('contenteditable'), 'false', 'locked, not rewritten');
        assert.equal(await tab.locator('a[href^="javascript:" i]').count(), 0);
        assert.equal(await tab.evaluate(() => window.kitPwned), undefined);
        assert.equal(await tab.$eval('#notes a[href="https://example.com"]', el => el.getAttribute('rel')), 'noopener noreferrer');
    });
});

test('InputRichText: the link popover refuses an unsafe link with a reason', async () => {
    await richText(async tab => {
        await tab.getByRole('textbox', { name: 'Notes' }).click();
        await tab.keyboard.press('ControlOrMeta+a');
        await tab.getByRole('toolbar', { name: 'Notes formatting' }).getByRole('button', { name: 'Edit link' }).click();
        const form = tab.locator('[data-rich-text-link]');
        await form.getByLabel('Link address').fill('javascript:alert(1)');
        await form.locator('button[type="submit"], button:has-text("Apply"), button:has-text("Save")').first().click();
        assert.match(await form.textContent(), /http, https, mailto, tel or relative/);
        assert.equal(await tab.locator('[data-rich-text-content] a[href^="javascript:" i]').count(), 0);
    });
});

test('InputRichText: a stored document outside the subset emits content-error instead of being rewritten', async () => {
    await richText(async tab => {
        await tab.waitForFunction(() => document.querySelector('[data-errors]').textContent.includes('invalid-document'));
        const box = tab.getByRole('textbox', { name: 'Broken' });
        assert.equal(await box.getAttribute('aria-invalid'), 'true');
    });
});

// ── The shell's public pages (?public): landmarks, the skip link, the footer, the Markdown body, no tracking ───────────────────────────────────────────

test('PublicShell and the Markdown page: labelled landmarks, a skip link to main, the footer links, the server HTML rendered, no third-party scripts, and no axe violations', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const requests = [];
    try {
        const tab = await context.newPage();
        tab.on('request', request => { if (!request.url().startsWith(url) && !request.url().startsWith('data:')) requests.push(request.url()); });
        await tab.goto(`${url}?public`);
        await tab.waitForSelector('main#content');
        assert.equal(await tab.getByRole('navigation', { name: 'Site' }).count(), 1);
        assert.equal(await tab.getByRole('navigation', { name: 'Footer' }).count(), 1);
        assert.deepEqual(await tab.getByRole('navigation', { name: 'Footer' }).locator('a').evaluateAll(els => els.map(el => el.getAttribute('href'))), ['/privacy', '/terms']);
        assert.equal(await tab.locator('header').count() >= 1 && await tab.locator('footer').count() >= 1, true);
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.getAttribute('href')), '#content', 'the skip link is first');
        await tab.keyboard.press('Enter');
        assert.equal(await tab.evaluate(() => document.activeElement.id), 'content', 'main takes focus');
        assert.equal(await tab.locator('main h2:has-text("Terms")').count(), 1, 'the server HTML renders');
        assert.equal(await tab.locator('script[src]:not([type="module"])').count(), 0, 'no third-party script tags');
        assert.deepEqual(requests, [], 'nothing is fetched from another origin');
        await tab.addScriptTag({ path: axeSource });
        const { violations } = await tab.evaluate(() => window.axe.run(document));
        assert.deepEqual(violations.map(v => v.id), []);
    } finally {
        await context.close();
    }
});

// ── DataTable selection (?datatable, ?datatable=bulk): selectable only with bulk actions; the BulkBar asks for its typed confirmation and sends the ids ─────

test('DataTable: no row checkboxes without bulk actions; with them each row\'s checkbox is named for the row, and a typed bulk action sends the ids with confirmation', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?datatable`);
        await tab.waitForSelector('table');
        assert.equal(await tab.locator('table [role="checkbox"], table input[type="checkbox"]').count(), 0, 'not selectable without bulk actions');
        await tab.goto(`${url}?datatable=bulk`);
        await tab.waitForSelector('table');
        const beta = tab.getByRole('checkbox', { name: 'Select Beta Bot' });
        assert.equal(await beta.count(), 1, 'the checkbox is named by the row label');
        assert.equal(await tab.getByRole('button', { name: 'Actions for Acme Assistant' }).count() + await tab.locator('[aria-label="Actions for Acme Assistant"]').count() >= 1, true, 'the row menu is named by the row label');
        await beta.click();
        await tab.getByRole('checkbox', { name: 'Select Acme Assistant' }).click();
        const inline = tab.locator('[data-bulk-bar] button:visible', { hasText: 'Archive' });
        if (await inline.count()) await inline.first().click();
        else {
            await tab.locator('[data-bulk-bar] button[aria-label^="More actions"]').click();
            await tab.getByRole('menuitem', { name: 'Archive' }).click();
        }
        await tab.waitForSelector('[data-confirm-dialog]');
        assert.equal(await tab.locator('[data-confirm-dialog] [data-confirm-icon]').count(), 1, 'the destructive Archive asks in a danger Dialog');
        assert.ok(await tab.isDisabled('[data-confirm-dialog] [data-confirm-action]'), 'off until typed');
        await tab.getByRole('textbox', { name: /archive/ }).fill('archive');
        await tab.click('[data-confirm-dialog] [data-confirm-action]');
        await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
        const [visit] = await tab.evaluate(() => window.kitVisits);
        assert.equal(visit.url, '/c/archive');
        assert.deepEqual([...visit.data.ids].sort(), [1, 2]);
        assert.equal(visit.data.confirmation, 'archive');
    } finally {
        await context.close();
    }
});

test('DataTable: a non-destructive bulk action with a confirm asks in a neutral Dialog, and sends the ids once confirmed', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?datatable=bulk`);
        await tab.waitForSelector('table');
        await tab.getByRole('checkbox', { name: 'Select Beta Bot' }).click();
        const ask = async (label) => {
            const inline = tab.locator('[data-bulk-bar] button:visible', { hasText: label });
            if (await inline.count()) await inline.first().click();
            else {
                await tab.locator('[data-bulk-bar] button[aria-label^="More actions"]').click();
                await tab.getByRole('menuitem', { name: label }).click();
            }
            await tab.waitForSelector('[data-confirm-dialog]');
            return {
                icon: await tab.locator('[data-confirm-dialog] [data-confirm-icon]').count(),
                fill: await tab.$eval('[data-confirm-dialog] [data-confirm-action]', el => getComputedStyle(el).backgroundColor),
            };
        };
        const archive = await ask('Archive');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[data-confirm-dialog]', { state: 'detached' });
        const notify = await ask('Notify owners');
        assert.equal(archive.icon, 1, 'the destructive Archive asks with the danger icon');
        assert.equal(notify.icon, 0, 'no danger icon');
        assert.notEqual(notify.fill, archive.fill, 'the confirm button is not filled like the danger one');
        await tab.click('[data-confirm-dialog] [data-confirm-action]');
        await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
        const [visit] = await tab.evaluate(() => window.kitVisits);
        assert.equal(visit.url, '/c/notify');
        assert.deepEqual(visit.data.ids, [2]);
    } finally {
        await context.close();
    }
});

// ── Rendered contracts (?a11y3): password rules, the passkey offer, row save status, SearchBox, rich-text image uploads ─────────────────────────────────

async function a11y3(check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?a11y3`);
        await tab.waitForSelector('[data-search]');
        await tab.waitForTimeout(150);
        await check(tab);
    } finally {
        await context.close();
    }
}

test('PasswordRules: the shared length rule and the breach check show before typing, and the length ticks once met (by characters)', async () => {
    await a11y3(async tab => {
        const text = await tab.locator('#password').evaluate(el => el.closest('[data-field], div').parentElement.textContent);
        assert.match(text, /At least 10 characters/);
        assert.match(text, /Not found in a known data breach \(checked when you submit\)/);
        assert.equal(await tab.locator('[data-met]').count(), 0);
        await tab.fill('#password', '😀😀😀😀😀😀😀😀😀😀');
        assert.equal(await tab.locator('[data-met]').count(), 1, 'ten emoji are ten characters');
    });
});

test('PasskeyOffer: offered on the page; Add failing says so as an alert and keeps the offer; Not now dismisses it on the server', async () => {
    await a11y3(async tab => {
        await tab.evaluate(() => { window.kitHttpClient = { request: () => Promise.reject(new Error('refused')) }; });
        assert.match(await tab.textContent('main'), /Add a passkey for next time/);
        await tab.locator('button:has-text("Add")').first().click();
        await tab.waitForSelector('[data-passkey-failed][role="alert"]');
        assert.equal(await tab.getByRole('button', { name: 'Dismiss the passkey offer' }).count() >= 1 || await tab.locator('[data-passkey-dismiss]').count() >= 1, true);
        await tab.locator('[data-passkey-dismiss]').click();
        assert.deepEqual(await tab.evaluate(() => window.kitVisits.map(v => `${v.method} ${v.url}`)), ['delete /passkeys/offer']);
    });
});

test('RowSaveStatus: a saved row is a status, a failure an alert titled with its reason', async () => {
    await a11y3(async tab => {
        assert.equal(await tab.locator('[data-row-saved] [role="status"]').count(), 1);
        assert.equal(await tab.locator('[data-row-failed] [role="alert"]').count(), 1);
        assert.equal(await tab.getAttribute('[data-row-failed] [role="alert"]', 'title'), 'The server refused.', 'the reason is the alert\'s title');
    });
});

test('SearchBox is a labelled combobox', async () => {
    await a11y3(async tab => {
        assert.equal(await tab.getByRole('combobox', { name: 'Search' }).count(), 1);
    });
});

test('InputRichText images: only with allow, a pattern and an upload callback; an uploaded src outside the pattern is refused, never inserted', async () => {
    await a11y3(async tab => {
        assert.equal(await tab.getByRole('toolbar', { name: 'Plain formatting' }).getByRole('button', { name: 'Insert image' }).count(), 0, 'off without allow');
        const insert = tab.getByRole('toolbar', { name: 'Body formatting' }).getByRole('button', { name: 'Insert image' });
        assert.equal(await insert.count(), 1);
        await tab.locator('[data-images] input[type="file"]').setInputFiles({ name: 'x.png', mimeType: 'image/png', buffer: Buffer.from('89504e470d0a1a0a', 'hex') });
        await tab.waitForFunction(() => document.querySelector('[data-image-errors]').textContent !== '0');
        assert.equal(await tab.locator('[data-images] img').count(), 0, 'nothing inserted');
        assert.equal(await tab.locator('img[src^="https://evil.example"]').count(), 0);
    });
});

test('DataTable on one page: the count line shows ("1–1 of 1") and there is no pager', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?datatable`);
        await tab.waitForSelector('table');
        assert.match(await tab.locator('[data-table-pagination]').textContent(), /1–1 of 1/);
        assert.equal(await tab.getByRole('navigation', { name: 'Pages' }).count(), 0);
    } finally {
        await context.close();
    }
});

// ── The page rhythm: <Page> owns the gap between its visible direct children ──────────────────────────────────────────────────────────────────────────
// Measured in pixels between boxes, not by class names: the gap is what a reader sees.
const rhythm = (tab) => tab.evaluate(() => {
    const page = document.querySelector('[data-slot="page"]');
    const flow = [...page.children].filter(el => {
        const style = getComputedStyle(el);
        return style.display !== 'none' && !['absolute', 'fixed'].includes(style.position);
    });
    const gaps = flow.slice(1).map((el, i) => Math.round(el.getBoundingClientRect().top - flow[i].getBoundingClientRect().bottom));
    const out = [...page.querySelectorAll(':scope > [data-test-out]')].map(el => getComputedStyle(el).display === 'none' || ['absolute', 'fixed'].includes(getComputedStyle(el).position));
    const a = page.querySelector('[data-test-block="a"]').getBoundingClientRect();
    const b = page.querySelector('[data-test-block="b"]').getBoundingClientRect();
    return { count: flow.length, gaps, out, ab: Math.round(b.top - a.bottom), teleportedInPage: page.contains(document.querySelector('[data-test-teleported]')) };
});

test('<Page> spaces its in-flow children with one equal gap, gives absolute, fixed, hidden, dialog and teleported children none, and <Page flush> none at all', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?rhythm`);
        await tab.waitForSelector('[data-slot="page-grid"]');
        const gapped = await rhythm(tab);
        assert.equal(gapped.count, 9, 'the head, A, the section, four grids and two stacks are in flow');
        assert.ok(gapped.out.every(Boolean), 'each out-of-flow child is out of flow');
        assert.equal(gapped.teleportedInPage, false);
        assert.ok(gapped.gaps[0] > 0, 'a gap at all');
        assert.deepEqual(gapped.gaps, gapped.gaps.map(() => gapped.gaps[0]), 'one equal gap, so no child adds its own margin');
        assert.equal(gapped.ab, gapped.gaps[0], 'the out-of-flow children between A and the section add nothing');
        await tab.goto(`${url}?rhythm&flush`);
        await tab.waitForSelector('[data-slot="page-grid"]');
        const flush = await rhythm(tab);
        assert.deepEqual(flush.gaps, flush.gaps.map(() => 0), 'flush: no gap');
    } finally {
        await context.close();
    }
});

test('PageGrid: presets and spans in 12 columns at 1280px, one column below md; Stack and the deprecated BlockStack stack the same', async () => {
    const columns = (tab) => tab.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-test-grid]')].map(grid => {
        const width = grid.getBoundingClientRect().width;
        return [grid.dataset.testGrid, [...grid.children].map(cell => ({ share: Math.round((cell.getBoundingClientRect().width / width) * 12), top: Math.round(cell.getBoundingClientRect().top) }))];
    })));
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?rhythm`);
        await tab.waitForSelector('[data-slot="page-grid"]');
        const wide = await columns(tab);
        // A cell's share of 12 rounds the gaps away: 8 and 4 are 7.6 and 3.6 of the row with gap-6 between them.
        assert.deepEqual(wide['main-aside'].map(c => c.share), [8, 4]);
        assert.equal(wide['main-aside'][0].top, wide['main-aside'][1].top, 'side by side');
        assert.deepEqual(wide.halves.map(c => c.share), [6, 6]);
        assert.deepEqual(wide.thirds.map(c => c.share), [4, 4, 4]);
        assert.deepEqual(wide.none.map(c => c.share), [12, 3], 'a plain cell takes the row, a PageGridItem its span');
        const stacks = await tab.evaluate(() => [...document.querySelectorAll('[data-test-stack]')].map(stack => {
            const [one, two] = stack.children;
            return Math.round(two.getBoundingClientRect().top - one.getBoundingClientRect().bottom);
        }));
        assert.ok(stacks[0] > 0);
        assert.equal(stacks[1], stacks[0], 'BlockStack renders a Stack');
        await tab.setViewportSize({ width: 390, height: 900 });
        const narrow = await columns(tab);
        for (const [name, cells] of Object.entries(narrow)) {
            assert.ok(cells.every(c => c.share === 12), `${name}: every cell the full width below md`);
            assert.ok(cells.slice(1).every((c, i) => c.top > cells[i].top), `${name}: stacked in source order`);
        }
    } finally {
        await context.close();
    }
});

test('PageEnd measures from the last in-flow sibling: a hidden, fixed or absolute helper just before it neither hides it on a long page nor shows it on a short one', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        for (const helper of ['hidden', 'fixed', 'absolute']) {
            await tab.goto(`${url}?page-end=${helper}`);
            await tab.waitForSelector('[data-page-end]');
            assert.equal(await tab.locator('[data-page-end]').count(), 1, `${helper}: shown after a long page`);
            const space = await tab.$eval('[data-page-end]', el => el.getBoundingClientRect().top - document.querySelector('[data-test-content]').getBoundingClientRect().bottom);
            assert.ok(Math.abs(space - 32) < 0.5, `${helper}: 32px below the content inside a Page (${space})`);
            await tab.goto(`${url}?page-end=${helper}&short`);
            await tab.waitForSelector('[data-test-content]');
            await tab.waitForTimeout(100);
            assert.equal(await tab.locator('[data-page-end]').count(), 0, `${helper}: not after a short page`);
        }
    } finally {
        await context.close();
    }
});

// ── In-flight guards and disabled reasons (?inflight): RowActions, a self-running ActionBar and a BulkBar each hold one visit in flight (window.kitHold), so a
// second activation runs nothing and the control is aria-busy; a disabled action with a `reason` stays focusable, shows it as a tooltip and is described by it ──

const inflight = async (run, { width = 1280 } = {}) => {
    const context = await browser.newContext({ viewport: { width, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?inflight`);
        await tab.waitForSelector('[data-row-actions]');
        await run(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
// Holds the next visits in flight until release() is called.
const hold = (tab) => tab.evaluate(() => { window.kitHold = new Promise(resolve => (window.kitRelease = resolve)); window.kitVisits = []; });
const release = (tab) => tab.evaluate(() => { window.kitHold = undefined; window.kitRelease(); });
const visits = (tab) => tab.evaluate(() => (window.kitVisits ?? []).map(visit => visit.url));
const description = (locator) => locator.evaluate(el => (el.getAttribute('aria-describedby') ?? '').split(' ').map(id => document.getElementById(id)?.textContent.trim() ?? '').join(' ').trim());

test('In flight: a second click on a RowActions action, an ActionBar action or a BulkBar action while its visit is pending runs nothing, and the control is aria-busy until it finishes', () =>
    inflight(async (tab) => {
        const archive = tab.locator('[data-rows] [data-action="archive"]');
        await hold(tab);
        await archive.click();
        await archive.click({ force: true });
        await tab.locator('[data-rows] [data-action="resend"]').click({ force: true });
        assert.deepEqual(await visits(tab), ['/people/7/archive'], 'RowActions: one visit, the second click and another action ran nothing');
        assert.equal(await archive.getAttribute('aria-busy'), 'true', 'RowActions: the running action is aria-busy');
        await release(tab);
        await tab.waitForFunction(() => !document.querySelector('[data-rows] [aria-busy="true"]'));
        await hold(tab);
        await archive.click();
        assert.deepEqual(await visits(tab), ['/people/7/archive'], 'RowActions: runs again once the first visit finished');
        await release(tab);
        await tab.waitForFunction(() => !document.querySelector('[data-rows] [aria-busy="true"]'));

        const publish = tab.locator('[data-bar] [data-action="publish"]:visible');
        await hold(tab);
        await publish.click();
        await publish.click({ force: true });
        assert.deepEqual(await visits(tab), ['/posts/3/publish'], 'ActionBar: one visit');
        assert.equal(await publish.getAttribute('aria-busy'), 'true', 'ActionBar: the running action is aria-busy');
        await release(tab);
        await tab.waitForFunction(() => !document.querySelector('[data-bar] [aria-busy="true"]'));

        const bulk = tab.locator('[data-bulk-bar] [data-action="archive"]:visible');
        await hold(tab);
        await bulk.click();
        await bulk.click({ force: true });
        await bulk.evaluate(el => el.click());
        assert.deepEqual(await visits(tab), ['/people/bulk-archive'], 'BulkBar: one visit');
        assert.equal(await tab.locator('[data-bulk-bar] [data-action-bar]').getAttribute('aria-busy'), 'true', 'BulkBar: the bar is aria-busy');
        await release(tab);
        await tab.waitForFunction(() => !document.querySelector('[data-bulk-bar] [aria-busy="true"]'));
    }));

test('A RowActions menu item stays guarded while another action of the row is in flight', () =>
    inflight(async (tab) => {
        await hold(tab);
        await tab.locator('[data-menu-rows] [data-action="archive"]').click();
        await tab.click('[data-menu-rows] button[aria-label="Actions for Ada Lovelace"]');
        await tab.locator('[role="menu"] [data-action="suspend"]').click({ force: true });
        assert.deepEqual(await visits(tab), ['/people/8/archive'], 'the menu item ran nothing while Archive was in flight');
        assert.equal(await tab.locator('[role="menu"] [data-action="suspend"]').getAttribute('aria-disabled'), 'true');
        const view = tab.locator('[role="menu"] [data-action="view"]');
        assert.equal(await view.getAttribute('aria-disabled'), 'true', 'a link action is guarded too');
        assert.equal(await view.evaluate(el => Boolean(el.closest('a') ?? el.querySelector('a'))), false, 'it is not a link while Archive is in flight');
        await view.click({ force: true });
        assert.equal(new URL(tab.url()).search, '?inflight', 'the page did not navigate');
        assert.deepEqual(await visits(tab), ['/people/8/archive']);
        await release(tab);
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.click('[data-menu-rows] button[aria-label="Actions for Ada Lovelace"]');
        assert.equal(await tab.locator('[role="menu"] a[data-action="view"], [role="menu"] [data-action="view"] a').count(), 1, 'View is a link again once the visit finished');
    }));

test('Disabled reason: a disabled RowActions button, RowActions menu item and ActionBar button keep focus and hover, show the reason as a tooltip and are described by it; choosing one runs nothing; axe passes', () =>
    inflight(async (tab) => {
        await hold(tab);
        const check = async (locator, reason, where) => {
            assert.equal(await locator.getAttribute('aria-disabled'), 'true', `${where}: aria-disabled`);
            assert.equal(await locator.evaluate(el => el.hasAttribute('disabled')), false, `${where}: not the disabled attribute`);
            assert.equal(await locator.getAttribute('title'), reason, `${where}: the reason is its tooltip`);
            assert.equal(await description(locator), reason, `${where}: the reason is its accessible description`);
            const name = await locator.evaluate(el => el.getAttribute('data-action'));
            const snapshot = await locator.ariaSnapshot();
            assert.equal(snapshot.includes(reason), false, `${where} (${name}): the reason is not part of its name (${snapshot})`);
        };
        const resend = tab.locator('[data-rows] [data-action="resend"]');
        await check(resend, 'Already accepted', 'RowActions button');
        await resend.focus();
        assert.equal(await resend.evaluate(el => document.activeElement === el), true, 'RowActions button: focusable');
        await resend.hover({ force: true });
        await resend.click({ force: true });
        const unpublish = tab.locator('[data-bar] [data-action="unpublish"]:visible');
        await check(unpublish, 'Not published yet', 'ActionBar button');
        await unpublish.focus();
        assert.equal(await unpublish.evaluate(el => document.activeElement === el), true, 'ActionBar button: focusable');
        await unpublish.click({ force: true });
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/inflight-disabled-reason.png` });
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document, { rules: { region: { enabled: false } } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, [], JSON.stringify(violations));

        await tab.click('[data-menu-rows] button[aria-label="Actions for Ada Lovelace"]');
        const remove = tab.locator('[role="menu"] [data-action="remove"]');
        await check(remove, 'Owners cannot be removed', 'RowActions menu item');
        await tab.keyboard.press('End');
        assert.equal(await remove.evaluate(el => document.activeElement === el), true, 'the menu item is reachable with the keyboard');
        await tab.keyboard.press('Enter');
        assert.equal(await tab.locator('[role="menu"]').count(), 1, 'choosing it keeps the menu open');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/inflight-disabled-menu.png` });
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.locator('[data-menu-bar] button[aria-label^="More actions"]:visible').click();
        const del = tab.locator('[role="menu"] [data-action="delete"]');
        await check(del, 'Published posts cannot be deleted', 'ActionBar menu item');
        await tab.keyboard.press('End');
        assert.equal(await del.evaluate(el => document.activeElement === el), true, 'the ActionBar menu item is reachable with the keyboard');
        await tab.keyboard.press('Enter');
        assert.equal(await tab.locator('[role="menu"]').count(), 1, 'choosing it keeps the menu open');
        assert.deepEqual(await visits(tab), [], 'no disabled action ran');
        await release(tab);
    }));

// ── Row action variants (?row-variants): `split` (the primary joined to a chevron menu in a ButtonGroup) and `overflow` (quiet inline actions plus "…"), the
// default rule (one primary plus menu items is split), an explicit variant winning, verb labels with the record in the accessible name and the confirmation ─

const rowVariants = async (run, { width = 1280 } = {}) => {
    const context = await browser.newContext({ viewport: { width, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?row-variants`);
        await tab.waitForSelector('[data-row-actions]');
        await run(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
const longRecord = 'Northwind Farms quarterly supplier reconciliation for the Eastern region';
const layoutOf = (tab, section) => tab.$eval(`[${section}] [data-row-actions]`, el => ({
    variant: el.getAttribute('data-row-actions'),
    grouped: Boolean(el.querySelector('[data-button-group]')),
    inline: [...el.querySelectorAll(':scope > [data-action], [data-button-group] [data-action]')].map(node => node.getAttribute('data-action')),
    overflowTrigger: el.querySelector('[data-row-menu]')?.getAttribute('aria-label') ?? null,
    chevronTrigger: el.querySelector('[data-button-group-menu]')?.getAttribute('aria-label') ?? null,
}));
const menuKeys = (tab) => tab.$$eval('[role="menu"] [data-action]', els => els.map(el => el.getAttribute('data-action')));

test('Row variants: one primary plus menu items defaults to split, two plain actions default to overflow, and an explicit variant wins either way', () =>
    rowVariants(async (tab) => {
        assert.deepEqual(await layoutOf(tab, 'data-default-split'), { variant: 'split', grouped: true, inline: ['open'], overflowTrigger: null, chevronTrigger: `Actions for ${longRecord}` });
        assert.deepEqual(await layoutOf(tab, 'data-default-overflow'), { variant: 'overflow', grouped: false, inline: ['open', 'copy'], overflowTrigger: null, chevronTrigger: null });
        assert.deepEqual(await layoutOf(tab, 'data-explicit-overflow'), { variant: 'overflow', grouped: false, inline: ['open'], overflowTrigger: 'Actions for Ana Lopez', chevronTrigger: null }, 'overflow chosen over the split default');
        assert.deepEqual(await layoutOf(tab, 'data-explicit-split'), { variant: 'split', grouped: true, inline: ['open'], overflowTrigger: null, chevronTrigger: 'Actions for Ana Lopez' }, 'split chosen over the overflow default');
        await tab.click('[data-explicit-split] button[aria-label="Actions for Ana Lopez"]');
        await tab.waitForSelector('[role="menu"]');
        assert.deepEqual(await menuKeys(tab), ['copy'], 'the split menu holds every action but the primary');
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.click(`[data-default-split] button[aria-label="Actions for ${longRecord}"]`);
        await tab.waitForSelector('[role="menu"]');
        assert.deepEqual(await menuKeys(tab), ['archive', 'export', 'delete']);
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-variants-split-menu.png` });
        await tab.keyboard.press('Escape');
        await tab.waitForSelector('[role="menu"]', { state: 'detached' });
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-variants.png` });
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document, { rules: { region: { enabled: false } } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, [], JSON.stringify(violations));
    }));

test('Row variants: the split primary keeps the in-flight guard and aria-busy, a disabled menu item shows its reason, and red is only destructive menu text', () =>
    rowVariants(async (tab) => {
        const open = tab.locator('[data-default-split] [data-action="open"]');
        assert.equal(await open.evaluate(el => /(^| )(bg|text)-destructive/.test(el.className)), false, 'the primary has no danger tone');
        await hold(tab);
        await open.click();
        await open.click({ force: true });
        assert.deepEqual(await visits(tab), ['/r/1'], 'one visit; the second click ran nothing');
        assert.equal(await open.getAttribute('aria-busy'), 'true');
        await tab.click(`[data-default-split] button[aria-label="Actions for ${longRecord}"]`);
        const del = tab.locator('[role="menu"] [data-action="delete"]');
        assert.equal(await del.getAttribute('aria-disabled'), 'true', 'a menu item waits while the primary is in flight');
        await del.click({ force: true });
        assert.deepEqual(await visits(tab), ['/r/1']);
        await release(tab);
        await tab.waitForFunction(() => !document.querySelector('[data-default-split] [aria-busy="true"]'));
        const archive = tab.locator('[role="menu"] [data-action="archive"]');
        assert.equal(await archive.getAttribute('title'), 'Already archived', 'the reason is its tooltip');
        assert.equal(await description(archive), 'Already archived', 'and its description');
        assert.equal(await archive.evaluate(el => /text-destructive/.test(el.className)), false);
        assert.equal(await del.evaluate(el => /text-destructive/.test(el.className)), true, 'Delete is the red menu text');
    }));

test('Row variants: a confirmation without a title asks a short "<Label> this item?" and wraps the long record name in full in the body of a danger Dialog', () =>
    rowVariants(async (tab) => {
        await tab.click(`[data-default-split] button[aria-label="Actions for ${longRecord}"]`);
        await tab.click('[role="menu"] [data-action="delete"]');
        const dialog = await tab.waitForSelector('[role="alertdialog"]');
        const shown = await dialog.evaluate(el => {
            const title = document.getElementById(el.getAttribute('aria-labelledby'));
            const record = el.querySelector('[data-confirm-record]');
            const clipped = (node) => node.scrollWidth > node.clientWidth + 1 || getComputedStyle(node).textOverflow === 'ellipsis' || getComputedStyle(node).webkitLineClamp !== 'none';
            return { title: title.textContent.trim(), record: record.textContent.trim(), clipped: clipped(title) || clipped(record), lines: Math.round(record.getBoundingClientRect().height / parseFloat(getComputedStyle(record).lineHeight)) };
        });
        assert.equal(shown.title, 'Delete this item?', 'the bold title is short and holds no record');
        assert.equal(shown.record, longRecord, 'the record is in the body');
        assert.equal(shown.clipped, false, 'neither the title nor the record is truncated');
        assert.ok(shown.lines >= 2, `the long record wraps (${shown.lines} lines)`);
        assert.equal(await tab.$eval('[data-confirm-action]', el => /(^| )bg-destructive( |$)/.test(el.className)), true, 'red is the confirm button');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-variants-confirm.png` });
    }, { width: 480 }));

test('Row variants: with the table noun, a confirmation without a title asks "<Label> this <noun>?"', () =>
    rowVariants(async (tab) => {
        await tab.click('[data-noun] button[aria-label="Actions for Ana Lopez"]');
        await tab.click('[role="menu"] [data-action="delete"]');
        const dialog = await tab.waitForSelector('[role="alertdialog"]');
        assert.equal(await dialog.evaluate(el => document.getElementById(el.getAttribute('aria-labelledby')).textContent.trim()), 'Delete this member?');
        assert.equal((await tab.locator('[data-confirm-record]').textContent()).trim(), 'Ana Lopez', 'the record is still in the body');
    }));

test('Row variants: menu items that share a label show their description under it, muted, on one truncated line, and are described by it', () =>
    rowVariants(async (tab) => {
        await tab.click('[data-grants] button[aria-label="Actions for Acme Assistant"]');
        await tab.waitForSelector('[role="menu"]');
        const items = await tab.$$eval('[role="menu"] [data-action^="remove-grant:"]', els => els.map(el => {
            const line = el.querySelector('[data-action-description]');
            const ids = (el.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean);
            const style = getComputedStyle(line);
            return {
                label: el.textContent.replace(line.textContent, '').trim(),
                description: line.textContent.trim(),
                describedBy: ids.map(id => document.getElementById(id)?.textContent.trim()).join(' '),
                oneLine: Math.round(line.getBoundingClientRect().height / parseFloat(style.lineHeight)) === 1,
                ellipsis: style.textOverflow === 'ellipsis' && style.whiteSpace === 'nowrap',
                overflows: line.scrollWidth > line.clientWidth + 1,
                belowLabel: line.getBoundingClientRect().top >= el.querySelector('span > span').getBoundingClientRect().bottom - 1,
                muted: style.color !== getComputedStyle(el).color,
            };
        }));
        const ledger = 'Read and export every ledger entry across all of the workspace accounts';
        assert.deepEqual(items.map(i => [i.label, i.description, i.describedBy]), [['Remove', 'Files', 'Files'], ['Remove', 'Calendar', 'Calendar'], ['Remove', ledger, ledger]]);
        for (const item of items) {
            assert.equal(item.oneLine, true, `${item.description} is one line`);
            assert.equal(item.ellipsis, true, 'truncated with an ellipsis');
            assert.equal(item.belowLabel, true, 'under the label');
            assert.equal(item.muted, true, 'muted');
        }
        assert.equal(items[2].overflows, true, 'the long grant is cut, not wrapped');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-variants-descriptions.png` });
        await tab.click('[role="menu"] [data-action="remove-grant:calendar"]');
        const dialog = await tab.waitForSelector('[role="alertdialog"]');
        assert.equal(await dialog.evaluate(el => document.getElementById(el.getAttribute('aria-labelledby')).textContent.trim()), 'Remove permission?');
        assert.equal(await tab.locator('[data-confirm-record]').count(), 0, 'a server title brings its own body');
    }));

test('Row variants: the split primary and its menu segment are the same height with their labels centred on one line', () =>
    rowVariants(async (tab) => {
        for (const section of ['data-default-split', 'data-explicit-split', 'data-grants']) {
            const box = await tab.$eval(`[${section}] [data-button-group]`, group => {
                const primary = group.querySelector('[data-action]');
                const menu = group.querySelector('[data-button-group-menu]');
                const range = document.createRange();
                range.selectNodeContents(primary);
                const text = range.getBoundingClientRect();
                const chevron = menu.querySelector('svg').getBoundingClientRect();
                const p = primary.getBoundingClientRect();
                const m = menu.getBoundingClientRect();
                return { heights: [p.height, m.height], tops: [p.top, m.top], centres: [text.top + text.height / 2, chevron.top + chevron.height / 2] };
            });
            assert.ok(Math.abs(box.heights[0] - box.heights[1]) < 0.5, `${section}: same height ${box.heights}`);
            assert.ok(Math.abs(box.tops[0] - box.tops[1]) < 0.5, `${section}: same top ${box.tops}`);
            assert.ok(Math.abs(box.centres[0] - box.centres[1]) < 1, `${section}: label and chevron centred together ${box.centres}`);
        }
    }));

test('Row variants: accessible names are "<Label> <record>" by default and ariaLabel wins, on RowActions, ActionBar and BulkBar', () =>
    rowVariants(async (tab) => {
        const name = (selector) => tab.locator(selector).first().evaluate(el => el.getAttribute('aria-label'));
        assert.equal(await name('[data-default-split] [data-action="open"]'), `Open ${longRecord}`);
        assert.equal(await name('[data-default-overflow] [data-action="copy"]'), 'Copy Ana Lopez');
        assert.equal(await name('[data-named] [data-action="remove-grant:files"]'), 'Remove Files');
        assert.equal((await tab.locator('[data-named] [data-action="remove-grant:files"]').textContent()).trim(), 'Remove', 'the visible label stays a verb');
        await tab.click('[data-named] button[aria-label="Actions for Acme Assistant"]');
        assert.equal(await name('[role="menu"] [data-action="revoke"]'), 'Revoke Acme Assistant', 'a menu item is named too');
        await tab.keyboard.press('Escape');
        assert.equal(await tab.getByRole('button', { name: 'Publish Spring menu' }).count() >= 1, true, 'ActionBar renders ariaLabel');
        assert.equal(await tab.locator('[data-bulk-bar] [aria-label="Archive the selected people"]:visible').count(), 1, 'BulkBar renders ariaLabel');
    }));

test('Row variants: a label over 24 characters or 3 words is clamped with an ellipsis and its full title, and the accessible name keeps it whole', () =>
    rowVariants(async (tab) => {
        const resend = tab.locator('[data-long] [data-action="resend"]');
        const shown = await resend.evaluate(el => ({ text: el.textContent.trim(), title: el.getAttribute('title'), name: el.getAttribute('aria-label') }));
        assert.equal(shown.text, 'Resend the invitation…');
        assert.ok(shown.text.length <= 24);
        assert.equal(shown.title, 'Resend the invitation email to Ana Lopez');
        assert.equal(shown.name, 'Resend the invitation email to Ana Lopez Ana Lopez', 'the name keeps the full label');
        await tab.click('[data-long] button[aria-label="Actions for Ana Lopez"]');
        const details = tab.locator('[role="menu"] [data-action="details"]');
        assert.equal((await details.textContent()).trim(), 'Show the full…', 'four words clamp to three');
        assert.equal(await details.getAttribute('title'), 'Show the full billing details');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/row-variants-clamp.png` });
    }));

// ── ButtonGroup segments (?button-group, `&dark`): one outline, a 1px divider in the outline colour between segments and none after the last; the split menu
// segment beside them keeps the group's height; `appearance="text"` has no dividers ─────────────────────────────────────────────────────────────────────
const buttonGroupSegments = (tab, section) => tab.$eval(`[${section}] [data-button-group] > div:first-child`, wrap => {
    const probe = getComputedStyle(document.querySelector('[data-outline-probe]')).borderTopColor;
    return { probe, segments: [...wrap.children].map(child => {
        const style = getComputedStyle(child);
        return { right: style.borderRightWidth, rightStyle: style.borderRightStyle, colour: style.borderRightColor, left: style.borderLeftWidth, height: child.getBoundingClientRect().height };
    }) };
});

const buttonGroupDividers = async (theme) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 600 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?button-group${theme === 'dark' ? '&dark' : ''}`);
        await tab.waitForSelector('[data-button-group]');
        for (const section of ['data-pair', 'data-split']) {
            const { probe, segments } = await buttonGroupSegments(tab, section);
            assert.equal(segments.length, 2, section);
            assert.equal(segments[0].right, '1px', `${section}: the first segment draws the divider`);
            assert.equal(segments[0].rightStyle, 'solid', section);
            assert.equal(segments[0].colour, probe, `${section}: the divider is the outline token`);
            assert.equal(segments[1].right, '0px', `${section}: no divider after the last segment`);
            assert.deepEqual(segments.map(s => s.left), ['0px', '0px'], `${section}: no segment keeps its own left border`);
        }
        const split = await tab.$eval('[data-split] [data-button-group]', group => [group.querySelector(':scope > div:first-child > *'), group.querySelector('[data-button-group-menu]')].map(el => el.getBoundingClientRect().height));
        assert.ok(Math.abs(split[0] - split[1]) < 0.5, `the menu segment matches the group's height ${split}`);
        const text = await buttonGroupSegments(tab, 'data-text');
        assert.deepEqual(text.segments.map(s => s.right), ['0px', '0px'], 'appearance="text" draws no divider');
        if (theme === 'light') {
            await tab.locator('[data-pair] [data-button-group] button').first().focus();
            await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/buttongroup-divider.png` });
        } else {
            await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/buttongroup-divider-dark.png` });
        }
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
test('ButtonGroup (light): a 1px outline-coloured divider between segments, none after the last, and none in appearance="text"', () => buttonGroupDividers('light'));
test('ButtonGroup (dark): a 1px outline-coloured divider between segments, none after the last, and none in appearance="text"', () => buttonGroupDividers('dark'));

// ── The notes composer (notes.html, on the real Inertia): kit controls only, labelled and keyboard-operable, axe-clean with contrast, light and dark ──────
async function notesPage(query, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        const posts = [];
        await tab.route(/\/@acme\/notes$/, async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            posts.push(route.request().postDataBuffer()?.toString('latin1') ?? '');
            const page = await tab.evaluate(() => window.kitInitialPage);
            await route.fulfill({ status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify(page) });
        });
        await tab.goto(`${authUrl}notes.html${query}`);
        await tab.waitForSelector('[data-note-composer] [data-dropzone]');
        await run({ tab, posts });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}
const notesAxe = async (tab) => {
    if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
    return tab.evaluate(async () => (await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
};

for (const [mode, query] of [['light', ''], ['dark', '?dark']]) {
    test(`the notes composer (${mode}): no native file chooser or select, and no axe violations (WCAG 2.2 AA, color-contrast included) empty and with files chosen`, async () => {
        await notesPage(query, async ({ tab }) => {
            assert.equal(await tab.locator('[data-note-composer] select:visible, [data-note-composer] input[type=file]:not(.sr-only)').count(), 0, 'no visible native select or file input');
            assert.deepEqual(await notesAxe(tab), [], 'empty composer');
            await tab.setInputFiles('[data-dropzone-input]', [{ name: 'plan.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') }]);
            await tab.waitForSelector('[aria-label="Files to attach"] li');
            assert.deepEqual(await notesAxe(tab), [], 'with a file chosen');
        });
    });
}

test('the notes composer by keyboard: the colour select and the dropzone are labelled and operable, Remove works, and a save posts the files once then clears them', async () => {
    await notesPage('', async ({ tab, posts }) => {
        const colour = tab.getByRole('combobox', { name: 'Colour' });
        assert.equal(await colour.count(), 1, 'the colour select is a combobox named by its label');
        await colour.focus();
        await tab.keyboard.press('ArrowDown');
        await tab.getByRole('option', { name: 'Blue' }).waitFor();
        await tab.keyboard.press('ArrowDown');
        await tab.keyboard.press('Enter');
        assert.match(await colour.evaluate(el => el.closest('[data-note-composer]').querySelector('input[name=colour]')?.value ?? ''), /blue/, 'the keyboard picked Blue');
        const zone = tab.getByRole('button', { name: 'browse' });
        const named = tab.locator('[data-dropzone][aria-label="Add files to this note: drop, paste or browse"]');
        assert.equal(await named.count(), 1, 'the dropzone is named');
        assert.equal(await named.getAttribute('tabindex'), '0', 'the dropzone takes focus (paste target)');
        // The select hands focus back to its trigger once its listbox has closed; wait for that, so it can't take focus from the dropzone after we move it.
        await tab.getByRole('listbox').waitFor({ state: 'detached' });
        await tab.waitForFunction(() => document.activeElement?.getAttribute('role') === 'combobox');
        await zone.focus();
        await tab.waitForFunction(el => document.activeElement === el, await zone.elementHandle());
        // Enter must open the picker: the kit's part is calling click() on its hidden file input, with the keypress's user activation. That is what is
        // asserted. Playwright's own 'filechooser' event is not: it switches interception on asynchronously when the first listener is added, and under
        // CPU load the click could land first, so the chooser was missed though the kit had opened it (seen under 6x throttling).
        await tab.evaluate(() => {
            window.kitPickerOpens = [];
            const click = HTMLInputElement.prototype.click;
            HTMLInputElement.prototype.click = function () {
                if (this.matches('[data-note-composer] [data-dropzone-input]')) {
                    window.kitPickerOpens.push(navigator.userActivation.isActive);
                    return;
                }
                return click.call(this);
            };
        });
        await tab.keyboard.press('Enter');
        assert.deepEqual(await tab.evaluate(() => window.kitPickerOpens), [true], 'Enter on the focused browse button opens the file picker once, as a user gesture');
        await tab.setInputFiles('[data-note-composer] [data-dropzone-input]', [{ name: 'a.png', mimeType: 'image/png', buffer: Buffer.from('png') }, { name: 'b.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF') }]);
        await tab.getByRole('button', { name: 'Remove a.png' }).waitFor();
        await tab.getByRole('button', { name: 'Remove a.png' }).focus();
        await tab.keyboard.press('Enter');
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 1);
        await tab.fill('textarea[name=body]', 'Call the supplier');
        await tab.click('[data-note-composer] button[type=submit]');
        for (let i = 0; i < 100 && posts.length === 0; i++) await tab.waitForTimeout(20);
        assert.equal(posts.length, 1, 'one save');
        assert.equal((posts[0].match(/filename="b\.pdf"/g) ?? []).length, 1, 'the remaining file is sent once');
        assert.ok(!posts[0].includes('filename="a.png"') && !posts[0].includes('filename=""'), 'the removed file and no empty file part are sent');
        assert.match(posts[0], /name="intent_key"/);
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 0);
        assert.equal(await tab.evaluate(() => document.querySelector('[data-dropzone-input]').files.length), 0, 'the dropzone is cleared after the save');
    });
});

// ── The dashboard grid with every kit widget on (dashboard.html): axe-clean (WCAG 2.2 AA, color-contrast, landmark-unique), light and dark ────────────────
for (const [mode, query] of [['light', ''], ['dark', '?dark']]) {
    test(`the dashboard with every kit widget (${mode}): no axe violations, and no two landmarks share a name`, async () => {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${authUrl}dashboard.html${query}`);
            await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"]');
            await tab.waitForSelector('[data-widget="kit.activity"] li, [data-widget="kit.activity"] article');
            assert.deepEqual(await tab.evaluate(() => [...document.querySelectorAll('[data-widget]')].map(e => e.dataset.widget)), ['kit.activity', 'kit.you', 'kit.people']);
            assert.deepEqual(await notesAxe(tab), []);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    });
}

// ── The workspace list (workspaces.html): each workspace a card that is one real link, keyboard-focusable with a visible ring on the card; Current marked;
// New workspace a header action only when sent; the empty state; axe-clean light and dark at 1280 and 375, nothing overflowing ─────────────────────────────────
const workspacesShots = process.env.KIT_WORKSPACES_SCREENSHOTS;
const workspacesLayout = (tab) => tab.evaluate(() => ({
    page: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    overflowing: [...document.querySelectorAll('[data-slot="page"], [data-slot="page"] *')].filter(el => el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX === 'visible').map(el => el.tagName),
}));
for (const width of [1280, 375]) {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        test(`the workspace list at ${width}px (${mode}): cards link their workspace, Current is marked, New workspace is a header action, axe passes, nothing overflows`, async () => {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                await tab.goto(`${authUrl}workspaces.html?create${dark}`);
                await tab.waitForSelector('[data-workspace-list]');
                const cards = await tab.$$eval('[data-workspace]', els => els.map(el => ({ link: el.querySelector('a[data-workspace-link]')?.getAttribute('href') ?? null, current: el.hasAttribute('data-current'), badge: el.querySelector('[data-workspace-current]')?.textContent.trim() ?? null, meta: el.querySelector('p')?.textContent.replace(/\s+/g, ' ').trim() })));
                assert.deepEqual(cards.map(c => c.link), ['/@acme', '/@northwind', null], 'each workspace with a page links to it; one with none is not a link');
                assert.deepEqual(cards.map(c => c.badge), ['Current', null, null], 'only the current workspace is marked Current, in words');
                assert.deepEqual(cards.map(c => c.meta), ['Admin · 12 members', 'Owner · 1 member', 'Member · 4 members']);
                assert.equal(await tab.locator('[data-slot="page-header"] a, [data-slot="page-header"] button').filter({ hasText: 'New workspace' }).count(), 1, 'New workspace is in the page header');
                assert.equal(await tab.locator('[data-workspace-invitation]').count(), 1, 'the open invitation has its own section');
                // The whole card takes the click: its centre hits the link (stretched over the card), never the Leave button's area.
                const hit = await tab.evaluate(() => { const card = document.querySelector('[data-workspace="2"]'); const r = card.getBoundingClientRect(); const el = document.elementFromPoint(r.left + 8, r.top + r.height / 2); return el?.closest('a')?.getAttribute('href') ?? null; });
                assert.equal(hit, '/@northwind', 'a click anywhere on the card opens the workspace');
                const layout = await workspacesLayout(tab);
                assert.equal(layout.page, 0, 'no horizontal page scroll');
                assert.deepEqual(layout.overflowing, [], 'nothing on the page overflows its box');
                assert.deepEqual(await notesAxe(tab), []);
                assert.deepEqual(errors, []);
                if (workspacesShots) await tab.screenshot({ path: `${workspacesShots}/me-workspaces-${width}-${mode}.png`, fullPage: true });
            } finally {
                await context.close();
            }
        });
    }
}

test('the workspace list: Tab reaches each workspace link, and the focused card shows a ring', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${authUrl}workspaces.html`);
        await tab.waitForSelector('[data-workspace-list]');
        const shadow = () => tab.$eval('[data-workspace="1"]', el => getComputedStyle(el).boxShadow);
        const before = await shadow();
        let focused = null;
        for (let i = 0; i < 20 && focused !== '/@acme'; i++) {
            await tab.keyboard.press('Tab');
            focused = await tab.evaluate(() => document.activeElement?.getAttribute('href') ?? null);
        }
        assert.equal(focused, '/@acme', 'the first workspace link takes keyboard focus');
        assert.notEqual(await shadow(), before, 'the focused card draws a ring');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.textContent.trim()), 'Leave', 'Leave follows its card\'s link');
    } finally {
        await context.close();
    }
});

for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
    test(`the workspace list with no workspace (${mode}): an EmptyState explains it, with New workspace when sent and none when not, axe passes`, async () => {
        const context = await browser.newContext({ viewport: { width: 375, height: 900 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${authUrl}workspaces.html?empty${dark}`);
            await tab.waitForSelector('[data-workspaces-empty]');
            assert.equal(await tab.locator('[data-workspace-list]').count(), 0);
            assert.equal(await tab.locator('a, button').filter({ hasText: 'New workspace' }).count(), 0, 'no New workspace without provisioning');
            assert.deepEqual(await notesAxe(tab), []);
            await tab.goto(`${authUrl}workspaces.html?empty&create${dark}`);
            await tab.waitForSelector('[data-workspaces-empty]');
            assert.equal(await tab.locator('a, button').filter({ hasText: 'New workspace' }).count(), 1, 'New workspace is there once when sent');
            assert.equal(await tab.locator('[data-workspaces-empty] a, [data-workspaces-empty] button').filter({ hasText: 'New workspace' }).count(), 1, 'the empty state offers it');
            assert.deepEqual(await workspacesLayout(tab), { page: 0, overflowing: [] });
            assert.deepEqual(await notesAxe(tab), []);
            if (workspacesShots) await tab.screenshot({ path: `${workspacesShots}/me-workspaces-empty-${mode}.png`, fullPage: true });
        } finally {
            await context.close();
        }
    });
}

// ── The dashboard's main/aside columns: side by side from md (768, PageGrid's breakpoint) up, stacked main-then-aside at 767, every card its column's full width,
// nothing overflowing ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
for (const width of [1440, 768, 767]) {
    test(`the dashboard at ${width}px: main and aside ${width >= 768 ? 'are separate columns' : 'stack in order'}, each card spans its column, nothing overflows`, async () => {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${authUrl}dashboard.html`);
            await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"]');
            const layout = await tab.evaluate(() => {
                const box = (el) => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width }; };
                const columns = Object.fromEntries([...document.querySelectorAll('[data-widget-region]')].map(c => [c.dataset.widgetRegion, { box: box(c), cards: [...c.querySelectorAll(':scope > [data-widget]')].map(card => ({ key: card.dataset.widget, ...box(card) })) }]));
                const overflowing = [...document.querySelectorAll('[data-widget-grid], [data-widget-grid] *')].filter(el => el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX === 'visible').map(el => el.tagName);
                return { columns, order: [...document.querySelectorAll('[data-widget]')].map(e => e.dataset.widget), page: document.documentElement.scrollWidth - document.documentElement.clientWidth, overflowing };
            });
            const { main, aside } = layout.columns;
            assert.deepEqual(main.cards.map(c => c.key), ['kit.activity'], 'main holds the main widgets');
            assert.deepEqual(aside.cards.map(c => c.key), ['kit.you', 'kit.people'], 'aside holds the aside widgets, in order');
            if (width >= 768) {
                assert.ok(main.box.right <= aside.box.left, 'main sits left of aside');
                assert.ok(Math.abs(main.box.top - aside.box.top) < 1, 'the two columns start level');
                assert.ok(main.box.width > aside.box.width * 1.5, 'main is the wider column');
            } else {
                assert.ok(Math.abs(main.box.left - aside.box.left) < 1 && Math.abs(main.box.width - aside.box.width) < 1, 'one column below md');
                assert.ok(main.box.bottom <= aside.box.top, 'main comes first, then aside');
            }
            for (const column of [main, aside]) {
                for (const card of column.cards) assert.ok(Math.abs(card.left - column.box.left) < 1 && Math.abs(card.width - column.box.width) < 1, `${card.key} spans its column`);
            }
            assert.equal(layout.page, 0, 'no horizontal page scroll');
            assert.deepEqual(layout.overflowing, [], 'nothing in the grid overflows its box');
        } finally {
            await context.close();
        }
    });
}

// ── The dashboard's setup placement (dashboard.html?setup): the person's own steps in You, the "Set up this workspace" card last in the aside, at 1280 and
// 375, light and dark, axe-clean with nothing overflowing; alone (?solo) it takes the grid's full width; `?wide` is the 7/5 `main-aside-wide` preset ─────────
const dashShots = process.env.KIT_DASHBOARD_SCREENSHOTS;
const dashLayout = (tab) => tab.evaluate(() => {
    const box = (el) => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width }; };
    return {
        grid: box(document.querySelector('[data-widget-grid]')),
        regions: Object.fromEntries([...document.querySelectorAll('[data-widget-region]')].map(c => [c.dataset.widgetRegion, { box: box(c), cards: [...c.querySelectorAll(':scope > [data-widget]')].map(card => card.dataset.widget) }])),
        order: [...document.querySelectorAll('[data-widget]')].map(e => e.dataset.widget),
        setup: document.querySelector('[data-widget="kit.setup"]') ? box(document.querySelector('[data-widget="kit.setup"]')) : null,
        page: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        overflowing: [...document.querySelectorAll('[data-widget-grid], [data-widget-grid] *')].filter(el => el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX === 'visible').map(el => el.tagName),
    };
});
test('the dashboard setup card at 1280 and 375, light and dark: personal steps in You, the setup card last in the aside, axe passes, nothing overflows', async () => {
    for (const width of [1280, 375]) {
        for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                await tab.goto(`${authUrl}dashboard.html?setup${dark}`);
                await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"]');
                await tab.waitForSelector('[data-widget="kit.setup"] [data-widget-setup]');
                const layout = await dashLayout(tab);
                assert.deepEqual(layout.regions.aside.cards, ['kit.you', 'kit.people', 'kit.setup'], `${width} ${mode}: the setup card is last in the aside`);
                if (width < 768) assert.equal(layout.order.at(-1), 'kit.setup', 'stacked, it is the last card on the page');
                assert.equal(await tab.locator('[data-widget="kit.you"] [data-widget-you-setup] li').count(), 2, 'You lists the person\'s own steps');
                assert.equal(await tab.locator('[data-widget="kit.setup"] li').count(), 2, 'the setup card lists the workspace\'s steps');
                assert.equal(await tab.locator('[data-widget-you-setup] a[href="/welcome/photo"]').count(), 1, 'a personal step links to its welcome step');
                assert.equal(layout.page, 0, `${width} ${mode}: no horizontal page scroll`);
                assert.deepEqual(layout.overflowing, [], `${width} ${mode}: nothing in the grid overflows its box`);
                assert.deepEqual(await notesAxe(tab), [], `${width} ${mode}: axe`);
                assert.deepEqual(errors, []);
                if (dashShots) await tab.screenshot({ path: `${dashShots}/dash-setup-${width}-${mode}.png`, fullPage: true });
            } finally {
                await context.close();
            }
        }
    }
});

// ── The You card (AB's form): no visible title, the viewer's avatar, name, local time and timezone with Edit, the "Finish setting up" checklist only while a
// step is open, still named "You" for assistive tech; at 1280 and 375, light and dark, axe-clean, nothing overflowing ──────────────────────────────────────
const youShots = process.env.KIT_YOUCARD_SCREENSHOTS;
test('the You card at 1280 and 375, light and dark: no "You" title, name, local time and timezone with Edit, the checklist only with open steps, axe passes', async () => {
    for (const width of [1280, 375]) {
        for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, timezoneId: 'Europe/Amsterdam', locale: 'en-US' });
            try {
                // A fake clock, 10 seconds before a minute turns (09:21:50 in New York), so the card's tick on the minute boundary is checked, not waited for.
                await context.clock.install({ time: new Date('2026-11-09T14:21:50Z') });
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                for (const [query, open] of [['?setup', true], ['?setup&done', false]]) {
                    await tab.goto(`${authUrl}dashboard.html${query}${dark}`);
                    await tab.waitForSelector('[data-widget="kit.you"] [data-widget-you]');
                    await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"]');
                    const card = tab.locator('[data-widget="kit.you"]');
                    assert.equal(await card.locator('header, h2').count(), 0, 'the You card has no title bar');
                    assert.equal((await card.innerText()).split('\n').some(line => line.trim() === 'You'), false, 'no visible "You" anywhere in the card');
                    assert.equal(await card.getAttribute('aria-label'), 'You', 'the card is still named "You"');
                    assert.equal(await tab.getByRole('region', { name: 'You', exact: true }).count(), 1, 'and is reachable as the "You" region');
                    assert.equal((await card.locator('[data-widget-you-name]').innerText()).trim(), 'Ada Example');
                    // The time is the viewer's own zone (America/New_York, sent by the server), never the browser's (Europe/Amsterdam here).
                    // The card's clock ticks each minute, so the time read may be the minute before or after this check's own; either is New York's.
                    // Any minute from one read just before the card to one read just after it is New York's; the browser's own zone (Amsterdam) never is.
                    const nyMinute = () => tab.evaluate(() => { const parts = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hourCycle: 'h23', timeZone: 'America/New_York' }).formatToParts(new Date()); const part = (type) => Number(parts.find(p => p.type === type).value); return part('hour') * 60 + part('minute'); });
                    const shown = async () => { const text = (await card.locator('[data-widget-you-time]').innerText()).trim(); const m = text.match(/^(\d{1,2}):(\d{2})\s*([AP]M)/); assert.ok(m, `a time is shown: ${text}`); return { text, minute: ((Number(m[1]) % 12) + (m[3] === 'PM' ? 12 : 0)) * 60 + Number(m[2]) }; };
                    const before = await nyMinute();
                    const card1 = await shown();
                    const after = await nyMinute();
                    assert.match(card1.text, / · America\/New York$/, 'local time and timezone, underscores as spaces');
                    assert.ok(card1.minute >= before && card1.minute <= after, `the time is New York's (between minute ${before} and ${after}), not the browser's: ${card1.text}`);
                    // The card turns on the minute boundary, not 60s after it mounted.
                    const turn = 60_000 - (await tab.evaluate(() => Date.now() % 60_000)) + 500;
                    await tab.clock.runFor(turn);
                    assert.equal((await shown()).minute, card1.minute + 1, 'the clock turns at the next minute boundary');
                    await tab.clock.runFor(60_000);
                    assert.equal((await shown()).minute, card1.minute + 2, 'and every minute after it');
                    assert.equal(await card.locator('[data-widget-you-time]').evaluate(el => el.scrollWidth <= el.clientWidth), true, 'the time and timezone are never cut off');
                    const edit = card.locator('a[data-widget-you-edit]');
                    assert.equal(await edit.getAttribute('href'), '/me/profile');
                    assert.ok((await edit.boundingBox()).height >= 44, 'Edit is a 44px touch target');
                    assert.equal((await edit.evaluate(el => el.firstChild.textContent)).trim(), 'Edit', 'the link reads Edit');
                    assert.equal(await card.locator('[data-widget-you-setup]').count(), open ? 1 : 0, open ? 'the checklist shows while a step is open' : 'no checklist once every step is done');
                    if (open) assert.equal(await card.locator('[data-widget-you-setup] a[href="/welcome/photo"]').count(), 1, 'the open step links to its welcome step');
                    const layout = await dashLayout(tab);
                    assert.equal(layout.page, 0, `${width} ${mode}: no horizontal page scroll`);
                    assert.deepEqual(layout.overflowing, [], `${width} ${mode}: nothing in the grid overflows its box`);
                    assert.deepEqual(await notesAxe(tab), [], `${width} ${mode}: axe`);
                    if (youShots) await card.screenshot({ path: `${youShots}/youcard-${width}-${mode}-${open ? 'open' : 'done'}.png` });
                    if (youShots && open) await tab.screenshot({ path: `${youShots}/youcard-${width}-${mode}-page.png`, fullPage: true });
                }
                assert.deepEqual(errors, []);
            } finally {
                await context.close();
            }
        }
    }
});

test('the dashboard with no other widget: the setup card alone takes the grid\'s full width, at 1280 and 375, light and dark, axe passes', async () => {
    for (const width of [1280, 375]) {
        for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            try {
                const tab = await context.newPage();
                await tab.goto(`${authUrl}dashboard.html?solo${dark}`);
                await tab.waitForSelector('[data-widget="kit.setup"] [data-widget-setup]');
                const layout = await dashLayout(tab);
                assert.deepEqual(layout.order, ['kit.setup']);
                assert.ok(Math.abs(layout.setup.left - layout.grid.left) < 1 && Math.abs(layout.setup.width - layout.grid.width) < 1, `${width} ${mode}: the setup card spans the grid`);
                assert.equal(layout.page, 0);
                assert.deepEqual(layout.overflowing, []);
                assert.deepEqual(await notesAxe(tab), [], `${width} ${mode}: axe`);
                if (dashShots) await tab.screenshot({ path: `${dashShots}/dash-solo-${width}-${mode}.png`, fullPage: true });
            } finally {
                await context.close();
            }
        }
    }
});

test('the dashboard\'s wide aside (main-aside-wide): main and aside about 58/42 of the row at 1280, 8/4 by default, stacked at 375, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        try {
            const tab = await context.newPage();
            const share = async (query) => {
                await tab.goto(`${authUrl}dashboard.html?setup${query}${dark}`);
                await tab.waitForSelector('[data-widget="kit.setup"] [data-widget-setup]');
                const { regions: { main, aside }, page, overflowing } = await dashLayout(tab);
                assert.equal(page, 0);
                assert.deepEqual(overflowing, []);
                return main.box.width / (main.box.width + aside.box.width);
            };
            const wide = await share('&wide');
            assert.ok(Math.abs(wide - 7 / 12) < 0.02, `${mode}: wide main is about 58% of the columns (${(wide * 100).toFixed(1)}%)`);
            assert.equal(await tab.getAttribute('[data-widget-grid]', 'data-preset'), 'main-aside-wide');
            assert.deepEqual(await notesAxe(tab), [], `${mode}: axe`);
            if (dashShots) await tab.screenshot({ path: `${dashShots}/dash-wide-1280-${mode}.png`, fullPage: true });
            const narrow = await share('');
            assert.ok(Math.abs(narrow - 8 / 12) < 0.02, `${mode}: the default stays 8/4 (${(narrow * 100).toFixed(1)}%)`);
            await tab.setViewportSize({ width: 375, height: 900 });
            await share('&wide');
            const { regions: { main, aside } } = await dashLayout(tab);
            assert.ok(Math.abs(main.box.width - aside.box.width) < 1 && main.box.bottom <= aside.box.top, `${mode}: one column at 375, main first`);
            if (dashShots) await tab.screenshot({ path: `${dashShots}/dash-wide-375-${mode}.png`, fullPage: true });
        } finally {
            await context.close();
        }
    }
});

// ── Deprecated until 0.16: an app's own `welcome` card (dashboard.html?legacy) renders on the dashboard Page as before 0.15.41 ─────────────────────────────
test('the dashboard Page with an app\'s deprecated welcome card: the card, its checklist links and Dismiss render above the widgets, axe passes, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            const dismissed = [];
            await tab.route('**/me/welcome/1', route => { dismissed.push(route.request().method()); return route.fulfill({ status: 409, body: '' }); });
            await tab.goto(`${authUrl}dashboard.html?legacy${dark}`);
            await tab.waitForSelector('[data-welcome-card]');
            assert.equal(await tab.textContent('[data-welcome-title]'), 'Welcome to Acme, Ada.');
            assert.deepEqual(await tab.$$eval('[data-welcome-checklist] a', els => els.map(el => el.getAttribute('href'))), ['/welcome/timezone', '/welcome/photo']);
            assert.equal(await tab.textContent('[data-welcome-progress]'), '1 of 2 done');
            assert.ok(await tab.evaluate(() => document.querySelector('[data-welcome-card]').compareDocumentPosition(document.querySelector('[data-widget-grid]')) & Node.DOCUMENT_POSITION_FOLLOWING), 'the card is above the widgets');
            assert.deepEqual(await notesAxe(tab), [], `${mode}: axe`);
            await tab.click('[data-welcome-dismiss]');
            await tab.waitForTimeout(200);
            assert.deepEqual(dismissed, ['DELETE'], 'Dismiss sends a DELETE to the card\'s dismiss url');
            assert.deepEqual(errors.filter(e => !/409|Conflict/i.test(e)), []);
        } finally {
            await context.close();
        }
    }
});

// ── The signed-in bar with ten top-level pages (shell.html): the pages that don't fit go into More, so search, the appearance toggle and the avatar stay ──
test('the shell bar with ten pages at 1440 and 1024: nothing overflows, the avatar can be clicked, the rest are under More, the current page stays, axe-clean', async () => {
    for (const width of [1440, 1024]) {
        for (const current of [0, 9]) {
            const context = await browser.newContext({ viewport: { width, height: 800 } });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                await tab.goto(`${authUrl}shell.html?current=${current}`);
                await tab.waitForSelector('[data-shell-nav] a');
                await tab.waitForFunction(() => document.querySelector('[data-shell-nav-more]'));
                const box = await tab.evaluate(() => {
                    const row = document.querySelector('[data-shell-bar] > div');
                    const rowRect = row.getBoundingClientRect();
                    const inside = (selector) => { const r = document.querySelector(selector).getBoundingClientRect(); return r.width > 0 && r.left >= rowRect.left - 0.5 && r.right <= rowRect.right + 0.5; };
                    const nav = document.querySelector('[data-shell-nav]').getBoundingClientRect();
                    const search = document.querySelector('[data-shell-search]').getBoundingClientRect();
                    return {
                        pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
                        rowOverflow: row.scrollWidth - row.clientWidth,
                        navBeforeSearch: nav.right <= search.left + 0.5,
                        search: inside('[data-shell-search]'),
                        toggle: inside('[data-shell-actions] button:not([data-shell-user-menu])'),
                        avatar: inside('[data-shell-user-menu]'),
                        shown: [...document.querySelectorAll('[data-shell-nav] > a')].map(a => a.textContent.trim()),
                        current: document.querySelector('[data-shell-nav] [aria-current="page"]')?.textContent.trim() ?? null,
                    };
                });
                const label = `${width}, page ${current} current`;
                assert.ok(box.pageOverflow <= 0 && box.rowOverflow <= 0, `${label}: nothing overflows (page ${box.pageOverflow}px, bar ${box.rowOverflow}px)`);
                assert.ok(box.navBeforeSearch && box.search && box.toggle && box.avatar, `${label}: the pages end before search, and search, the appearance toggle and the avatar sit in the bar`);
                assert.ok(box.shown.length < 10, `${label}: some pages moved to More (${box.shown.length} shown)`);
                assert.equal(box.current, current === 0 ? 'Overview' : 'Integrations', `${label}: the current page stays in the bar`);
                assert.deepEqual(await notesAxe(tab), [], `${label}: axe`);
                // Keyboard: the More button opens a menu of the rest, in order, the current page not among them.
                await tab.focus('[data-shell-nav-more]');
                await tab.keyboard.press('Enter');
                await tab.waitForSelector('[role="menu"] [data-shell-nav-more-item]');
                const more = await tab.evaluate(() => [...document.querySelectorAll('[role="menu"] [data-shell-nav-more-item]')].map(a => a.textContent.trim()));
                assert.equal(box.shown.length + more.length, 10, `${label}: every page is in the bar or under More`);
                assert.ok(!more.includes(box.current), `${label}: the current page is not under More`);
                const order = ['Overview', 'Projects', 'Schedules', 'Contacts', 'Invoices', 'Documents', 'Inventory', 'Reports', 'Automations', 'Integrations'];
                assert.deepEqual(more, order.filter(name => !box.shown.includes(name)), `${label}: More lists the rest in their order`);
                assert.equal(await tab.getAttribute('[data-shell-nav-more]', 'aria-expanded'), 'true');
                assert.ok(await tab.evaluate(() => document.activeElement?.closest('[role="menu"]') !== null), `${label}: focus moves into the menu`);
                await tab.keyboard.press('Escape');
                await tab.waitForFunction(() => !document.querySelector('[role="menu"]'));
                // The avatar can be clicked: its menu opens.
                await tab.click('[data-shell-user-menu]');
                await tab.waitForSelector('[role="menu"]');
                if (process.env.SHELL_SHOTS) await tab.screenshot({ path: `${process.env.SHELL_SHOTS}/shell-${width}-${current}.png` });
                assert.deepEqual(errors, []);
            } finally {
                await context.close();
            }
        }
    }
});

// Four pages beside an app's own search (the bar slot) at 1024, with the room just 1px wider than the pages need: every page stays and there is no More. The
// pages' need is read off the ruler (their widths and the gaps between them, none after the last), and the switcher is pushed out until the room is that
// plus 1px, so the check holds whatever the fonts. Taking 3px more away, beyond what the search can give up (its slot down to its minimum), is a real overflow,
// and More appears.
test('the shell bar with four pages and search at 1024: pages that fit stay in the bar, More only when they really overflow', async () => {
    for (const current of [0, 3]) {
        const context = await browser.newContext({ viewport: { width: 1024, height: 800 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${authUrl}shell.html?pages=four&bar&current=${current}`);
            await tab.waitForSelector('[data-shell-nav] a');
            const squeeze = (spare) => tab.evaluate(async (spare) => {
                const frames = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                const row = document.querySelector('[data-shell-bar] > div');
                const room = document.querySelector('[data-shell-nav-room]');
                const switcher = room.previousElementSibling;
                const ruler = document.querySelector('[data-shell-nav-measure] > div');
                const widths = [...ruler.querySelectorAll('[data-measure-item]')].map(el => el.getBoundingClientRect().width);
                const gap = Number.parseFloat(getComputedStyle(ruler).columnGap) || 0;
                const need = widths.reduce((sum, width) => sum + width, 0) + gap * (widths.length - 1);
                switcher.style.marginRight = '0px';
                await frames();
                // Search shrinks first: a short room takes the slot down to its minimum (its own width or the 10rem token) before any page moves.
                const slotBox = document.querySelector('[data-shell-bar-slot]');
                const give = spare < 0 ? slotBox.getBoundingClientRect().width - Math.min(slotBox.scrollWidth, Number.parseFloat(getComputedStyle(document.documentElement).fontSize) * 10) : 0;
                switcher.style.marginRight = `${room.getBoundingClientRect().width - need - spare + give}px`;
                await frames();
                await frames();
                const nav = document.querySelector('[data-shell-nav]').getBoundingClientRect();
                const slot = document.querySelector('[data-shell-bar-slot]').getBoundingClientRect();
                return {
                    spare: Math.round((room.getBoundingClientRect().width - need) * 10) / 10,
                    rowOverflow: row.scrollWidth - row.clientWidth,
                    navBeforeSearch: nav.right <= slot.left + 0.5,
                    shown: [...document.querySelectorAll('[data-shell-nav] > a')].map(a => a.textContent.trim()),
                    more: document.querySelector('[data-shell-nav-more]') !== null,
                };
            }, spare);
            const label = `page ${current} current`;
            const fits = await squeeze(1);
            assert.ok(Math.abs(fits.spare - 1) < 0.6, `${label}: the room is 1px wider than the pages (${fits.spare}px)`);
            assert.deepEqual(fits.shown, ['Dashboard', 'Assignments', 'Notes', 'Settings'], `${label}: all four pages stay in the bar when they fit`);
            assert.equal(fits.more, false, `${label}: no More when the pages fit`);
            assert.ok(fits.rowOverflow <= 0 && fits.navBeforeSearch, `${label}: nothing overflows and the pages end before search (bar ${fits.rowOverflow}px)`);
            const over = await squeeze(-2);
            assert.equal(over.more, true, `${label}: More appears once the pages really overflow (${over.spare}px)`);
            assert.ok(over.shown.length < 4 && over.shown.includes(current === 0 ? 'Dashboard' : 'Settings'), `${label}: some pages moved to More, the current one stays`);
            assert.ok(over.rowOverflow <= 0 && over.navBeforeSearch, `${label}: nothing overflows with More (bar ${over.rowOverflow}px)`);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
});

// Search shrinks first: an app's real bar (?app: a tile mark, four pages, its own 13rem search field in the bar slot, the appearance toggle and a bell) at
// exactly 1024, where the pages need a few pixels more than the bar leaves them beside the full field. The field gives them up: all four pages stay, there is
// no More, and the field is narrower but at or above its minimum (its own width or the 10rem token, whichever is smaller), inside its slot, clear of the
// actions. On a fresh load and after resizing from 1000 (the drawer) and from 1280. Then, with the switcher pushed out past what the field can give, More appears
// and the field sits at its minimum.
test('search shrinks first: an app bar with four pages and its own search at 1024 keeps every page, the field narrower but not below its minimum', async () => {
    const frames = (tab) => tab.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const read = (tab) => tab.evaluate(() => {
        const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
        const row = document.querySelector('[data-shell-bar] > div');
        const slot = rect('[data-shell-bar-slot]');
        const field = rect('[data-global-search-input]');
        const ruler = document.querySelector('[data-shell-nav-measure] > div');
        const widths = [...ruler.querySelectorAll('[data-measure-item]')].map(el => el.getBoundingClientRect().width);
        const gap = Number.parseFloat(getComputedStyle(ruler).columnGap) || 0;
        return {
            need: widths.reduce((sum, width) => sum + width, 0) + gap * (widths.length - 1),
            room: rect('[data-shell-nav-room]').width,
            slot: slot.width,
            field: field.width,
            natural: document.querySelector('[data-global-search] > div').scrollWidth,
            token: Number.parseFloat(getComputedStyle(document.documentElement).fontSize) * 10,
            fieldInside: field.left >= slot.left - 0.5 && field.right <= slot.right + 0.5,
            clearOfActions: slot.right <= rect('[data-shell-actions]').left + 0.5,
            navBeforeSearch: rect('[data-shell-nav]').right <= slot.left + 0.5,
            rowOverflow: row.scrollWidth - row.clientWidth,
            pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
            shown: [...document.querySelectorAll('[data-shell-nav] > a')].map(a => a.textContent.trim()),
            more: document.querySelector('[data-shell-nav-more]') !== null,
        };
    });
    for (const from of [null, 1000, 1280]) {
        const context = await browser.newContext({ viewport: { width: from ?? 1024, height: 800 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${authUrl}shell.html?pages=four&app`);
            await tab.waitForSelector('[data-shell-bar-slot] input');
            if (from !== null) {
                await frames(tab);
                await tab.setViewportSize({ width: 1024, height: 800 });
            }
            await tab.waitForSelector('[data-shell-nav] a');
            await frames(tab);
            await frames(tab);
            const label = from === null ? '1024, fresh load' : `1024, resized from ${from}`;
            const bar = await read(tab);
            const floor = Math.min(208, bar.token);
            assert.deepEqual(bar.shown, ['Dashboard', 'Assignments', 'Notes', 'Settings'], `${label}: all four pages stay in the bar`);
            assert.equal(bar.more, false, `${label}: no More`);
            assert.ok(bar.slot < 208 - 0.5, `${label}: the search gave up width (its slot is ${bar.slot}px of 208)`);
            assert.ok(bar.slot >= floor - 0.5 && bar.field >= floor - 0.5, `${label}: the field is at or above its minimum of ${floor}px (slot ${bar.slot}px, field ${bar.field}px)`);
            assert.ok(bar.room >= bar.need - 0.5, `${label}: the pages have their room (${bar.room}px for ${bar.need}px)`);
            assert.ok(bar.fieldInside && bar.natural <= bar.slot + 0.5, `${label}: the field narrows inside its slot, no spill (field box ${bar.natural}px, slot ${bar.slot}px)`);
            assert.ok(bar.clearOfActions && bar.navBeforeSearch, `${label}: the pages end before search and search ends before the actions`);
            assert.ok(bar.rowOverflow <= 0 && bar.pageOverflow <= 0, `${label}: nothing overflows (bar ${bar.rowOverflow}px, page ${bar.pageOverflow}px)`);
            if (process.env.SHELL_SHOTS) await tab.screenshot({ path: `${process.env.SHELL_SHOTS}/searchshrink-1024-${from ?? 'fresh'}.png` });
            if (from === null) {
                // Push the switcher out until the pages are 2px short even with the field at its minimum: now More appears, and the field is at its minimum.
                const give = bar.slot - floor;
                const spare = bar.room - bar.need;
                await tab.evaluate((px) => { document.querySelector('[data-shell-nav-room]').previousElementSibling.style.marginRight = `${px}px`; }, give + spare + 2);
                await frames(tab);
                await frames(tab);
                const over = await read(tab);
                assert.equal(over.more, true, `${label}, ${give + spare + 2}px taken: More appears once even the shrunk search can't make room`);
                assert.ok(over.shown.length < 4 && over.shown.includes('Dashboard'), `${label}: some pages moved to More, the current one stays`);
                assert.ok(Math.abs(over.slot - floor) < 0.6, `${label}: the field is at its minimum when More appears (slot ${over.slot}px, minimum ${floor}px)`);
                assert.ok(over.fieldInside && over.clearOfActions && over.navBeforeSearch && over.rowOverflow <= 0, `${label}: nothing overlaps or overflows with More (bar ${over.rowOverflow}px)`);
                if (process.env.SHELL_SHOTS) await tab.screenshot({ path: `${process.env.SHELL_SHOTS}/searchshrink-1024-more.png` });
            }
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
});

// The search's floor holds only from lg: resizing from 1440 to 390 must not leave a frame where the bar slot keeps its lg minimum and pushes the drawer's
// Menu button off the edge. The page width is read right after the resize, before any ResizeObserver callback, ten times over (the failure was timing-bound).
test('the bar slot drops its search floor below lg in the same frame, and PrimaryNav causes no ResizeObserver loop: no page overflow right after resizing from 1440 to 390', async () => {
    for (let run = 0; run < 10; run++) {
        const context = await browser.newContext({ viewport: { width: 1440, height: 800 } });
        try {
            const tab = await context.newPage();
            const logged = [];
            tab.on('console', message => logged.push(message.text()));
            tab.on('pageerror', error => logged.push(error.message));
            await tab.addInitScript(() => window.addEventListener('error', (event) => console.error(`window error: ${event.message}`)));
            await tab.goto(`${authUrl}shell.html?pages=four&app`);
            await tab.waitForSelector('[data-shell-nav] a');
            // A few resizes across the room's range first: PrimaryNav never writes inside its own ResizeObserver callback, so no "ResizeObserver loop" error.
            // The text size changes too (a host's zoom or font load): the pages' ruler resizes, so the room's flex-basis changes and the room with it.
            for (const [width, size] of [[1100, '17px'], [1024, '16px'], [1300, '18px'], [1440, '']]) {
                await tab.setViewportSize({ width, height: 800 });
                await tab.evaluate((size) => { document.documentElement.style.fontSize = size; }, size);
                await tab.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(resolve, 50)))));
            }
            assert.deepEqual(logged.filter(text => /ResizeObserver loop/.test(text)), [], `run ${run}: no ResizeObserver loop error across the resizes`);
            await tab.waitForFunction(() => document.querySelector('[data-shell-bar-slot]').style.length > 0 || document.querySelector('[data-shell-nav-room]').style.flexBasis !== '');
            await tab.setViewportSize({ width: 390, height: 800 });
            const now = await tab.evaluate(() => ({ scroll: document.documentElement.scrollWidth, inner: window.innerWidth, menu: [...document.querySelectorAll('[data-shell-actions] button')].filter(b => b.getClientRects().length).map(b => Math.round(b.getBoundingClientRect().right)) }));
            assert.ok(now.scroll <= now.inner, `run ${run}: the page is ${now.scroll}px wide at ${now.inner}px right after the resize (buttons end at ${now.menu.join(', ')})`);
            assert.ok(now.menu.every(right => right <= now.inner), `run ${run}: every bar button is on screen right after the resize`);
        } finally {
            await context.close();
        }
    }
});

// A bar that mounts with no navigation (?late) and gets its pages afterwards still follows the room: narrowing the window moves pages into More, widening
// it brings them back. The resize observer attaches to the nav when it appears, not only at mount.
test('the shell bar given its pages after mount: the split follows the window as it narrows and widens', async () => {
    const context = await browser.newContext({ viewport: { width: 1920, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${authUrl}shell.html?late`);
        await tab.waitForSelector('[data-shell-bar]');
        assert.equal(await tab.locator('[data-shell-nav]').count(), 0, 'no nav while there are no pages');
        await tab.evaluate(() => window.kitShowNavigation());
        await tab.waitForSelector('[data-shell-nav] a');
        const shown = () => tab.evaluate(() => document.querySelectorAll('[data-shell-nav] > a').length);
        // Two frames for the first measure, then read how many pages the wide bar shows (the bar is 80rem at most, so not all ten).
        await tab.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const wide = await shown();
        await tab.setViewportSize({ width: 1024, height: 800 });
        await tab.waitForFunction((wide) => document.querySelectorAll('[data-shell-nav] > a').length < wide, wide, { timeout: 5000 }).catch(() => {});
        const narrow = await shown();
        assert.ok(narrow < wide, `narrowing from 1920 to 1024 moves pages into More (${wide} then ${narrow} shown)`);
        const overflow = await tab.evaluate(() => { const row = document.querySelector('[data-shell-bar] > div'); return row.scrollWidth - row.clientWidth; });
        assert.ok(overflow <= 0, `nothing overflows at 1024 (bar ${overflow}px)`);
        await tab.setViewportSize({ width: 1920, height: 800 });
        await tab.waitForFunction((wide) => document.querySelectorAll('[data-shell-nav] > a').length === wide, wide, { timeout: 5000 }).catch(() => {});
        assert.equal(await shown(), wide, 'back at 1920 the bar shows as many pages as before');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── Navigation (shell.html?module=N): the trail as a bar by default, a module's sibling pages as <Page>'s tabs from `layout.navigation`, no SubNav strip ──
test('a module page at 1280 and 375: the trail bar on its top page and a sub-page, its pages as tabs with the right one current, no strip, no overflow, axe-clean', async () => {
    for (const width of [1280, 375]) {
        for (const [module, label, trail] of [[0, 'All projects', ['Acme Workshop', 'Projects']], [2, 'Budgets', ['Acme Workshop', 'Projects', 'Budgets']]]) {
            const context = await browser.newContext({ viewport: { width, height: 800 } });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                await tab.goto(`${authUrl}shell.html?module=${module}`);
                await tab.waitForSelector('[data-page-module-tabs]');
                const seen = await tab.evaluate(() => {
                    const tabs = document.querySelector('[data-page-module-tabs]');
                    const list = tabs.querySelector('ul');
                    return {
                        bar: [...document.querySelectorAll('[data-shell-breadcrumbs] li')].map(li => li.textContent.trim() || li.querySelector('a')?.getAttribute('aria-label')),
                        crumbCurrent: document.querySelector('[data-shell-breadcrumbs] [aria-current="page"]')?.textContent.trim() ?? null,
                        lines: document.querySelectorAll('[data-slot="breadcrumbs"], [data-slot="breadcrumbs-spacer"]').length,
                        rows: document.querySelectorAll('[data-page-tabs]').length,
                        links: [...tabs.querySelectorAll('a')].map(a => a.textContent.trim()),
                        current: tabs.querySelector('a[aria-current="page"]')?.textContent.trim() ?? null,
                        phone: tabs.querySelector('.sm\\:hidden [role="combobox"]')?.value ?? '',
                        strip: document.querySelectorAll('[data-shell-subnav]').length,
                        tabsAboveHeading: tabs.getBoundingClientRect().bottom <= document.querySelector('[data-slot="page-heading"]').getBoundingClientRect().top + 0.5,
                        underBar: tabs.getBoundingClientRect().top >= document.querySelector('[data-shell-breadcrumbs]').getBoundingClientRect().bottom - 0.5,
                        pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
                        listScrolls: getComputedStyle(list).overflowX,
                        tabsRight: tabs.getBoundingClientRect().right,
                    };
                });
                const name = `${width}, module page ${module}`;
                assert.deepEqual(seen.bar, trail, `${name}: the trail bar shows the whole trail`);
                assert.equal(seen.crumbCurrent, trail.at(-1), `${name}: the current page is the trail's last crumb`);
                assert.equal(seen.lines, 0, `${name}: the trail is drawn once, as the bar`);
                assert.equal(seen.rows, 1, `${name}: one row of tabs`);
                assert.equal(seen.links.length, 7, `${name}: every sibling page is a tab`);
                assert.equal(seen.current, label, `${name}: the right tab is current`);
                assert.equal(seen.strip, 0, `${name}: no SubNav strip by default`);
                assert.ok(seen.underBar && seen.tabsAboveHeading, `${name}: the tabs sit under the trail bar, above the heading`);
                assert.ok(seen.pageOverflow <= 0 && seen.tabsRight <= width + 0.5, `${name}: nothing overflows (page ${seen.pageOverflow}px)`);
                assert.equal(seen.listScrolls, 'auto', `${name}: a long tab row scrolls sideways inside itself`);
                if (width < 640) assert.equal(seen.phone, label, `${name}: on a phone the tabs are a picker showing the current page`);
                assert.deepEqual(await notesAxe(tab), [], `${name}: axe`);
                if (width === 1280 && module === 2 && process.env.NAV_SHOT) await tab.screenshot({ path: process.env.NAV_SHOT, fullPage: true });
                if (process.env.SHELL_SHOTS) await tab.screenshot({ path: `${process.env.SHELL_SHOTS}/nav-${width}-${module}.png`, fullPage: true });
                assert.deepEqual(errors, []);
            } finally {
                await context.close();
            }
        }
    }
});

test('a module tab row never scrolls up and down: wide windows fit it with no overflow, a narrow sm window scrolls it sideways with no scrollbar, and ring and underline stay whole', async () => {
    for (const scheme of ['light', 'dark']) {
        for (const width of [1920, 1280, 640]) {
            const context = await browser.newContext({ viewport: { width, height: 800 }, colorScheme: scheme });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                await tab.goto(`${authUrl}shell.html?module=0&billing${scheme === 'dark' ? '&dark' : ''}`);
                await tab.waitForSelector('[data-page-module-tabs] ul a');
                const name = `${scheme} ${width}`;
                const measure = () => tab.evaluate(() => {
                    const list = document.querySelector('[data-page-module-tabs] ul');
                    const box = list.getBoundingClientRect();
                    const style = getComputedStyle(list);
                    const inside = (el) => { const r = el.getBoundingClientRect(); const css = getComputedStyle(el); const o = css.outlineStyle === 'none' ? 0 : parseFloat(css.outlineOffset) + parseFloat(css.outlineWidth); return { top: r.top - Math.max(o, 0) >= box.top - 0.5, bottom: r.bottom + Math.max(o, 0) <= box.bottom - parseFloat(style.borderBottomWidth) + 0.5, left: r.left >= box.left - 0.5, right: r.right <= box.right + 0.5 }; };
                    const active = list.querySelector('a[aria-current="page"]');
                    const focused = list.contains(document.activeElement) ? document.activeElement : null;
                    return {
                        labels: [...list.querySelectorAll('a')].map(a => a.textContent.trim()),
                        vertical: list.scrollHeight - list.clientHeight,
                        horizontal: list.scrollWidth - list.clientWidth,
                        overflowY: style.overflowY,
                        scrollbarWidth: style.scrollbarWidth,
                        gutter: list.offsetHeight - list.clientHeight - parseFloat(style.borderTopWidth) - parseFloat(style.borderBottomWidth),
                        scrollLeft: list.scrollLeft,
                        underline: parseFloat(getComputedStyle(active).borderBottomWidth),
                        activeInside: inside(active),
                        focusedInside: focused ? inside(focused) : null,
                        ring: focused ? getComputedStyle(focused).outlineStyle : null,
                    };
                });
                assert.equal(await tab.evaluate(() => document.documentElement.classList.contains('dark')), scheme === 'dark', `${name}: the scheme's tokens`);
                const seen = await measure();
                assert.deepEqual(seen.labels, ['Invoices', 'Subscriptions', 'Allowances', 'Plans', 'Add-ons', 'Passes', 'Billing entities'], `${name}: the seven tabs`);
                assert.ok(seen.vertical <= 0, `${name}: no vertical overflow (${seen.vertical}px)`);
                assert.equal(seen.overflowY, 'hidden', `${name}: the row never scrolls up and down`);
                assert.equal(seen.scrollbarWidth, 'none', `${name}: no scrollbar is drawn`);
                assert.equal(seen.gutter, 0, `${name}: no scrollbar gutter`);
                assert.equal(seen.underline, 2, `${name}: the current tab has its underline`);
                assert.deepEqual(seen.activeInside, { top: true, bottom: true, left: true, right: true }, `${name}: the underline sits wholly inside the row`);
                if (width >= 1280) assert.ok(seen.horizontal <= 0, `${name}: the tabs fit, so the row does not scroll sideways (${seen.horizontal}px)`);
                else assert.ok(seen.horizontal > 0, `${name}: at a narrow sm width the tabs don't fit and the row scrolls sideways`);
                // Keyboard: Tab to the last tab; the focused tab scrolls into view and its ring is drawn whole inside the row.
                await tab.locator('[data-page-module-tabs] ul a').first().focus();
                for (let i = 0; i < 6; i++) await tab.keyboard.press('Tab');
                const focused = await measure();
                assert.equal(focused.ring, 'solid', `${name}: the focused tab shows its ring`);
                assert.deepEqual(focused.focusedInside, { top: true, bottom: true, left: true, right: true }, `${name}: the focused tab and its ring are in view, unclipped`);
                if (width < 1280) assert.ok(focused.scrollLeft > 0, `${name}: the row scrolled to the focused tab`);
                assert.ok(focused.vertical <= 0, `${name}: still no vertical overflow with a ring shown`);
                assert.deepEqual(await notesAxe(tab), [], `${name}: axe`);
                if (process.env.TABSCROLL_SHOTS) await tab.screenshot({ path: `${process.env.TABSCROLL_SHOTS}/tabscroll-${scheme}-${width}.png`, clip: await tab.locator('[data-page-module-tabs]').boundingBox().then(b => ({ x: 0, y: Math.max(b.y - 80, 0), width, height: b.height + 160 })) });
                assert.deepEqual(errors, []);
            } finally {
                await context.close();
            }
        }
    }
});

test('a record frame with its own PageTabs gets one tab row, and subNav="strip" keeps the old strip with no tabs', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${authUrl}shell.html?module=0&record`);
        await tab.waitForSelector('[data-record-tabs]');
        const record = await tab.evaluate(() => ({ rows: document.querySelectorAll('[data-page-tabs]').length, auto: document.querySelectorAll('[data-page-module-tabs]').length }));
        assert.deepEqual(record, { rows: 1, auto: 0 }, 'the record\'s own PageTabs claims the navigation: one row');
        await tab.goto(`${authUrl}shell.html?module=2&strip`);
        await tab.waitForSelector('[data-shell-subnav]');
        const strip = await tab.evaluate(() => ({ tabs: document.querySelectorAll('[data-page-tabs]').length, current: document.querySelector('[data-shell-subnav] [aria-current="page"]')?.textContent.trim() }));
        assert.deepEqual(strip, { tabs: 0, current: 'Budgets' }, 'the strip, and no tabs');
        assert.deepEqual(await notesAxe(tab), [], 'axe with the strip');
    } finally {
        await context.close();
    }
});

test('a record frame whose PageTabs is turned off gives the tab row back to <Page>, and takes it again when turned on', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${authUrl}shell.html?module=0&record`);
        await tab.waitForSelector('[data-record-tabs]');
        const rows = () => tab.evaluate(() => ({ rows: document.querySelectorAll('[data-page-tabs]').length, auto: document.querySelectorAll('[data-page-module-tabs]').length, own: document.querySelectorAll('[data-record-tabs]').length }));
        assert.deepEqual(await rows(), { rows: 1, auto: 0, own: 1 }, 'the record\'s PageTabs holds the row');
        await tab.evaluate(() => window.kitRecordTabs(false));
        await tab.waitForFunction(() => document.querySelectorAll('[data-record-tabs]').length === 0);
        assert.deepEqual(await rows(), { rows: 1, auto: 1, own: 0 }, 'turned off, its claim is released: <Page> draws layout.navigation');
        await tab.evaluate(() => window.kitRecordTabs(true));
        await tab.waitForSelector('[data-record-tabs]');
        assert.deepEqual(await rows(), { rows: 1, auto: 0, own: 1 }, 'turned on again: one row, the record\'s');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

test('the notes composer: a second browse replaces the chosen files, while a drop and a paste add to them, and the save sends exactly the list shown', async () => {
    await notesPage('', async ({ tab, posts }) => {
        const names = () => tab.evaluate(() => [...document.querySelectorAll('[aria-label="Files to attach"] li span')].map(span => span.textContent.trim()));
        const file = (name, type) => ({ name, mimeType: type, buffer: Buffer.from(name) });
        await tab.setInputFiles('[data-dropzone-input]', [file('a.png', 'image/png')]);
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 1);
        await tab.setInputFiles('[data-dropzone-input]', [file('b.png', 'image/png')]);
        await tab.waitForFunction(() => document.querySelector('[aria-label="Files to attach"] li span')?.textContent.trim() === 'b.png');
        assert.deepEqual(await names(), ['b.png'], 'browsing again replaces the selection');
        await tab.evaluate(() => {
            const transfer = new DataTransfer();
            transfer.items.add(new File(['c'], 'c.pdf', { type: 'application/pdf' }));
            document.querySelector('[data-dropzone]').dispatchEvent(new DragEvent('drop', { dataTransfer: transfer, bubbles: true, cancelable: true }));
        });
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 2);
        assert.deepEqual(await names(), ['b.png', 'c.pdf'], 'a drop adds');
        await tab.getByRole('button', { name: 'Remove b.png' }).click();
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 1);
        await tab.evaluate(() => {
            const transfer = new DataTransfer();
            transfer.items.add(new File(['d'], 'd.png', { type: 'image/png' }));
            document.querySelector('[data-dropzone]').dispatchEvent(new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true }));
        });
        await tab.waitForFunction(() => document.querySelectorAll('[aria-label="Files to attach"] li').length === 2);
        assert.deepEqual(await names(), ['c.pdf', 'd.png'], 'a paste adds');
        await tab.fill('textarea[name=body]', 'Three ways in');
        await tab.click('[data-note-composer] button[type=submit]');
        for (let i = 0; i < 100 && posts.length === 0; i++) await tab.waitForTimeout(20);
        assert.deepEqual([...posts[0].matchAll(/filename="([^"]*)"/g)].map(match => match[1]), ['c.pdf', 'd.png'], 'the request carries exactly the listed files');
    });
});

// ── The bar's own mark (?logomark-bar): kit.brand.logomark_bar replaces the filtered logomark in the bar, unfiltered; unset, the bar is as before ──
test('logomark_bar: set, the bar shows it unfiltered and not the logomark; unset, the bar filters the logomark as before; the page never uses it', async () => {
    await page.goto(`${url}?logomark-bar`);
    await page.waitForSelector('[data-bar="with"] [data-shell-mark-bar]');
    const marks = await page.evaluate(() => {
        const imgs = (selector) => [...document.querySelectorAll(`${selector} [data-shell-wordmark] img`)].filter((img) => getComputedStyle(img).display !== 'none').map((img) => ({ bar: img.hasAttribute('data-shell-mark-bar'), src: img.getAttribute('src'), filter: getComputedStyle(img).filter }));
        return { plain: imgs('[data-bar="plain"]'), with: imgs('[data-bar="with"]'), page: imgs('[data-page="with"]') };
    });
    assert.equal(marks.with.length, 1, 'the bar with logomark_bar shows one mark');
    assert.ok(marks.with[0].bar && marks.with[0].filter === 'none', `the bar's own mark is unfiltered (${marks.with[0].filter})`);
    assert.ok(marks.plain.length === 1 && !marks.plain[0].bar && marks.plain[0].filter !== 'none', `without it the bar filters the logomark as before (${marks.plain[0]?.filter})`);
    assert.ok(marks.page.length === 1 && !marks.page[0].bar && marks.page[0].src === marks.plain[0].src, 'the page tone keeps the logomark');
});

// ── The lockup in the signed-in bar (?lockup-bar): capped, so a 120-character product and workspace name never overflow the row, the page or the breadcrumbs ──
test('the lockup in the bar, with a 120-character product and workspace name at 1440 and 768: the row, the page and the breadcrumbs never overflow, and the switcher still shows', async () => {
    for (const width of [1440, 768]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto(`${url}?lockup-bar`);
        await page.waitForSelector('[data-shell-lockup]');
        const box = await page.evaluate(() => {
            const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
            const row = document.querySelector('[data-bar-row]');
            return {
                pageOverflow: document.documentElement.scrollWidth - window.innerWidth,
                rowOverflow: row.scrollWidth - row.clientWidth,
                crumbsOverflow: document.querySelector('[data-crumbs]').scrollWidth - document.querySelector('[data-crumbs]').clientWidth,
                wordmark: rect('[data-shell-wordmark]').width,
                switcher: rect('[data-shell-switcher-group]').width,
                switcherRight: rect('[data-shell-switcher-group]').right,
                rowRight: row.getBoundingClientRect().right,
            };
        });
        assert.ok(box.pageOverflow <= 0, `${width}: the page does not scroll sideways (${box.pageOverflow}px)`);
        assert.ok(box.rowOverflow <= 0 && box.switcherRight <= box.rowRight + 0.5, `${width}: the bar row holds the lockup and the switcher`);
        assert.ok(box.crumbsOverflow <= 0, `${width}: the breadcrumbs truncate`);
        assert.ok(box.wordmark <= 256 + 0.5, `${width}: the lockup is capped (${box.wordmark}px)`);
        assert.ok(box.switcher >= 88, `${width}: the switcher keeps room (${box.switcher}px)`);
    }
    await page.setViewportSize({ width: 1280, height: 800 });
});

// ── The guest page's lockup (?lockup-page): the mark's box is the wordmark's height, a padded mark included; a cropped mark fills it; a lockup image goes as it is ──
test('the lockup on the page: a padded or cropped mark is at least 80% of the wordmark\'s height, and a full lockup image replaces both, named for the product', async () => {
    for (const scheme of ['light', 'dark']) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 600 }, colorScheme: scheme });
        const tab = await context.newPage();
        await tab.goto(`${url}?lockup-page`);
        if (scheme === 'dark') await tab.evaluate(() => { document.documentElement.classList.add('dark'); document.body.style.background = '#09090b'; });
        await tab.waitForFunction(() => [...document.images].every((img) => img.complete && img.naturalWidth > 0));
        const box = await tab.evaluate(() => {
            const visible = (root) => [...root.querySelectorAll('img')].filter((img) => getComputedStyle(img).display !== 'none');
            const sizes = (key) => {
                const root = document.querySelector(`[data-lockup="${key}"]`);
                const [mark, wordmark] = visible(root);
                return { mark: mark.getBoundingClientRect().height, wordmark: wordmark?.getBoundingClientRect().height ?? 0, count: visible(root).length };
            };
            const image = document.querySelector('[data-lockup="image"] [data-shell-lockup-image]');
            return { padded: sizes('padded'), cropped: sizes('cropped'), image: image && { alt: image.getAttribute('alt'), height: image.getBoundingClientRect().height, count: visible(document.querySelector('[data-lockup="image"]')).length, lockup: !!document.querySelector('[data-lockup="image"] [data-shell-lockup]') } };
        });
        for (const key of ['padded', 'cropped']) {
            assert.equal(box[key].count, 2, `${scheme} ${key}: the mark and the wordmark show`);
            assert.ok(box[key].wordmark > 0 && box[key].mark >= 0.8 * box[key].wordmark, `${scheme} ${key}: the mark's box (${box[key].mark}px) is at least 80% of the wordmark's (${box[key].wordmark}px)`);
        }
        assert.ok(box.image && box.image.count === 1 && !box.image.lockup, `${scheme}: the lockup image shows alone, with no mark or wordmark`);
        assert.equal(box.image.alt, 'Brightfield', `${scheme}: the lockup image is named for the product`);
        assert.ok(box.image.height >= 0.8 * box.padded.wordmark, `${scheme}: the lockup image keeps the lockup's height (${box.image.height}px)`);
        if (process.env.SHOTS) await tab.screenshot({ path: `${process.env.SHOTS}/lockup-${scheme}.png` });
        await context.close();
    }
});

// kitImports (docs/kit-imports.md), the dev-server plugin, over the real barrel: the dashboard grid loads only the kit files it uses, never index.ts, and still
// renders (without the plugin the whole barrel loads, 216 kit files, and stops at a shadcn piece the stand-ins lack). Every kit page here keeps no `@kit/ui` import.
test('kitImports: in dev the dashboard grid requests only the kit files it uses, never the barrel, and every kit page imports no name through it', async () => {
    const { kitImports } = await import(path.join(kit, 'Kit/kitImports.js'));
    const base = (await import(path.join(here, 'vite.auth.config.js'))).default;
    const barrel = path.join(kit, 'Kit/index.ts');
    const alias = [{ find: /^@kit\/ui$/, replacement: barrel }, ...base.resolve.alias.filter((entry) => String(entry.find) !== String(/^@kit\/ui$/))];
    const dev = await createServer({
        ...base, configFile: false, root: here, logLevel: 'error', cacheDir: path.join(here, 'node_modules/.vite-kit-imports'),
        resolve: { ...base.resolve, alias }, plugins: [...base.plugins, kitImports()],
        optimizeDeps: { entries: ['dashboard.html'] }, server: { port: 0, host: '127.0.0.1', hmr: false },
    });
    try {
        await dev.listen();
        const devPage = await browser.newPage({ viewport: { width: 1280, height: 800 } });
        const requested = [];
        const errors = [];
        devPage.on('request', (request) => requested.push(new URL(request.url()).pathname));
        devPage.on('pageerror', (error) => errors.push(error.message));
        await devPage.goto(`${dev.resolvedUrls.local[0]}dashboard.html`);
        await devPage.getByText('Ben Sample').waitFor({ timeout: 30000 });
        await devPage.waitForLoadState('networkidle');
        await devPage.close();
        const kitFiles = requested.filter((pathname) => pathname.startsWith('/.kit/Kit/'));
        assert.deepEqual(errors, []);
        assert.ok(!kitFiles.includes('/.kit/Kit/index.ts'), 'the barrel is never requested');
        assert.ok(kitFiles.length > 0 && kitFiles.length < 100, `the grid requests ${kitFiles.length} kit files, not the barrel's ~216`);
        const pages = readdirSync(path.join(kit, 'Pages'), { recursive: true }).filter((file) => file.endsWith('.vue'));
        let rewritten = 0;
        for (const file of pages) {
            const source = readFileSync(path.join(kit, 'Pages', file), 'utf8');
            if (!/from ['"]@kit\/ui['"]/.test(source)) continue;
            const { code } = await dev.transformRequest(`/.kit/Pages/${file}`);
            assert.ok(!code.includes('/.kit/Kit/index.ts'), `${file} still imports through the barrel`);
            rewritten++;
        }
        assert.ok(rewritten >= 10, `${rewritten} kit pages checked`);
    } finally {
        await dev.close();
    }
});

// The kit's mail (tests/MailRenderCheck.php writes the snapshots in mail/, and fails when they fall behind the views), opened in Chromium in light and dark
// (prefers-color-scheme, as Apple Mail applies it). Served from disk on a stand-in origin, so the lockup can be read back from a canvas; nothing leaves the machine.
const mailNames = ['reset-password', 'sign-in-again', 'long-label'];
const mailTab = async (scheme, width) => {
    const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: scheme });
    await context.route('http://mail.test/**', (route) => route.fulfill({ path: path.join(here, 'mail', path.basename(new URL(route.request().url()).pathname)) }));
    return { context, tab: await context.newPage() };
};

test('the kit mail passes axe contrast in light and dark, and dark mode paints the page dark', async () => {
    for (const scheme of ['light', 'dark']) {
        const { context, tab } = await mailTab(scheme, 640);
        try {
            for (const name of mailNames) {
                await tab.goto(`http://mail.test/${name}.html`);
                await tab.addScriptTag({ path: axeSource });
                const { violations } = await tab.evaluate(() => window.axe.run(document, { runOnly: ['color-contrast'] }));
                assert.deepEqual(violations.flatMap((v) => v.nodes.map((n) => `${n.target.join(' ')}: ${n.failureSummary}`)), [], `${name} (${scheme}) has no contrast failures`);
                const page = await tab.$eval('.inner-body', (el) => getComputedStyle(el).backgroundColor);
                assert.equal(page, scheme === 'dark' ? 'rgb(24, 24, 27)' : 'rgb(255, 255, 255)', `${name} (${scheme}): the card is ${scheme}`);
                const button = await tab.$eval('a.button', (el) => [getComputedStyle(el).color, getComputedStyle(el).backgroundColor]);
                assert.equal(button[0], 'rgb(255, 255, 255)', `${name} (${scheme}): the button label is white`);
            }
        } finally {
            await context.close();
        }
    }
});

test('no mail button label breaks inside a word, and the button fits, from 320px to 640px', async () => {
    for (const width of [320, 340, 360, 375, 400, 640]) {
        const { context, tab } = await mailTab('light', width);
        try {
            for (const name of mailNames) {
                await tab.goto(`http://mail.test/${name}.html`);
                const result = await tab.$eval('a.button', (el) => {
                    // Each character's line (its box's top): a line change between two letters is a break inside a word.
                    const text = el.firstChild;
                    const tops = [];
                    for (let i = 0; i < text.length; i++) {
                        const range = document.createRange();
                        range.setStart(text, i);
                        range.setEnd(text, i + 1);
                        const rect = range.getClientRects()[0];
                        tops.push(rect ? Math.round(rect.top) : null);
                    }
                    const midWord = [];
                    for (let i = 1; i < text.length; i++) {
                        const [a, b] = [text.data[i - 1], text.data[i]];
                        if (/\S/.test(a) && /\S/.test(b) && tops[i] !== null && tops[i - 1] !== null && tops[i] > tops[i - 1]) midWord.push(text.data.slice(0, i) + ' / ' + text.data.slice(i));
                    }
                    const r = el.getBoundingClientRect();
                    return { midWord, left: r.left, right: r.right, scroll: document.documentElement.scrollWidth, label: text.data.trim() };
                });
                assert.deepEqual(result.midWord, [], `${name} at ${width}px: "${result.label}" breaks only between words`);
                assert.ok(result.left >= 0 && result.right <= width, `${name} at ${width}px: the button fits (${Math.round(result.left)} to ${Math.round(result.right)})`);
                assert.ok(result.scroll <= width, `${name} at ${width}px: no sideways scroll (${result.scroll}px wide)`);
            }
        } finally {
            await context.close();
        }
    }
});

test('the mail lockup sits on its own opaque light plate, so its ink reads on the dark header', async () => {
    const { context, tab } = await mailTab('dark', 640);
    try {
        await tab.goto('http://mail.test/sign-in-again.html');
        await tab.waitForFunction(() => document.querySelector('.header img').complete);
        const read = await tab.evaluate(() => {
            const img = document.querySelector('.header img');
            const canvas = Object.assign(document.createElement('canvas'), { width: img.naturalWidth, height: img.naturalHeight });
            const g = canvas.getContext('2d');
            g.drawImage(img, 0, 0);
            const at = (x, y) => Array.from(g.getImageData(x, y, 1, 1).data);
            const luminance = ([r, gr, b]) => [r, gr, b].map((c) => c / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)).reduce((s, c, i) => s + c * [0.2126, 0.7152, 0.0722][i], 0);
            let darkest = 1;
            const { data } = g.getImageData(0, 0, canvas.width, canvas.height);
            for (let i = 0; i < data.length; i += 4) if (data[i + 3] === 255) darkest = Math.min(darkest, luminance([data[i], data[i + 1], data[i + 2]]));
            const header = getComputedStyle(document.body).backgroundColor;
            return { margin: [at(12, Math.floor(canvas.height / 2)), at(Math.floor(canvas.width / 2), 3)], ratio: (1 + 0.05) / (darkest + 0.05), header, shown: img.getBoundingClientRect().width };
        });
        assert.equal(read.header, 'rgb(9, 9, 11)', 'the page behind the header is dark');
        for (const pixel of read.margin) assert.deepEqual(pixel, [255, 255, 255, 255], 'the plate is opaque white');
        assert.ok(read.ratio >= 4.5, `the ink reads on the plate (${read.ratio.toFixed(2)}:1)`);
        assert.equal(Math.round(read.shown), 220, 'the plated lockup shows 220px wide');
    } finally {
        await context.close();
    }
});
