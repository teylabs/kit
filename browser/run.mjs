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
// fixed or absolute helper. A PageAside (?page-aside, &tall) spaces its sections with the page's gap, sticks while it fits the viewport and scrolls with the
// page when taller.
// A FormStatus mounted before the visit (?form-status) must show after each visit that carries a message and hide only while one is in flight.
// session-ui's guest pages (?auth=) over an Inertia <Form> stand-in: failures, a 429 and a bad reset token stay in the card (no error modal), fields reset and refocus.
// Each kit form control (?form-attrs) must put a caller's aria-describedby, aria-invalid and required on its focusable control, not a wrapper, and pass axe.
// The leave-page guard (leave.html, on the real @inertiajs/vue3) must hold Back from a dirty form (Stay keeps it, Leave goes) and not a clean one, with the
// Navigation API and with window.navigation deleted (the history fallback, leaving no entry behind), count a BulkDrop upload in flight as unsaved, and let a
// save's onSuccess visit through.
// A SwatchPicker (?swatch, light and dark) must pick by keyboard, post an empty value for its default swatch and pass axe.
// A SegmentedToggle and a PaneTabs (?segmented, light and dark) must move the selection by keyboard, keep a neutral selected surface and pass axe with contrast.
// Every kit date and time input (?date-targets, light and dark, at 1280 and 390px) must pass axe target-size: each typed segment at least 24×24, the fields h-9.
// A FormCard showing 'Saved' and the pale Alerts (?form-saved, light and dark) must pass axe with contrast, checked on the status text and the alerts' text.
// BulkDrop in each state (?bulk-drop, light and dark) must pass axe with contrast; disabled keeps its text at full contrast and carries aria-disabled.
// A ScriptForm (?script-form) must submit by Enter and its button without a browser post, keep required checks, and ignore a submit while busy.
// An InputSwitch (?switch, light and dark) must toggle by click, label and Space with aria-checked, submit like a checkbox only when named, ignore input while
// disabled with its text at full contrast, link its description and a Field's error, and pass axe with contrast.
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
// A searchable InputSelect (?select-query, &modal in the kit Modal) must never search for its chosen label: typing anywhere in it starts a fresh query; Escape, a click
// outside, a choice and focus leaving drop the query and show the label again; a clearable field has a Clear button in the tab order, a default one none.
// A FormCard over useSubmitForm (?form-submit) must submit on Enter with no navigation, disable Save while pending, show a 422's field error and summary
// with focus on the field, then Saved, and pass axe with contrast. A Workbench with :form (?workbench-form=submit|inertia, the latter a stand-in useForm with
// `save`) must save on Ctrl+S but not from a contenteditable that binds it or while pending, focus a 422's field in its collapsed inspector, show a 409's
// conflict with the edits kept, hold a dirty leave with one guard, ignore Enter in the filter, and pass axe with contrast.
// Page grounds (?tinted, light and dark, at 1280px): on the sample tinted ground a DataTable, FormCard, EmptyState and PageSection each sit on one card with a
// --border edge and a background other than the ground, none nested, the chrome on the ground, and axe passes; on the white ground (?tinted=white) no card is added.
// The settings forms (forms.html) and the MCP consent page's Approve, filled and submitted as a person would: the toast or landing page, the posted values, the
// values after a reload, an inline error for a refused value, and no error page.
// The kit's mail (mail/, from tests/MailRenderCheck.php) must pass axe contrast in light and dark, break no button label inside a word from 320 to 640px, and
// show the lockup on its opaque plate over the dark header.
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { cpSync, readFileSync, readdirSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { chromium } from 'playwright';
import { build, createServer, preview } from 'vite';

const here = import.meta.dirname;
const kit = path.join(here, '.kit');
rmSync(kit, { recursive: true, force: true });
// The shared components load from the package, as in an app: .kit/vendor/tey/kit stands in for the app's vendor/tey/kit, and vite.config.js aliases
// `@/components/Kit` to its resources/js/components/Kit (docs/kit-components.md).
const kitComponents = path.join(kit, 'vendor/tey/kit/resources/js/components/Kit');
cpSync(path.join(here, '../resources/js/components/Kit'), kitComponents, { recursive: true });
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
// The error page in each shell (error.html).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Error'), path.join(kit, 'Pages/Workspace/Error'), { recursive: true });
// The Account pages (auth.html?auth=Workspace/Account/Security).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Account'), path.join(kit, 'Pages/Workspace/Account'), { recursive: true });
// settings' General and Email sending pages (forms.html).
cpSync(path.join(here, '../resources/features/settings/pages'), path.join(kit, 'Pages'), { recursive: true });
// The Account pages: Profile, Password, Security and Delete account (forms.html).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Account'), path.join(kit, 'Pages/Workspace/Account'), { recursive: true });
// People, its modals and Delete workspace (people.html).
cpSync(path.join(here, '../resources/features/workspace-ui/pages/Workspace/Settings'), path.join(kit, 'Pages/Workspace/Settings'), { recursive: true });
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
// People's forms build on the real @inertiajs/vue3 and the real @inertiaui/modal-vue (vite.people.config.js), served on their own at the app's own addresses.
await build({ root: here, configFile: path.join(here, 'vite.people.config.js') });
const peopleServer = await preview({ root: here, configFile: path.join(here, 'vite.people.config.js'), preview: { port: 0, host: '127.0.0.1' } });
const peopleUrl = peopleServer.resolvedUrls.local[0].replace(/\/$/, '');
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
test.after(async () => {
    await browser.close();
    await new Promise((resolve) => server.httpServer.close(resolve));
    await new Promise((resolve) => authServer.httpServer.close(resolve));
    await new Promise((resolve) => peopleServer.httpServer.close(resolve));
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

test('a modal whose field mounts after the trap has fired (late content) ends with focus on that field', async () => {
    assert.equal(await openFocusModal('modal-focus=300&late=900', {
        beforeTrap: (tab) => tab.waitForSelector('[data-first-field]'),
    }), 'first');
});

test('a modal with nothing tabbable (no field, no Close) puts focus on the panel itself, inside the modal', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?modal-focus=300&nofield&noclose`);
        await tab.click('[data-open]');
        await tab.waitForFunction(() => window.trapFocused === true);
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => document.activeElement?.parentElement?.getAttribute('role')), 'dialog');
    } finally {
        await context.close();
    }
});

test('a modal closed in the trap\'s own frame, while it leaves, does not pull focus back to the viewer\'s earlier field', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?modal-focus=1000&leave&closeontrap`);
        const before = await tab.evaluate(() => window.openingListeners());
        await tab.click('[data-open]');
        await tab.waitForFunction(() => document.activeElement?.hasAttribute('data-first-field'));
        await tab.keyboard.press('Tab');
        await tab.waitForFunction(() => window.trapFocused === true);
        await tab.waitForSelector('[data-leaving]');
        await tab.waitForTimeout(150);
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-second-field')), false, 'focus stays off the leaving panel\'s field');
        assert.equal(await tab.evaluate(() => window.openingListeners()), before);
    } finally {
        await context.close();
    }
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

// ── InputSelect's search (?select-query): the chosen label is never the query; Escape, a click outside, a choice and blur drop it; a clearable one clears ─────
async function selectQuery(run, query = '') {
    const tab = await browser.newPage({ viewport: { width: 1280, height: 800 }, permissions: ['clipboard-read', 'clipboard-write'] });
    try {
        await tab.goto(`${url}?select-query${query}`);
        await tab.waitForSelector('#for');
        await run(tab, {
            input: tab.locator('#for'),
            list: tab.locator('[role="listbox"]'),
            model: async () => JSON.parse(await tab.locator('[data-test-model]').innerText()),
        });
    } finally {
        await tab.close();
    }
}
const LABEL = 'Not a member (a public booking)';

test('InputSelect: typing on a chosen label starts a fresh search, before, inside or after the label (the reported "No results for fNot a member" case)', async () => {
    await selectQuery(async (tab, { input, list }) => {
        assert.equal(await input.inputValue(), LABEL);
        await input.click();
        await input.press('Home');
        await tab.keyboard.type('f');
        assert.equal(await input.inputValue(), 'f', 'the caret before the label: the query is "f", not "fNot a member (a public booking)"');
        assert.doesNotMatch(await list.innerText(), /fNot a member/);
        assert.deepEqual(await tab.getByRole('option').allInnerTexts(), ['Frances Allen'], 'and it filters on "f" alone');
        await tab.keyboard.press('Escape');
        await input.click();
        await input.press('End');
        await tab.keyboard.press('ArrowLeft');
        await tab.keyboard.press('ArrowLeft');
        await tab.keyboard.type('gra');
        assert.equal(await input.inputValue(), 'gra', 'inside the label');
        await tab.keyboard.press('Escape');
        await input.click();
        await input.press('End');
        await tab.keyboard.type('ada');
        assert.equal(await input.inputValue(), 'ada', 'after the label');
        assert.deepEqual(await tab.getByRole('option').allInnerTexts(), ['Ada Lovelace']);
        // A real paste (Ctrl+V) before the label that repeats its first letter: the query is what was pasted, never worked out from the field's text.
        await tab.keyboard.press('Escape');
        await input.click();
        await input.press('Home');
        await tab.evaluate(() => navigator.clipboard.writeText('Nnamdi'));
        await input.press('ControlOrMeta+V');
        assert.equal(await input.inputValue(), 'Nnamdi', 'a paste before the label');
        // An IME composition (Chrome's own IME input) inside the label, overlapping its letters: it replaces the label whole, and the commit is the query.
        await tab.keyboard.press('Escape');
        await input.click();
        await input.press('End');
        for (let i = 0; i < 5; i++) await input.press('ArrowLeft');
        const ime = await tab.context().newCDPSession(tab);
        await ime.send('Input.imeSetComposition', { text: 'bo', selectionStart: 2, selectionEnd: 2 });
        await ime.send('Input.imeSetComposition', { text: 'book', selectionStart: 4, selectionEnd: 4 });
        await ime.send('Input.insertText', { text: 'booking' });
        assert.equal(await input.inputValue(), 'booking', 'an IME composition inside the label');
        assert.deepEqual(await tab.getByRole('option').allInnerTexts(), [LABEL], 'it filters on "booking" alone (with the label in the query nothing would match)');
    });
});

test('InputSelect: Escape and a click outside close the list, drop the typed text and show the chosen label again, even with focus in the list', async () => {
    await selectQuery(async (tab, { input, list, model }) => {
        await input.click();
        await tab.keyboard.type('zq');
        await list.waitFor();
        await tab.keyboard.press('Escape');
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), LABEL, 'Escape restores the label');
        assert.equal(await input.getAttribute('aria-expanded'), 'false');
        // A press on the list's empty message moves focus into the list; Escape there closes it too, and focus comes back to the field.
        await input.click();
        await tab.keyboard.type('zq');
        await list.click();
        assert.equal(await input.getAttribute('aria-expanded'), 'true');
        await tab.keyboard.press('Escape');
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), LABEL);
        assert.equal(await tab.evaluate(() => document.activeElement?.id), 'for');
        // A click outside.
        await tab.keyboard.type('zq');
        await list.waitFor();
        await tab.click('[data-outside]');
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), LABEL, 'a click outside restores the label');
        // The query is gone, not hidden: focusing and typing again starts from nothing.
        await input.click();
        await tab.keyboard.type('a');
        assert.equal(await input.inputValue(), 'a');
        assert.deepEqual(await model(), { for: 'public', room: 'north' }, 'none of it changed the value');
    });
});

test('InputSelect in the kit Modal: Escape and a click outside the list close only the list and restore the label; the modal stays open', async () => {
    await selectQuery(async (tab, { input, list }) => {
        await tab.waitForTimeout(300);
        await input.click();
        await input.press('Home');
        await tab.keyboard.type('f');
        assert.equal(await input.inputValue(), 'f');
        await tab.keyboard.press('Escape');
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), LABEL);
        await input.click();
        await tab.keyboard.type('zq');
        await list.waitFor();
        await tab.locator('dialog [data-outside]').click();
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), LABEL);
        assert.equal(await tab.evaluate(() => document.querySelector('[data-package-dialog]').open), true);
        assert.deepEqual(await tab.evaluate(() => window.kitEvents), []);
    }, '&modal');
});

test('InputSelect: Clear is opt-in: a clearable select has a Clear button in the tab order (Enter and Space clear it), a default one has none, and axe passes', async () => {
    await selectQuery(async (tab, { input, model }) => {
        await tab.addScriptTag({ path: axeSource });
        const violations = await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })));
        assert.deepEqual(violations, []);
        const clearIn = (id) => tab.locator(`#${id}`).evaluate(el => el.closest('[popover]').querySelector('[data-select-clear]') !== null);
        assert.equal(await clearIn('for'), false, 'For (a default single select) has none');
        assert.equal(await clearIn('room'), true, 'Room (clearable) has one');
        const room = tab.locator('#room');
        const clear = tab.locator('[data-select-clear]');
        assert.ok(await clear.getAttribute('aria-label'), 'named');
        // Escape on a closed default field does not clear it.
        await input.focus();
        await input.press('Escape');
        assert.deepEqual(await model(), { for: 'public', room: 'north' });
        await room.focus();
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.hasAttribute('data-select-clear')), true, 'Tab from the clearable field reaches Clear');
        await tab.keyboard.press('Enter');
        assert.deepEqual(await model(), { for: 'public', room: null }, 'Enter clears');
        assert.equal(await room.inputValue(), '');
        assert.equal(await tab.evaluate(() => document.activeElement?.id), 'room', 'and focus goes back to the field');
        assert.equal(await clear.count(), 0, 'nothing left to clear');
        await room.click();
        await tab.getByRole('option', { name: 'Garden' }).click();
        await room.focus();
        await tab.keyboard.press('Tab');
        await tab.keyboard.press('Space');
        assert.deepEqual(await model(), { for: 'public', room: null }, 'Space clears');
    });
});

test('InputSelect: a choice, choosing the current option again and focus leaving each drop the typed text; the next keystroke starts fresh', async () => {
    await selectQuery(async (tab, { input, list, model }) => {
        await input.click();
        await tab.keyboard.type('gra');
        await tab.getByRole('option', { name: 'Grace Hopper' }).click();
        assert.equal(await input.inputValue(), 'Grace Hopper', 'a choice shows its label');
        assert.deepEqual(await model(), { for: 'grace-hopper', room: 'north' });
        await input.click();
        await input.press('End');
        await tab.keyboard.type('a');
        assert.equal(await input.inputValue(), 'a', 'not "Grace Hoppera"');
        await tab.keyboard.press('Escape');
        // The current option again, through a search.
        await input.click();
        await tab.keyboard.type('hop');
        await tab.getByRole('option', { name: 'Grace Hopper' }).click();
        assert.equal(await input.inputValue(), 'Grace Hopper');
        await input.click();
        await tab.keyboard.type('k');
        assert.equal(await input.inputValue(), 'k', 'choosing the current option leaves no query');
        // Focus leaving the field (Tab on to Room).
        await tab.keyboard.press('Tab');
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), 'Grace Hopper', 'Tab away restores the label');
        await tab.locator('#room').focus();
        await input.focus();
        assert.equal(await input.inputValue(), 'Grace Hopper');
        await tab.keyboard.type('m');
        assert.equal(await input.inputValue(), 'm', 'focus back and a keystroke: a fresh query');
        await tab.locator('#room').focus();
        await list.waitFor({ state: 'detached' });
        assert.equal(await input.inputValue(), 'Grace Hopper', 'focus moving to another field with the list open restores it');
        assert.deepEqual(await model(), { for: 'grace-hopper', room: 'north' });
    });
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
            const extra = '[data-test-extra] [data-finalize-control]';
            assert.match(await tab.textContent(`${extra} [role=alert]`), /may not be greater than 500/, 'a declared extra\'s error from the page is shown inline in the slot');
            await tab.fill(`${extra} textarea`, 'Re-measured the garage.');
            await tab.click(`${extra} label[for^="finalize-certified-"]`);
            await tab.click(`${extra} button[type=submit]`);
            const sent = await tab.evaluate(() => (window.kitVisits ?? []).filter(v => v.url === '#finalize-extra'));
            assert.deepEqual(sent.map(v => [v.method, v.data]), [['post', { what_changed: 'Re-measured the garage.', certified: true }]], 'the extra rides on the one certify post, beside certified');

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

// ── BulkDrop in every state (?bulk-drop): idle, error, busy, disabled with and without a prompt; axe with contrast, light and dark ──────────────────────────
test('BulkDrop has no axe violations in any state, colour contrast included and checked on the disabled prompt and hint, light and dark', async () => {
    for (const query of ['bulk-drop', 'bulk-drop=dark']) {
        const context = await browser.newContext({ viewport: { width: 1024, height: 1100 } });
        try {
            const tab = await context.newPage();
            await tab.goto(`${url}?${query}`);
            await tab.waitForSelector('[data-state="disabled"] [data-dropzone]');
            await tab.setInputFiles('[data-state="idle"] [data-dropzone-input]', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('synthetic') });
            await tab.waitForSelector('[data-state="idle"] [role="alert"]');
            if (process.env.KIT_BULKDROP_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_BULKDROP_SCREENSHOT}-${query.includes('dark') ? 'dark' : 'light'}.png`, fullPage: true });
            const disabled = tab.locator('[data-state="disabled"] [data-dropzone]');
            assert.equal(await disabled.getAttribute('aria-disabled'), 'true');
            assert.equal(await tab.locator('[data-state="disabled"] [data-dropzone-input]').isDisabled(), true);
            assert.equal((await tab.textContent('[data-state="disabled"] [data-dropzone-prompt]')).trim(), 'Save first to add files here');
            await tab.addScriptTag({ path: axeSource });
            // axe passes the whole page first, then again with aria-disabled lifted: axe skips contrast inside an aria-disabled region, and the disabled
            // text must still read at AA, so the second run measures it.
            const axeRun = () => tab.evaluate(async () => {
                const run = await window.axe.run(document, { elementRef: true });
                const checked = (selector) => run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.matches(selector)).length;
                return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), details: run.violations.flatMap(v => v.nodes.map(n => `${n.target.join(' ')}: ${n.any.map(c => c.message).join('; ')}`)), disabled: checked('[data-state="disabled"] [data-dropzone-prompt], [data-state="disabled"] [data-dropzone-hint]'), error: checked('[data-state="idle"] [role="alert"]') };
            });
            const page = await axeRun();
            if (page.violations.length) console.log(query, JSON.stringify(page.violations, null, 1), page.details.join('\n'));
            assert.deepEqual(page.violations, []);
            await disabled.evaluate(el => el.removeAttribute('aria-disabled'));
            const result = await axeRun();
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1), result.details.join('\n'));
            assert.deepEqual(result.violations, []);
            assert.equal(result.disabled, 2, `${query}: axe checked the disabled prompt's and hint's contrast`);
            assert.equal(result.error, 1, `${query}: axe checked the error text's contrast`);
        } finally {
            await context.close();
        }
    }
});

// ── BulkDrop's limit hint (?bulk-drop): shown by default; `showLimit: false` leaves no hint element (and no aria-describedby) unless `hint` is given ─────────
test('BulkDrop shows its size limit by default, none with showLimit false, and a given hint either way', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 1300 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?bulk-drop`);
        await tab.waitForSelector('[data-state="no-limit"] [data-dropzone]');
        if (process.env.KIT_BULKDROP_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_BULKDROP_SCREENSHOT}-show-limit.png`, fullPage: true });
        assert.match((await tab.textContent('[data-state="idle"] [data-dropzone-hint]')).trim(), /^Up to .+ MiB each$/, 'the default hint is the size limit');
        assert.equal(await tab.locator('[data-state="no-limit"] [data-dropzone-hint]').count(), 0, 'no empty hint element');
        assert.equal(await tab.locator('[data-state="no-limit"] [aria-describedby]').count(), 0, 'no aria-describedby left behind');
        assert.equal((await tab.textContent('[data-state="own-hint"] [data-dropzone-hint]')).trim(), 'Square images work best');
    } finally {
        await context.close();
    }
});

// ── InputSwitch (?switch): toggling by click, label and Space, aria-checked, FormData named and unnamed, disabled, its links, axe light and dark ────────────
const switchPage = async (query = 'switch') => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 640 } });
    const tab = await context.newPage();
    await tab.goto(`${url}?${query}`);
    await tab.waitForSelector('[data-switch-form]');
    return { context, tab };
};
const switchState = (tab) => tab.evaluate(() => JSON.parse(document.querySelector('[data-values]').textContent));
const switchSubmit = async (tab) => {
    const before = await tab.textContent('[data-submitted]');
    await tab.click('[data-submit]');
    await tab.waitForFunction((before) => document.querySelector('[data-submitted]').textContent !== before, before);
    return JSON.parse(await tab.textContent('[data-submitted]'));
};

test('an InputSwitch is a role="switch" that toggles on a click and on its label, with aria-checked following', async () => {
    const { context, tab } = await switchPage();
    try {
        const control = tab.locator('#alerts');
        assert.equal(await control.getAttribute('role'), 'switch');
        assert.equal(await control.getAttribute('aria-checked'), 'false');
        assert.equal((await tab.getByRole('switch', { name: 'Mention alerts' }).count()), 1, 'named by its label');
        await control.click();
        assert.equal(await control.getAttribute('aria-checked'), 'true');
        assert.equal((await switchState(tab)).alerts, true, 'v-model follows');
        await tab.click('label[for="alerts"]');
        assert.equal(await control.getAttribute('aria-checked'), 'false');
        assert.equal((await switchState(tab)).alerts, false);
        await tab.click('label[for="compact"]');
        assert.equal(await tab.getAttribute('#compact', 'aria-checked'), 'true');
        assert.equal((await switchState(tab)).compact, true);
    } finally {
        await context.close();
    }
});

test('an InputSwitch toggles on Space and shows a focus ring when reached by Tab', async () => {
    const { context, tab } = await switchPage();
    try {
        const ring = () => tab.$eval('#alerts', el => getComputedStyle(el).boxShadow);
        const before = await ring();
        await tab.click('h1');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.id), 'alerts', 'Tab reaches the switch');
        assert.equal(await tab.$eval('#alerts', el => el.matches(':focus-visible')), true);
        assert.notEqual(await ring(), before, 'the focus ring is drawn');
        await tab.keyboard.press('Space');
        assert.equal(await tab.getAttribute('#alerts', 'aria-checked'), 'true');
        await tab.keyboard.press('Space');
        assert.equal(await tab.getAttribute('#alerts', 'aria-checked'), 'false');
        assert.equal((await switchState(tab)).alerts, false);
    } finally {
        await context.close();
    }
});

test('a named InputSwitch submits like a checkbox, and an unnamed or disabled one submits nothing', async () => {
    const { context, tab } = await switchPage();
    try {
        // Initially: alerts off, digest on (value "weekly"), compact (unnamed) off, share (required) off, the disabled ones off and on.
        await tab.click('[data-submit]');
        assert.equal(await tab.$eval('[data-switch-form]', form => form.checkValidity()), false, 'a required switch that is off blocks the submit');
        assert.equal(await tab.textContent('[data-submitted]'), '');
        await tab.click('#share');
        assert.deepEqual(await switchSubmit(tab), [['kept', 'yes'], ['digest', 'weekly'], ['share', 'on']]);
        await tab.click('#alerts');
        await tab.click('#compact');
        await tab.click('#digest');
        assert.deepEqual(await switchSubmit(tab), [['kept', 'yes'], ['alerts', 'on'], ['share', 'on']]);
        assert.deepEqual(await tab.$$eval('[data-switch-form] input', els => els.map(el => el.name)), ['kept', 'alerts', 'digest', 'share', 'frozen_off', 'frozen_on'], 'no input for the unnamed switch');
        assert.equal((await switchState(tab)).compact, true, 'the unnamed switch still holds its v-model');
    } finally {
        await context.close();
    }
});

test('a disabled InputSwitch ignores a click, its label and Space, and keeps its label and description at full opacity', async () => {
    const { context, tab } = await switchPage();
    try {
        for (const [id, checked] of [['frozen-off', 'false'], ['frozen-on', 'true']]) {
            const control = tab.locator(`#${id}`);
            assert.equal(await control.isDisabled(), true);
            await control.click({ force: true });
            await tab.click(`label[for="${id}"]`, { force: true });
            await control.evaluate(el => el.focus());
            await tab.keyboard.press('Space');
            assert.equal(await control.getAttribute('aria-checked'), checked, `${id} is unchanged`);
            const opacity = await tab.$$eval(`label[for="${id}"], #${id}-hint`, els => els.map(el => { let o = 1; for (let n = el; n; n = n.parentElement) o *= Number(getComputedStyle(n).opacity); return o; }));
            assert.deepEqual(opacity, [1, 1], `${id}: no opacity on its text`);
        }
        const state = await switchState(tab);
        assert.equal(state.frozenOff, false);
        assert.equal(state.frozenOn, true);
    } finally {
        await context.close();
    }
});

test('an InputSwitch links its description and a Field\'s error by aria-describedby, and the error marks it invalid and is announced', async () => {
    const { context, tab } = await switchPage();
    try {
        assert.equal(await tab.getAttribute('#alerts', 'aria-describedby'), 'alerts-hint');
        assert.equal((await tab.textContent('#alerts-hint')).trim(), 'Email me when a teammate mentions me.');
        assert.equal(await tab.getAttribute('#compact', 'aria-describedby'), null, 'nothing described, no attribute');
        const share = tab.locator('#share');
        const ids = (await share.getAttribute('aria-describedby')).split(' ');
        assert.ok(ids.includes('share-error'), 'the Field\'s error is linked');
        assert.equal(await share.getAttribute('aria-invalid'), 'true');
        assert.equal(await share.getAttribute('aria-required'), 'true');
        assert.equal(await tab.getAttribute('#share-error', 'role'), 'alert', 'the error is announced');
        assert.equal((await tab.textContent('#share-error')).trim(), 'Turn sharing on to invite guests.');
    } finally {
        await context.close();
    }
});

test('an InputSwitch in a locked LockedForm is read-only: aria-readonly, still in the Tab order, and the label, a click, Space and Enter leave it unchanged', async () => {
    const { context, tab } = await switchPage();
    try {
        const control = tab.locator('#held');
        assert.equal(await control.getAttribute('aria-readonly'), 'true');
        assert.equal(await control.isDisabled(), false, 'locked is read-only, not disabled');
        await control.click();
        await tab.click('label[for="held"]');
        await control.focus();
        assert.equal(await tab.evaluate(() => document.activeElement?.id), 'held', 'still focusable');
        await tab.keyboard.press('Space');
        await tab.keyboard.press('Enter');
        assert.equal(await control.getAttribute('aria-checked'), 'true');
        assert.equal((await switchState(tab)).held, true, 'v-model unchanged');
    } finally {
        await context.close();
    }
});

test('a locked InputSwitch stays unchanged after each of a click, its label, Space and Enter, checked one at a time so two toggles cannot cancel out', async () => {
    const { context, tab } = await switchPage();
    try {
        const control = tab.locator('#held');
        const unchanged = async (what) => {
            assert.equal(await control.getAttribute('aria-checked'), 'true', `${what}: aria-checked unchanged`);
            assert.equal((await switchState(tab)).held, true, `${what}: v-model unchanged`);
        };
        await control.click();
        await unchanged('a click');
        await tab.click('label[for="held"]');
        await unchanged('a click on its label');
        await control.focus();
        await tab.keyboard.press('Space');
        await unchanged('Space');
        await tab.keyboard.press('Enter');
        await unchanged('Enter');
        // The guard cancels Space and Enter themselves, so no browser's keyup activation can reach the switch, and leaves other keys alone.
        const cancelled = (key, type = 'keydown') => control.evaluate((el, [key, type]) => !el.dispatchEvent(new KeyboardEvent(type, { key, bubbles: true, cancelable: true })), [key, type]);
        assert.equal(await cancelled(' '), true, 'Space keydown is cancelled');
        assert.equal(await cancelled(' ', 'keyup'), true, 'Space keyup is cancelled');
        assert.equal(await cancelled('Enter'), true, 'Enter keydown is cancelled');
        assert.equal(await cancelled('Enter', 'keyup'), true, 'Enter keyup is cancelled');
        assert.equal(await cancelled('Tab'), false, 'Tab is not cancelled');
        assert.equal(await tab.evaluate(() => document.activeElement?.id), 'held', 'focus stays on the switch');
        await tab.keyboard.press('Tab');
        assert.notEqual(await tab.evaluate(() => document.activeElement?.id), 'held', 'Tab still leaves the switch');
    } finally {
        await context.close();
    }
});

test('InputSwitch has no axe violations, colour contrast included and checked on the disabled labels and descriptions, light and dark', async () => {
    for (const query of ['switch', 'switch=dark']) {
        const { context, tab } = await switchPage(query);
        try {
            if (process.env.KIT_SWITCH_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_SWITCH_SCREENSHOT}-${query.includes('dark') ? 'dark' : 'light'}.png`, fullPage: true });
            await tab.addScriptTag({ path: axeSource });
            const disabledText = '[for="frozen-off"], [for="frozen-on"], #frozen-off-hint, #frozen-on-hint';
            const axeRun = () => tab.evaluate(async (disabledText) => {
                const run = await window.axe.run(document, { elementRef: true });
                const checked = run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.matches(disabledText)).length;
                return { violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), details: run.violations.flatMap(v => v.nodes.map(n => `${n.target.join(' ')}: ${n.any.map(c => c.message).join('; ')}`)), checked };
            }, disabledText);
            const page = await axeRun();
            if (page.violations.length) console.log(query, JSON.stringify(page.violations, null, 1), page.details.join('\n'));
            assert.deepEqual(page.violations, []);
            // axe skips contrast on text that labels or describes a disabled control (and inside an aria-disabled region), so the second run lifts both and
            // measures the disabled switches' labels and descriptions, which must still read at AA.
            await tab.$$eval('#frozen-off, #frozen-on', els => els.forEach(el => { el.removeAttribute('disabled'); el.removeAttribute('aria-disabled'); }));
            const result = await axeRun();
            if (result.violations.length) console.log(query, JSON.stringify(result.violations, null, 1), result.details.join('\n'));
            assert.deepEqual(result.violations, []);
            assert.equal(result.checked, 4, `${query}: axe measured the disabled labels' and descriptions' contrast`);
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

// ── The viewer's clock (?clock): kitClock 12h or 24h sets Intl's hourCycle in DateTime and the time inputs; auto (or no share) leaves the locale's own ─────

const clockPage = async (query) => {
    const context = await browser.newContext({ viewport: { width: 1024, height: 700 } });
    const tab = await context.newPage();
    const errors = [];
    tab.on('pageerror', error => errors.push(error.message));
    await tab.goto(`${url}?${query}`);
    await tab.waitForSelector('[data-slot="input-time-range"]');
    const spaced = (value) => value.replace(/\s/gu, ' ').trim();
    const time = async (name) => spaced(await tab.$eval(`time[data-case="${name}"]`, el => el.textContent));
    // What a time field shows: its segments' text, literals included ("3:30 PM", "15:30").
    const field = async (index) => spaced(await tab.locator('[data-slot^="input-time"] [aria-labelledby]').nth(index).evaluate(el => [...el.querySelectorAll('[data-reka-time-field-segment]')].map(segment => segment.textContent).join('')));
    const hidden = (name) => tab.$eval(`form#clock input[name="${name}"]`, el => el.value);
    return { context, tab, errors, time, field, hidden };
};

test('Clock: kitClock 24h shows DateTime and the time inputs on a 24-hour clock in en-US, 12h on a 12-hour clock in en-GB; the submitted value is HH:MM either way', async () => {
    for (const [query, expected] of [
        ['clock=24h&lang=en-US', { time: '20:30', local: 'Oct 29, 2026, 20:30', opens: '15:30', hours: '09:00 – 17:00', dayPeriods: 0, fixed: '3:30 PM' }],
        ['clock=12h&lang=en-GB', { time: '08:30 pm', local: '29 Oct 2026, 08:30 pm', opens: '3:30 PM', hours: '9:00 AM – 5:00 PM', dayPeriods: 3, fixed: '3:30 PM' }],
    ]) {
        const { context, tab, errors, time, field, hidden } = await clockPage(query);
        try {
            assert.equal(await time('time'), expected.time, `${query}: DateTime format=time`);
            assert.equal(await time('local'), expected.local, `${query}: DateTime local`);
            assert.equal(await time('explicit'), query.includes('en-GB') ? '08:30 pm' : '8:30 PM', `${query}: an explicit clock="12h" wins over kitClock`);
            assert.equal(await field(0), expected.opens, `${query}: InputTime shows the clock`);
            assert.match(await field(1), new RegExp(`^${expected.hours.split(' – ')[0]}`), `${query}: InputTimeRange's start shows the clock`);
            const dayPeriods = tab.locator('[data-slot^="input-time"] [aria-labelledby]');
            assert.equal((await dayPeriods.nth(0).locator('[data-reka-time-field-segment="dayPeriod"]').count()) + (await dayPeriods.nth(1).locator('[data-reka-time-field-segment="dayPeriod"]').count()), expected.dayPeriods, `${query}: AM/PM segments only on a 12-hour clock`);
            assert.equal(await field(2), expected.fixed, `${query}: an explicit hour-cycle="12" on InputTime wins over kitClock`);
            assert.deepEqual([await hidden('opens'), await hidden('hours[start]'), await hidden('hours[end]')], ['15:30', '09:00', '17:00'], `${query}: the submitted values are 24-hour HH:MM whatever the display`);
        } finally {
            await context.close();
        }
        assert.deepEqual(errors, []);
    }
});

test('Clock: auto and no kitClock at all render exactly the locale\'s own clock (en-US 12-hour, en-GB 24-hour), so an app that never sets a clock sees no change; a changed share re-renders', async () => {
    for (const lang of ['en-US', 'en-GB']) {
        const read = async (query) => {
            const { context, errors, time, field } = await clockPage(query);
            try {
                return [await time('time'), await time('local'), await field(0), await field(1), errors];
            } finally {
                await context.close();
            }
        };
        const none = await read(`clock&lang=${lang}`);
        const auto = await read(`clock=auto&lang=${lang}`);
        assert.deepEqual(auto, none, `${lang}: auto is byte for byte no share`);
        assert.deepEqual(none.slice(0, 3), lang === 'en-US' ? ['8:30 PM', 'Oct 29, 2026, 8:30 PM', '3:30 PM'] : ['20:30', '29 Oct 2026, 20:30', '15:30'], `${lang}: the locale's own clock`);
        assert.deepEqual(none[4], []);
    }
    const { context, tab, errors, time, field } = await clockPage('clock=auto&lang=en-US');
    try {
        await tab.evaluate(() => window.kitClock('24h'));
        await tab.waitForFunction(() => document.querySelector('time[data-case="time"]')?.textContent === '20:30');
        assert.equal(await time('local'), 'Oct 29, 2026, 20:30');
        assert.equal(await field(0), '15:30', 'the time input follows a changed share');
        if (!(await tab.evaluate(() => Boolean(window.axe)))) await tab.addScriptTag({ path: axeSource });
        assert.deepEqual(await tab.evaluate(async () => (await window.axe.run(document)).violations.map(v => v.id)), []);
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

test('DataTable (?datatable=phone): at phone width the row actions\' visually hidden reasons and menu labels add no scroll past the footer', async () => {
    const context = await browser.newContext({ viewport: { width: 390, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?datatable=phone`);
        await tab.waitForSelector('[data-table-card]');
        assert.ok(await tab.$$eval('[data-action-reason]', els => els.length) > 0, 'the page has disabled actions with reasons');
        const { scrollHeight, end, card } = await tab.evaluate(() => {
            const footer = document.querySelector('[data-test="page-footer"]').getBoundingClientRect().bottom + window.scrollY;
            const padding = parseFloat(getComputedStyle(document.querySelector('main')).paddingBottom);
            return { scrollHeight: document.documentElement.scrollHeight, end: Math.max(window.innerHeight, Math.ceil(footer + padding)), card: getComputedStyle(document.querySelector('[data-table-card]')).position };
        });
        assert.ok(scrollHeight <= end + 1, `the document ends at the footer (scrollHeight ${scrollHeight}, footer end ${end})`);
        assert.equal(card, 'relative', 'the clipping card is the sr-only spans\' containing block');
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
// #703: a page with its own Compare / Keep my edits flow turns the kit's conflict alert off (`conflict-alert` false) and calls useWorkbench(key).keepMine(form).
const workbenchFormKeepMine = (transport) => async () => {
    await onPage(`workbench-form=${transport}&own-alert`, async (tab) => {
        await tab.click('[data-workbench-collapsed] button');
        await tab.fill('input[name="title"]', 'Mine');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 1);
        await tab.evaluate(() => window.kitSave.respond('409'));
        await tab.waitForSelector('[data-test-own-conflict]');
        await tab.waitForSelector('[data-workbench-save][data-blocked="conflict"]');
        assert.equal(await tab.locator('[data-workbench-conflict]').count(), 0, 'the page shows its own alert, so the kit alert is off');
        assert.equal(await tab.locator('[role="alert"]').count(), 1, 'one conflict alert, not two');
        assert.equal(await tab.getAttribute('[data-save-state]', 'data-save-state'), 'conflict', 'the status still says the work changed elsewhere');
        if (process.env.KIT_KEEPMINE_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_KEEPMINE_SCREENSHOT}-${transport}-${tab.viewportSize().width}.png` });
        await tab.click('[data-test-keep-mine]');
        await tab.waitForFunction(() => !document.querySelector('[data-workbench-save]').disabled);
        assert.equal(await tab.getAttribute('[data-workbench-save]', 'data-blocked'), null);
        assert.equal(await tab.getAttribute('[data-save-state]', 'data-save-state'), 'unsaved');
        assert.equal(await tab.inputValue('input[name="title"]'), 'Mine', 'keepMine keeps the edits');
        assert.equal(await tab.locator('[data-workbench-form-summary]').count(), 0, 'no errors summary left over from the conflict');
        await tab.keyboard.press('Control+s');
        await tab.waitForFunction(() => window.kitSave.calls.length === 2);
        assert.equal((await tab.evaluate(() => window.kitSave.calls[1].data)).title, 'Mine', 'the re-save sends my values');
        await tab.evaluate(() => window.kitSave.respond('ok'));
        await tab.waitForSelector('[data-save-state="saved"]');
    });
};
test('a Workbench with :form (submit): conflict-alert false leaves the page\'s own alert alone; keepMine clears the conflict and the re-save sends my edits', workbenchFormKeepMine('submit'));
test('a Workbench with :form (inertia): conflict-alert false leaves the page\'s own alert alone; keepMine clears errors.stale_write and the re-save sends my edits', workbenchFormKeepMine('inertia'));
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

// ── ScriptForm (?script-form): a form posted by script, never by the browser, that ignores a second submit while its ceremony runs ──────────────────────

test('a ScriptForm submits by Enter and its button without a browser post, checks required, and runs one ceremony at a time', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 640 } });
    try {
        const tab = await context.newPage();
        const posts = [];
        tab.on('request', request => { if (request.method() !== 'GET') posts.push(request.url()); });
        await tab.goto(`${url}?script-form`);
        const form = tab.locator('form[data-test-form]');
        await form.waitFor();
        assert.equal(await form.getAttribute('data-script-form'), '', 'marked for form guards');
        await tab.click('[data-test-submit]');
        assert.equal(await tab.textContent('[data-runs]'), '0', 'an empty required field stops the submit, as in any form');
        await tab.fill('#code', '4');
        await tab.press('#code', 'Enter');
        await tab.waitForFunction(() => document.querySelector('[data-runs]').textContent === '1');
        assert.equal(await form.getAttribute('aria-busy'), 'true');
        assert.equal(await tab.textContent('[data-event]'), 'SubmitEvent submit true', 'submit is emitted with the SubmitEvent, already prevented');
        assert.equal(await tab.textContent('[data-test-submit]'), 'Waiting…', 'the slot gets busy');
        await tab.click('[data-test-submit]');
        await tab.press('#code', 'Enter');
        assert.equal(await tab.textContent('[data-runs]'), '1', 'no second ceremony while busy');
        await tab.evaluate(() => window.kitRelease());
        await tab.waitForFunction(() => !document.querySelector('form[data-test-form]').hasAttribute('aria-busy'));
        await tab.click('[data-test-submit]');
        await tab.waitForFunction(() => document.querySelector('[data-runs]').textContent === '2');
        assert.deepEqual(posts, [], 'the browser never posts the form');
        assert.equal(tab.url(), `${url}?script-form`, 'the page stays where it is');
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

// ── RadioCardGroup in a form (?radio-form): a group without `name` submits nothing, a named group submits its value, and the unnamed radios stay one group ──
async function radioForm(check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?radio-form`);
        await tab.waitForSelector('[data-radio-form]');
        await check(tab);
    } finally {
        await context.close();
    }
}
const submitRadioForm = async (tab) => {
    await tab.click('[data-submit]');
    await tab.waitForFunction(() => document.querySelector('[data-submitted]')?.textContent.trim() !== '');
    return JSON.parse(await tab.textContent('[data-submitted]'));
};

test('RadioCardGroup without a name adds no key to its form\'s FormData, in either commit mode, even once chosen (a null name too)', async () => {
    await radioForm(async tab => {
        await tab.locator('[data-group="view"] label').nth(2).click();
        await tab.locator('[data-group="tally"] [role="radio"]').nth(2).click();
        await tab.locator('[data-group="nulled"] label').nth(1).click();
        const keys = (await submitRadioForm(tab)).map(([key]) => key);
        assert.deepEqual(keys, ['kept', 'plan'], 'only the hidden field and the named group submit');
        assert.equal(await tab.textContent('[data-view]'), 'c', 'v-model still follows the choice');
    });
});

test('RadioCardGroup with a name submits its value under that name', async () => {
    await radioForm(async tab => {
        assert.deepEqual(await submitRadioForm(tab), [['kept', 'yes'], ['plan', 'b']]);
        await tab.locator('[data-group="plan"] label').nth(2).click();
        assert.deepEqual((await submitRadioForm(tab)).find(([key]) => key === 'plan'), ['plan', 'c']);
    });
});

test('RadioCardGroup without a name keeps native grouping: one tab stop, and the arrows move the single selection', async () => {
    await radioForm(async tab => {
        const radios = tab.locator('[data-group="view"] input[type="radio"]');
        await radios.first().focus();
        await tab.keyboard.press('ArrowRight');
        assert.deepEqual(await radios.evaluateAll(els => els.map(el => el.checked)), [false, true, false], 'arrowing selects the next radio, and only it');
        assert.equal(await tab.textContent('[data-view]'), 'b');
        await tab.keyboard.press('Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.closest('[data-group]')?.dataset.group), 'tally', 'Tab leaves the group in one stop');
        await tab.keyboard.press('Shift+Tab');
        assert.equal(await tab.evaluate(() => document.activeElement.value), 'b', 'Shift+Tab returns to the checked radio');
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

// ── PageAside (?page-aside, &tall): sections a page gap apart; sticky only while it fits the viewport ─────────────────────────────────────────────────────
// Measured in pixels after scrolling, not by class names: whether it stays put is what a reader sees.

test('a PageAside spaces its sections with a gap, sticks while it fits the viewport, and scrolls with the page when taller', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const shot = process.env.KIT_PAGE_ASIDE_SCREENSHOT;
    try {
        const tab = await context.newPage();
        const measure = () => tab.evaluate(() => {
            const aside = document.querySelector('[data-slot="page-aside"]');
            const notes = document.querySelector('[data-test-feed="notes"]').getBoundingClientRect();
            const changes = document.querySelector('[data-test-feed="changes"]').getBoundingClientRect();
            return { top: Math.round(aside.getBoundingClientRect().top), height: aside.getBoundingClientRect().height, gap: Math.round(changes.top - notes.bottom), sticky: aside.dataset.sticky, name: aside.getAttribute('aria-label'), tag: aside.tagName };
        });
        const scrolled = async () => {
            await tab.evaluate(() => window.scrollTo(0, 600));
            await tab.waitForFunction(() => window.scrollY === 600);
            return measure();
        };
        await tab.goto(`${url}?page-aside`);
        await tab.waitForSelector('[data-slot="page-aside"][data-sticky="true"]');
        const short = await measure();
        assert.equal(short.tag, 'ASIDE');
        assert.equal(short.name, 'Activity', 'a named landmark');
        assert.equal(short.gap, 24, 'two stacked sections sit the page gap (gap-6) apart, never touching');
        assert.ok(short.height < 800, 'fits the viewport');
        if (shot) await tab.screenshot({ path: `${shot}-1280.png`, fullPage: false });
        const shortScrolled = await scrolled();
        assert.equal(shortScrolled.top, 120, 'short: it stays below the shell header (7.5rem) as the page scrolls');
        await tab.evaluate(() => window.kitSticky(false));
        await tab.waitForFunction(() => document.querySelector('[data-slot="page-aside"]').dataset.sticky === 'false');
        assert.equal(await tab.evaluate(() => getComputedStyle(document.querySelector('[data-slot="page-aside"]')).position), 'static', 'turning `sticky` off after mount unsticks it');
        await tab.evaluate(() => window.kitSticky(true));
        await tab.waitForFunction(() => document.querySelector('[data-slot="page-aside"]').dataset.sticky === 'true', null, { timeout: 5000 });

        await tab.goto(`${url}?page-aside&tall`);
        await tab.waitForSelector('[data-slot="page-aside"]');
        await tab.waitForFunction(() => document.querySelector('[data-slot="page-aside"]').dataset.sticky === 'false');
        const tall = await measure();
        assert.ok(tall.height > 800, 'taller than the viewport');
        assert.equal(tall.gap, 24);
        const tallScrolled = await scrolled();
        assert.equal(tallScrolled.top, tall.top - 600, 'tall: it scrolls with the page, so its end can be reached');
        assert.equal(await tab.evaluate(() => getComputedStyle(document.querySelector('[data-slot="page-aside"]')).position), 'static');

        await tab.setViewportSize({ width: 390, height: 800 });
        await tab.goto(`${url}?page-aside`);
        await tab.waitForSelector('[data-slot="page-aside"]');
        assert.equal(await tab.evaluate(() => getComputedStyle(document.querySelector('[data-slot="page-aside"]')).position), 'static', 'below md the aside stacks under the main column, in the flow');
        assert.equal(await tab.evaluate(() => document.querySelector('[data-slot="page-aside"]').dataset.sticky), 'false', 'data-sticky says what a reader sees: not sticky below md');
        assert.equal(await tab.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'no sideways scroll at phone width');
        if (shot) {
            await tab.evaluate(() => document.querySelector('[data-slot="page-aside"]').scrollIntoView());
            await tab.screenshot({ path: `${shot}-390.png`, fullPage: false });
        }
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

// ── ActionBar's leading segment on the outline path (?filled-bar, `&dark`): `primary` alone keeps it unfilled; `filled` gives it the primary fill; a disabled
// segment (filled or not) reads as disabled at rest and on hover ─────────────────────────────────────────────────────────────────────────────────────────
const leadSegments = (tab, section) => tab.$eval(`[${section}] [data-action-layout-full] [data-button-group] > div:first-child`, wrap => [...wrap.children].map(child => {
    const style = getComputedStyle(child);
    return { background: style.backgroundColor, colour: style.color, opacity: style.opacity, cursor: style.cursor };
}));

const filledBar = async (theme) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 600 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?filled-bar${theme === 'dark' ? '&dark' : ''}`);
        await tab.waitForSelector('[data-filled-disabled] [data-button-group]');
        const probe = await tab.$eval('[data-primary-probe]', el => ({ background: getComputedStyle(el).backgroundColor, colour: getComputedStyle(el).color }));
        const primary = await leadSegments(tab, 'data-primary');
        assert.notEqual(primary[0].background, probe.background, 'primary alone: the outline path stays unfilled (the default is unchanged)');
        const filled = await leadSegments(tab, 'data-filled');
        assert.equal(filled[0].background, probe.background, 'filled: the leading segment takes the primary fill');
        assert.equal(filled[0].colour, probe.colour, 'filled: its label is the primary foreground');
        assert.notEqual(filled[1].background, probe.background, 'only the leading segment is filled');
        assert.deepEqual(filled.map(s => s.opacity), ['1', '1'], 'enabled segments are not dimmed');
        const disabled = await leadSegments(tab, 'data-filled-disabled');
        assert.equal(disabled[0].background, probe.background, 'a disabled leading segment keeps its fill');
        assert.deepEqual(disabled.map(s => s.opacity), ['0.5', '0.5'], 'disabled segments, the filled one included, are dimmed');
        assert.deepEqual(disabled.map(s => s.cursor), ['not-allowed', 'not-allowed']);
        for (const index of [0, 1]) {
            await tab.locator('[data-filled-disabled] [data-action-layout-full] [data-button-group] > div:first-child > *').nth(index).hover();
            assert.equal((await leadSegments(tab, 'data-filled-disabled'))[index].opacity, '0.5', `disabled segment ${index} stays dimmed on hover`);
        }
        await tab.mouse.move(0, 0);
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/actionbar-filled${theme === 'dark' ? '-dark' : ''}.png` });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
test('ActionBar filled (light): primary alone is unfilled, filled takes the primary fill, disabled segments dim at rest and on hover', () => filledBar('light'));
test('ActionBar filled (dark): the same under the dark tokens', () => filledBar('dark'));

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

// ── A widget's Sort by control (dashboard.html?sorted): a labelled select at the right of the card's header, "Last touched" by default; choosing an order
// with the keyboard PUTs it to the widget's sort URL as a partial visit for the widget's props, and the card shows the new order; a refused choice reverts.
// At 1280 and 1600, light and dark, axe-clean, the title not truncated, nothing overflowing ──────────────────────────────────────────────────────────────────
const sortShots = process.env.KIT_SORT_SCREENSHOTS;
const sortedTitles = (tab) => tab.$$eval('[data-widget="app.ongoing"] li, [data-widget="app.ongoing"] article', els => els.map(el => el.textContent.replace(/\s+/g, ' ').replace(/\s*[A-Z][a-z]{2} \d{1,2}, \d{4}.*$/, '').trim()).filter(Boolean));
test('a dashboard widget\'s Sort by control: labelled, top right of the header, keyboard-operable, saves and reloads only the widget\'s props, axe passes', async () => {
    for (const width of [1280, 1600]) {
        for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            try {
                const tab = await context.newPage();
                const errors = [];
                tab.on('pageerror', error => errors.push(error.message));
                const sent = [];
                await tab.route(/\/@acme\/widgets\/app\.ongoing\/sort$/, async (route) => {
                    const request = route.request();
                    const body = JSON.parse(request.postData() ?? '{}');
                    sent.push({ method: request.method(), sort: body.sort, only: [...new Set((request.headers()['x-inertia-partial-data'] ?? '').split(',').filter(Boolean))].join(',') });
                    const page = await tab.evaluate(() => window.kitInitialPage);
                    const widgets = await tab.evaluate((order) => [window.kitOngoing(order), ...window.kitInitialPage.props.widgets.slice(1)], body.sort);
                    await route.fulfill({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ ...page, props: { errors: {}, widgets } }) });
                });
                await tab.goto(`${authUrl}dashboard.html?sorted${dark}`);
                await tab.waitForSelector('[data-widget="app.ongoing"] [data-collection-sort]');
                await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"]');
                const control = tab.getByRole('combobox', { name: 'Sort by' });
                assert.equal(await control.count(), 1, 'one control, named "Sort by" by its visible label');
                assert.equal(await tab.locator('[data-widget="kit.activity"] [data-collection-sort], [data-widget="kit.people"] [data-collection-sort]').count(), 0, 'a widget that declares no orders has no control');
                assert.equal((await control.inputValue()).trim(), 'Last touched', 'the default order shows');
                const geometry = await tab.evaluate(() => {
                    const card = document.querySelector('[data-widget="app.ongoing"]');
                    const header = card.querySelector('header').getBoundingClientRect();
                    const title = card.querySelector('h2');
                    const sort = card.querySelector('[data-collection-sort]').getBoundingClientRect();
                    return { headerRight: header.right, headerTop: header.top, headerBottom: header.bottom, sortRight: sort.right, sortTop: sort.top, sortBottom: sort.bottom, titleRight: title.getBoundingClientRect().right, sortLeft: sort.left, titleCut: title.scrollWidth > title.clientWidth };
                });
                assert.ok(geometry.headerRight - geometry.sortRight <= 24, 'the control sits at the header\'s right edge');
                assert.ok(geometry.sortTop >= geometry.headerTop && geometry.sortBottom <= geometry.headerBottom, 'inside the header');
                assert.ok(geometry.titleRight <= geometry.sortLeft, 'right of the title');
                assert.equal(geometry.titleCut, false, 'the title is not truncated');
                assert.deepEqual(await sortedTitles(tab), ['Roof survey', 'Lease renewal', 'Boiler check'], 'the rows in the default order');
                assert.deepEqual(await notesAxe(tab), [], 'closed');
                // Keyboard only: focus the control, open it, move to "Due soonest" and pick it.
                await control.focus();
                await tab.keyboard.press('ArrowDown');
                await tab.getByRole('listbox').waitFor();
                assert.deepEqual(await tab.getByRole('option').allInnerTexts().then(o => o.map(t => t.trim())), ['Last touched', 'Newest', 'Due soonest'], 'the declared orders, in order');
                // Open, axe's best-practice `region` rule flags the list's portal outside any landmark (InputSelect's own popper wrapper); every other rule holds.
                assert.deepEqual((await notesAxe(tab)).filter(v => !(v.id === 'region' && v.targets.every(t => t.includes('data-reka-popper-content-wrapper')))), [], 'open');
                await tab.keyboard.press('Escape');
                assert.equal(await tab.getByRole('listbox').count(), 0, 'Escape closes it with nothing chosen');
                assert.equal(sent.length, 0);
                await tab.keyboard.press('ArrowDown');
                await tab.getByRole('listbox').waitFor();
                for (let i = 0; i < 5 && (await tab.locator('[role="option"][data-highlighted]').innerText().catch(() => '')).trim() !== 'Due soonest'; i++) await tab.keyboard.press('ArrowDown');
                await tab.keyboard.press('Enter');
                await tab.waitForFunction(() => document.querySelector('[data-widget="app.ongoing"] li, [data-widget="app.ongoing"] article')?.textContent.includes('Lease renewal'));
                await tab.waitForFunction(() => !document.querySelector('[data-widget="app.ongoing"] [data-collection-sort] input')?.disabled);
                assert.deepEqual(sent, [{ method: 'PUT', sort: 'due', only: 'widgets' }], 'one PUT of the chosen order, a partial visit for the widget\'s props only');
                assert.deepEqual(await sortedTitles(tab), ['Lease renewal', 'Boiler check', 'Roof survey'], 'the card shows the new order');
                assert.equal((await control.inputValue()).trim(), 'Due soonest');
                assert.deepEqual(await tab.evaluate(() => [...document.querySelectorAll('[data-widget]')].map(e => e.dataset.widget)), ['app.ongoing', 'kit.activity', 'kit.you', 'kit.people'], 'the other cards stay');
                const overflow = await tab.evaluate(() => ({ page: document.documentElement.scrollWidth - document.documentElement.clientWidth, overflowing: [...document.querySelectorAll('[data-widget-grid] header, [data-widget-grid] header *')].filter(el => el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX === 'visible').map(el => el.tagName) }));
                assert.deepEqual(overflow, { page: 0, overflowing: [] }, 'nothing overflows');
                assert.deepEqual(await notesAxe(tab), [], 'after the change');
                assert.deepEqual(errors, []);
                if (sortShots) await tab.screenshot({ path: `${sortShots}/dash-sort-${width}-${mode}.png`, fullPage: true });
            } finally {
                await context.close();
            }
        }
    }
});

test('a dashboard widget\'s Sort by control: a choice the server refuses puts the previous order back', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        await tab.route(/\/@acme\/widgets\/app\.ongoing\/sort$/, async (route) => {
            const page = await tab.evaluate(() => window.kitInitialPage);
            await route.fulfill({ status: 200, contentType: 'application/json', headers: { 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ ...page, props: { ...page.props, errors: { sort: 'Choose one of the orders this card offers.' } } }) });
        });
        await tab.goto(`${authUrl}dashboard.html?sorted`);
        await tab.waitForSelector('[data-widget="app.ongoing"] [data-collection-sort]');
        const control = tab.getByRole('combobox', { name: 'Sort by' });
        await control.click();
        await tab.getByRole('option', { name: 'Newest' }).click();
        await tab.waitForFunction(() => !document.querySelector('[data-widget="app.ongoing"] [data-collection-sort] input')?.disabled);
        await tab.waitForFunction(() => document.querySelector('[data-widget="app.ongoing"] [data-collection-sort] input')?.value.trim() === 'Last touched');
        assert.deepEqual(await sortedTitles(tab), ['Roof survey', 'Lease renewal', 'Boiler check'], 'the rows keep the order they had');
    } finally {
        await context.close();
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
                    assert.equal(await card.locator('[data-collection-section]').getAttribute('aria-label'), 'You', 'the card\'s section is still named "You"');
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

// ── Removed in 0.17: an app that still sends a `welcome` prop (dashboard.html?legacy) gets no welcome card; the widgets render as usual ─────────────────────
test('the dashboard Page ignores an app\'s removed welcome prop: no welcome card, the widgets render, axe passes, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${authUrl}dashboard.html?legacy${dark}`);
            await tab.waitForSelector('[data-widget-grid]');
            assert.equal(await tab.locator('[data-welcome-card]').count(), 0, `${mode}: no welcome card`);
            assert.equal(await tab.locator('[data-welcome-checklist], [data-welcome-dismiss]').count(), 0, `${mode}: no checklist or Dismiss`);
            assert.deepEqual(await notesAxe(tab), [], `${mode}: axe`);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
});

// ── A widget's loading height (dashboard.html?tall): the ready body holds the declared minimum height, so the card keeps its place while its content loads ──
test('a widget with a loadingHeight holds that minimum height on its ready body; one without has none', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const read = () => tab.evaluate(() => {
            const rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
            const body = (key) => { const el = document.querySelector(`[data-widget="${key}"] [data-collection-body]`); return { min: getComputedStyle(el).minHeight, height: el.getBoundingClientRect().height, marked: el.hasAttribute('data-collection-min-height') }; };
            return { rem, activity: body('kit.activity'), people: body('kit.people') };
        });
        await tab.goto(`${authUrl}dashboard.html?tall`);
        await tab.waitForSelector('[data-widget="kit.activity"]');
        const tall = await read();
        assert.equal(tall.activity.min, `${18 * tall.rem}px`, 'the declared 18rem is the body\'s min-height');
        assert.ok(tall.activity.height >= 18 * tall.rem - 0.5, 'and the body is at least that tall');
        assert.ok(tall.activity.marked, 'the body is marked with its loading height');
        assert.ok(!tall.people.marked && ['0px', 'auto'].includes(tall.people.min), 'a widget that declares none keeps its natural height');
        await tab.goto(`${authUrl}dashboard.html`);
        await tab.waitForSelector('[data-widget="kit.activity"]');
        const plain = await read();
        assert.ok(!plain.activity.marked && ['0px', 'auto'].includes(plain.activity.min), 'without loadingHeight there is no min-height');
    } finally {
        await context.close();
    }
});

// ── A list's see-all link (dashboard.html?see-all, docs/see-all.md): "All cases →" in a footer at the card's bottom right, after the list's end and outside its
// scroll, so it stays in view while the list scrolls; a stat card's in its header with its count; one link per card (the header action the server also sends
// for an older WidgetCard is not drawn twice); Tab reaches it right after the list; axe passes, light and dark ──────────────────────────────────────────────
const seeAllShots = process.env.KIT_SEE_ALL_SCREENSHOTS;
async function seeAllDashboard(dark, check) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${authUrl}dashboard.html?see-all${dark}`);
        await tab.waitForSelector('[data-widget="app.cases"] [data-scroll-list="cases"] li');
        await tab.waitForSelector('[data-widget="kit.people"] [data-scroll-list="people"] li');
        await check(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test('a list widget\'s see-all: one "All cases" link with an arrow, in the card footer at the bottom right after the list\'s end, axe passes, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await seeAllDashboard(dark, async (tab) => {
            const card = tab.locator('[data-widget="app.cases"]');
            const link = card.getByRole('link', { name: 'All cases', exact: true });
            assert.equal(await link.count(), 1, `${mode}: one link named "All cases" in the list card (the header action with its URL is not drawn too)`);
            assert.deepEqual(await card.locator('header a').evaluateAll(els => els.map(el => [el.textContent.trim(), el.hasAttribute('data-see-all-title')])), [['Open cases', true]], `${mode}: the list card's header holds only its title link, no second "All cases"`);
            assert.equal(await tab.getByRole('link', { name: /view all|see all/i }).count(), 0, `${mode}: no "View all" or "See all" anywhere`);
            const box = await tab.evaluate(() => {
                const card = document.querySelector('[data-widget="app.cases"]');
                const footer = card.querySelector('[data-collection-footer]');
                const link = footer.querySelector('a[data-see-all]');
                const list = card.querySelector('[data-scroll-list="cases"]');
                const r = (el) => el.getBoundingClientRect();
                return {
                    last: footer === card.querySelector('[data-collection-section]').lastElementChild, text: link.textContent.trim(), href: link.getAttribute('href'), arrow: link.querySelector('svg')?.getAttribute('aria-hidden'),
                    cardRight: r(card).right, cardBottom: r(card).bottom, footerTop: r(footer).top, footerBottom: r(footer).bottom, linkRight: r(link).right, linkLeft: r(link).left, footerLeft: r(footer).left, listBottom: r(list).bottom,
                    scrolls: list.scrollHeight > list.clientHeight, inList: list.contains(link),
                };
            });
            assert.ok(box.last && Math.abs(box.footerBottom - box.cardBottom) <= 1.5, `${mode}: the footer is the card's last part, at its bottom`);
            assert.ok(box.footerTop >= box.listBottom - 0.5 && !box.inList, `${mode}: after the list's end, outside its scroll`);
            assert.ok(box.cardRight - box.linkRight <= 24 && box.linkLeft - box.footerLeft > 200, `${mode}: at the right of the footer`);
            assert.ok(box.scrolls, `${mode}: the list scrolls inside the card`);
            assert.equal(box.text, 'All cases');
            assert.equal(box.href, '/@acme/cases');
            assert.equal(box.arrow, 'true', `${mode}: the arrow is an icon hidden from assistive tech`);
            assert.deepEqual(await notesAxe(tab), [], mode);
            if (seeAllShots) await tab.screenshot({ path: `${seeAllShots}/dash-see-all-${mode}.png`, fullPage: true });
        });
    }
});

test('a list widget\'s see-all stays in view, in the same place, while the list scrolls inside the card', async () => {
    await seeAllDashboard('', async (tab) => {
        const at = () => tab.evaluate(() => {
            const link = document.querySelector('[data-widget="app.cases"] [data-collection-footer] a[data-see-all]');
            const list = document.querySelector('[data-scroll-list="cases"]');
            const r = link.getBoundingClientRect();
            const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
            return { top: Math.round(r.top), left: Math.round(r.left), hit: Boolean(hit && link.contains(hit)), inView: r.top >= 0 && r.bottom <= window.innerHeight, scrollTop: list.scrollTop, end: list.scrollHeight - list.clientHeight, page: window.scrollY };
        });
        const before = await at();
        assert.ok(before.hit && before.inView && before.scrollTop === 0 && before.end > 100, 'visible and on top before the list scrolls');
        const list = tab.locator('[data-scroll-list="cases"]');
        const listBox = await list.boundingBox();
        await tab.mouse.move(listBox.x + listBox.width / 2, listBox.y + listBox.height / 2);
        await tab.mouse.wheel(0, 400);
        await tab.waitForFunction(() => document.querySelector('[data-scroll-list="cases"]').scrollTop > 0);
        const middle = await at();
        await list.evaluate(el => { el.scrollTop = el.scrollHeight; });
        const end = await at();
        for (const [label, now] of [['part way', middle], ['at the end', end]]) {
            assert.ok(now.scrollTop > 0, `${label}: the list scrolled`);
            assert.deepEqual([now.top, now.left, now.hit, now.inView, now.page], [before.top, before.left, true, true, 0], `${label}: the link has not moved, is on top and in view, and the page did not scroll`);
        }
        assert.ok(end.scrollTop >= end.end - 1, 'the list reached its end');
    });
});

test('a stat card\'s see-all is in its header, after its title, with its count: "All cases (42)"', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await seeAllDashboard(dark, async (tab) => {
            const card = tab.locator('[data-widget="app.case-stats"]');
            assert.equal(await card.getByRole('link', { name: 'All cases (42)', exact: true }).count(), 1, `${mode}: named with its count`);
            assert.equal(await card.locator('a').count(), 1, `${mode}: one link in the stat card: its title opts out (linkTitle false)`);
            const box = await tab.evaluate(() => {
                const card = document.querySelector('[data-widget="app.case-stats"]');
                const header = card.querySelector('header');
                const link = card.querySelector('a[data-see-all]');
                const title = header.querySelector('h2').getBoundingClientRect();
                const r = link.getBoundingClientRect();
                return { inHeader: header.contains(link), placement: link.dataset.placement, footer: card.querySelectorAll('[data-collection-footer]').length, right: card.getBoundingClientRect().right - r.right, afterTitle: r.left >= title.right, sameRow: r.top < title.bottom && r.bottom > title.top };
            });
            assert.deepEqual([box.inHeader, box.placement, box.footer], [true, 'header', 0], `${mode}: in the header, no footer`);
            assert.ok(box.right <= 24 && box.afterTitle && box.sameRow, `${mode}: at the header's right, on the title's row`);
        });
    }
});

test('keyboard: a list card\'s focus order is its title link, its list and items, then its see-all, before the next card; each link shows a focus ring', async () => {
    await seeAllDashboard('', async (tab) => {
        const stops = [];
        for (let i = 0; i < 60; i++) {
            await tab.keyboard.press('Tab');
            const stop = await tab.evaluate(() => {
                const el = document.activeElement;
                if (!el || el === document.body) return null;
                return { widget: el.closest('[data-widget]')?.dataset.widget ?? null, what: el.matches('[data-scroll-list]') ? `list:${el.dataset.scrollList}` : `${el.tagName.toLowerCase()}:${el.textContent.trim()}`, ring: getComputedStyle(el).boxShadow };
            });
            if (!stop) break;
            stops.push(stop);
        }
        const names = stops.map(s => `${s.widget} ${s.what}`);
        const rows = Array.from({ length: 30 }, (_, i) => `app.cases a:Case ${101 + i}`);
        assert.deepEqual(names.slice(0, 33), ['app.cases a:Open cases', 'app.cases list:cases', ...rows, 'app.cases a:All cases'], `title, then the list and its 30 rows, then the see-all: ${names.join(' | ')}`);
        assert.notEqual(names[33]?.split(' ')[0], 'app.cases', 'the see-all is the last stop in its card');
        assert.notEqual(stops[0].ring, 'none', 'the focused title link shows a ring');
        assert.notEqual(stops[32].ring, 'none', 'the focused see-all shows a ring');
        const stat = names.indexOf('app.case-stats a:All cases (42)');
        assert.ok(stat > names.indexOf('kit.people list:people'), 'the stat card\'s header link comes after the cards before it in the aside');
        assert.equal(names.filter(n => n.startsWith('app.case-stats')).length, 1, 'the stat card\'s plain title is no stop');
        await tab.getByRole('link', { name: 'All cases', exact: true }).focus();
        await tab.keyboard.press('Shift+Tab');
        assert.equal(await tab.evaluate(() => document.activeElement?.textContent.trim()), 'Case 130', 'Shift+Tab from the see-all goes back to the list\'s last row');
    });
});

// ── A see-all card's title (docs/see-all.md): a link to the same URL by default, plain text with `linkTitle: false`; in the heading's colour (never a body
// link's, even where the host colours links), underlined on hover, with a visible focus ring. On the dashboard and on CollectionSection (?collection-section) ────────
const titleLinks = (tab) => tab.evaluate(() => [...document.querySelectorAll('[data-widget], [data-case]')].filter(card => card.querySelector('h1, h2, h3, h4, h5, h6')).map(card => {
    const heading = card.querySelector('h1, h2, h3, h4, h5, h6');
    const link = heading.querySelector('a');
    const seeAll = card.querySelector('a[data-see-all]');
    return { card: card.dataset.widget ?? card.dataset.case, title: heading.textContent.trim(), link: link ? { href: link.getAttribute('href'), name: link.textContent.trim(), title: link.hasAttribute('data-see-all-title') } : null, seeAll: seeAll?.getAttribute('href') ?? null };
}).filter(card => card.seeAll !== null || card.link !== null));
async function collectionPage(dark, check, viewport = { width: 1280, height: 900 }) {
    const context = await browser.newContext({ viewport });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?collection-section${dark}`);
        await tab.waitForSelector('[data-case="list"] [data-collection-body] li');
        await check(tab);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test('a see-all card\'s title links to the same URL as its see-all; with linkTitle false the title is plain text', async () => {
    await seeAllDashboard('', async (tab) => {
        assert.deepEqual(await titleLinks(tab), [
            { card: 'app.cases', title: 'Open cases', link: { href: '/@acme/cases', name: 'Open cases', title: true }, seeAll: '/@acme/cases' },
            { card: 'app.case-stats', title: 'Cases this week', link: null, seeAll: '/@acme/cases' },
        ], 'dashboard: the list card\'s title is a link named by the title with the see-all\'s href; the opted-out stat card\'s is plain text');
        assert.equal(await tab.getByRole('heading', { name: 'Open cases' }).getByRole('link', { name: 'Open cases', exact: true }).count(), 1, 'the link sits inside the heading, so the heading keeps its name');
        assert.equal(await tab.locator('[data-widget="kit.people"] h2 a, [data-widget="kit.activity"] h2 a').count(), 0, 'a card with no see-all keeps a plain title');
    });
    await collectionPage('', async (tab) => {
        assert.deepEqual(await titleLinks(tab), [
            { card: 'list', title: 'Ongoing cases', link: { href: '/@acme/cases?status=ongoing', name: 'Ongoing cases', title: true }, seeAll: '/@acme/cases?status=ongoing' },
            { card: 'stat', title: 'Cases this week', link: null, seeAll: '/@acme/cases?week=current' },
            { card: 'empty', title: 'Overdue cases', link: { href: '/@acme/cases?overdue=1', name: 'Overdue cases', title: true }, seeAll: '/@acme/cases?overdue=1' },
            { card: 'grid', title: 'Active projects', link: { href: '/@acme/projects?status=active', name: 'Active projects', title: true }, seeAll: '/@acme/projects?status=active' },
            { card: 'grid-empty', title: 'Archived projects', link: { href: '/@acme/projects?status=archived', name: 'Archived projects', title: true }, seeAll: '/@acme/projects?status=archived' },
        ], 'CollectionCard and bare CollectionSection: a list card, an empty card and both bare sections link their titles to the filtered index; the opted-out stat card\'s is plain text');
        assert.equal(await tab.locator('[data-case="empty-slot"] h2 a').count(), 0, 'a card with no see-all keeps a plain title');
    });
});

test('a see-all title link keeps the heading\'s colour, not a body link\'s, is underlined only on hover, and shows an unclipped focus ring, light and dark', async () => {
    // A host that colours its links (a base `a` rule, as many apps' app.css has): the title must still read as a heading.
    const hostLink = 'rgb(37, 99, 235)';
    const style = `@layer base { a { color: ${hostLink}; } }`;
    const read = (tab, selector) => tab.evaluate(([s]) => {
        const link = document.querySelector(s);
        const heading = link.closest('h1, h2, h3, h4, h5, h6');
        const body = document.querySelector('[data-body-link]');
        const [l, h] = [link.getBoundingClientRect(), heading.getBoundingClientRect()];
        return { inside: l.left >= h.left - 0.5 && l.right <= h.right + 0.5, link: getComputedStyle(link).color, heading: getComputedStyle(heading).color, body: getComputedStyle(body).color, primary: getComputedStyle(document.querySelector('[data-primary-link]')).color, underline: getComputedStyle(link).textDecorationLine, ring: getComputedStyle(link).boxShadow };
    }, [selector]);
    const check = async (tab, selector, label) => {
        // `--primary` set apart from the text colour, so the text-primary reference cannot match the heading by chance.
        await tab.addStyleTag({ content: `${style} :root { --primary: rgb(147, 51, 234); }` });
        // A plain body link and one in kit's body-link style (`text-primary`), outside any card, as references.
        await tab.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<p><a href="/x" data-body-link>body link</a> <a href="/y" class="text-primary underline" data-primary-link>primary link</a></p>'));
        await tab.mouse.move(0, 0);
        const rest = await read(tab, selector);
        assert.equal(rest.body, hostLink, `${label}: the host's link colour applies to a body link`);
        assert.equal(rest.link, rest.heading, `${label}: the title link has the heading's colour`);
        assert.notEqual(rest.link, rest.body, `${label}: not the host's body-link colour`);
        assert.notEqual(rest.link, rest.primary, `${label}: not kit's text-primary link colour`);
        assert.equal(rest.underline, 'none', `${label}: no underline at rest`);
        await tab.hover(selector);
        assert.equal((await read(tab, selector)).underline, 'underline', `${label}: underlined on hover`);
        await tab.mouse.move(0, 0);
        await tab.locator(selector).focus();
        await tab.keyboard.press('Shift+Tab');
        await tab.keyboard.press('Tab');
        const focused = await read(tab, selector);
        assert.ok(await tab.evaluate((s) => document.activeElement === document.querySelector(s), selector), `${label}: keyboard focus is on the title link`);
        assert.notEqual(focused.ring, 'none', `${label}: a visible focus ring`);
        assert.ok(focused.inside, `${label}: the link (and its inset ring) stays inside its heading, which clips what overflows when it truncates`);
        assert.equal(focused.link, focused.heading, `${label}: still the heading's colour while focused`);
    };
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await seeAllDashboard(dark, (tab) => check(tab, '[data-widget="app.cases"] h2 a[data-see-all-title]', `dashboard ${mode}`));
        await collectionPage(dark, (tab) => check(tab, '[data-case="list"] h2 a[data-see-all-title]', `CollectionSection ${mode}`));
    }
});

// ── CollectionSection's parts (?collection-section, docs/collection-section.md): segment tabs and the Sort by menu, the empty state from the descriptor or a slot,
// the scrolling body with the footer in view, the see-all in the footer or the header, axe light and dark ──────────────────────────────────────────────────────
test('CollectionSection: segment tabs are links named by the card, the current one aria-current, with counts; Sort by is keyboard-operable and visits with the order', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await collectionPage(dark, async (tab) => {
            const card = tab.locator('[data-case="list"]');
            const nav = card.getByRole('navigation', { name: 'Ongoing cases views' });
            assert.equal(await nav.count(), 1, `${mode}: the segments are a navigation named by the card`);
            assert.deepEqual(await nav.getByRole('link').evaluateAll(els => els.map(el => [el.getAttribute('href'), el.getAttribute('aria-current')])), [
                ['/@acme/cases?status=ongoing', 'page'],
                ['/@acme/cases?status=ongoing&segment=new', null],
                ['/@acme/cases?status=ongoing&segment=progress', null],
            ], `${mode}: one link per segment, to the same index filtered to it, only the current one marked`);
            for (const name of ['All (30)', 'New (4)', 'In progress (26)']) assert.equal(await nav.getByRole('link', { name, exact: true }).count(), 1, `${mode}: a segment is named with its count, "${name}"`);
            const tabs = await tab.evaluate(() => {
                const header = document.querySelector('[data-case="list"] header').getBoundingClientRect();
                const nav = document.querySelector('[data-case="list"] [data-collection-segments]').getBoundingClientRect();
                const body = document.querySelector('[data-case="list"] [data-collection-body]').getBoundingClientRect();
                const [on, off] = ['all', 'new'].map(key => getComputedStyle(document.querySelector(`[data-case="list"] [data-segment="${key}"]`)).backgroundColor);
                return { below: nav.top >= header.bottom - 0.5 && nav.bottom <= body.top + 0.5, distinct: on !== off };
            });
            assert.ok(tabs.below && tabs.distinct, `${mode}: between the header and the items, the current one drawn apart`);
            assert.equal(await card.getByRole('link', { name: 'New case', exact: true }).count(), 1, `${mode}: the header action`);
            const control = card.getByRole('combobox', { name: 'Sort by' });
            assert.equal((await control.inputValue()).trim(), 'Last touched', `${mode}: the current order shows`);
            assert.equal(await tab.locator('[data-case="stat"] [data-collection-sort], [data-case="stat"] [data-collection-segments]').count(), 0, `${mode}: a card that sends neither has no menu and no tabs`);
            assert.deepEqual(await notesAxe(tab), [], mode);
            if (dark) return;
            await control.focus();
            await tab.keyboard.press('ArrowDown');
            await tab.getByRole('listbox').waitFor();
            for (let i = 0; i < 4 && (await tab.locator('[role="option"][data-highlighted]').innerText().catch(() => '')).trim() !== 'Due soonest'; i++) await tab.keyboard.press('ArrowDown');
            await tab.keyboard.press('Enter');
            await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
            assert.deepEqual(await tab.evaluate(() => window.kitVisits), [{ url: '/@acme', method: 'get', data: { sort: 'due' } }], 'one visit to the sort URL with the chosen order');
            assert.equal((await control.inputValue()).trim(), 'Due soonest');
        });
    }
});

test('CollectionSection: an empty card shows the descriptor\'s empty state, or its #empty slot, never the items; its see-all stays', async () => {
    await collectionPage('', async (tab) => {
        const read = (key) => tab.evaluate((k) => {
            const card = document.querySelector(`[data-case="${k}"]`);
            return { state: card.dataset.state, empty: card.querySelector('[data-collection-empty]')?.innerText.replace(/\s+/g, ' ').trim() ?? null, items: card.textContent.includes('never shown'), footer: card.querySelector('[data-collection-footer] a[data-see-all]')?.textContent.trim() ?? null };
        }, key);
        assert.deepEqual(await read('empty'), { state: 'empty', empty: 'Nothing overdue Cases past their due date show up here.', items: false, footer: 'All overdue cases' }, 'the descriptor\'s title and description, no items, the see-all still in the footer');
        assert.deepEqual(await read('empty-slot'), { state: 'empty', empty: 'Flag a case to keep it here.', items: false, footer: null }, 'the #empty slot in its place');
        assert.equal(await tab.locator('[data-case="list"] [data-collection-empty]').count(), 0, 'a ready card has none');
    });
});

test('CollectionSection: with maxHeight the items scroll inside the card, a named, focusable region, and the footer\'s see-all stays in view and in place', async () => {
    await collectionPage('', async (tab) => {
        const at = () => tab.evaluate(() => {
            const card = document.querySelector('[data-case="list"]');
            const body = card.querySelector('[data-collection-body]');
            const footer = card.querySelector('[data-collection-footer]');
            const link = footer.querySelector('a[data-see-all]');
            const r = link.getBoundingClientRect();
            const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
            return { top: Math.round(r.top), right: Math.round(card.getBoundingClientRect().right - r.right), hit: Boolean(hit && link.contains(hit)), inView: r.top >= 0 && r.bottom <= window.innerHeight, scrollTop: body.scrollTop, end: body.scrollHeight - body.clientHeight, height: Math.round(body.getBoundingClientRect().height), rem: parseFloat(getComputedStyle(document.documentElement).fontSize), outside: !body.contains(link), last: footer === card.querySelector('[data-collection-section]').lastElementChild, page: window.scrollY };
        });
        const region = tab.getByRole('region', { name: 'Ongoing cases items' });
        assert.equal(await region.count(), 1, 'the scrolling body is a region named after the card');
        assert.equal(await region.getAttribute('tabindex'), '0', 'and keyboard-scrollable');
        const before = await at();
        assert.ok(before.end > 100 && before.height <= 12 * before.rem + 0.5, `the body stops at 12rem and scrolls (${before.height}px)`);
        assert.ok(before.outside && before.last && before.right <= 24 && before.hit && before.inView, 'the see-all is in the footer, the card\'s last part, at its right, outside the scroll, on top and in view');
        const box = await region.boundingBox();
        await tab.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await tab.mouse.wheel(0, 300);
        await tab.waitForFunction(() => document.querySelector('[data-case="list"] [data-collection-body]').scrollTop > 0);
        const middle = await at();
        await region.evaluate(el => { el.scrollTop = el.scrollHeight; });
        const end = await at();
        for (const [label, now] of [['part way', middle], ['at the end', end]]) {
            assert.ok(now.scrollTop > 0, `${label}: the items scrolled`);
            assert.deepEqual([now.top, now.hit, now.inView, now.page], [before.top, true, true, before.page], `${label}: the see-all has not moved and is on top and in view`);
        }
        assert.ok(end.scrollTop >= end.end - 1, 'the items reached their end');
        assert.equal(await tab.locator('[data-case="stat"] [data-collection-body]').getAttribute('role'), null, 'a card without maxHeight does not scroll and is no region');
    });
});

test('CollectionSection: the see-all is one link, "All <noun>" with an arrow: in the footer for a list, in the header with its count for a stat card; axe passes, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await collectionPage(dark, async (tab) => {
            const read = (key) => tab.evaluate((k) => {
                const card = document.querySelector(`[data-case="${k}"]`);
                const links = [...card.querySelectorAll('a[data-see-all]')];
                const link = links[0];
                const r = link.getBoundingClientRect();
                return { count: links.length, text: link.textContent.trim(), header: Boolean(link.closest('header')), footer: Boolean(link.closest('[data-collection-footer]')), arrow: link.querySelector('svg')?.getAttribute('aria-hidden'), right: Math.round(card.getBoundingClientRect().right - r.right) };
            }, key);
            const list = await read('list');
            assert.deepEqual([list.count, list.text, list.footer, list.header, list.arrow], [1, 'All ongoing cases', true, false, 'true'], `${mode}: a list card's see-all is in its footer, its arrow hidden`);
            const stat = await read('stat');
            assert.deepEqual([stat.count, stat.text, stat.header, stat.footer], [1, 'All cases (42)', true, false], `${mode}: a stat card's is in its header, with its count`);
            assert.ok(list.right <= 24 && stat.right <= 24, `${mode}: both at the card's right`);
            assert.equal(await tab.locator('[data-case="stat"] [data-collection-footer]').count(), 0, `${mode}: a header see-all leaves no footer`);
            assert.deepEqual(await notesAxe(tab), [], mode);
            if (seeAllShots) await tab.screenshot({ path: `${seeAllShots}/collection-section-${mode}.png`, fullPage: true });
        });
    }
});

test('CollectionSection, bare: no card, a grid of cards as its items, the linked title and header action above, and "All <noun>" under the grid at its right; axe passes, light and dark', async () => {
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        await collectionPage(dark, async (tab) => {
            const read = await tab.evaluate(() => {
                const style = (el) => getComputedStyle(el);
                const section = document.querySelector('[data-case="grid"]');
                const list = document.querySelector('[data-case="list"]');
                const header = section.querySelector('header');
                const grid = section.querySelector('[data-project-grid]').getBoundingClientRect();
                const cards = [...section.querySelectorAll('[data-project-card]')];
                const footer = section.querySelector('[data-collection-footer]');
                const link = footer?.querySelector('a[data-see-all]');
                const r = link.getBoundingClientRect();
                return {
                    kinds: [section.matches('[data-collection-section]') && !section.closest('[data-collection-card]'), list.matches('[data-collection-card]')],
                    noCard: style(section).borderTopWidth === '0px' && style(section).backgroundColor === 'rgba(0, 0, 0, 0)' && style(section).boxShadow === 'none',
                    listCard: style(list).borderTopWidth !== '0px',
                    rules: [style(header).borderBottomWidth, style(footer).borderTopWidth],
                    cards: cards.length,
                    cardsBordered: cards.every((card) => style(card).borderTopWidth !== '0px'),
                    firstRow: cards.filter((card) => Math.abs(card.getBoundingClientRect().top - cards[0].getBoundingClientRect().top) < 1).length,
                    below: r.top >= grid.bottom - 0.5,
                    right: Math.round(grid.right - r.right),
                    after: Boolean(section.querySelector('[data-collection-body]').compareDocumentPosition(footer) & Node.DOCUMENT_POSITION_FOLLOWING) && footer === section.lastElementChild,
                    inCard: Boolean(link.closest('[data-project-card]')),
                    arrow: link.querySelector('svg')?.getAttribute('aria-hidden'),
                    title: section.querySelector('h2 a[data-see-all-title]')?.getAttribute('href') ?? null,
                };
            });
            assert.deepEqual(read.kinds, [true, true], `${mode}: the grid is a bare CollectionSection, the list a CollectionCard`);
            assert.ok(read.noCard && read.listCard, `${mode}: no border, fill or shadow around the grid section; the list section has its card`);
            assert.deepEqual(read.rules, ['0px', '0px'], `${mode}: no rule under the header or over the footer without a card`);
            assert.ok(read.cards === 6 && read.cardsBordered && read.firstRow === 3, `${mode}: the items are a grid of six cards, three to a row at 1280px`);
            assert.ok(read.below && read.after && !read.inCard && read.right <= 1, `${mode}: the see-all sits under the grid, after it, outside every card, flush with the grid's right edge (${read.right}px)`);
            assert.equal(read.arrow, 'true', `${mode}: the arrow is hidden`);
            assert.equal(read.title, '/@acme/projects?status=active', `${mode}: the title links to the see-all's URL`);
            const section = tab.locator('[data-case="grid"]');
            assert.equal(await section.getByRole('link', { name: 'All active projects', exact: true }).count(), 1, `${mode}: one link named "All active projects"`);
            assert.equal(await section.getByRole('link', { name: 'New project', exact: true }).count(), 1, `${mode}: the header action`);
            assert.deepEqual(await notesAxe(tab), [], mode);
            if (dark) return;
            await section.locator('h2 a').focus();
            const order = [];
            for (let i = 0; i < 9; i++) {
                order.push(await tab.evaluate(() => document.activeElement.textContent.trim()));
                await tab.keyboard.press('Tab');
            }
            assert.deepEqual(order, ['Active projects', 'New project', 'Harbour', 'Lantern', 'Meadow', 'Orchard', 'Quarry', 'Willow', 'All active projects'], 'keyboard: the title, the header action, each card, then the see-all');
        });
    }
});

test('CollectionSection, bare: an empty section shows its empty state in place of the grid, and keeps its see-all under it', async () => {
    await collectionPage('', async (tab) => {
        const read = await tab.evaluate(() => {
            const section = document.querySelector('[data-case="grid-empty"]');
            return { border: getComputedStyle(section).borderTopWidth, empty: section.querySelector('[data-collection-empty]')?.innerText.replace(/\s+/g, ' ').trim() ?? null, items: section.textContent.includes('never shown'), footer: section.querySelector('[data-collection-footer] a[data-see-all]')?.textContent.trim() ?? null, last: section.querySelector('[data-collection-footer]') === section.lastElementChild };
        });
        assert.deepEqual(read, { border: '0px', empty: 'Nothing archived Projects you archive show up here.', items: false, footer: 'All archived projects', last: true });
    });
});

test('CollectionSection and CollectionCard draw one descriptor the same way: the card adds its border and padding and nothing else; axe passes, light and dark', async () => {
    // Each element of the section, with what it says and does: tag, accessible attributes and text, children in order. Classes and styles are left out (they
    // are where the card's padding and rules differ), and so is `data-case`, the page's own hook.
    const read = async (twin, dark) => {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?collection-section&twin=${twin}${dark}`);
            await tab.waitForSelector('[data-case="twin"] [data-collection-section] li, [data-case="twin"][data-collection-section] li');
            const result = await tab.evaluate(() => {
                const shape = (el) => [el.tagName, [...el.attributes].filter((a) => !['class', 'style', 'data-case'].includes(a.name)).map((a) => `${a.name}=${a.value}`).sort().join(' '), el.children.length ? [...el.children].map(shape) : el.textContent.trim()];
                const root = document.querySelector('[data-case="twin"]');
                const section = root.matches('[data-collection-section]') ? root : root.querySelector('[data-collection-section]');
                const style = (el) => getComputedStyle(el);
                const pad = (el) => [style(el).paddingLeft, style(el).paddingRight];
                return {
                    shape: JSON.stringify(shape(section)),
                    wrapper: root === section ? null : { only: root.children.length === 1, border: style(root).borderTopWidth !== '0px', fill: style(root).backgroundColor !== 'rgba(0, 0, 0, 0)', radius: style(root).borderTopLeftRadius !== '0px' },
                    look: [style(section).borderTopWidth, style(section).backgroundColor],
                    pad: [...pad(section.querySelector('header')), ...pad(section.querySelector('[data-collection-body]')), ...pad(section.querySelector('[data-collection-footer]'))],
                    rules: [style(section.querySelector('header')).borderBottomWidth, style(section.querySelector('[data-collection-footer]')).borderTopWidth],
                };
            });
            const part = tab.locator('[data-case="twin"]');
            result.title = await part.getByRole('heading', { name: 'Due this week' }).getByRole('link', { name: 'Due this week', exact: true }).getAttribute('href');
            result.seeAll = await part.getByRole('link', { name: 'All cases due this week', exact: true }).count();
            result.aria = await tab.locator('[data-collection-section]').ariaSnapshot();
            result.axe = await notesAxe(tab);
            assert.deepEqual(errors, []);
            return result;
        } finally {
            await context.close();
        }
    };
    for (const [mode, dark] of [['light', ''], ['dark', '&dark']]) {
        const bare = await read('section', dark);
        const card = await read('card', dark);
        assert.equal(card.shape, bare.shape, `${mode}: inside the card, the section has the same elements, attributes, roles, names, links and text as the bare one`);
        assert.equal(card.aria, bare.aria, `${mode}: the same accessibility tree`);
        for (const [name, one] of [['bare', bare], ['card', card]]) {
            assert.deepEqual([one.title, one.seeAll], ['/@acme/cases?due=week', 1], `${mode} ${name}: the title links to the see-all's URL, and there is one see-all`);
            assert.deepEqual(one.axe, [], `${mode} ${name}: axe`);
            assert.deepEqual(one.look, ['0px', 'rgba(0, 0, 0, 0)'], `${mode} ${name}: the section itself draws no border or fill`);
        }
        assert.equal(bare.wrapper, null, `${mode}: the bare section is the root`);
        assert.deepEqual(card.wrapper, { only: true, border: true, fill: true, radius: true }, `${mode}: the card holds the section and nothing else, with a border, a fill and rounded corners`);
        assert.deepEqual(bare.pad, Array(6).fill('0px'), `${mode}: the bare section's header, body and footer have no padding`);
        assert.deepEqual(card.pad, Array(6).fill('16px'), `${mode}: on the card they take its padding`);
        assert.deepEqual([bare.rules, card.rules], [['0px', '0px'], ['1px', '1px']], `${mode}: rules under the header and over the footer on the card only`);
    }
});

test('CollectionSection: header actions keep their descriptor: a confirmed POST asks first and visits only on confirm, a disabled one does nothing and gives its reason; a new-tab twin of the see-all stays; an empty state\'s POST is a button; an error card links nothing; an empty card never shows its items', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?collection-section&edge`);
        await tab.waitForSelector('[data-case="actions"] [data-collection-header]');
        // At rest, before any action runs (a running action is dimmed while its visit is in flight, and this page's router never finishes one).
        assert.deepEqual(await notesAxe(tab), [], 'axe');
        const card = tab.locator('[data-case="actions"]');
        const plainLink = card.getByRole('link', { name: 'Add an open case', exact: true });
        assert.deepEqual([await plainLink.getAttribute('href'), (await plainLink.innerText()).trim()], ['/@acme/cases/new', 'New case'], 'a plain GET stays a link, named by its ariaLabel');
        // The ActionBar places it (inline or behind its More menu); either way it is a button or a menu item, never a link.
        assert.equal(await card.getByRole('link', { name: 'Close all' }).count(), 0, 'a confirmed POST is not a link');
        const close = async () => {
            const inline = card.getByRole('button', { name: 'Close all', exact: true });
            if (await inline.isVisible().catch(() => false)) return inline.click();
            await card.getByRole('button', { name: /^More actions/ }).click();
            await tab.getByRole('menuitem', { name: 'Close all' }).click();
        };
        await close();
        const dialog = tab.getByRole('alertdialog', { name: 'Close all open cases?' }).or(tab.getByRole('dialog', { name: 'Close all open cases?' }));
        await dialog.waitFor();
        assert.deepEqual(await tab.evaluate(() => window.kitVisits ?? []), [], 'asking sends nothing');
        await dialog.getByRole('button', { name: 'Cancel' }).click();
        await dialog.waitFor({ state: 'hidden' });
        assert.deepEqual(await tab.evaluate(() => window.kitVisits ?? []), [], 'cancelling sends nothing');
        await close();
        await dialog.waitFor();
        await dialog.getByRole('button', { name: 'Close all', exact: true }).click();
        await tab.waitForFunction(() => (window.kitVisits ?? []).length > 0);
        assert.deepEqual((await tab.evaluate(() => window.kitVisits)).map(({ url, method }) => [url, method]), [['/@acme/cases/close', 'post']], 'confirming makes one POST');
        const exportButton = card.getByRole('button', { name: 'Export', exact: true });
        assert.deepEqual([await exportButton.getAttribute('aria-disabled'), await exportButton.getAttribute('title')], ['true', 'Exports are off on this plan'], 'a disabled action is marked so, with its reason');
        assert.equal(await card.getByRole('link', { name: 'Export' }).count(), 0, 'and is no link');
        await exportButton.click({ force: true });
        assert.equal((await tab.evaluate(() => window.kitVisits)).length, 1, 'clicking it sends nothing');
        const twin = await card.locator('a[href="/@acme/cases?status=open"]:not([data-see-all]):not([data-see-all-title])').evaluateAll((els) => els.map((el) => [el.textContent.trim(), el.getAttribute('target'), el.getAttribute('rel')]));
        assert.deepEqual(twin, [['Open in a new tab (opens in new tab)', '_blank', 'noopener noreferrer']], 'a new-tab action to the see-all\'s URL stays, opening a new tab: only a plain twin is folded into the see-all');
        const importing = tab.locator('[data-case="empty-action"]');
        assert.equal(await importing.getByRole('link', { name: 'Import cases' }).count(), 0, 'an empty state\'s POST action is no link');
        await importing.getByRole('button', { name: 'Import cases', exact: true }).click();
        await tab.waitForFunction(() => window.kitVisits.length === 2);
        assert.deepEqual((await tab.evaluate(() => window.kitVisits)).map(({ url, method }) => [url, method])[1], ['/@acme/cases/import', 'post'], 'it makes its POST');
        const failed = tab.locator('[data-case="error"]');
        assert.equal(await failed.getByRole('alert').count(), 1, 'the error card shows its error');
        assert.deepEqual([await failed.locator('h2 a').count(), await failed.locator('a[data-see-all]').count(), (await failed.locator('h2').innerText()).trim()], [0, 0, 'Due cases'], 'an error card\'s title is plain text and it has no see-all');
        assert.deepEqual([await failed.getByRole('button', { name: 'Assign' }).count(), await failed.getByRole('button', { name: 'Only mine' }).count(), await failed.locator('[data-collection-filters]').count()], [0, 0, 0], 'an error card hides what its #actions and #filters slots hold, as it hides its own actions and tabs');
        const empty = await tab.evaluate(() => {
            const card = document.querySelector('[data-case="bare-empty"]');
            return { state: card.querySelector('[data-collection-section]').dataset.state, items: card.textContent.includes('never shown'), body: card.querySelector('[data-collection-empty]') !== null, seeAll: card.querySelector('[data-collection-footer] a[data-see-all]')?.textContent.trim() ?? null };
        });
        assert.deepEqual(empty, { state: 'empty', items: false, body: true, seeAll: 'All stalled cases' }, 'an empty card with no copy and no slot still never shows its items, and keeps its see-all');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

test('SearchBox: the results end with "All results" and an arrow, kept in view while they scroll, and it visits the full results page', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 700 } });
    try {
        const tab = await context.newPage();
        await tab.route(/\/search\?q=/, route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ partial: false, groups: [{ key: 'cases', label: 'Cases', results: Array.from({ length: 30 }, (_, i) => ({ id: String(i), label: `Case ${101 + i}`, url: `/@acme/cases/${i}`, description: null, meta: null })) }] }) }));
        await tab.goto(`${url}?collection-section`);
        await tab.getByRole('combobox', { name: 'Search' }).fill('case');
        await tab.waitForSelector('[data-search-option]');
        const link = tab.getByRole('link', { name: 'All results', exact: true });
        assert.equal(await link.count(), 1);
        assert.equal(await link.getAttribute('href'), '/search?q=case');
        assert.equal(await tab.getByText('See all results').count(), 0, 'the old "See all results" label is gone');
        const pinned = () => tab.evaluate(() => {
            const box = document.querySelector('[data-search-results]');
            const anchor = document.querySelector('[data-search-popup] [data-search-all] a');
            const link = anchor.getBoundingClientRect();
            const r = document.querySelector('[data-search-popup]').getBoundingClientRect();
            const hit = document.elementFromPoint(link.left + link.width / 2, link.top + link.height / 2);
            return { scrolls: box.scrollHeight > box.clientHeight, scrollTop: box.scrollTop, inside: link.top >= r.top && link.bottom <= r.bottom + 0.5, hit: Boolean(hit?.closest('[data-search-all]')), outsideListbox: anchor.closest('[role="listbox"]') === null && !box.contains(anchor), owned: [...box.querySelectorAll('[role="group"] > *')].every((el) => el.getAttribute('role') === 'option' || el.getAttribute('aria-hidden') === 'true') };
        });
        const top = await pinned();
        assert.ok(top.scrolls && top.inside && top.hit, 'the results scroll and the link is in view at the top of them');
        assert.ok(top.outsideListbox && top.owned, 'the link is outside the listbox and its scroll; the listbox\'s groups hold only options (and their hidden labels)');
        await tab.locator('[data-search-results]').evaluate(el => { el.scrollTop = el.scrollHeight / 2; });
        const middle = await pinned();
        assert.ok(middle.scrollTop > 0 && middle.inside && middle.hit, 'and part way down');
        await link.click();
        assert.deepEqual(await tab.evaluate(() => (window.kitVisits ?? []).map(v => v.url)), ['/search?q=case'], 'one visit to the full results page');
        assert.equal(await tab.getByRole('combobox', { name: 'Search' }).inputValue(), '', 'the field is cleared');
        assert.equal(await tab.locator('[data-search-results]').isVisible(), false, 'and the results close');
    } finally {
        await context.close();
    }
});

// ── The shell's backdrop blur (shell.html?blur=…&subblur=…): no backdrop-filter, so no compositing layer, unless the token is a non-zero length ──────────
test('the shell bar and the trail bar have no backdrop-filter when --shell-bar-blur and --shell-subbar-blur are unset or zero, and blur when they are set', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const filters = async (query) => {
            await tab.goto(`${authUrl}shell.html?module=1${query}`);
            await tab.waitForSelector('[data-shell-bar]');
            await tab.waitForSelector('[data-shell-breadcrumbs]');
            return tab.evaluate(() => Object.fromEntries(['[data-shell-bar]', '[data-shell-breadcrumbs]'].map((selector) => {
                const style = getComputedStyle(document.querySelector(selector));
                return [selector, style.backdropFilter || style.webkitBackdropFilter || 'none'];
            })));
        };
        for (const query of ['', '&blur=0&subblur=0', '&blur=0px&subblur=0rem', '&blur=&subblur=', '&blur=calc(0px)&subblur=calc(1rem%20-%2016px)']) {
            assert.deepEqual(await filters(query), { '[data-shell-bar]': 'none', '[data-shell-breadcrumbs]': 'none' }, `no backdrop-filter for "${query || 'unset'}"`);
        }
        const set = await filters('&blur=12px&subblur=6px');
        assert.match(set['[data-shell-bar]'], /blur\(12px\)/, 'the bar blurs by its token');
        assert.match(set['[data-shell-breadcrumbs]'], /blur\(6px\)/, 'the trail bar blurs by its own token');
        const mixed = await filters('&blur=8px&subblur=0');
        assert.match(mixed['[data-shell-bar]'], /blur\(8px\)/);
        assert.equal(mixed['[data-shell-breadcrumbs]'], 'none', 'each bar reads its own token');
        // A theme switch that sets the token later (on the root) turns the blur on without a reload, and setting it back to zero turns it off.
        await filters('');
        await tab.evaluate(() => document.documentElement.style.setProperty('--shell-bar-blur', '10px'));
        await tab.waitForFunction(() => /blur\(10px\)/.test(getComputedStyle(document.querySelector('[data-shell-bar]')).backdropFilter));
        await tab.evaluate(() => document.documentElement.style.setProperty('--shell-bar-blur', '0'));
        await tab.waitForFunction(() => getComputedStyle(document.querySelector('[data-shell-bar]')).backdropFilter === 'none');
    } finally {
        await context.close();
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

// The error page names itself once: one h1, its title, in every shell. A guest's error outside the app shell (the PublicShell, a GuestShell with no title)
// had none, since only the app shell's <Page> draws the layout's title; inside the app shell, or a GuestShell given the title, the page draws no second.
test('the error page has exactly one h1, its title, in the public, guest and app shells', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        for (const shell of ['public', 'guest', 'guest-titled', 'app']) {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${authUrl}error.html?shell=${shell}`);
            await tab.getByRole('alert').waitFor();
            const headings = await tab.locator('h1').allTextContents();
            assert.deepEqual(headings.map(text => text.trim()), ['Page not found'], `${shell}: one h1, the error's title`);
            assert.equal(await tab.getByRole('heading', { level: 1, name: 'Page not found' }).count(), 1, `${shell}: the h1 is exposed as a level-one heading`);
            if (process.env.KIT_ERROR_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_ERROR_SCREENSHOT}-${shell}.png`, fullPage: true });
            assert.deepEqual(errors, [], shell);
            await tab.close();
        }
    } finally {
        await context.close();
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
    const { kitImports } = await import(path.join(kitComponents, 'kitImports.js'));
    const base = (await import(path.join(here, 'vite.auth.config.js'))).default;
    const barrel = path.join(kitComponents, 'index.ts');
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
        const kitFiles = requested.filter((pathname) => pathname.startsWith('/.kit/vendor/tey/kit/resources/js/components/Kit/'));
        assert.deepEqual(errors, []);
        assert.ok(!kitFiles.includes('/.kit/vendor/tey/kit/resources/js/components/Kit/index.ts'), 'the barrel is never requested');
        assert.ok(kitFiles.length > 0 && kitFiles.length < 100, `the grid requests ${kitFiles.length} kit files, not the barrel's ~216`);
        const pages = readdirSync(path.join(kit, 'Pages'), { recursive: true }).filter((file) => file.endsWith('.vue'));
        let rewritten = 0;
        for (const file of pages) {
            const source = readFileSync(path.join(kit, 'Pages', file), 'utf8');
            if (!/from ['"]@kit\/ui['"]/.test(source)) continue;
            const { code } = await dev.transformRequest(`/.kit/Pages/${file}`);
            assert.ok(!code.includes('/.kit/vendor/tey/kit/resources/js/components/Kit/index.ts'), `${file} still imports through the barrel`);
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
                // The label is white or near-black, whichever holds 4.5:1 on the fill (Brand::mailButton); in dark mode the fill holds 3:1 on the dark card.
                const lum = (rgb) => { const [r, g, b] = rgb.match(/\d+/g).slice(0, 3).map((c) => { const v = Number(c) / 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
                const ratio = (a, b) => (Math.max(lum(a), lum(b)) + 0.05) / (Math.min(lum(a), lum(b)) + 0.05);
                assert.ok(['rgb(255, 255, 255)', 'rgb(9, 9, 11)'].includes(button[0]), `${name} (${scheme}): the button label is white or near-black (${button[0]})`);
                assert.ok(ratio(button[0], button[1]) >= 4.5, `${name} (${scheme}): the button label holds 4.5:1 on its fill (${button[0]} on ${button[1]})`);
                if (scheme === 'dark') assert.ok(ratio(button[1], page) >= 3, `${name} (dark): the button fill holds 3:1 on the dark card (${button[1]} on ${page})`);
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

// ── The settings forms filled and saved as a person would (forms.html, on the real Inertia, with the shell's Toaster) ──────────────────────────────────
// Each test types into every field through the kit's own controls (the RadioCardGroup and the searchable select included), presses the button, and reads what
// came back: the toast, the page the redirect lands on, and the same page loaded again. run.mjs is the server: it keeps each form's values in `store`,
// checks a save the way the controller's validation would, and answers it with the page Laravel's redirect lands on (`errors` on it when refused). A saved value
// shows after the reload only if the form posted it. The PHP side (SettingsUi, the controllers' own validation) is not exercised here.
const formsShots = process.env.KIT_FORMS_SCREENSHOTS;
// Each Save test's recorded payload is a committed fixture (tests/fixtures/form-payloads/<name>.json) that tests/FormPayloadsCheck.php posts to the real
// route, so what the page sends is what the controller is proven to take. A payload that drifts from its fixture fails here; KIT_FORM_PAYLOADS_WRITE=1
// rewrites the fixtures instead (review the diff, then run composer test:form-payloads).
const payloadFixture = (name, payload) => {
    const file = path.join(here, `../tests/fixtures/form-payloads/${name}.json`);
    if (process.env.KIT_FORM_PAYLOADS_WRITE) writeFileSync(file, `${JSON.stringify(payload, null, 4)}\n`);
    assert.deepEqual(payload, JSON.parse(readFileSync(file, 'utf8')), `the ${name} payload matches tests/fixtures/form-payloads/${name}.json`);
};
const formsInertia = { 'Content-Type': 'application/json', 'X-Inertia': 'true', Vary: 'X-Inertia' };
async function formsPage({ form, component, save, page: render, accept, check }, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const pageErrors = [];
        tab.on('pageerror', error => pageErrors.push(error.message));
        const pageUrl = `/forms.html?form=${form}`;
        const posts = [];
        let next = { errors: {}, flash: {} };
        const answer = () => {
            const { errors, flash } = next;
            next = { errors: {}, flash: {} };
            return { component, props: { ...render(), errors }, url: pageUrl, version: null, flash, clearHistory: false, encryptHistory: false };
        };
        await tab.route(/\/forms\.html\?form=/, async (route) => {
            if (!route.request().headers()['x-inertia']) return route.continue();
            await route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(answer()) });
        });
        for (const [action, handle] of [[save, accept], [check?.url, check?.run]].filter(([at]) => at)) {
            await tab.route((u) => u.pathname === action, async (route) => {
                const body = route.request().postDataJSON() ?? {};
                posts.push({ url: action, method: route.request().method(), body });
                next = handle(body);
                // Laravel's 303 to the page, followed: Playwright does not route a redirect it fulfilled itself, so the write is answered with the page the
                // redirect lands on (as the Register test answers its invitation redirect).
                await route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(answer()) });
            });
        }
        await tab.goto(`${authUrl}forms.html?form=${form}`);
        await tab.waitForSelector('[data-form-card]');
        const toast = async (message) => {
            const shown = tab.locator('[data-sonner-toast]', { hasText: message });
            await shown.first().waitFor({ timeout: 5000 });
            return shown.count();
        };
        await run({ tab, posts, toast, reload: async () => { await tab.reload(); await tab.waitForSelector('[data-form-card]'); } });
        assert.equal(await tab.locator('dialog, iframe').count(), 0, 'no error page (Inertia\'s error modal) appeared');
        assert.deepEqual(pageErrors, []);
    } finally {
        await context.close();
    }
}
const saved = (message) => ({ errors: {}, flash: { toast: { id: `t-${Math.random()}`, type: 'success', message, from: 'save', style: 'pulse', duration: 4000 } } });

// General: the display name, then Date & time: the timezone (TimezoneSelect, searchable) and, when the app wires Registry::workspaceClockUsing (`clock`
// given here), the time format, as SettingsController renders and saves them. Without the wiring `settings.clock` is null and no time format is sent.
function generalForm({ clock = null } = {}) {
    const store = { display_name: 'Acme Workshop', timezone: 'UTC', version: 1, clock };
    return {
        store,
        form: 'General',
        component: 'Workspace/Settings/General/Page',
        save: '/@acme/settings/general',
        page: () => ({ title: 'Workspace settings', layout: { title: 'Workspace settings' }, settings: { workspace_id: 1, ...store, navigation: [] }, save: { label: 'Save', url: '/@acme/settings/general', method: 'put', key: 'save' }, intent_key: 'intent-1' }),
        accept: (body) => {
            const name = String(body.display_name ?? '').trim();
            if (name === '' || name.length > 120) return { errors: { display_name: 'The display name field is required.' }, flash: {} };
            if ('clock' in body && (store.clock === null || !['auto', '12h', '24h'].includes(body.clock))) return { errors: { clock: 'Choose a time format.' }, flash: {} };
            Object.assign(store, { display_name: name, timezone: body.timezone, version: store.version + 1, ...('clock' in body ? { clock: body.clock } : {}) });
            return saved('Workspace settings saved.');
        },
    };
}
// The Date & time section: its heading, then the labels of the fields under it, in page order.
const dateTimeSection = (tab, scope) => tab.$eval(scope, (section) => ({
    heading: section.querySelector('h2, h3')?.textContent.trim(),
    fields: [...section.querySelectorAll('[id$="-label"]')].map(el => el.textContent.trim()),
}));
async function pickTimezone(tab, zone) {
    await tab.getByRole('combobox', { name: /^Time ?zone$/ }).click();
    await tab.keyboard.type(zone.split('/').pop());
    await tab.getByRole('option', { name: zone }).first().click();
}

test('General settings: a person changes the name and timezone and presses Save; the toast shows, and the saved values come back after the redirect and a reload', async () => {
    const general = generalForm();
    await formsPage(general, async ({ tab, posts, toast, reload }) => {
        assert.deepEqual(await dateTimeSection(tab, '[data-date-time]'), { heading: 'Date & time', fields: ['Time zone'] }, 'Date & time holds Time zone only: the app wires no workspace clock');
        assert.equal(await tab.locator('input[name=clock]').count(), 0, 'no time format field without workspaceClockUsing');
        if (accountShots) await accountShot(tab, 'general-date-time-unwired');
        await tab.getByRole('textbox', { name: 'Display name', exact: true }).fill('Northwind Farms');
        await pickTimezone(tab, 'Europe/Amsterdam');
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Workspace settings saved.'), 1);
        assert.equal(posts.length, 1, 'one save');
        assert.equal(posts[0].method, 'PUT');
        assert.deepEqual({ ...posts[0].body }, { expected_version: '1', intent_key: 'intent-1', display_name: 'Northwind Farms', timezone: 'Europe/Amsterdam' });
        payloadFixture('general', posts[0].body);
        assert.deepEqual({ display_name: general.store.display_name, timezone: general.store.timezone }, { display_name: 'Northwind Farms', timezone: 'Europe/Amsterdam' });
        assert.equal(await tab.inputValue('#display-name'), 'Northwind Farms', 'the page the redirect lands on shows the saved name');
        await reload();
        assert.equal(await tab.inputValue('#display-name'), 'Northwind Farms');
        assert.equal(await tab.locator('input[name=timezone]').inputValue(), 'Europe/Amsterdam', 'the reloaded page keeps the saved timezone');
        assert.equal(await tab.locator('input[name=expected_version]').inputValue(), '2', 'the next save carries the new version');
    });
});

test('General settings, Date & time with a workspace clock wired: Time zone then Time format under the heading; a person chooses 24-hour and presses Save, the clock is posted with the rest and comes back after a reload', async () => {
    const general = generalForm({ clock: 'auto' });
    await formsPage(general, async ({ tab, posts, toast, reload }) => {
        assert.deepEqual(await dateTimeSection(tab, '[data-date-time]'), { heading: 'Date & time', fields: ['Time zone', 'Time format'] }, 'the heading, then Time zone, then Time format');
        const chosen = () => tab.$eval('[data-date-time] input[name=clock]:checked', el => el.value);
        assert.equal(await chosen(), 'auto', 'a workspace with no clock of its own shows Automatic');
        assert.deepEqual(await tab.$$eval('[data-date-time] input[name=clock]', els => els.map(el => el.value)), ['12h', '24h', 'auto']);
        await tab.locator('[data-date-time]').getByText('24-hour', { exact: true }).click();
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Workspace settings saved.'), 1);
        assert.equal(posts.length, 1, 'one save');
        assert.deepEqual({ ...posts[0].body }, { expected_version: '1', intent_key: 'intent-1', display_name: 'Acme Workshop', timezone: 'UTC', clock: '24h' });
        payloadFixture('general-clock', posts[0].body);
        assert.equal(general.store.clock, '24h');
        assert.equal(await chosen(), '24h', 'after the redirect');
        await reload();
        assert.equal(await chosen(), '24h', 'after a reload');
        if (accountShots) await accountShot(tab, 'general-date-time');
    });
});

test('General settings: an empty display name is refused with the error inline on the field, and nothing is saved', async () => {
    const general = generalForm();
    await formsPage(general, async ({ tab, posts }) => {
        await tab.getByRole('textbox', { name: 'Display name', exact: true }).fill(' ');
        await tab.getByRole('button', { name: 'Save' }).click();
        await tab.waitForSelector('#display-name-error');
        assert.equal(posts.length, 1);
        assert.match(await tab.textContent('#display-name-error'), /display name field is required/);
        assert.equal(await tab.getAttribute('#display-name', 'aria-invalid'), 'true');
        assert.equal(general.store.display_name, 'Acme Workshop', 'nothing saved');
        assert.equal(await tab.locator('[data-sonner-toast]').count(), 0, 'no success toast');
    });
});

// Email sending: the sender name, the from address (RadioCardGroups sharing `from_mode`: the domain itself, recommended, with its domain and address name inline;
// the platform's; and, under Advanced, the send. subdomain), and where replies go (with a custom address indented under its choice), as MailIdentityController
// renders and saves them; Check now asks MailDomainCheckController. The store follows WorkspaceMailIdentity: `root` is stored as `domain` with the bare domain.
const utc = (date) => date.toISOString().slice(0, 19).replace('T', ' ');
function emailForm({ domain = null, status = 'none', checkedAt = null } = {}) {
    const store = { sender_name: null, from_mode: domain ? 'domain' : 'platform', domain, from_local: 'hello', domain_status: status, checked_at: checkedAt, reply_to_mode: 'member', reply_to_address: null, version: 1 };
    // Named relative to the zone, as Resend names them: northwind.example's own records, or send.northwind.example's under `send`.
    const records = (state) => {
        const under = store.domain.startsWith('send.') ? '.send' : '';
        return [{ type: 'TXT', name: `resend._domainkey${under}`, value: 'p=MIGfMA0', priority: null, status: state }, { type: 'MX', name: `send${under}`, value: 'feedback-smtp.example.net', priority: 10, status: state }];
    };
    return {
        store,
        form: 'Email',
        component: 'Workspace/Settings/Email/Page',
        save: '/@acme/settings/email',
        page: () => ({
            title: 'Email sending',
            layout: { title: 'Email sending' },
            kitTimezone: 'America/Halifax',
            identity: { workspace_id: 1, ...store, sender_name_default: 'Acme Workshop', dns_records: store.domain ? records(store.domain_status) : [], platform_address: 'acme@mail.example.com', fixed_reply_to: 'support@example.com', effective_from: store.from_mode === 'domain' && store.domain_status === 'verified' ? `${store.from_local}@${store.domain}` : 'acme@mail.example.com' },
            save: { label: 'Save', url: '/@acme/settings/email', method: 'put', key: 'save' },
            check: { label: 'Check now', url: '/@acme/settings/email/domain-checks', method: 'post' },
        }),
        accept: (body) => {
            const errors = {};
            if (!['platform', 'domain', 'root'].includes(body.from_mode)) errors.from_mode = 'Choose where mail comes from.';
            if (body.from_mode !== 'platform' && !/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i.test(String(body.domain ?? ''))) errors.domain = 'Enter a domain like example.com.';
            if (body.reply_to_mode === 'custom' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(body.reply_to_address ?? ''))) errors.reply_to_address = 'Enter a valid email address.';
            if (Object.keys(errors).length) return { errors, flash: {} };
            const domain = { domain: `send.${String(body.domain).replace(/^send\./, '')}`, root: body.domain, platform: store.domain }[body.from_mode];
            Object.assign(store, { sender_name: body.sender_name || null, from_mode: body.from_mode === 'platform' ? 'platform' : 'domain', domain, from_local: body.from_local ?? store.from_local, domain_status: domain === store.domain ? store.domain_status : 'pending', reply_to_mode: body.reply_to_mode, reply_to_address: body.reply_to_mode === 'custom' ? body.reply_to_address : null, version: store.version + 1 });
            return saved('Email sending saved.');
        },
        check: {
            url: '/@acme/settings/email/domain-checks',
            run: () => {
                Object.assign(store, { domain_status: 'verified', checked_at: utc(new Date()) });
                return { errors: {}, flash: { toast: { id: 'check-1', type: 'success', message: 'Your domain is verified. Mail now goes from it.', style: 'plain', duration: 4000 } } };
            },
        },
    };
}
const checkedRadio = (tab, name) => tab.locator(`input[type=radio][name=${name}]:checked`).inputValue();
const fromLineText = async (tab) => (await tab.textContent('[data-from-line]')).trim();
const currentText = async (tab) => (await tab.textContent('[data-current-sender]')).replace(/\s+/g, ' ').trim();

test('Email sending: a person picks Your own domain (recommended), fills the domain inline under it, sends replies to another address and presses Save; the domain itself is posted as root and every value comes back', async () => {
    const email = emailForm();
    await formsPage(email, async ({ tab, posts, toast, reload }) => {
        await tab.getByRole('textbox', { name: 'Sender name', exact: true }).fill('Northwind Support');
        await tab.getByRole('radiogroup', { name: 'From address' }).getByText('Your own domain (recommended)').click();
        assert.equal(await tab.locator('[data-domain-fields] #domain').count(), 1, 'the domain field shows inline under the chosen radio');
        await tab.getByRole('textbox', { name: 'Domain', exact: true }).fill('northwind.example');
        await tab.getByRole('textbox', { name: 'Address name', exact: true }).fill('news');
        await tab.getByRole('radiogroup', { name: 'Replies go to' }).getByText('Another address').click();
        await tab.getByRole('textbox', { name: 'Reply-to address', exact: true }).fill('help@northwind.example');
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Email sending saved.'), 1);
        assert.equal(posts.length, 1, 'one save');
        assert.equal(posts[0].method, 'PUT');
        assert.deepEqual({ ...posts[0].body }, { expected_version: '1', sender_name: 'Northwind Support', from_mode: 'root', domain: 'northwind.example', from_local: 'news', reply_to_mode: 'custom', reply_to_address: 'help@northwind.example' });
        payloadFixture('email-root', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await tab.inputValue('#sender-name'), 'Northwind Support', when);
            assert.equal(await checkedRadio(tab, 'from_mode'), 'root', `${when}: a stored domain without send. reads back as the domain itself`);
            assert.equal(await tab.inputValue('#domain'), 'northwind.example', when);
            assert.equal(await tab.inputValue('#from-local'), 'news', when);
            assert.equal(await checkedRadio(tab, 'reply_to_mode'), 'custom', when);
            assert.equal(await tab.inputValue('#reply-to-address'), 'help@northwind.example', when);
            assert.equal(await tab.textContent('#domain-heading'), 'northwind.example', when);
            assert.equal((await tab.textContent('[data-domain-status]')).trim(), 'Waiting for DNS', when);
            assert.equal(await currentText(tab), 'Until northwind.example is verified, mail goes from Northwind Support <acme@mail.example.com>', `${when}: the server's effective_from`);
            if (when === 'after the redirect') await reload();
        }
        if (formsShots) await tab.screenshot({ path: `${formsShots}/email-saved-1280.png`, fullPage: true });
    });
});

test('Email sending, Mail will show as: the From and Replies-to lines follow the form as it is typed, while the Right now line keeps the server\'s effective_from', async () => {
    const email = emailForm();
    await formsPage(email, async ({ tab, posts }) => {
        assert.equal(await fromLineText(tab), 'Acme Workshop <acme@mail.example.com>', 'at first the platform address with the workspace name');
        assert.equal(await currentText(tab), 'Right now, mail goes from Acme Workshop <acme@mail.example.com>');
        await tab.getByRole('textbox', { name: 'Sender name', exact: true }).fill('Northwind Support');
        assert.equal(await fromLineText(tab), 'Northwind Support <acme@mail.example.com>', 'the sender name as it is typed');
        await tab.getByRole('radiogroup', { name: 'From address' }).getByText('Your own domain (recommended)').click();
        await tab.getByRole('textbox', { name: 'Domain', exact: true }).fill('Northwind.Example');
        await tab.getByRole('textbox', { name: 'Address name', exact: true }).fill('News');
        assert.equal(await fromLineText(tab), 'Northwind Support <news@northwind.example>', 'the domain itself, lowercased, with no send.');
        assert.match(await tab.textContent('#from-mode'), /news@northwind\.example/, 'the recommended choice names the address');
        await tab.locator('[data-from-advanced] summary').click();
        await tab.getByRole('radiogroup', { name: 'A send. subdomain of your domain' }).getByText('A send. subdomain of your domain').click();
        assert.equal(await fromLineText(tab), 'Northwind Support <news@send.northwind.example>', 'the send. subdomain once chosen');
        assert.equal(await tab.inputValue('#domain'), 'Northwind.Example', 'the typed domain moves with the choice');
        assert.match(await tab.textContent('[data-replies-line]'), /Replies go to the member who sent it/);
        await tab.getByRole('radiogroup', { name: 'Replies go to' }).getByText('The fixed address', { exact: true }).click();
        assert.match(await tab.textContent('[data-replies-line]'), /Replies go to support@example\.com/);
        await tab.getByRole('radiogroup', { name: 'Replies go to' }).getByText('Another address').click();
        assert.equal(await tab.locator('[data-custom-reply] #reply-to-address').count(), 1, 'the custom reply-to field is indented under its choice');
        await tab.getByRole('textbox', { name: 'Reply-to address', exact: true }).fill('desk@northwind.example');
        assert.match(await tab.textContent('[data-replies-line]'), /Replies go to desk@northwind\.example/);
        assert.equal(await currentText(tab), 'Right now, mail goes from Acme Workshop <acme@mail.example.com>', 'the Right now line never follows the form');
        assert.equal(posts.length, 0, 'nothing is posted while typing');
    });
});

test('Email sending, Advanced: the send. subdomain is under a closed Advanced disclosure; an existing send. setup opens on it, keeps its choice and posts domain', async () => {
    const fresh = emailForm();
    await formsPage(fresh, async ({ tab }) => {
        assert.equal(await tab.locator('[data-from-advanced]').evaluate((el) => el.open), false, 'Advanced starts closed');
        assert.equal(await tab.getByText('A send. subdomain of your domain', { exact: true }).first().isVisible(), false, 'the subdomain choice is hidden under it');
        await tab.locator('[data-from-advanced] summary').click();
        assert.equal(await tab.getByRole('radiogroup', { name: 'A send. subdomain of your domain' }).isVisible(), true, 'and shows once opened');
    });
    const existing = emailForm({ domain: 'send.northwind.example', status: 'pending' });
    await formsPage(existing, async ({ tab, posts, toast }) => {
        assert.equal(await checkedRadio(tab, 'from_mode'), 'domain', 'a send. row stays on the subdomain choice');
        assert.equal(await tab.locator('[data-from-advanced]').evaluate((el) => el.open), true, 'Advanced is open on it');
        assert.equal(await tab.inputValue('#domain'), 'northwind.example');
        assert.equal(await currentText(tab), 'Until send.northwind.example is verified, mail goes from Acme Workshop <acme@mail.example.com>');
        await tab.getByRole('textbox', { name: 'Address name', exact: true }).fill('news');
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Email sending saved.'), 1);
        assert.equal(posts[0].body.from_mode, 'domain', 'saving without choosing again keeps the subdomain');
        assert.equal(existing.store.domain, 'send.northwind.example');
    });
});

test('Email sending: Advanced, the send. subdomain choice posts domain, as recorded for the payload check', async () => {
    const email = emailForm();
    await formsPage(email, async ({ tab, posts, toast }) => {
        await tab.getByRole('textbox', { name: 'Sender name', exact: true }).fill('Northwind Support');
        await tab.locator('[data-from-advanced] summary').click();
        await tab.getByRole('radiogroup', { name: 'A send. subdomain of your domain' }).getByText('A send. subdomain of your domain').click();
        assert.equal(await tab.locator('[data-from-advanced] [data-domain-fields] #domain').count(), 1, 'the domain field shows inline under the subdomain choice');
        await tab.getByRole('textbox', { name: 'Domain', exact: true }).fill('northwind.example');
        await tab.getByRole('textbox', { name: 'Address name', exact: true }).fill('news');
        await tab.getByRole('radiogroup', { name: 'Replies go to' }).getByText('Another address').click();
        await tab.getByRole('textbox', { name: 'Reply-to address', exact: true }).fill('help@northwind.example');
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Email sending saved.'), 1);
        payloadFixture('email', posts[0].body);
        assert.equal(await tab.textContent('#domain-heading'), 'send.northwind.example');
    });
});

test('Email sending: switching back to the Platform address posts it and hides the domain fields once saved', async () => {
    const email = emailForm({ domain: 'send.northwind.example', status: 'pending' });
    await formsPage(email, async ({ tab, posts, toast, reload }) => {
        assert.equal(await checkedRadio(tab, 'from_mode'), 'domain');
        await tab.getByRole('radiogroup', { name: 'Platform address' }).getByText('Platform address').click();
        assert.equal(await tab.locator('#domain').count(), 0, 'the domain fields go with the choice');
        await tab.getByRole('button', { name: 'Save' }).click();
        assert.equal(await toast('Email sending saved.'), 1);
        assert.equal(posts[0].body.from_mode, 'platform');
        payloadFixture('email-platform', posts[0].body);
        await reload();
        assert.equal(await checkedRadio(tab, 'from_mode'), 'platform');
        assert.equal(await tab.locator('#domain').count(), 0);
    });
});

test('Email sending: a domain that is not one is refused with the error inline on the Domain field, the choice kept and nothing saved', async () => {
    const email = emailForm();
    await formsPage(email, async ({ tab, posts }) => {
        await tab.getByRole('radiogroup', { name: 'From address' }).getByText('Your own domain (recommended)').click();
        await tab.getByRole('textbox', { name: 'Domain', exact: true }).fill('not a domain');
        await tab.getByRole('button', { name: 'Save' }).click();
        await tab.waitForSelector('#domain-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#domain-error'), 'Enter a domain like example.com.');
        assert.equal(await tab.getAttribute('#domain', 'aria-invalid'), 'true');
        assert.equal(await checkedRadio(tab, 'from_mode'), 'root', 'the chosen mode is kept with the error');
        assert.equal(email.store.from_mode, 'platform', 'nothing saved');
        assert.equal(await tab.locator('[data-sonner-toast]').count(), 0, 'no success toast');
    });
});

test('Email sending, DNS records: a list, each with what it is for, its status, a Name with the full name in its title and a copy button, and a Value with a copy button; Last checked is in the viewer\'s timezone', async () => {
    const checked = new Date(Date.now() - 60_000);
    const email = emailForm({ domain: 'send.northwind.example', status: 'pending', checkedAt: utc(checked) });
    await formsPage(email, async ({ tab }) => {
        await tab.evaluate(() => {
            window.copied = [];
            Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async (text) => { window.copied.push(text); } } });
        });
        assert.equal(await tab.locator('[data-domain] table').count(), 0, 'not a table');
        const items = tab.locator('[data-dns-records] > li[data-dns-record]');
        assert.equal(await items.count(), 2);
        assert.deepEqual(await tab.locator('[data-record-kind]').evaluateAll((els) => els.map((el) => el.textContent.replace(/\s+/g, ' ').trim())), ['DKIM TXT', 'MX, priority 10']);
        assert.deepEqual(await tab.locator('[data-record-status]').allTextContents(), ['Waiting for DNS', 'Waiting for DNS']);
        assert.deepEqual(await tab.locator('[data-record-name]').allTextContents(), ['resend._domainkey.send', 'send.send']);
        assert.deepEqual(await tab.locator('[data-record-name]').evaluateAll((els) => els.map((el) => el.title)), ['resend._domainkey.send.northwind.example', 'send.send.northwind.example'], 'the full name is in the title');
        await tab.getByRole('button', { name: 'Copy DKIM name' }).click();
        await tab.getByRole('button', { name: 'Copy MX value' }).click();
        await tab.locator('[data-copy-button][data-state=copied]').nth(1).waitFor();
        assert.deepEqual(await tab.evaluate(() => window.copied), ['resend._domainkey.send', 'feedback-smtp.example.net'], 'each button copies its own row');
        assert.equal(await tab.locator('[data-dns-record]').first().getByRole('button', { name: 'Copy DKIM value' }).count(), 1, 'the Value row has its own copy button');
        const clock = new Intl.DateTimeFormat('en-US', { timeZone: 'America/Halifax', hour: 'numeric', minute: '2-digit' }).format(checked).replace(/\s/g, ' ');
        const shown = (await tab.textContent('[data-last-checked]')).replace(/\s/g, ' ');
        assert.match(shown, new RegExp(`^Last checked (Today|Yesterday) ${clock.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), `Last checked in America/Halifax, not raw UTC: ${shown}`);
        assert.equal(await tab.getByRole('button', { name: 'Check now' }).count(), 1);
        if (formsShots) for (const width of [1280, 390]) {
            await tab.setViewportSize({ width, height: 900 });
            assert.equal(await tab.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, `no sideways scroll at ${width}px`);
            await tab.screenshot({ path: `${formsShots}/email-dns-${width}.png`, fullPage: true });
        }
    });
});

test('Email sending, Check now: pressing it on a pending domain posts the check, toasts the result, and the page shows the domain verified, also after a reload', async () => {
    const email = emailForm({ domain: 'send.northwind.example', status: 'pending' });
    await formsPage(email, async ({ tab, posts, toast, reload }) => {
        assert.equal((await tab.textContent('[data-domain-status]')).trim(), 'Waiting for DNS');
        await tab.getByRole('button', { name: 'Check now' }).click();
        assert.equal(await toast('Your domain is verified. Mail now goes from it.'), 1);
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [['/@acme/settings/email/domain-checks', 'POST']]);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal((await tab.textContent('[data-domain-status]')).trim(), 'Verified', when);
            assert.equal(await currentText(tab), 'Right now, mail goes from Acme Workshop <hello@send.northwind.example>', `${when}: the server's effective_from`);
            assert.match(await tab.textContent('[data-last-checked]'), /^Last checked Today /, when);
            assert.equal(await tab.getByRole('button', { name: 'Check now' }).count(), 0, `${when}: no Check now once verified`);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('Email sending, Check now: a provider refusal (422 from verify or get) shows its message by the button, with no success toast, and the domain stays waiting', async () => {
    const email = emailForm({ domain: 'send.northwind.example', status: 'pending' });
    const refusal = 'The mail provider could not check this domain. The provider said: validation_error: expected object, received array';
    email.check.run = () => ({ errors: { domain: refusal }, flash: {} });
    await formsPage(email, async ({ tab, posts }) => {
        await tab.getByRole('button', { name: 'Check now' }).click();
        await tab.locator('[data-domain-check-error]', { hasText: 'expected object' }).waitFor();
        assert.equal(posts.length, 1);
        assert.equal((await tab.textContent('[data-domain-check-error]')).trim(), refusal);
        assert.equal(await tab.locator('[data-domain] [role=alert]', { hasText: 'expected object' }).count(), 1, 'the refusal is announced inside the domain section');
        assert.equal(await tab.locator('[data-sonner-toast]').count(), 0, 'no success toast');
        assert.equal((await tab.textContent('[data-domain-status]')).trim(), 'Waiting for DNS', 'the status is not claimed verified');
        if (formsShots) for (const width of [1280, 1920]) {
            await tab.setViewportSize({ width, height: 900 });
            await tab.screenshot({ path: `${formsShots}/email-check-error-${width}.png`, fullPage: true });
        }
    });
});

// The MCP consent page's Approve (auth.html?auth=Mcp/Authorize): a plain form post that ends in a redirect to the assistant. Passport's approve is the server:
// it answers the post with that redirect, and the browser lands on the assistant's address. A workspace it refuses is a 403 by design (BindConsentWorkspace),
// so the invalid case here is the one the page owns: no workspace chosen, no Approve.
async function consentPage(query, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const pageErrors = [];
        tab.on('pageerror', error => pageErrors.push(error.message));
        const posts = [];
        await tab.route(/\/oauth\/authorize$/, async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            posts.push(new URLSearchParams(route.request().postData() ?? ''));
            // Passport's 302 to the assistant, followed: Playwright does not route a redirect it fulfilled itself, so the post is answered with the page it lands on.
            await route.fulfill({ status: 200, contentType: 'text/html', body: '<p data-landed>Connected</p>' });
        });
        await tab.goto(`${authUrl}auth.html?auth=Mcp/Authorize${query}`);
        await tab.waitForSelector('[data-test="consent-approve"]');
        await run({ tab, posts });
        assert.deepEqual(pageErrors, []);
    } finally {
        await context.close();
    }
}

test('MCP consent: a person picks the second workspace, ticks the opt-in grant and presses Approve; the post carries both as a full-page post and the browser follows its answer', async () => {
    await consentPage('&two', async ({ tab, posts }) => {
        await tab.getByRole('combobox', { name: 'Workspace' }).click();
        await tab.getByRole('option', { name: 'Globex Labs' }).click();
        await tab.getByLabel('Files: read uploaded files').check();
        await Promise.all([tab.waitForURL(/\/oauth\/authorize$/), tab.getByRole('button', { name: 'Approve' }).click()]);
        assert.equal(posts.length, 1, 'one approval');
        assert.deepEqual([...posts[0]], [['_token', 'c'], ['auth_token', 't'], ['workspace', '2'], ['grants[]', 'files']]);
        payloadFixture('consent', [...posts[0]]);
        assert.equal(await tab.textContent('[data-landed]'), 'Connected', 'a full-page post, answered with the assistant\'s page, not an error page');
        assert.equal(await tab.locator('[data-kit-consent]').count(), 0, 'the consent form is gone');
    });
});

test('MCP consent: with no workspace chosen Approve stays disabled and nothing posts; choosing one enables it', async () => {
    await consentPage('&two&unchosen', async ({ tab, posts }) => {
        const approve = tab.getByRole('button', { name: 'Approve' });
        assert.equal(await approve.isDisabled(), true);
        await approve.click({ force: true });
        await tab.waitForTimeout(200);
        assert.equal(posts.length, 0, 'nothing posted');
        await tab.getByRole('combobox', { name: 'Workspace' }).click();
        await tab.getByRole('option', { name: 'Northwind Farms' }).click();
        assert.equal(await approve.isDisabled(), false);
    });
});

// ── The account and session forms filled and submitted as a person would (forms.html, on the real Inertia, with the shell's Toaster) ────────────────────
// Account › Profile and its photo, Password, Security (two-factor on, confirmed and off, recovery codes, passkeys added, renamed and removed, other sessions
// signed out), Delete account, the welcome flow's timezone and photo steps, and the notes wall. As above, run.mjs is the server: each test's `store` holds
// the account, a write is checked the way its controller (or Fortify's action) would, and answered with the page the redirect lands on. A photo is posted as
// multipart; its fixture records the file's name, type and bytes, so tests/AccountFormPayloadsCheck.php posts the same file. Two browser APIs are stubbed,
// and only those: navigator.credentials.create (the WebAuthn ceremony: no authenticator here) and crypto.randomUUID (the notes' intent keys, so a fixture is
// stable). The authenticator app's TOTP code is typed as a person would; the stand-in takes one fixed code, and the PHP check swaps in a real one.
const accountShots = process.env.KIT_FORMS_SCREENSHOTS;
// An 8×8 PNG (GD-made), the photo every upload test chooses.
const photo = { name: 'robin.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWOUqzjBgA0wYRUdtBIAFSEBbqmGMXwAAAAASUVORK5CYII=', 'base64') };
// A request's body as the server reads it: JSON, or multipart with each file as { file, type, base64 }.
async function sentBody(request) {
    const type = request.headers()['content-type'] ?? '';
    if (!type.startsWith('multipart/form-data')) return request.postDataJSON() ?? {};
    const form = await new Response(request.postDataBuffer(), { headers: { 'content-type': type } }).formData();
    const body = {};
    for (const [name, value] of form) body[name] = typeof value === 'string' ? value : { file: value.name, type: value.type, base64: Buffer.from(await value.arrayBuffer()).toString('base64') };
    return body;
}
// `pages` maps a form name (forms.html?form=<name>) to its component and props; `routes` are the writes: [method, path or RegExp, handle(body, match)].
// A handle returns { errors, flash, to } (the page the redirect lands on, else the same one), { location } (Inertia::location, or a redirect off Inertia),
// or { json, status } (a JSON endpoint, as Fortify's passkey registration).
async function accountPage({ start, pages, routes, seed = 0, query = '' }, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        // Stable intent keys, distinct per test (`seed`), so no two fixtures replay each other's key in the PHP check.
        await context.addInitScript((group) => {
            let next = 0;
            crypto.randomUUID = () => `00000000-0000-4000-${group}-${String(++next).padStart(12, '0')}`;
        }, `8${String(seed).padStart(3, '0')}`);
        const tab = await context.newPage();
        const pageErrors = [];
        tab.on('pageerror', error => pageErrors.push(error.message));
        const posts = [];
        let current = start;
        const answer = ({ errors = {}, flash = {} } = {}) => {
            const { component, props, page = {} } = pages[current]();
            return { component, props: { ...props, errors }, url: `/forms.html?form=${current}`, version: null, flash, clearHistory: false, encryptHistory: false, ...page };
        };
        await tab.route(/\/forms\.html\?form=/, async (route) => {
            if (!route.request().headers()['x-inertia']) return route.continue();
            current = new URL(route.request().url()).searchParams.get('form');
            await route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(answer()) });
        });
        await tab.route('**/landed', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<p data-landed>Landed</p>' }));
        for (const [method, at, handle] of routes) {
            const matches = (u) => (typeof at === 'string' ? u.pathname === at : at.test(u.pathname));
            await tab.route(matches, async (route) => {
                const request = route.request();
                if (request.method() !== method) return route.fallback();
                const where = new URL(request.url()).pathname;
                const body = method === 'GET' ? {} : await sentBody(request);
                posts.push({ url: where, method, body, inertia: Boolean(request.headers()['x-inertia']) });
                const result = handle(body, typeof at === 'string' ? null : where.match(at));
                if (result.json !== undefined) return route.fulfill({ status: result.status ?? 200, contentType: 'application/json', body: JSON.stringify(result.json) });
                if (result.location) return route.fulfill({ status: 409, headers: { 'X-Inertia-Location': `${authUrl}landed` }, body: '' });
                if (result.to) current = result.to;
                // Laravel's 302/303 to the page, followed: answered with the page the redirect lands on (as above).
                await route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(answer(result)) });
            });
        }
        await tab.goto(`${authUrl}forms.html?form=${start}${query}`);
        await tab.waitForSelector('#app > *');
        await tab.waitForLoadState('networkidle');
        const toast = async (message) => {
            const shown = tab.locator('[data-sonner-toast]', { hasText: message });
            await shown.first().waitFor({ timeout: 5000 });
            return shown.count();
        };
        const reload = async () => { await tab.reload(); await tab.waitForSelector('#app > *'); await tab.waitForLoadState('networkidle'); };
        // A destructive Dialog: the trigger in `scope`, then the confirm button inside the dialog it opens.
        const confirm = async (scope, trigger, label = trigger) => {
            await scope.getByRole('button', { name: trigger, exact: true }).click();
            await tab.getByRole('alertdialog').getByRole('button', { name: label, exact: true }).click();
        };
        const settle = async (count) => { for (let i = 0; i < 200 && posts.length < count; i++) await tab.waitForTimeout(20); await tab.waitForLoadState('networkidle'); };
        await run({ tab, posts, toast, reload, confirm, settle });
        assert.equal(await tab.locator('dialog, iframe').count(), 0, 'no error page (Inertia\'s error modal) appeared');
        assert.deepEqual(pageErrors, []);
    } finally {
        await context.close();
    }
}
// KIT_FORMS_SCREENSHOTS: the page at 1280px and at 1920px.
async function accountShot(tab, name) {
    await tab.screenshot({ path: `${accountShots}/${name}-1280.png`, fullPage: true });
    await tab.setViewportSize({ width: 1920, height: 1000 });
    await tab.screenshot({ path: `${accountShots}/${name}-1920.png`, fullPage: true });
    await tab.setViewportSize({ width: 1280, height: 900 });
}
const titled = (title) => ({ title, layout: { title } });
const PASSWORD = 'correct horse battery staple';
// PasswordConfirmation::require, as the controllers ask for it again.
const passwordError = (body) => (!body.password ? { password: 'Enter your password to continue.' } : body.password !== PASSWORD ? { password: 'That password is not right.' } : null);

// Account › Profile: the name and email (Fortify's profile update, UpdateProfile) and the photo (AvatarController: StoreAvatar, RemoveAvatar).
function profileAccount() {
    const store = { name: 'Robin Vale', email: 'robin@example.test', verified: true, avatar: null, notice: null, clock: 'workspace', timezone: 'Europe/London' };
    const initials = () => store.name.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase();
    return {
        store,
        start: 'Profile',
        pages: {
            Profile: () => {
                const notice = store.notice;
                store.notice = null;
                return { component: 'Workspace/Account/Profile/Page', props: { ...titled('Profile'), name: store.name, initials: initials(), avatar: store.avatar ? { url: `/avatars/${store.avatar.token}`, url_2x: null } : null, store_url: '/me/avatar', max_kb: 2048, email: store.email, email_verified: store.verified, profile_url: '/user/profile-information', notice, verify_url: '/email/verification-notification', clock: store.clock, clock_url: '/me/profile/clock', timezone: store.timezone, kitClock: store.clock === 'workspace' ? 'auto' : store.clock } };
            },
        },
        routes: [
            ['PUT', '/user/profile-information', (body) => {
                const errors = {};
                if (!String(body.name ?? '').trim()) errors.name = 'The name field is required.';
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(body.email ?? ''))) errors.email = 'The email field must be a valid email address.';
                if (Object.keys(errors).length) return { errors };
                const changed = body.email.toLowerCase() !== store.email.toLowerCase();
                Object.assign(store, { name: body.name.trim(), email: body.email, verified: store.verified && !changed, notice: changed ? 'Your email address changed. We sent a link to the new address; until you confirm it, your access is limited.' : null });
                return {};
            }],
            ['POST', '/me/avatar', (body) => {
                if (!body.avatar?.file) return { errors: { avatar: 'Choose a photo.' } };
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(body.avatar.type)) return { errors: { avatar: 'The photo must be a JPEG, PNG or WebP image.' } };
                store.avatar = { token: 'a'.repeat(40), bytes: Buffer.from(body.avatar.base64, 'base64'), type: body.avatar.type };
                return {};
            }],
            ['DELETE', '/me/avatar', () => { store.avatar = null; return {}; }],
            ['PUT', '/me/profile/clock', (body) => {
                if (!['workspace', '12h', '24h'].includes(body.clock)) return { errors: { clock: 'Choose a time format.' } };
                if ('timezone' in body && !Intl.supportedValuesOf('timeZone').includes(body.timezone)) return { errors: { timezone: 'Choose a time zone from the list.' } };
                Object.assign(store, { clock: body.clock, timezone: body.timezone ?? store.timezone });
                return saved('Date & time saved.');
            }],
        ],
    };
}
// The stored photo, served where the page's avatar url points, so a shown photo is the uploaded one.
const servePhoto = (tab, store) => tab.route(/\/avatars\/[A-Za-z0-9]{40}$/, route => (store.avatar ? route.fulfill({ status: 200, contentType: store.avatar.type, body: store.avatar.bytes }) : route.fulfill({ status: 404, body: '' })));
const avatarSrc = (tab) => tab.locator('[data-form-card] img').first().getAttribute('src');

test('Profile: a person changes the name and email and presses Save; the posted fields are exactly those two, the email-changed notice and the unconfirmed hint show, and both values come back after a reload', async () => {
    const profile = profileAccount();
    await accountPage(profile, async ({ tab, posts, reload, settle }) => {
        await tab.getByRole('textbox', { name: 'Name', exact: true }).fill('Robin Ashby');
        await tab.getByRole('textbox', { name: 'Email address', exact: true }).fill('robin.ashby@example.test');
        await tab.getByRole('button', { name: 'Save', exact: true }).click();
        await settle(1);
        await tab.waitForSelector('[data-account-notice]');
        assert.equal(posts.length, 1, 'one save');
        assert.deepEqual([posts[0].method, posts[0].url, posts[0].inertia], ['PUT', '/user/profile-information', true]);
        assert.deepEqual({ ...posts[0].body }, { name: 'Robin Ashby', email: 'robin.ashby@example.test' });
        payloadFixture('profile', posts[0].body);
        assert.match(await tab.textContent('[data-account-notice]'), /Your email address changed/);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await tab.inputValue('#profile-name'), 'Robin Ashby', when);
            assert.equal(await tab.inputValue('#profile-email'), 'robin.ashby@example.test', when);
            assert.match(await tab.textContent('[data-form-card]'), /This address is not confirmed yet/, when);
            assert.equal(await tab.getByRole('button', { name: 'Send the confirmation link again' }).count(), 1, `${when}: the re-send form shows`);
            if (when === 'after the redirect') await reload();
        }
        assert.equal(await tab.locator('[data-account-notice]').count(), 0, 'the notice was flashed once, not kept');
        if (accountShots) await accountShot(tab, 'profile-saved');
    });
});

test('Profile, Date & time: Time zone then Time format under one heading; a person picks a zone and 24-hour and presses Save date & time; only those two are posted, a toast confirms it, and both are still chosen after a reload; Use workspace setting is the default', async () => {
    const profile = profileAccount();
    await accountPage(profile, async ({ tab, posts, reload, settle, toast }) => {
        assert.deepEqual(await dateTimeSection(tab, '[data-clock-form] [data-form-card]'), { heading: 'Date & time', fields: ['Time zone', 'Time format'] }, 'the heading, then Time zone, then Time format');
        assert.equal(await tab.getByRole('heading', { name: 'Clock', exact: true }).count(), 0, 'no separate Clock card');
        const chosen = () => tab.$eval('[data-clock-form] input[type=radio]:checked', el => el.value);
        assert.equal(await chosen(), 'workspace', 'with no choice of their own, Use workspace setting is chosen');
        assert.equal(await tab.locator('[data-clock-form] input[name=timezone]').inputValue(), 'Europe/London', 'the saved zone is shown');
        assert.deepEqual(await tab.$$eval('[data-clock-form] [role=radio], [data-clock-form] input[type=radio]', els => [...new Set(els.map(el => el.value ?? el.getAttribute('value')))]), ['workspace', '12h', '24h']);
        await pickTimezone(tab, 'Europe/Amsterdam');
        await tab.locator('[data-clock-form]').getByText('24-hour', { exact: true }).click();
        assert.equal(await tab.getByRole('radio', { name: /^24-hour/ }).isChecked(), true, 'the card checks its radio');
        await tab.getByRole('button', { name: 'Save date & time', exact: true }).click();
        await settle(1);
        assert.equal(posts.length, 1, 'one save');
        assert.deepEqual([posts[0].method, posts[0].url, posts[0].inertia, { ...posts[0].body }], ['PUT', '/me/profile/clock', true, { timezone: 'Europe/Amsterdam', clock: '24h' }], 'the Date & time form posts only its fields, not the name or email');
        payloadFixture('profile-clock', posts[0].body);
        assert.equal(await toast('Date & time saved.'), 1);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await chosen(), '24h', when);
            assert.equal(await tab.locator('[data-clock-form] input[name=timezone]').inputValue(), 'Europe/Amsterdam', when);
            if (when === 'after the redirect') await reload();
        }
        assert.equal(await tab.inputValue('#profile-name'), 'Robin Vale', 'the profile form is untouched');
        if (accountShots) await accountShot(tab, 'profile-clock');
    });
});

test('Profile: an empty name is refused with the error inline on the Name field, and nothing is saved', async () => {
    const profile = profileAccount();
    await accountPage(profile, async ({ tab, posts }) => {
        await tab.getByRole('textbox', { name: 'Name', exact: true }).fill(' ');
        await tab.getByRole('button', { name: 'Save', exact: true }).click();
        await tab.waitForSelector('#profile-name-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#profile-name-error'), 'The name field is required.');
        assert.equal(await tab.getAttribute('#profile-name', 'aria-invalid'), 'true');
        assert.equal(profile.store.name, 'Robin Vale', 'nothing saved');
    });
});

test('Profile photo: a person chooses a photo and presses Upload photo; it is posted as multipart with the file, and the photo shows after the redirect and a reload, with Replace photo', async () => {
    const profile = profileAccount();
    await accountPage(profile, async ({ tab, posts, reload, settle }) => {
        await servePhoto(tab, profile.store);
        assert.equal(await tab.locator('[data-form-card] img').count(), 0, 'initials before a photo');
        await tab.setInputFiles('#avatar', photo);
        await tab.getByRole('button', { name: 'Upload photo', exact: true }).click();
        await settle(1);
        await tab.getByRole('button', { name: 'Replace photo', exact: true }).waitFor();
        assert.equal(posts.length, 1, 'one upload');
        assert.deepEqual([posts[0].method, posts[0].url], ['POST', '/me/avatar']);
        assert.deepEqual(Object.keys(posts[0].body), ['avatar'], 'only the photo is posted');
        assert.deepEqual(posts[0].body.avatar, { file: 'robin.png', type: 'image/png', base64: photo.buffer.toString('base64') }, 'the chosen file, byte for byte');
        payloadFixture('avatar', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await avatarSrc(tab), `/avatars/${'a'.repeat(40)}`, `${when}: the stored photo shows`);
            assert.equal(await tab.locator('[data-form-card] img').first().evaluate(img => img.complete && img.naturalWidth), 8, `${when}: and it is the uploaded image`);
            assert.equal(await tab.getByRole('button', { name: 'Remove photo', exact: true }).count(), 1, `${when}: Remove photo is offered`);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('Profile photo: pressing Upload photo with no photo chosen comes back inline on the photo field ("Choose a photo."), not an error page, and nothing is stored', async () => {
    const profile = profileAccount();
    await accountPage(profile, async ({ tab, posts }) => {
        await tab.getByRole('button', { name: 'Upload photo', exact: true }).click();
        await tab.waitForSelector('#avatar-error');
        assert.equal(posts.length, 1, 'the empty form is posted, and the server answers');
        assert.equal(posts[0].body.avatar?.file ?? '', '', 'no file in the post');
        assert.equal(await tab.textContent('#avatar-error'), 'Choose a photo.');
        assert.equal(profile.store.avatar, null, 'nothing stored');
    });
});

test('Profile photo: Remove photo asks first, then deletes it; the initials show again after a reload', async () => {
    const profile = profileAccount();
    profile.store.avatar = { token: 'a'.repeat(40), bytes: photo.buffer, type: 'image/png' };
    await accountPage(profile, async ({ tab, posts, reload, confirm, settle }) => {
        await servePhoto(tab, profile.store);
        assert.equal(await avatarSrc(tab), `/avatars/${'a'.repeat(40)}`);
        await confirm(tab.locator('main'), 'Remove photo');
        await settle(1);
        assert.deepEqual(posts.map(({ method, url }) => [method, url]), [['DELETE', '/me/avatar']]);
        assert.deepEqual(posts[0].body, {}, 'Remove photo posts nothing but the method');
        await tab.getByRole('button', { name: 'Upload photo', exact: true }).waitFor();
        await reload();
        assert.equal(await tab.locator('[data-form-card] img').count(), 0, 'no photo after a reload');
        assert.equal(await tab.getByRole('button', { name: 'Remove photo', exact: true }).count(), 0);
    });
});

// Account › Password (Fortify's password update, UpdatePassword).
function passwordAccount() {
    const store = { password: PASSWORD, notice: null };
    return {
        store,
        start: 'Password',
        pages: { Password: () => { const notice = store.notice; store.notice = null; return { component: 'Workspace/Account/Password/Page', props: { ...titled('Password'), update_url: '/user/password', notice } }; } },
        routes: [['PUT', '/user/password', (body) => {
            if (body.current_password !== store.password) return { errors: { current_password: 'That is not your current password.' } };
            if (String(body.password ?? '').length < 8) return { errors: { password: 'The password field must be at least 8 characters.' } };
            if (body.password !== body.password_confirmation) return { errors: { password: 'The password field confirmation does not match.' } };
            Object.assign(store, { password: body.password, notice: 'Your password was changed and your other sessions were signed out.' });
            return {};
        }]],
    };
}

test('Password: a person types the current password and a new one twice and presses Update password; the notice shows and the three fields are cleared', async () => {
    const account = passwordAccount();
    await accountPage(account, async ({ tab, posts, settle }) => {
        await tab.fill('#current-password', PASSWORD);
        await tab.fill('#new-password', 'a fresh long passphrase 42');
        await tab.fill('#confirm-password', 'a fresh long passphrase 42');
        await tab.getByRole('button', { name: 'Update password', exact: true }).click();
        await settle(1);
        await tab.waitForSelector('[data-account-notice]');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'PUT', '/user/password']);
        assert.deepEqual({ ...posts[0].body }, { current_password: PASSWORD, password: 'a fresh long passphrase 42', password_confirmation: 'a fresh long passphrase 42' });
        payloadFixture('password', posts[0].body);
        assert.equal(await tab.textContent('[data-account-notice]'), 'Your password was changed and your other sessions were signed out.');
        assert.equal(account.store.password, 'a fresh long passphrase 42');
        for (const id of ['#current-password', '#new-password', '#confirm-password']) assert.equal(await tab.inputValue(id), '', `${id} is cleared`);
    });
});

test('Password: a wrong current password is refused inline on that field, and the password is unchanged', async () => {
    const account = passwordAccount();
    await accountPage(account, async ({ tab, posts }) => {
        await tab.fill('#current-password', 'not it at all');
        await tab.fill('#new-password', 'a fresh long passphrase 42');
        await tab.fill('#confirm-password', 'a fresh long passphrase 42');
        await tab.getByRole('button', { name: 'Update password', exact: true }).click();
        await tab.waitForSelector('#current-password-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#current-password-error'), 'That is not your current password.');
        assert.equal(account.store.password, PASSWORD, 'nothing saved');
    });
});

// Account › Profile and Password inside the persistent layouts (`&shell`): the Workspace Layout owns the document title, from `layout.documentTitle`.
// Back and Forward restore the page from Inertia's history entry; the title must follow the heading and the current sidebar item every time.
test('Account sections in the shell: Back and Forward restore the document title in the same render as the heading and the current section', async () => {
    const profile = profileAccount();
    const password = passwordAccount();
    const sections = ['Profile', 'Password'];
    const framed = (name, page) => () => {
        const shown = page();
        const navigation = [{ label: null, items: sections.map((label) => ({ label, url: `/forms.html?form=${label}`, current: label === name, icon: null, badge: null })) }];
        return { ...shown, props: { ...shown.props, layout: { ...shown.props.layout, documentTitle: `${name} · Example Product`, breadcrumbs: [], navigation, navigationHeader: null } } };
    };
    const fixture = { start: 'Password', query: '&shell', routes: [], pages: { Profile: framed('Profile', profile.pages.Profile), Password: framed('Password', password.pages.Password) } };
    await accountPage(fixture, async ({ tab }) => {
        // Moves (a click, Back or Forward) and reads the page the moment the sidebar first marks `name` current, from a MutationObserver: the sentinel on the
        // persistent frame, the current item, the heading, the address and document.title. A reader that polls the heading (a browser test) can land there.
        const move = (how, name) => tab.evaluate(([how, name]) => new Promise((done) => {
            const read = () => [document.querySelector('[data-settings-frame]')?.dataset.sentinel ?? null, document.querySelector('[data-settings-sidebar] [aria-current=page]')?.textContent.trim(), document.querySelector('[data-slot=page-heading]')?.textContent.trim(), new URL(location.href).searchParams.get('form'), document.title];
            const observer = new MutationObserver(() => { const state = read(); if (state[1] === name) { observer.disconnect(); done(state); } });
            observer.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
            if (how === 'back') history.back();
            else if (how === 'forward') history.forward();
            else document.querySelector(`[data-settings-sidebar] a[href$="form=${name}"]`).click();
        }), [how, name]);
        await tab.waitForSelector('[data-settings-sidebar] [aria-current=page]');
        await tab.evaluate(() => { document.querySelector('[data-settings-frame]').dataset.sentinel = 'kept'; });
        assert.equal(await tab.title(), 'Password · Example Product');
        assert.deepEqual(await move('click', 'Profile'), ['kept', 'Profile', 'Profile', 'Profile', 'Profile · Example Product']);
        assert.deepEqual(await move('back', 'Password'), ['kept', 'Password', 'Password', 'Password', 'Password · Example Product'], 'Back restores the title with the page');
        assert.deepEqual(await move('forward', 'Profile'), ['kept', 'Profile', 'Profile', 'Profile', 'Profile · Example Product'], 'Forward restores the title with the page');
        await tab.waitForTimeout(50);
        assert.equal(await tab.title(), 'Profile · Example Product', 'a moment later the title still names the page');
        // documentTitle false: the shell's own <title> goes, so a page's <Head> title is not left behind it.
        await tab.evaluate(() => window.kitShellTitle(false));
        await tab.waitForFunction(() => !document.head.querySelector('title[data-kit-title]'));
        await tab.evaluate(() => window.kitShellTitle(true));
        await tab.waitForFunction(() => document.title === 'Profile · Example Product');
    });
});

// Under load (`&lazy`: each page resolves a task later, as a code-split import() does; the CPU throttled 6x; the main thread kept busy after each move), ten
// rounds (`KIT_TITLE_ROUNDS`) of a click, Back, Forward and Back. Every write to the head's <title> elements is recorded: once the sidebar marks a section current, the title
// names it and nothing writes another, and Inertia renders no <title> of its own in the shell (the shell owns it, so a queued <Head> write cannot land late).
test('Account sections in the shell under load: ten rounds of click, Back and Forward, and no write ever leaves an older page\'s title', async () => {
    const profile = profileAccount();
    const password = passwordAccount();
    const sections = ['Profile', 'Password'];
    const framed = (name, page) => () => {
        const shown = page();
        const navigation = [{ label: null, items: sections.map((label) => ({ label, url: `/forms.html?form=${label}`, current: label === name, icon: null, badge: null })) }];
        return { ...shown, props: { ...shown.props, layout: { ...shown.props.layout, documentTitle: `${name} · Example Product`, breadcrumbs: [], navigation, navigationHeader: null } } };
    };
    const fixture = { start: 'Password', query: '&shell&lazy', routes: [], pages: { Profile: framed('Profile', profile.pages.Profile), Password: framed('Password', password.pages.Password) } };
    await accountPage(fixture, async ({ tab }) => {
        const cdp = await tab.context().newCDPSession(tab);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 6 });
        try {
            await tab.waitForSelector('[data-settings-sidebar] [aria-current=page]');
            await tab.evaluate(() => {
                window.kitTitles = [];
                new MutationObserver(() => window.kitTitles.push(document.title)).observe(document.head, { subtree: true, childList: true, characterData: true });
            });
            const stale = [];
            for (let round = 0; round < Number(process.env.KIT_TITLE_ROUNDS ?? 10); round++) {
                for (const [how, name] of [['click', 'Profile'], ['back', 'Password'], ['forward', 'Profile'], ['back', 'Password']]) {
                    const first = await tab.evaluate(([how, name]) => new Promise((done) => {
                        window.kitTitles = [];
                        const observer = new MutationObserver(() => {
                            if (document.querySelector('[data-settings-sidebar] [aria-current=page]')?.textContent.trim() !== name) return;
                            observer.disconnect();
                            done(document.title);
                        });
                        observer.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
                        if (how === 'back') history.back();
                        else if (how === 'forward') history.forward();
                        else document.querySelector(`[data-settings-sidebar] a[href$="form=${name}"]`).click();
                        // A busy main thread right after the move, as a loaded runner has.
                        setTimeout(() => { const end = performance.now() + 8; while (performance.now() < end); });
                    }), [how, name]);
                    // Past Inertia's debounced head write (1ms, throttled), so a late write would be recorded.
                    await tab.waitForTimeout(40);
                    const after = await tab.evaluate(() => ({ title: document.title, writes: window.kitTitles, inertia: document.head.querySelectorAll('title[data-inertia]').length }));
                    const want = `${name} · Example Product`;
                    if (first !== want || after.title !== want || after.writes.some((written) => written !== want) || after.inertia) stale.push({ round, how, first, ...after });
                }
            }
            assert.deepEqual(stale, [], 'every move names the shown section, first and last');
        } finally {
            await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });
        }
    });
});

// Account › Security: TwoFactorController, RecoveryCodesController, PasskeysController (and Fortify's passkey registration), SessionsController.
const TOTP = '492039';
function securityAccount({ twoFactor = 'off', passkeys = [{ id: 7, name: 'MacBook passkey', created: '2026-10-01T09:00:00+00:00', last_used: null }] } = {}) {
    const store = { twoFactor, codes: twoFactor === 'on' ? ['aaaa-1111', 'bbbb-2222'] : [], shown: [], passkeys, nextPasskey: 8, sessions: 2, notice: null };
    const make = (n) => Array.from({ length: 8 }, (_, i) => `code${n}-${String(i + 1).padStart(4, '0')}`);
    let generation = 0;
    return {
        store,
        start: 'Security',
        pages: {
            Security: () => {
                const { shown, notice } = store;
                store.shown = [];
                store.notice = null;
                return {
                    component: 'Workspace/Account/Security/Page',
                    props: {
                        ...titled('Security'),
                        two_factor: { state: store.twoFactor, qr_svg: store.twoFactor === 'pending' ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4"><rect width="2" height="2"/></svg>' : null, secret_key: store.twoFactor === 'pending' ? 'JBSWY3DPEHPK3PXP' : null, codes_left: store.twoFactor === 'on' ? store.codes.length : 0, recovery_codes: shown },
                        sessions: { supported: true, items: [{ agent: 'Chrome on macOS', ip: '203.0.113.5', last_active: '2026-11-09T09:00:00+00:00', current: true }, ...Array.from({ length: store.sessions - 1 }, () => ({ agent: 'Safari on iOS', ip: '198.51.100.7', last_active: '2026-11-08T18:00:00+00:00', current: false }))] },
                        events: [],
                        two_factor_url: '/me/security/two-factor',
                        recovery_codes_url: '/me/security/recovery-codes',
                        sessions_url: '/me/security/sessions',
                        notice,
                        passkeys: { options_url: '/user/passkeys/options', store_url: '/user/passkeys', confirm_url: '/user/confirm-password', url: '/me/security/passkeys', items: store.passkeys },
                    },
                };
            },
        },
        routes: [
            ['POST', '/me/security/two-factor', (body) => {
                const refused = passwordError(body);
                if (refused) return { errors: refused };
                store.twoFactor = 'pending';
                return {};
            }],
            ['PUT', '/me/security/two-factor', (body) => {
                if (String(body.code ?? '').replace(/ /g, '') !== TOTP) return { errors: { code: 'The provided two factor authentication code was invalid.' } };
                store.twoFactor = 'on';
                store.codes = make(++generation);
                store.shown = store.codes;
                return {};
            }],
            ['DELETE', '/me/security/two-factor', (body) => {
                const refused = passwordError(body);
                if (refused) return { errors: refused };
                Object.assign(store, { twoFactor: 'off', codes: [] });
                return {};
            }],
            ['POST', '/me/security/recovery-codes', (body) => {
                const refused = passwordError(body);
                if (refused) return { errors: refused };
                store.codes = make(++generation);
                store.shown = store.codes;
                return {};
            }],
            ['PATCH', /^\/me\/security\/passkeys\/(\d+)$/, (body, match) => {
                const name = String(body.name ?? '').trim();
                if (!name || name.length > 60) return { errors: { name: 'The name field is required.' } };
                store.passkeys.find(key => key.id === Number(match[1])).name = name;
                return {};
            }],
            ['DELETE', /^\/me\/security\/passkeys\/(\d+)$/, (body, match) => {
                const refused = passwordError(body);
                if (refused) return { errors: refused };
                store.passkeys = store.passkeys.filter(key => key.id !== Number(match[1]));
                return {};
            }],
            // Fortify's registration: the password confirmed (password.confirm guards it by default), the options, then the browser's credential with its name (JSON,
            // through the page's HTTP client).
            ['POST', '/user/confirm-password', (body) => (body.password === PASSWORD ? { json: '', status: 201 } : { json: { message: 'The provided password was incorrect.', errors: { password: ['The provided password was incorrect.'] } }, status: 422 })],
            ['GET', '/user/passkeys/options', () => ({ json: { options: { challenge: 'Y2hhbGxlbmdl', rp: { name: 'Kit', id: 'localhost' }, user: { id: 'MQ', name: 'robin@example.test', displayName: 'Robin Vale' }, pubKeyCredParams: [{ type: 'public-key', alg: -7 }] } } })],
            ['POST', '/user/passkeys', (body) => {
                store.passkeys = [...store.passkeys, { id: store.nextPasskey++, name: body.name, created: '2026-11-09T10:00:00+00:00', last_used: null }];
                return { json: { id: store.nextPasskey - 1 }, status: 201 };
            }],
            ['DELETE', '/me/security/sessions', (body) => {
                const refused = passwordError(body);
                if (refused) return { errors: refused };
                const ended = store.sessions - 1;
                store.sessions = 1;
                store.notice = ended === 0 ? 'There were no other sessions.' : `Signed out ${ended} other ${ended === 1 ? 'session' : 'sessions'}.`;
                return {};
            }],
        ],
    };
}
const card = (tab, title) => tab.locator('[data-form-card]', { has: tab.getByText(title, { exact: true }) });

test('Two-factor: a person types the password and presses Turn on; the QR code and setup key show, waiting for a code, also after a reload', async () => {
    const security = securityAccount();
    await accountPage(security, async ({ tab, posts, reload, settle }) => {
        await tab.fill('#two-factor-password', PASSWORD);
        await tab.getByRole('button', { name: 'Turn on', exact: true }).click();
        await settle(1);
        await tab.waitForSelector('[data-two-factor-qr]');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'POST', '/me/security/two-factor']);
        assert.deepEqual({ ...posts[0].body }, { password: PASSWORD });
        payloadFixture('two-factor-enable', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await tab.textContent('[data-two-factor-key]'), 'JBSWY3DPEHPK3PXP', when);
            assert.equal(await tab.getByRole('button', { name: 'Confirm and turn on', exact: true }).count(), 1, when);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('Two-factor: a wrong password on Turn on is refused inline on the password field, and two-factor stays off', async () => {
    const security = securityAccount();
    await accountPage(security, async ({ tab, posts }) => {
        await tab.fill('#two-factor-password', 'not it at all');
        await tab.getByRole('button', { name: 'Turn on', exact: true }).click();
        await tab.waitForSelector('#two-factor-password-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#two-factor-password-error'), 'That password is not right.');
        assert.equal(security.store.twoFactor, 'off');
    });
});

test('Two-factor: the code from the app (typed with a space) confirms it; it is on, the recovery codes show once, and after a reload it is still on with the codes gone', async () => {
    const security = securityAccount({ twoFactor: 'pending' });
    await accountPage(security, async ({ tab, posts, reload, settle }) => {
        await tab.fill('#two-factor-code', '492 039');
        await tab.getByRole('button', { name: 'Confirm and turn on', exact: true }).click();
        await settle(1);
        await tab.waitForSelector('[data-recovery-codes]');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'PUT', '/me/security/two-factor']);
        assert.deepEqual({ ...posts[0].body }, { code: '492 039' });
        payloadFixture('two-factor-confirm', posts[0].body);
        assert.equal(await tab.locator('[data-recovery-codes] li').count(), 8);
        assert.equal(await tab.getByText('Two-factor authentication is on', { exact: true }).count(), 1);
        await reload();
        assert.equal(await tab.getByText('Two-factor authentication is on', { exact: true }).count(), 1, 'still on after a reload');
        assert.equal(await tab.locator('[data-recovery-codes]').count(), 0, 'the codes were shown once');
    });
});

test('Two-factor: a wrong code is refused inline on the code field, and it stays waiting', async () => {
    const security = securityAccount({ twoFactor: 'pending' });
    await accountPage(security, async ({ tab, posts }) => {
        await tab.fill('#two-factor-code', '000000');
        await tab.getByRole('button', { name: 'Confirm and turn on', exact: true }).click();
        await tab.waitForSelector('#two-factor-code-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#two-factor-code-error'), 'The provided two factor authentication code was invalid.');
        assert.equal(await tab.locator('[data-two-factor-qr]').count(), 1, 'the QR code is still there');
        assert.equal(security.store.twoFactor, 'pending');
    });
});

test('Two-factor: Turn off asks first, posts the password, and it is off after the redirect and a reload', async () => {
    const security = securityAccount({ twoFactor: 'on' });
    await accountPage(security, async ({ tab, posts, reload, confirm, settle }) => {
        await tab.fill('#off-password', PASSWORD);
        await confirm(card(tab, 'Turn off two-factor authentication'), 'Turn off');
        await settle(1);
        await tab.waitForSelector('#two-factor-password');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'DELETE', '/me/security/two-factor']);
        assert.deepEqual({ ...posts[0].body }, { password: PASSWORD });
        payloadFixture('two-factor-disable', posts[0].body);
        await reload();
        assert.equal(await tab.getByRole('button', { name: 'Turn on', exact: true }).count(), 1, 'off after a reload');
    });
});

test('Recovery codes: Regenerate asks first, posts the password, and the new codes show once; the count of codes left is the new set\'s', async () => {
    const security = securityAccount({ twoFactor: 'on' });
    await accountPage(security, async ({ tab, posts, reload, confirm, settle }) => {
        await tab.fill('#codes-password', PASSWORD);
        await confirm(card(tab, 'Recovery codes'), 'Regenerate recovery codes');
        await settle(1);
        await tab.waitForSelector('[data-recovery-codes]');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'POST', '/me/security/recovery-codes']);
        assert.deepEqual({ ...posts[0].body }, { password: PASSWORD });
        payloadFixture('recovery-codes', posts[0].body);
        assert.deepEqual(await tab.locator('[data-recovery-codes] li').allTextContents(), security.store.codes);
        assert.equal(await tab.inputValue('#codes-password'), '', 'the password is cleared');
        await reload();
        assert.equal(await tab.locator('[data-recovery-codes]').count(), 0, 'shown once');
        assert.match(await tab.textContent('main'), /8 recovery codes left/);
    });
});

test('Recovery codes: a wrong password is refused inline, and the old codes stay', async () => {
    const security = securityAccount({ twoFactor: 'on' });
    await accountPage(security, async ({ tab, posts, confirm }) => {
        await tab.fill('#codes-password', 'not it at all');
        await confirm(card(tab, 'Recovery codes'), 'Regenerate recovery codes');
        await tab.waitForSelector('#codes-password-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#codes-password-error'), 'That password is not right.');
        assert.deepEqual(security.store.codes, ['aaaa-1111', 'bbbb-2222']);
    });
});

test('Passkeys: Add a passkey asks for the options, hands them to the browser (navigator.credentials.create, stubbed: no authenticator) and posts its credential with a name; the list shows it after a reload', async () => {
    const security = securityAccount();
    await accountPage(security, async ({ tab, posts, reload, settle }) => {
        await tab.evaluate(() => {
            window.kitCreateCalls = [];
            window.PublicKeyCredential = window.PublicKeyCredential ?? function PublicKeyCredential() {};
            window.PublicKeyCredential.parseCreationOptionsFromJSON = (json) => ({ parsed: json });
            navigator.credentials.create = async (options) => {
                window.kitCreateCalls.push(options);
                return { toJSON: () => ({ id: 'Y3JlZA', rawId: 'Y3JlZA', type: 'public-key', response: { clientDataJSON: 'e30', attestationObject: 'o2Nmb3Jt' } }) };
            };
        });
        // passkeysSupported() is read on mount: a reload with the stub in place.
        await tab.addInitScript(() => {
            window.PublicKeyCredential = window.PublicKeyCredential ?? function PublicKeyCredential() {};
            window.PublicKeyCredential.parseCreationOptionsFromJSON = (json) => ({ parsed: json });
            window.kitCreateCalls = [];
            navigator.credentials.create = async (options) => {
                window.kitCreateCalls.push(options);
                return { toJSON: () => ({ id: 'Y3JlZA', rawId: 'Y3JlZA', type: 'public-key', response: { clientDataJSON: 'e30', attestationObject: 'o2Nmb3Jt' } }) };
            };
            Object.defineProperty(navigator, 'userAgentData', { value: { platform: 'macOS' }, configurable: true });
        });
        await reload();
        await tab.locator('[data-passkey-add-form]').getByLabel('Your password').fill(PASSWORD);
        await tab.getByRole('button', { name: 'Add a passkey', exact: true }).click();
        await settle(3);
        await tab.locator('[data-passkey="8"]').waitFor();
        assert.deepEqual(posts.slice(0, 3).map(({ method, url }) => [method, url]), [['POST', '/user/confirm-password'], ['GET', '/user/passkeys/options'], ['POST', '/user/passkeys']]);
        assert.deepEqual(posts[0].body, { password: PASSWORD }, 'the password is confirmed first');
        assert.equal((await tab.evaluate(() => window.kitCreateCalls)).length, 1, 'the browser was asked once');
        assert.equal((await tab.evaluate(() => window.kitCreateCalls[0].publicKey.parsed.challenge)), 'Y2hhbGxlbmdl', 'with the server\'s options');
        assert.deepEqual(posts[2].body, { name: 'macOS passkey', credential: { id: 'Y3JlZA', rawId: 'Y3JlZA', type: 'public-key', response: { clientDataJSON: 'e30', attestationObject: 'o2Nmb3Jt' } } });
        payloadFixture('passkey-add', posts[2].body);
        assert.equal(await tab.locator('[data-passkey-add-failed]').count(), 0);
        await reload();
        assert.deepEqual(await tab.locator('[data-passkey] > span > .font-medium').allTextContents(), ['MacBook passkey', 'macOS passkey']);
    });
});

test('Passkeys: Rename posts the new name for that passkey only, and the list shows it after the redirect and a reload', async () => {
    const security = securityAccount({ passkeys: [{ id: 7, name: 'MacBook passkey', created: '2026-10-01T09:00:00+00:00', last_used: null }, { id: 9, name: 'Phone passkey', created: '2026-10-02T09:00:00+00:00', last_used: '2026-11-01T09:00:00+00:00' }] });
    await accountPage(security, async ({ tab, posts, reload, settle }) => {
        const row = tab.locator('[data-passkey="9"]');
        await row.getByRole('textbox', { name: 'Name', exact: true }).fill('Work phone');
        await row.getByRole('button', { name: 'Rename', exact: true }).click();
        await settle(1);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'PATCH', '/me/security/passkeys/9']);
        assert.deepEqual({ ...posts[0].body }, { name: 'Work phone' });
        payloadFixture('passkey-rename', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            await tab.locator('[data-passkey="9"] > span > .font-medium', { hasText: 'Work phone' }).waitFor();
            assert.deepEqual(await tab.locator('[data-passkey] > span > .font-medium').allTextContents(), ['MacBook passkey', 'Work phone'], when);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('Passkeys: Remove asks first, posts the password, and the passkey is gone after a reload; a wrong password is refused inline first', async () => {
    const security = securityAccount();
    await accountPage(security, async ({ tab, posts, reload, confirm, settle }) => {
        const row = tab.locator('[data-passkey="7"]');
        await row.locator('#passkey-password-7').fill('not it at all');
        await confirm(row, 'Remove', 'Remove passkey');
        await tab.waitForSelector('#passkey-password-7-error');
        assert.equal(await tab.textContent('#passkey-password-7-error'), 'That password is not right.');
        assert.equal(security.store.passkeys.length, 1, 'not removed');
        await row.locator('#passkey-password-7').fill(PASSWORD);
        await confirm(row, 'Remove', 'Remove passkey');
        await settle(2);
        await tab.waitForSelector('[data-passkeys-empty]');
        assert.deepEqual(posts.map(({ method, url }) => [method, url]), [['DELETE', '/me/security/passkeys/7'], ['DELETE', '/me/security/passkeys/7']]);
        assert.deepEqual({ ...posts[1].body }, { password: PASSWORD });
        payloadFixture('passkey-remove', posts[1].body);
        await reload();
        assert.equal(await tab.locator('[data-passkey]').count(), 0);
    });
});

test('Signed-in devices: Sign out other sessions asks first, posts the password, says how many ended, and the list keeps only this device', async () => {
    const security = securityAccount();
    await accountPage(security, async ({ tab, posts, reload, confirm, settle }) => {
        assert.equal(await tab.locator('[data-sessions] li').count(), 2);
        await tab.fill('#sessions-password', PASSWORD);
        await confirm(card(tab, 'Sign out other sessions'), 'Sign out other sessions');
        await settle(1);
        await tab.waitForSelector('[data-account-notice]');
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'DELETE', '/me/security/sessions']);
        assert.deepEqual({ ...posts[0].body }, { password: PASSWORD });
        payloadFixture('sessions-sign-out', posts[0].body);
        assert.equal(await tab.textContent('[data-account-notice]'), 'Signed out 1 other session.');
        await reload();
        assert.equal(await tab.locator('[data-sessions] li').count(), 1, 'only this device after a reload');
        if (accountShots) await accountShot(tab, 'security');
    });
});

// Account › Delete account (AccountDeletionController): the password, then signed out and sent to `/`.
function deleteAccount({ owned = [] } = {}) {
    const store = { deleted: false, owned };
    return {
        store,
        start: 'DeleteAccount',
        pages: { DeleteAccount: () => ({ component: 'Workspace/Account/Delete/Page', props: { ...titled('Delete account'), owned: store.owned, submit: { label: 'Delete account', url: '/me/delete', method: 'delete', destructive: true }, confirms_password: true } }) },
        routes: [['DELETE', '/me/delete', (body) => {
            if (!body.password) return { errors: { password: 'The password field is required.' } };
            if (body.password !== PASSWORD) return { errors: { password: 'That password is not right.' } };
            store.deleted = true;
            return { location: '/' };
        }]],
    };
}

test('Delete account: a person types the password and presses Delete account; the password is posted and the browser leaves for the signed-out page', async () => {
    const account = deleteAccount();
    await accountPage(account, async ({ tab, posts }) => {
        await tab.fill('#delete-account-password', PASSWORD);
        await Promise.all([tab.waitForURL(/\/landed$/), tab.getByRole('button', { name: 'Delete account', exact: true }).click()]);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'DELETE', '/me/delete']);
        assert.deepEqual({ ...posts[0].body }, { password: PASSWORD });
        payloadFixture('account-delete', posts[0].body);
        assert.equal(await tab.textContent('[data-landed]'), 'Landed', 'a full visit away, not an error page');
        assert.equal(account.store.deleted, true);
    });
});

test('Delete account: a wrong password is refused inline on the password field, and the account stays', async () => {
    const account = deleteAccount();
    await accountPage(account, async ({ tab, posts }) => {
        await tab.fill('#delete-account-password', 'not it at all');
        await tab.getByRole('button', { name: 'Delete account', exact: true }).click();
        await tab.waitForSelector('#delete-account-password-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#delete-account-password-error'), 'That password is not right.');
        assert.equal(account.store.deleted, false);
    });
});

// The welcome flow (WelcomeController, WelcomeSkipController): the timezone step saves each change and Continue confirms it; then the photo step, which
// uploads (or is skipped); the flow ends with Inertia::location to where the person was going.
function welcomeFlow() {
    const store = { saved: null, confirmed: null, avatar: null, skipped: [], finished: false };
    const step = (id, heading, description, submitLabel, extra) => ({ title: 'Welcome', heading, description, submit: { label: submitLabel, url: `/welcome/${id}`, method: 'post' }, initials: 'RV', max_kb: 2048, ...extra });
    return {
        store,
        start: 'WelcomeTimezone',
        pages: {
            WelcomeTimezone: () => ({ component: 'Welcome/Timezone/Page', props: step('timezone', 'Confirm your timezone', 'Dates and times show in your timezone.', 'Continue', { skip: null, value: store.saved ?? 'Europe/London', save: { label: 'Save', url: '/welcome/timezone', method: 'put' } }) }),
            WelcomePhoto: () => ({ component: 'Welcome/Photo/Page', props: step('photo', 'Add a photo', 'Teammates see it next to your name.', 'Upload and continue', { skip: { label: 'Skip for now', url: '/welcome/photo/skip', method: 'post' }, value: null, save: null }) }),
        },
        routes: [
            ['PUT', '/welcome/timezone', (body) => {
                if (!Intl.supportedValuesOf('timeZone').includes(body.timezone)) return { errors: { timezone: 'Choose a timezone from the list.' } };
                store.saved = body.timezone;
                return {};
            }],
            ['POST', '/welcome/timezone', (body) => {
                if (!Intl.supportedValuesOf('timeZone').includes(body.timezone)) return { errors: { timezone: 'Choose a timezone from the list.' } };
                Object.assign(store, { saved: body.timezone, confirmed: body.timezone });
                return { to: 'WelcomePhoto' };
            }],
            ['POST', '/welcome/photo', (body) => {
                if (!body.avatar?.file) return { errors: { avatar: 'Choose a photo, or skip this step.' } };
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(body.avatar.type)) return { errors: { avatar: 'The photo must be a JPEG, PNG or WebP image.' } };
                store.avatar = body.avatar;
                store.finished = true;
                return { location: '/me/workspaces' };
            }],
            ['POST', '/welcome/photo/skip', () => { store.skipped.push('photo'); store.finished = true; return { location: '/me/workspaces' }; }],
        ],
    };
}

test('Welcome, timezone: picking another zone saves it as it is chosen (no Save button), Continue confirms it and the photo step follows; a reload of the step keeps the zone', async () => {
    const welcome = welcomeFlow();
    await accountPage(welcome, async ({ tab, posts, settle, reload }) => {
        assert.equal(await tab.locator('input[name=timezone]').inputValue(), 'Europe/London');
        await pickTimezone(tab, 'Europe/Amsterdam');
        await settle(1);
        await tab.waitForFunction(() => document.querySelector('[data-welcome-saved]')?.textContent === 'Saved');
        assert.deepEqual([posts[0].method, posts[0].url, posts[0].body], ['PUT', '/welcome/timezone', { timezone: 'Europe/Amsterdam' }], 'the change is saved as it is made');
        payloadFixture('welcome-timezone-save', posts[0].body);
        await reload();
        assert.equal(await tab.locator('input[name=timezone]').inputValue(), 'Europe/Amsterdam', 'the saved zone after a reload');
        await tab.getByRole('button', { name: 'Continue', exact: true }).click();
        await settle(2);
        await tab.waitForSelector('[data-welcome-step="photo"]');
        assert.equal(posts.length, 2);
        assert.deepEqual([posts[1].method, posts[1].url, { ...posts[1].body }], ['POST', '/welcome/timezone', { timezone: 'Europe/Amsterdam' }]);
        payloadFixture('welcome-timezone', posts[1].body);
        assert.equal(welcome.store.confirmed, 'Europe/Amsterdam');
    });
});

test('Welcome, photo: Upload and continue stays disabled until a photo is chosen; then the photo is posted as multipart and the flow ends where the person was going', async () => {
    const welcome = welcomeFlow();
    await accountPage({ ...welcome, start: 'WelcomePhoto' }, async ({ tab, posts }) => {
        const upload = tab.getByRole('button', { name: 'Upload and continue', exact: true });
        assert.equal(await upload.isDisabled(), true, 'no photo, no upload');
        await tab.setInputFiles('#avatar', photo);
        assert.equal(await tab.locator('[data-welcome-step] img').count(), 1, 'the preview shows');
        await Promise.all([tab.waitForURL(/\/landed$/), upload.click()]);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'POST', '/welcome/photo']);
        assert.deepEqual(posts[0].body, { avatar: { file: 'robin.png', type: 'image/png', base64: photo.buffer.toString('base64') } });
        payloadFixture('welcome-photo', posts[0].body);
        assert.equal(welcome.store.finished, true);
    });
});

test('Welcome, photo: a file that is not a photo is refused inline on the photo field, the step stays, and Skip for now then moves on without a photo', async () => {
    const welcome = welcomeFlow();
    await accountPage({ ...welcome, start: 'WelcomePhoto' }, async ({ tab, posts }) => {
        await tab.setInputFiles('#avatar', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('not a photo') });
        await tab.getByRole('button', { name: 'Upload and continue', exact: true }).click();
        await tab.waitForSelector('#avatar-error');
        assert.equal(await tab.textContent('#avatar-error'), 'The photo must be a JPEG, PNG or WebP image.');
        assert.equal(await tab.locator('[data-welcome-step="photo"]').count(), 1, 'still on the step');
        assert.equal(welcome.store.avatar, null);
        await Promise.all([tab.waitForURL(/\/landed$/), tab.getByRole('button', { name: 'Skip for now', exact: true }).click()]);
        assert.deepEqual(posts.map(({ method, url }) => [method, url]), [['POST', '/welcome/photo'], ['POST', '/welcome/photo/skip']]);
        assert.deepEqual(welcome.store.skipped, ['photo']);
    });
});

// The notes wall (NoteController over WorkspaceNotes): add, edit and delete, each with its intent key and, for a change, the version the card shows.
function notesWall() {
    const colours = [{ key: 'yellow', label: 'Yellow' }, { key: 'pink', label: 'Pink' }, { key: 'blue', label: 'Blue' }, { key: 'green', label: 'Green' }];
    const store = { notes: [], next: 1 };
    const item = (note) => ({
        id: String(note.id), title: note.body.split('\n')[0].slice(0, 80), url: null, description: null, meta: [], badges: [], variant: null, group: null, actions: [],
        note: { id: note.id, workspace_id: 1, author_id: 1, author: 'Robin Vale', body: note.body, colour: note.colour, pinned: note.pinned, version: note.version, subject: null, attachments: [], created_at: '2026-11-09T09:00:00+00:00', updated_at: '2026-11-09T09:00:00+00:00' },
        update: { label: 'Edit', url: `/@acme/notes/${note.id}`, method: 'put', key: 'edit', disabled: false, reason: null, confirm: null, destructive: false },
        delete: { label: 'Delete', url: `/@acme/notes/${note.id}`, method: 'delete', key: 'delete', disabled: false, reason: null, confirm: { title: 'Delete this note?', description: 'The note and its attachments are removed for everyone in this workspace.', label: 'Delete note' }, destructive: true },
    });
    const ordered = () => [...store.notes].sort((a, b) => Number(b.pinned) - Number(a.pinned) || b.id - a.id);
    const bodyError = (body) => (!String(body.body ?? '').trim() ? { body: 'The body field is required.' } : String(body.body).length > 5000 ? { body: 'The body field must not be greater than 5000 characters.' } : null);
    const toast = (message, from) => ({ toast: { id: `n-${Math.random()}`, type: 'success', message, from, style: 'pulse', duration: 4000 } });
    const stale = (note, body) => (Number(body.expected_version) !== note.version ? { errors: { expected_version: 'This note changed since you opened it.' } } : null);
    return {
        store,
        start: 'Notes',
        pages: {
            Notes: () => ({
                component: 'Workspace/Notes/Index/Page',
                props: {
                    ...titled('Notes'),
                    composer: { submit: { label: 'Add note', url: '/@acme/notes', method: 'post' }, placeholder: 'Write a note…', colours, default_colour: 'yellow', media: { accept: ['image/png', 'image/jpeg', 'application/pdf'], max_files: 5, max_kb: 1024 }, subject: null, options: [], subject_search: null },
                    colours, colour: null, index_url: '/@acme/notes', list: 'notes',
                    notes: { key: 'notes', title: 'Notes', state: store.notes.length ? 'ready' : 'empty', data: ordered().map(item), empty: { variant: 'first-use', title: 'No notes yet', description: 'Write the first one above.', action: null }, error: null },
                },
                // Inertia's scroll prop for the wall, one page (as Inertia::scroll answers it).
                page: { scrollProps: { notes: { pageName: 'notes', previousPage: null, nextPage: null, currentPage: 1, reset: false } } },
            }),
        },
        routes: [
            ['POST', '/@acme/notes', (body) => {
                const refused = bodyError(body);
                if (refused) return { errors: refused };
                if (store.notes.some(note => note.intent === body.intent_key)) return { flash: toast('Note added', 'save') };
                store.notes.push({ id: store.next++, body: body.body, colour: body.colour ?? 'yellow', pinned: ['1', 'true', true].includes(body.pinned), version: 1, intent: body.intent_key });
                return { flash: toast('Note added', 'save') };
            }],
            ['PUT', /^\/@acme\/notes\/(\d+)$/, (body, match) => {
                const note = store.notes.find(each => each.id === Number(match[1]));
                const refused = stale(note, body) ?? (body.body !== undefined ? (bodyError(body) ? { errors: bodyError(body) } : null) : null);
                if (refused) return refused;
                Object.assign(note, { body: body.body ?? note.body, colour: body.colour ?? note.colour, pinned: body.pinned ?? note.pinned, version: note.version + 1 });
                return { flash: toast('Note saved', 'save') };
            }],
            ['DELETE', /^\/@acme\/notes\/(\d+)$/, (body, match) => {
                const note = store.notes.find(each => each.id === Number(match[1]));
                const refused = stale(note, body);
                if (refused) return refused;
                store.notes = store.notes.filter(each => each !== note);
                return { flash: toast('Note deleted', 'delete') };
            }],
        ],
    };
}
const noteBodies = (tab) => tab.locator('[data-note] p.whitespace-pre-line').allTextContents();

test('Notes, add: a person writes a note, picks Blue, pins it and presses Add note; the toast shows, the composer clears, and the note is on the wall, pinned and blue, after a reload', async () => {
    const wall = { ...notesWall(), seed: 1 };
    await accountPage(wall, async ({ tab, posts, toast, reload, settle }) => {
        await tab.getByRole('textbox', { name: 'New note', exact: true }).fill('Order more seed trays');
        await tab.getByRole('combobox', { name: 'Colour' }).click();
        await tab.getByRole('option', { name: 'Blue' }).click();
        await tab.getByLabel('Pin to the top').check();
        await tab.getByRole('button', { name: 'Add note', exact: true }).click();
        assert.equal(await toast('Note added'), 1);
        await settle(1);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'POST', '/@acme/notes']);
        assert.deepEqual({ ...posts[0].body, intent_key: undefined }, { body: 'Order more seed trays', colour: 'blue', pinned: '1', intent_key: undefined });
        assert.match(posts[0].body.intent_key, /^00000000-0000-4000-8\d{3}-\d{12}$/, 'the intent key is the composer\'s crypto.randomUUID()');
        payloadFixture('note-create', posts[0].body);
        assert.equal(await tab.inputValue('[data-note-composer] textarea[name=body]'), '', 'the composer is cleared');
        for (const when of ['after the redirect', 'after a reload']) {
            assert.deepEqual(await noteBodies(tab), ['Order more seed trays'], when);
            assert.equal(await tab.getAttribute('[data-note="1"]', 'data-colour'), 'blue', when);
            assert.equal(await tab.locator('[data-note="1"] [aria-label="Pinned"]').count(), 1, `${when}: pinned`);
            if (when === 'after the redirect') await reload();
        }
        if (accountShots) await accountShot(tab, 'notes');
    });
});

test('Notes, add: a blank note is refused with the error inline on the note field, and nothing is added', async () => {
    const wall = notesWall();
    await accountPage(wall, async ({ tab, posts }) => {
        await tab.getByRole('textbox', { name: 'New note', exact: true }).fill(' ');
        await tab.getByRole('button', { name: 'Add note', exact: true }).click();
        await tab.waitForSelector('#note-body-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#note-body-error'), 'The body field is required.');
        assert.equal(wall.store.notes.length, 0);
        assert.equal(await tab.locator('[data-sonner-toast]').count(), 0, 'no success toast');
    });
});

test('Notes, edit: Edit opens the note\'s form; a new body and colour are saved with the version shown and a fresh intent key; the card shows them after a reload', async () => {
    const wall = { ...notesWall(), seed: 2 };
    wall.store.notes.push({ id: 1, body: 'Call the supplier', colour: 'yellow', pinned: false, version: 3, intent: 'seed' });
    wall.store.next = 2;
    await accountPage(wall, async ({ tab, posts, toast, reload, settle }) => {
        await tab.getByRole('button', { name: 'Actions for note 1' }).click();
        await tab.getByRole('menuitem', { name: 'Edit' }).click();
        await tab.locator('#note-1-body').fill('Call the supplier on Monday');
        await tab.getByRole('combobox', { name: 'Colour' }).last().click();
        await tab.getByRole('option', { name: 'Green' }).click();
        await tab.locator('[data-note-editor]').getByRole('button', { name: 'Save', exact: true }).click();
        assert.equal(await toast('Note saved'), 1);
        await settle(1);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'PUT', '/@acme/notes/1']);
        assert.deepEqual({ ...posts[0].body, intent_key: undefined }, { expected_version: '3', intent_key: undefined, body: 'Call the supplier on Monday', colour: 'green' });
        assert.match(posts[0].body.intent_key, /^00000000-0000-4000-8\d{3}-\d{12}$/, 'a fresh intent key from crypto.randomUUID()');
        payloadFixture('note-update', posts[0].body);
        await tab.locator('[data-note-editor]').waitFor({ state: 'detached' });
        for (const when of ['after the redirect', 'after a reload']) {
            assert.deepEqual(await noteBodies(tab), ['Call the supplier on Monday'], when);
            assert.equal(await tab.getAttribute('[data-note="1"]', 'data-colour'), 'green', when);
            if (when === 'after the redirect') await reload();
        }
        assert.equal(wall.store.notes[0].version, 4);
    });
});

test('Notes, delete: Delete asks first, then sends the version shown and an intent key; the toast shows and the note is gone after a reload', async () => {
    const wall = { ...notesWall(), seed: 3 };
    wall.store.notes.push({ id: 1, body: 'Call the supplier', colour: 'yellow', pinned: false, version: 2, intent: 'seed' }, { id: 2, body: 'Water the seedlings', colour: 'pink', pinned: false, version: 1, intent: 'seed-2' });
    wall.store.next = 3;
    await accountPage(wall, async ({ tab, posts, toast, reload, settle }) => {
        await tab.getByRole('button', { name: 'Actions for note 1' }).click();
        await tab.getByRole('menuitem', { name: 'Delete' }).click();
        await tab.getByRole('alertdialog').getByRole('button', { name: 'Delete note', exact: true }).click();
        assert.equal(await toast('Note deleted'), 1);
        await settle(1);
        assert.deepEqual([posts.length, posts[0].method, posts[0].url], [1, 'DELETE', '/@acme/notes/1']);
        assert.deepEqual({ ...posts[0].body, intent_key: undefined }, { expected_version: 2, intent_key: undefined });
        assert.match(posts[0].body.intent_key, /^00000000-0000-4000-8\d{3}-\d{12}$/, 'an intent key from crypto.randomUUID()');
        payloadFixture('note-delete', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.deepEqual(await noteBodies(tab), ['Water the seedlings'], when);
            if (when === 'after the redirect') await reload();
        }
    });
});

// ── People's forms filled and sent as a person would (people.html: the real Inertia and @inertiaui/modal-vue, with the shell's Toaster) ────────────────────
// People is the base page; Change role, Invite member and Make owner open over it as server-driven modals from its own descriptors; Suspend, Reinstate, Remove,
// Resend and Revoke are its row actions (each asks first where Concierge gives it a confirm); Delete workspace is a page of its own. run.mjs is the server, at the
// app's own addresses: it answers each page, modal and write from `store`, refuses what the controllers' validation or Concierge would, and answers a write with
// the page its redirect lands on. The rows' actions follow Concierge's MembersIndex as PeopleTable reshapes them (keys, labels, methods, confirms); the
// PHP side posts each recorded payload to the real routes (tests/PeopleFormPayloadsCheck.php).
const peopleWorkspace = 'Northwind Farms';
const peopleAt = (suffix = '') => `/@northwind/settings/people${suffix}`;
function peopleStore() {
    return {
        version: 1,
        owner: 1,
        members: [
            { id: 1, name: 'Robin Park', email: 'robin@example.com', role: 'owner', status: 'active' },
            { id: 2, name: 'Ana Lopez', email: 'ana@example.com', role: 'member', status: 'active' },
            { id: 3, name: 'Kai Moreno', email: 'kai@example.com', role: 'member', status: 'suspended' },
        ],
        invitations: [{ id: '01JC0000000000000000000001', email: 'sam@example.com', role: 'member', sent: '2026-11-02T09:00:00+00:00', expires: '2026-11-16T09:00:00+00:00' }],
        password: 'correct horse battery staple',
        deleted: false,
        link: null,
        sent: 0,
        // A role Concierge refuses on this save (one the viewer could pick, refused by the time it is sent): the refusal #823 puts on the role field.
        refuse: null,
    };
}
const peopleRoles = [{ value: 'admin', label: 'Admin' }, { value: 'member', label: 'Member' }];
const roleLabel = (role) => ({ owner: 'Owner', admin: 'Admin', member: 'Member', support: 'Support' })[role];
const peopleColumn = (key, label, more = {}) => ({ key, label, sortable: false, hideable: false, hiddenByDefault: false, align: null, srOnlyLabel: false, sortBy: null, ...more });
// A member row's actions as Concierge offers them to the owner (MembersIndex::memberRow, PeopleTable::action): none on the owner's own row.
function memberActions(store, m) {
    if (m.id === store.owner) return [];
    const at = peopleAt(`/members/${m.id}`);
    const active = m.status === 'active';
    return [
        active && { key: 'change-role', label: 'Change role', url: at, method: 'put', destructive: false, confirm: null },
        active && { key: 'suspend', label: 'Suspend', url: `${at}/suspend`, method: 'post', destructive: false, confirm: { title: `Suspend ${m.name}?`, description: 'They lose access until reinstated. Their roles are kept.', label: 'Suspend member' } },
        !active && { key: 'reinstate', label: 'Reinstate', url: `${at}/reinstate`, method: 'post', destructive: false, confirm: { title: `Reinstate ${m.name}?`, description: 'They get their access and roles back.', label: 'Reinstate member' } },
        active && { key: 'transfer-ownership', label: 'Make owner', url: `${at}/transfer`, method: 'post', destructive: false, confirm: { title: `Transfer ownership to ${m.name}?`, description: 'They become the owner and you become an admin.', label: 'Transfer ownership' } },
        { key: 'remove', label: 'Remove', url: at, method: 'delete', destructive: true, confirm: { title: `Remove ${m.name}?`, description: 'They lose access to this workspace straight away. You can invite them again later.', label: 'Remove member' } },
    ].filter(Boolean);
}
function peopleProps(store, tab) {
    const members = tab === 'members';
    const badge = (role) => [{ value: role, label: roleLabel(role), variant: null }];
    const rows = members
        ? store.members.map((m) => ({ id: String(m.id), member: { name: m.name, email: m.email, initials: m.name.split(' ').map((w) => w[0]).join(''), avatar: null, is_you: m.id === 1, invited_as: null }, roles: badge(m.role), role: m.role, status: m.status, joined: '2026-10-01T09:00:00+00:00', actions: memberActions(store, m) }))
        : store.invitations.map((i) => ({ id: i.id, email: i.email, roles: badge(i.role), role: i.role, status: 'pending', invitedBy: 'Robin Park', sent: i.sent, expires: i.expires, actions: [
            { key: 'resend', label: 'Resend', url: peopleAt(`/invitations/${i.id}`), method: 'put', destructive: false, confirm: null },
            { key: 'revoke', label: 'Revoke', url: peopleAt(`/invitations/${i.id}`), method: 'delete', destructive: true, confirm: { title: `Revoke the invitation to ${i.email}?`, description: 'The link stops working. You can invite them again later.', label: 'Revoke invitation' } },
        ] }));
    const columns = members
        ? [peopleColumn('member', 'Member'), peopleColumn('roles', 'Role'), peopleColumn('status', 'Status', { hideable: true, hiddenByDefault: true }), peopleColumn('joined', 'Joined', { date: 'date' }), peopleColumn('actions', 'Actions', { align: 'end', srOnlyLabel: true })]
        : [peopleColumn('email', 'Email'), peopleColumn('roles', 'Role'), peopleColumn('invitedBy', 'Invited by'), peopleColumn('sent', 'Sent', { date: 'date' }), peopleColumn('expires', 'Expires', { date: 'date' }), peopleColumn('actions', 'Actions', { align: 'end', srOnlyLabel: true })];
    const tabs = [['members', 'Members', store.members.length], ['invitations', 'Invitations', store.invitations.length]].map(([key, label, count]) => ({ key, label, url: key === 'members' ? peopleAt() : peopleAt('?tab=invitations'), count, current: key === tab }));
    const link = store.link;
    store.link = null;
    return {
        title: 'People',
        layout: { title: 'People' },
        tab,
        table: { rows, columns, sort: null, search: null, filters: [], tabs, bulkActions: [], pagination: { page: 1, perPage: 25, total: rows.length, from: rows.length ? 1 : 0, to: rows.length, lastPage: 1 }, emptyState: null },
        invite: { label: 'Invite member', url: peopleAt('/invitations'), method: 'get' },
        roles: peopleRoles,
        invite_link: link,
    };
}
const peopleRefused = (field, message) => ({ errors: { [field]: message } });
// The server: a page or modal for each GET, a write for the rest, as SessionUi's controllers answer them.
function peopleServe(store, method, at, body, posts) {
    const member = (id) => store.members.find((m) => m.id === Number(id));
    const ok = (to, flash = {}) => ({ to, flash });
    let match;
    if (method === 'GET') {
        if (at.pathname === peopleAt()) return { component: 'Workspace/Settings/People/Page', props: peopleProps(store, at.searchParams.get('tab') === 'invitations' ? 'invitations' : 'members') };
        if (at.pathname === peopleAt('/invitations')) return { component: 'Workspace/Settings/People/Invite/Page', props: { title: 'Invite member', roles: peopleRoles, submit: { label: 'Send invitation', url: peopleAt('/invitations'), method: 'post' } } };
        if ((match = at.pathname.match(/^\/@northwind\/settings\/people\/members\/(\d+)$/))) {
            const m = member(match[1]);
            return { component: 'Workspace/Settings/People/ChangeRole/Page', props: { title: `Change role for ${m.name}`, role: m.role, roles: peopleRoles, submit: { label: 'Save', url: peopleAt(`/members/${m.id}`), method: 'put' } } };
        }
        if ((match = at.pathname.match(/^\/@northwind\/settings\/people\/members\/(\d+)\/transfer$/))) {
            const m = member(match[1]);
            return { component: 'Workspace/Settings/People/Transfer/Page', props: { title: `Transfer ownership to ${m.name}`, membership: m.id, recipient: m.name, workspace_name: peopleWorkspace, demoted_to: 'Admin', submit: { label: 'Transfer ownership', url: '/@northwind/settings/ownership', method: 'put' } } };
        }
        if (at.pathname === '/@northwind/settings/delete') return { component: 'Workspace/Settings/Delete/Page', props: { title: 'Delete workspace', layout: { title: 'Delete workspace' }, workspace_name: peopleWorkspace, members: store.members.filter((m) => m.status === 'active').length, submit: { label: 'Delete workspace', url: '/@northwind/settings/delete', method: 'delete', destructive: true }, confirms_password: true, removes: ['settings', 'invitations'] } };
        if (at.pathname === '/me/workspaces') return { component: 'Workspace/Index/Page', props: { shell: { switcher: { current: null, choices: [{ id: 2, name: 'Globex Labs', url: '/@globex', current: false }] } }, title: 'Your workspaces', layout: { title: 'Your workspaces' }, workspaces: [{ id: 2, name: 'Globex Labs', initials: 'GL', role: 'Member', members: 4, current: false, leave: null }, ...(store.deleted ? [] : [{ id: 1, name: peopleWorkspace, initials: 'NF', role: 'Owner', members: store.members.length, current: true, leave: null }])], invitations: [], create: null } };
        throw new Error(`no page at ${at.pathname}`);
    }
    posts.push({ url: at.pathname, method, body });
    // back(): inertiaui/modal's Redirector, the modal's base page when the request names one, else the page it was sent from.
    const back = 'back';
    if ((match = at.pathname.match(/^\/@northwind\/settings\/people\/members\/(\d+)(\/suspend|\/reinstate)?$/))) {
        const m = member(match[1]);
        if (match[2] === '/suspend') m.status = 'suspended';
        else if (match[2] === '/reinstate') m.status = 'active';
        else if (method === 'DELETE') store.members = store.members.filter((x) => x !== m);
        else {
            // MemberController::update: `role` required; Concierge refuses a role the viewer may not give (on errors.role since #823).
            if (!body.role) return { ...peopleRefused('role', 'The role field is required.'), to: null };
            if (!peopleRoles.some((r) => r.value === body.role) || store.refuse === body.role) return { ...peopleRefused('role', 'You cannot grant the [admin] role.'), to: null };
            m.role = body.role;
        }
        return ok(peopleAt());
    }
    if (at.pathname === peopleAt('/invitations') && method === 'POST') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(body.email ?? ''))) return { ...peopleRefused('email', 'Enter a valid email address.'), to: null };
        if (!peopleRoles.some((r) => r.value === body.role) || store.refuse === body.role) return { ...peopleRefused('role', 'You cannot grant the [admin] role.'), to: null };
        store.invitations.push({ id: '01JC0000000000000000000002', email: body.email, role: body.role, sent: '2026-11-09T10:00:00+00:00', expires: '2026-11-23T10:00:00+00:00' });
        store.link = 'https://kit.test/invitations/new-link-token-0002';
        return ok(peopleAt());
    }
    if ((match = at.pathname.match(/^\/@northwind\/settings\/people\/invitations\/(\w{26})$/))) {
        const invitation = store.invitations.find((i) => i.id === match[1]);
        if (method === 'DELETE') {
            store.invitations = store.invitations.filter((i) => i !== invitation);
            return ok(back, { toast: { id: `revoked-${Math.random()}`, type: 'success', message: 'Invitation revoked.', style: 'plain', duration: 4000 } });
        }
        invitation.sent = '2026-11-09T10:00:00+00:00';
        store.link = `https://kit.test/invitations/resent-link-${++store.sent}`;
        return ok(back);
    }
    if (at.pathname === '/@northwind/settings/ownership') {
        const errors = {};
        if (body.confirmation !== peopleWorkspace) errors.confirmation = 'Type the workspace name exactly to confirm.';
        if (body.password !== store.password) errors.password = 'That password is not right.';
        if (Object.keys(errors).length) return { errors, to: null };
        store.members.find((m) => m.id === store.owner).role = 'admin';
        store.owner = Number(body.membership);
        member(body.membership).role = 'owner';
        return ok(peopleAt());
    }
    if (at.pathname === '/@northwind/settings/delete') {
        const errors = {};
        if (body.confirmation !== peopleWorkspace) errors.confirmation = 'Type the workspace name exactly to confirm.';
        if (body.password !== store.password) errors.password = 'That password is not right.';
        if (Object.keys(errors).length) return { errors, to: null };
        store.deleted = true;
        return ok('/me/workspaces');
    }
    throw new Error(`no write at ${method} ${at.pathname}`);
}
const peopleModals = ['Workspace/Settings/People/ChangeRole/Page', 'Workspace/Settings/People/Invite/Page', 'Workspace/Settings/People/Transfer/Page'];
async function peoplePage(store, start, run) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        const tab = await context.newPage();
        const pageErrors = [];
        tab.on('pageerror', error => pageErrors.push(error.message));
        const posts = [];
        const html = readFileSync(path.join(here, 'dist-people/people.html'), 'utf8');
        const pageObject = (component, props, url, errors = {}, flash = {}) => ({ component, props: { ...props, errors }, url, version: null, flash, clearHistory: false, encryptHistory: false });
        // The page the last full visit showed: where a write with no Referer lands back.
        let current = start;
        // A GET as the server answers it. A modal's URL asked for by the modal package (X-InertiaUI-Modal) is the modal alone; asked for by a plain Inertia visit
        // that names a base page (a refused save's redirect, followed with the modal's headers), it is inertiaui/modal's dual response: the base page, with the
        // modal in its `_inertiaui_modal` prop and the page's URL the modal's, so the open modal stays and takes the errors. Errors are shared to both.
        const landed = (at, headers, errors = {}, flash = {}) => {
            const { component, props } = peopleServe(store, 'GET', at, null, posts);
            const url = at.pathname + at.search;
            const base = headers['x-inertiaui-modal-base-url'];
            if (peopleModals.includes(component) && !headers['x-inertiaui-modal'] && base) {
                const page = peopleServe(store, 'GET', new URL(base), null, posts);
                return pageObject(page.component, { ...page.props, _inertiaui_modal: { component, props: { ...props, errors }, url, version: null, id: null, baseUrl: base } }, url, errors, flash);
            }
            return pageObject(component, props, url, errors, flash);
        };
        await tab.route((u) => u.pathname.startsWith('/@northwind/') || u.pathname.startsWith('/me/'), async (route) => {
            const request = route.request();
            const at = new URL(request.url());
            const headers = request.headers();
            if (!headers['x-inertia']) return route.fulfill({ status: 200, contentType: 'text/html', body: html });
            const method = request.method();
            if (method === 'GET') {
                if (!headers['x-inertiaui-modal']) current = at.pathname + at.search;
                return route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(landed(at, headers)) });
            }
            const body = request.postDataJSON() ?? {};
            const { to, flash = {}, errors = {} } = peopleServe(store, method, at, body, posts);
            // Laravel's 303, followed (as formsPage answers): the write is answered with the page it lands on. A refusal is a ValidationException, whose
            // redirect is url()->previous(), the Referer: from a modal, the modal's own URL. back() is inertiaui/modal's Redirector: the modal's base page when the
            // request names one (X-InertiaUI-Modal-Base-Url), else the Referer.
            const referer = headers.referer ? new URL(headers.referer) : new URL(current, at);
            const landing = to === null ? referer : to === 'back' ? new URL(headers['x-inertiaui-modal-base-url'] || referer.href) : new URL(to, at);
            return route.fulfill({ status: 200, headers: formsInertia, body: JSON.stringify(landed(landing, headers, errors, flash)) });
        });
        await tab.goto(`${peopleUrl}${start}`);
        const ready = () => tab.waitForFunction(() => document.querySelector('#app')?.textContent.trim());
        await ready();
        const toast = async (message) => {
            const shown = tab.locator('[data-sonner-toast]', { hasText: message });
            await shown.first().waitFor({ timeout: 5000 });
            return shown.count();
        };
        await run({ tab, posts, toast, reload: async () => { await tab.reload(); await ready(); } });
        assert.equal(await tab.locator('iframe').count(), 0, 'no error page (Inertia\'s error modal) appeared');
        assert.deepEqual(pageErrors, []);
    } finally {
        await context.close();
    }
}
const peopleRow = (tab, name) => tab.locator('tbody tr', { hasText: name });
// A row action by its accessible name, inline or behind the row's menu: People names each row by the person (its `row-label`), so the control is
// "<label> <name>" and the menu "Actions for <name>", asserted by finding them by those exact names.
async function rowAction(tab, name, label) {
    const row = peopleRow(tab, name);
    const inline = row.locator('[data-row-actions]').getByRole('button', { name: `${label} ${name}`, exact: true });
    if (await inline.count()) return inline.click();
    await row.getByRole('button', { name: `Actions for ${name}`, exact: true }).click();
    await tab.getByRole('menuitem', { name: `${label} ${name}`, exact: true }).click();
}
// The visible text only: the phone card's field label is hidden at this width.
const roleOf = async (tab, name) => (await peopleRow(tab, name).locator('[data-cell="roles"]').innerText()).trim();

test('People, Change role: a person opens Change role on a member, picks Admin and presses Save; the modal closes, the row shows Admin, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        assert.equal(await roleOf(tab, 'Ana Lopez'), 'Member');
        await rowAction(tab, 'Ana Lopez', 'Change role');
        const modal = tab.getByRole('dialog', { name: 'Change role for Ana Lopez' });
        await modal.waitFor();
        await modal.getByRole('combobox', { name: 'Role' }).click();
        await tab.getByRole('option', { name: 'Admin' }).click();
        await modal.getByRole('button', { name: 'Save' }).click();
        await modal.waitFor({ state: 'detached' });
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/members/2'), 'PUT']]);
        assert.deepEqual({ ...posts[0].body }, { role: 'admin' });
        payloadFixture('people-change-role', posts[0].body);
        assert.equal(store.members[1].role, 'admin');
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await roleOf(tab, 'Ana Lopez'), 'Admin', when);
            assert.equal(new URL(tab.url()).pathname, peopleAt(), `${when}: on People`);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('People, Change role: a role refused on save comes back inline on the Role field, the modal stays open and nothing changes', async () => {
    const store = { ...peopleStore(), refuse: 'admin' };
    await peoplePage(store, peopleAt(), async ({ tab, posts }) => {
        await rowAction(tab, 'Ana Lopez', 'Change role');
        const modal = tab.getByRole('dialog', { name: 'Change role for Ana Lopez' });
        await modal.getByRole('combobox', { name: 'Role' }).click();
        await tab.getByRole('option', { name: 'Admin' }).click();
        await modal.getByRole('button', { name: 'Save' }).click();
        await modal.locator('#change-role-error').waitFor();
        assert.equal(posts.length, 1);
        payloadFixture('people-change-role-refused', posts[0].body);
        assert.equal(await modal.locator('#change-role-error').textContent(), 'You cannot grant the [admin] role.');
        assert.equal(await modal.getByRole('combobox', { name: 'Role' }).getAttribute('aria-invalid'), 'true');
        assert.equal(await modal.isVisible(), true, 'the modal stays open with the error');
        assert.equal(store.members[1].role, 'member', 'nothing saved');
        assert.equal(await tab.locator('[data-sonner-toast]').count(), 0, 'no success toast');
    });
});

test('People, Invite member: a person opens Invite member, fills the address, role and message and presses Send invitation; the modal closes, the link shows once, and the invitation is listed, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        await tab.getByRole('link', { name: 'Invite member' }).click();
        const modal = tab.getByRole('dialog', { name: 'Invite member' });
        await modal.waitFor();
        await modal.getByRole('textbox', { name: 'Email address' }).fill('lee@example.com');
        await modal.getByRole('combobox', { name: 'Role' }).click();
        await tab.getByRole('option', { name: 'Admin' }).click();
        await modal.getByRole('textbox', { name: /Personal message/ }).fill('Welcome to the farm team.');
        if (formsShots) for (const width of [1280, 1920]) {
            await tab.setViewportSize({ width, height: 900 });
            await tab.screenshot({ path: `${formsShots}/people-invite-${width}.png` });
        }
        await modal.getByRole('button', { name: 'Send invitation' }).click();
        await modal.waitFor({ state: 'detached' });
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/invitations'), 'POST']]);
        assert.deepEqual({ ...posts[0].body }, { email: 'lee@example.com', role: 'admin', message: 'Welcome to the farm team.' });
        payloadFixture('people-invite', posts[0].body);
        assert.match(await tab.textContent('[data-invite-link]'), /https:\/\/kit\.test\/invitations\/new-link-token-0002/, 'the link is shown on the page the redirect lands on');
        await tab.getByRole('link', { name: /Invitations/ }).or(tab.getByRole('tab', { name: /Invitations/ })).first().click();
        await peopleRow(tab, 'lee@example.com').waitFor();
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await roleOf(tab, 'lee@example.com'), 'Admin', when);
            if (when === 'after the redirect') await reload();
        }
        assert.equal(await tab.locator('[data-invite-link]').count(), 0, 'the link is shown once, not again after a reload');
    });
});

test('People, Invite member: a role refused on send comes back inline on the Role field (#823), the modal stays open with what was typed and nothing is sent', async () => {
    const store = { ...peopleStore(), refuse: 'admin' };
    await peoplePage(store, peopleAt(), async ({ tab, posts }) => {
        await tab.getByRole('link', { name: 'Invite member' }).click();
        const modal = tab.getByRole('dialog', { name: 'Invite member' });
        await modal.getByRole('textbox', { name: 'Email address' }).fill('lee@example.com');
        await modal.getByRole('combobox', { name: 'Role' }).click();
        await tab.getByRole('option', { name: 'Admin' }).click();
        await modal.getByRole('button', { name: 'Send invitation' }).click();
        await modal.locator('#invite-role-error').waitFor();
        assert.equal(posts.length, 1);
        payloadFixture('people-invite-refused', posts[0].body);
        assert.equal(await modal.locator('#invite-role-error').textContent(), 'You cannot grant the [admin] role.');
        assert.equal(await modal.getByRole('combobox', { name: 'Role' }).getAttribute('aria-invalid'), 'true');
        assert.equal(await modal.getByRole('textbox', { name: 'Email address' }).inputValue(), 'lee@example.com', 'what was typed is kept');
        assert.equal(store.invitations.length, 1, 'nothing sent');
    });
});

test('People, Suspend: a person picks Suspend on a member, confirms, and the row offers Reinstate instead, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        await rowAction(tab, 'Ana Lopez', 'Suspend');
        const confirm = tab.getByRole('alertdialog', { name: 'Suspend Ana Lopez?' });
        await confirm.getByRole('button', { name: 'Suspend member' }).click();
        await confirm.waitFor({ state: 'detached' });
        await peopleRow(tab, 'Ana Lopez').getByRole('button', { name: 'Reinstate Ana Lopez', exact: true }).waitFor();
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/members/2/suspend'), 'POST']]);
        payloadFixture('people-suspend', posts[0].body);
        assert.equal(store.members[1].status, 'suspended');
        await reload();
        assert.equal(await peopleRow(tab, 'Ana Lopez').getByRole('button', { name: 'Reinstate Ana Lopez', exact: true }).count(), 1, 'after a reload: Reinstate');
        assert.equal(await peopleRow(tab, 'Ana Lopez').getByRole('button', { name: 'Change role Ana Lopez', exact: true }).count(), 0, 'after a reload: no Change role on a suspended member');
    });
});

test('People, Reinstate: a person picks Reinstate on a suspended member, confirms, and the row offers Change role again, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        await rowAction(tab, 'Kai Moreno', 'Reinstate');
        const confirm = tab.getByRole('alertdialog', { name: 'Reinstate Kai Moreno?' });
        await confirm.getByRole('button', { name: 'Reinstate member' }).click();
        await peopleRow(tab, 'Kai Moreno').getByRole('button', { name: 'Change role Kai Moreno', exact: true }).waitFor();
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/members/3/reinstate'), 'POST']]);
        payloadFixture('people-reinstate', posts[0].body);
        assert.equal(store.members[2].status, 'active');
        await reload();
        assert.equal(await peopleRow(tab, 'Kai Moreno').getByRole('button', { name: 'Change role Kai Moreno', exact: true }).count(), 1, 'after a reload: Change role');
        assert.equal(await roleOf(tab, 'Kai Moreno'), 'Member', 'the role was kept');
    });
});

test('People, Remove: a person picks Remove on a member, confirms in the danger dialog, and the member is gone, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        await rowAction(tab, 'Ana Lopez', 'Remove');
        const confirm = tab.getByRole('alertdialog', { name: 'Remove Ana Lopez?' });
        await confirm.getByRole('button', { name: 'Remove member' }).click();
        await peopleRow(tab, 'Ana Lopez').waitFor({ state: 'detached' });
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/members/2'), 'DELETE']]);
        payloadFixture('people-remove', posts[0].body);
        await reload();
        assert.equal(await peopleRow(tab, 'Ana Lopez').count(), 0, 'after a reload: gone');
        assert.equal(await peopleRow(tab, 'Kai Moreno').count(), 1, 'the others stay');
    });
});

test('People, Resend: on the Invitations tab a person presses Resend; the fresh link shows, the tab stays, and the invitation shows its new sent date, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt('?tab=invitations'), async ({ tab, posts, reload }) => {
        const sent = async () => (await peopleRow(tab, 'sam@example.com').locator('[data-cell="sent"]').innerText()).trim();
        assert.equal(await sent(), 'Nov 2, 2026');
        await rowAction(tab, 'sam@example.com', 'Resend');
        await tab.waitForSelector('[data-invite-link]');
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/invitations/01JC0000000000000000000001'), 'PUT']]);
        payloadFixture('people-resend', posts[0].body);
        assert.match(await tab.textContent('[data-invite-link]'), /resent-link-1/);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(new URL(tab.url()).search, '?tab=invitations', `${when}: still on Invitations`);
            assert.equal(await sent(), 'Nov 9, 2026', when);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('People, Revoke: on the Invitations tab a person picks Revoke, confirms, sees the toast, and the invitation is gone, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt('?tab=invitations'), async ({ tab, posts, toast, reload }) => {
        await rowAction(tab, 'sam@example.com', 'Revoke');
        const confirm = tab.getByRole('alertdialog', { name: /Revoke the invitation to sam@example\.com\?/ });
        await confirm.getByRole('button', { name: 'Revoke invitation' }).click();
        assert.equal(await toast('Invitation revoked.'), 1);
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [[peopleAt('/invitations/01JC0000000000000000000001'), 'DELETE']]);
        payloadFixture('people-revoke', posts[0].body);
        assert.equal(store.invitations.length, 0);
        await reload();
        assert.equal(await peopleRow(tab, 'sam@example.com').count(), 0, 'after a reload: gone');
    });
});

const transferOpen = async (tab) => {
    await rowAction(tab, 'Ana Lopez', 'Make owner');
    const modal = tab.getByRole('dialog', { name: 'Transfer ownership to Ana Lopez' });
    await modal.waitFor();
    return modal;
};
test('People, Transfer ownership: a person picks Make owner, types the workspace\'s name and their password and presses Transfer ownership; the modal closes and Ana is the owner, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts, reload }) => {
        const modal = await transferOpen(tab);
        assert.match(await modal.locator('[data-transfer-warning]').textContent(), /Ana Lopez becomes the owner and you become Admin/);
        await modal.getByRole('textbox', { name: 'Type Northwind Farms to confirm' }).fill('Northwind Farms');
        await modal.getByLabel('Your password').fill('correct horse battery staple');
        await modal.getByRole('button', { name: 'Transfer ownership' }).click();
        await modal.waitFor({ state: 'detached' });
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [['/@northwind/settings/ownership', 'PUT']]);
        assert.deepEqual({ ...posts[0].body }, { membership: '2', confirmation: 'Northwind Farms', password: 'correct horse battery staple' });
        payloadFixture('people-transfer', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            assert.equal(await roleOf(tab, 'Ana Lopez'), 'Owner', when);
            assert.equal(await roleOf(tab, 'Robin Park'), 'Admin', when);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('People, Transfer ownership: a wrong password comes back inline on the password field, the modal stays open and nothing changes', async () => {
    const store = peopleStore();
    await peoplePage(store, peopleAt(), async ({ tab, posts }) => {
        const modal = await transferOpen(tab);
        await modal.getByRole('textbox', { name: 'Type Northwind Farms to confirm' }).fill('Northwind Farms');
        await modal.getByLabel('Your password').fill('not my password');
        await modal.getByRole('button', { name: 'Transfer ownership' }).click();
        await modal.locator('#transfer-password-error').waitFor();
        assert.equal(posts.length, 1);
        assert.equal(await modal.locator('#transfer-password-error').textContent(), 'That password is not right.');
        if (formsShots) await tab.screenshot({ path: `${formsShots}/people-transfer-error-1280.png` });
        assert.equal(await modal.getByLabel('Your password').getAttribute('aria-invalid'), 'true');
        assert.equal(await modal.locator('#transfer-confirmation-error').count(), 0, 'the typed name was right: no error on it');
        assert.equal(store.owner, 1, 'nothing changed');
    });
});

test('Delete workspace: the owner types the workspace\'s name and their password and presses Delete workspace; the browser lands on the workspace list without it, also after a reload', async () => {
    const store = peopleStore();
    await peoplePage(store, '/@northwind/settings/delete', async ({ tab, posts, reload }) => {
        assert.match(await tab.textContent('[data-delete-warning]'), /Deleting Northwind Farms removes it for all 2 members, with its settings and invitations/);
        await tab.getByRole('textbox', { name: 'Type Northwind Farms to confirm' }).fill('Northwind Farms');
        await tab.getByLabel('Your password').fill('correct horse battery staple');
        await tab.getByRole('button', { name: 'Delete workspace' }).click();
        await tab.waitForURL(/\/me\/workspaces$/);
        assert.deepEqual(posts.map(({ url, method }) => [url, method]), [['/@northwind/settings/delete', 'DELETE']]);
        assert.deepEqual({ ...posts[0].body }, { confirmation: 'Northwind Farms', password: 'correct horse battery staple' });
        payloadFixture('workspace-delete', posts[0].body);
        for (const when of ['after the redirect', 'after a reload']) {
            await tab.getByText('Globex Labs').first().waitFor();
            assert.equal(await tab.getByText('Northwind Farms').count(), 0, `${when}: the deleted workspace is not listed`);
            if (when === 'after the redirect') await reload();
        }
    });
});

test('Delete workspace: a name that does not match comes back inline on the confirmation field and nothing is deleted', async () => {
    const store = peopleStore();
    await peoplePage(store, '/@northwind/settings/delete', async ({ tab, posts }) => {
        await tab.getByRole('textbox', { name: 'Type Northwind Farms to confirm' }).fill('Northwind');
        await tab.getByLabel('Your password').fill('correct horse battery staple');
        await tab.getByRole('button', { name: 'Delete workspace' }).click();
        await tab.waitForSelector('#delete-confirmation-error');
        assert.equal(posts.length, 1);
        assert.equal(await tab.textContent('#delete-confirmation-error'), 'Type the workspace name exactly to confirm.');
        assert.equal(await tab.getAttribute('#delete-confirmation', 'aria-invalid'), 'true');
        assert.equal(new URL(tab.url()).pathname, '/@northwind/settings/delete', 'still on the page');
        assert.equal(store.deleted, false, 'nothing deleted');
    });
});

test('People: each row\'s controls are named by the person, not the row\'s number (People passes DataTable its row-label)', async () => {
    await peoplePage(peopleStore(), peopleAt(), async ({ tab }) => {
        const ana = peopleRow(tab, 'Ana Lopez');
        assert.equal(await ana.getByRole('button', { name: 'Change role Ana Lopez', exact: true }).count(), 1);
        assert.equal(await ana.getByRole('button', { name: 'Actions for Ana Lopez', exact: true }).count(), 1);
        assert.equal(await tab.getByRole('button', { name: /row \d/ }).count(), 0, 'no control named by a row number');
        await tab.getByRole('link', { name: /Invitations/ }).or(tab.getByRole('tab', { name: /Invitations/ })).first().click();
        const sam = peopleRow(tab, 'sam@example.com');
        await sam.waitFor();
        assert.equal(await sam.getByRole('button', { name: 'Resend sam@example.com', exact: true }).count(), 1, 'an invitation is named by its address');
    });
});

test('People\'s modals on the real @inertiaui/modal-vue: each is the package\'s native <dialog>, named by its title (ModalPanel names a <dialog>, not only a role=dialog)', async () => {
    await peoplePage(peopleStore(), peopleAt(), async ({ tab }) => {
        for (const [open, title] of [[() => rowAction(tab, 'Ana Lopez', 'Change role'), 'Change role for Ana Lopez'], [() => tab.getByRole('link', { name: 'Invite member' }).click(), 'Invite member'], [() => rowAction(tab, 'Ana Lopez', 'Make owner'), 'Transfer ownership to Ana Lopez']]) {
            await open();
            const modal = tab.getByRole('dialog', { name: title });
            await modal.waitFor();
            assert.equal(await modal.evaluate((el) => el.tagName), 'DIALOG', `${title}: the package's native dialog`);
            assert.equal(await modal.evaluate((el) => document.getElementById(el.getAttribute('aria-labelledby'))?.textContent?.trim()), title, `${title}: named by its title`);
            await modal.getByRole('button', { name: 'Cancel' }).click();
            await modal.waitFor({ state: 'detached' });
        }
    });
});

// ── Settings › Security, Add a passkey (auth.html?auth=Workspace/Account/Security, on the real Inertia) ─────────────────────────────────────────────────────
// Fortify guards its passkey registration with password.confirm by default, so the page asks for the password inline and confirms it first. The test is the
// server: Fortify's confirmation (201, or a 422 on password), the options (a 423 until the password is confirmed, as password.confirm answers JSON), the store,
// and the page reload. Only the browser's WebAuthn boundary is stubbed: navigator.credentials.create (no authenticator here), and
// PublicKeyCredential.parseCreationOptionsFromJSON where the browser lacks it.
async function securityPasskeys(run, { confirmation = true, page: auth = 'Workspace/Account/Security' } = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    try {
        await context.addInitScript(() => {
            window.PublicKeyCredential = window.PublicKeyCredential ?? function PublicKeyCredential() {};
            if (typeof window.PublicKeyCredential.parseCreationOptionsFromJSON !== 'function') window.PublicKeyCredential.parseCreationOptionsFromJSON = (json) => json;
            window.kitCreateCalls = 0;
            navigator.credentials.create = async () => {
                window.kitCreateCalls++;
                return { toJSON: () => ({ id: 'Y3JlZA', rawId: 'Y3JlZA', type: 'public-key', response: { clientDataJSON: 'e30', attestationObject: 'o2Nmb3Jt' } }) };
            };
        });
        const tab = await context.newPage();
        const pageErrors = [];
        tab.on('pageerror', error => pageErrors.push(error.message));
        const requests = [];
        // With the confirmation off (fortify-options.passkeys.confirmPassword false), Fortify serves the options without it.
        const server = { confirmed: !confirmation, passkeys: [{ id: 7, name: 'MacBook passkey', created: '2026-10-01T09:00:00+00:00', last_used: null }] };
        await tab.route('**/user/confirm-password', async (route) => {
            const body = route.request().postDataJSON();
            requests.push(['POST', '/user/confirm-password', body]);
            if (body?.password !== 'correct horse battery staple') return route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'The provided password was incorrect.', errors: { password: ['The provided password was incorrect.'] } }) });
            server.confirmed = true;
            await route.fulfill({ status: 201, contentType: 'application/json', body: '""' });
        });
        await tab.route('**/user/passkeys/options', async (route) => {
            requests.push(['GET', '/user/passkeys/options']);
            if (!server.confirmed) return route.fulfill({ status: 423, contentType: 'application/json', body: JSON.stringify({ message: 'Password confirmation required.' }) });
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ options: { challenge: 'Y2hhbGxlbmdl', rp: { name: 'Kit', id: 'localhost' }, user: { id: 'MQ', name: 'robin@example.test', displayName: 'Robin Vale' }, pubKeyCredParams: [{ type: 'public-key', alg: -7 }] } }) });
        });
        await tab.route('**/user/passkeys', async (route) => {
            const body = route.request().postDataJSON();
            requests.push(['POST', '/user/passkeys', body]);
            if (!server.confirmed) return route.fulfill({ status: 423, contentType: 'application/json', body: '{}' });
            server.passkeys.push({ id: 8, name: body.name, created: '2026-11-09T10:00:00+00:00', last_used: null });
            await route.fulfill({ status: 201, contentType: 'application/json', body: '{"id":8}' });
        });
        // The page's reload after a passkey is added (router.reload({ only: ['passkeys', 'events'] })).
        await tab.route('**/me/security', async (route) => {
            requests.push(['GET', '/me/security']);
            const page = await tab.evaluate(() => window.kitInitialPage);
            const passkeys = { ...page.props.passkeys, items: server.passkeys };
            await route.fulfill({ status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ ...page, props: { ...page.props, passkeys } }) });
        });
        // The passkey offer's close once a passkey is added (router.delete(dismiss_url)): the page again, with the offer gone.
        await tab.route('**/me/passkey-offer', async (route) => {
            requests.push([route.request().method(), '/me/passkey-offer']);
            const page = await tab.evaluate(() => window.kitInitialPage);
            await route.fulfill({ status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', Vary: 'X-Inertia' }, body: JSON.stringify({ ...page, props: { ...page.props, passkeyOffer: null } }) });
        });
        await tab.goto(`${authUrl}auth.html?auth=${auth}${confirmation ? '' : '&noconfirm'}`);
        await tab.waitForSelector('[data-passkey-add]');
        await run({ tab, requests, server });
        assert.deepEqual(pageErrors, []);
    } finally {
        await context.close();
    }
}
const passkeyNames = (tab) => tab.locator('[data-passkey] > span > .font-medium').allTextContents();

test('Security, Add a passkey: the password is asked for inline and confirmed with Fortify first; then the browser creates the passkey, it is stored and listed', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        const add = tab.locator('[data-passkey-add-form]');
        await add.getByLabel('Your password').fill('correct horse battery staple');
        await add.getByRole('button', { name: 'Add a passkey' }).click();
        await tab.locator('[data-passkey="8"]').waitFor();
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['POST', '/user/confirm-password'], ['GET', '/user/passkeys/options'], ['POST', '/user/passkeys'], ['GET', '/me/security']], 'confirmed, then registered, then the list reloaded; never a 423');
        assert.deepEqual(requests[0][2], { password: 'correct horse battery staple' });
        assert.equal(requests[2][2].credential.id, 'Y3JlZA', 'the browser\'s credential is posted');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 1);
        assert.deepEqual(await passkeyNames(tab), ['MacBook passkey', requests[2][2].name]);
        assert.equal(await tab.locator('[data-passkey-add-failed]').count(), 0, 'no failure alert');
        assert.equal(await tab.inputValue('#passkey-add-password'), '', 'the password is cleared');
    });
});

test('Security, Add a passkey: a wrong password is refused inline on its field; the browser is never asked, nothing is registered, and the right one then works', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        const add = tab.locator('[data-passkey-add-form]');
        await add.getByLabel('Your password').fill('not it at all');
        await add.getByRole('button', { name: 'Add a passkey' }).click();
        await tab.waitForSelector('#passkey-add-password-error');
        assert.equal(await tab.textContent('#passkey-add-password-error'), 'The provided password was incorrect.');
        assert.equal(await tab.getAttribute('#passkey-add-password', 'aria-invalid'), 'true');
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['POST', '/user/confirm-password']], 'only the confirmation was asked');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 0, 'no passkey prompt');
        assert.equal(await tab.locator('[data-passkey-add-failed]').count(), 0, 'the error is on the field, not the failure alert');
        await add.getByLabel('Your password').fill('correct horse battery staple');
        await add.getByRole('button', { name: 'Add a passkey' }).click();
        await tab.locator('[data-passkey="8"]').waitFor();
        assert.equal(await tab.locator('#passkey-add-password-error').count(), 0, 'the error clears');
    });
});

test('Security, Add a passkey with the host\'s password confirmation off (no confirm_url): no password is asked for, and the browser creates the passkey straight away', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        assert.equal(await tab.locator('#passkey-add-password').count(), 0, 'no password field');
        await tab.locator('[data-passkey-add-form]').getByRole('button', { name: 'Add a passkey' }).click();
        await tab.locator('[data-passkey="8"]').waitFor();
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['GET', '/user/passkeys/options'], ['POST', '/user/passkeys'], ['GET', '/me/security']], 'registered without a confirmation');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 1, 'the browser was asked once');
        assert.equal(await tab.locator('[data-passkey-add-failed]').count(), 0);
        assert.ok(requests[1][2].name, 'the credential is posted with a name');
        assert.deepEqual(await passkeyNames(tab), ['MacBook passkey', requests[1][2].name]);
    }, { confirmation: false });
});

// The post-sign-up passkey offer (core's PasskeyOffer) on the same Fortify endpoints: its registration is behind the same password confirmation, so the offer
// confirms the password first, as Security does.
test('PasskeyOffer, Add a passkey: the password is asked for in the offer and confirmed with Fortify first; then the browser creates the passkey, it is stored and the offer closes', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        const offer = tab.locator('[data-passkey-offer]');
        await offer.getByLabel('Your password').fill('correct horse battery staple');
        await offer.getByRole('button', { name: 'Add a passkey' }).click();
        await offer.waitFor({ state: 'detached' });
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['POST', '/user/confirm-password'], ['GET', '/user/passkeys/options'], ['POST', '/user/passkeys'], ['DELETE', '/me/passkey-offer']], 'confirmed, then registered, then the offer dismissed; never a 423');
        assert.deepEqual(requests[0][2], { password: 'correct horse battery staple' });
        assert.equal(requests[2][2].credential.id, 'Y3JlZA', 'the browser\'s credential is posted');
        assert.ok(requests[2][2].name, 'with a name');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 1);
    }, { page: 'PasskeyOffer' });
});

test('PasskeyOffer, Add a passkey: a wrong password is refused inline on its field; the browser is never asked, nothing is registered, and the offer stays', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        const offer = tab.locator('[data-passkey-offer]');
        await offer.getByLabel('Your password').fill('not it at all');
        await offer.getByRole('button', { name: 'Add a passkey' }).click();
        await tab.waitForSelector('#passkey-offer-password-error');
        assert.equal(await tab.textContent('#passkey-offer-password-error'), 'The provided password was incorrect.');
        assert.equal(await tab.getAttribute('#passkey-offer-password', 'aria-invalid'), 'true');
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['POST', '/user/confirm-password']], 'only the confirmation was asked');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 0, 'no passkey prompt');
        assert.equal(await tab.locator('[data-passkey-failed]').count(), 0, 'the error is on the field, not the failure alert');
        assert.equal(await offer.count(), 1, 'the offer stays open');
    }, { page: 'PasskeyOffer' });
});

test('PasskeyOffer with the host\'s password confirmation off (no confirm_url): no password is asked for, and the browser creates the passkey straight away', async () => {
    await securityPasskeys(async ({ tab, requests }) => {
        const offer = tab.locator('[data-passkey-offer]');
        assert.equal(await tab.locator('#passkey-offer-password').count(), 0, 'no password field');
        await offer.getByRole('button', { name: 'Add a passkey' }).click();
        await offer.waitFor({ state: 'detached' });
        assert.deepEqual(requests.map(([method, url]) => [method, url]), [['GET', '/user/passkeys/options'], ['POST', '/user/passkeys'], ['DELETE', '/me/passkey-offer']], 'registered without a confirmation');
        assert.equal(await tab.evaluate(() => window.kitCreateCalls), 1);
    }, { page: 'PasskeyOffer', confirmation: false });
});

// ── Alert actionsPlacement and LockedForm hideLockFooter (?alert-layout) ──────────────────────────────────────────────────────────────────────────────────────
const alertLayoutPage = async (width) => {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const tab = await context.newPage();
    await tab.goto(`${url}?alert-layout`);
    await tab.waitForSelector('[data-test-inline] [data-slot="alert-actions"]');
    return { context, tab };
};
const alertBoxes = (tab, which) => tab.evaluate((which) => {
    const root = document.querySelector(`[data-test-${which}]`);
    const box = (slot) => root.querySelector(`[data-slot="${slot}"]`).getBoundingClientRect().toJSON();
    return { title: box('alert-title'), body: box('alert-body'), actions: box('alert-actions'), sameColumn: root.querySelector('[data-slot="alert-actions"]').parentElement === root.querySelector('[data-slot="alert-body"]').parentElement };
}, which);

for (const width of [1280, 390]) {
    test(`an Alert's default actions placement stays under the body in the text column at ${width}px`, async () => {
        const { context, tab } = await alertLayoutPage(width);
        try {
            assert.equal(await tab.locator('[data-test-below] [data-actions-placement]').count(), 0, 'the default renders no inline wrapper');
            const { body, actions, sameColumn } = await alertBoxes(tab, 'below');
            assert.ok(sameColumn, 'the actions share the title and body column');
            assert.ok(actions.top >= body.bottom, 'the actions sit below the body');
        } finally {
            await context.close();
        }
    });
}

test('an inline Alert puts its actions on the right of the title and body, vertically centred, at 1280px', async () => {
    const { context, tab } = await alertLayoutPage(1280);
    try {
        const { title, body, actions } = await alertBoxes(tab, 'inline');
        assert.ok(actions.left >= Math.max(title.right, body.right), 'the actions sit to the right of the text');
        const text = (title.top + body.bottom) / 2;
        assert.ok(Math.abs((actions.top + actions.bottom) / 2 - text) <= 2, 'the actions are vertically centred on the title and body');
        if (process.env.KIT_ALERT_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_ALERT_SCREENSHOT}-1280.png`, fullPage: true });
    } finally {
        await context.close();
    }
});

test('an inline Alert stacks its actions below the body at 390px', async () => {
    const { context, tab } = await alertLayoutPage(390);
    try {
        const { body, actions } = await alertBoxes(tab, 'inline');
        assert.ok(actions.top >= body.bottom, 'the actions sit below the body on a phone');
        if (process.env.KIT_ALERT_SCREENSHOT) await tab.screenshot({ path: `${process.env.KIT_ALERT_SCREENSHOT}-390.png`, fullPage: true });
    } finally {
        await context.close();
    }
});

test('a locked FormCard and a lone FormFieldset show their notice by default and none with hideLockFooter, still locked either way', async () => {
    const { context, tab } = await alertLayoutPage(1280);
    try {
        assert.equal((await tab.textContent('[data-test-footer] [data-form-notice]')).trim(), 'Read-only while this record is closed.');
        assert.ok(await tab.getAttribute('[data-test-footer]', 'aria-describedby'), 'the area is described by its notice');
        assert.equal(await tab.locator('[data-test-no-footer] [data-form-notice]').count(), 0, 'hideLockFooter renders no notice');
        assert.equal(await tab.getAttribute('[data-test-no-footer]', 'aria-describedby'), null, 'no description points at a missing notice');
        assert.equal(await tab.getAttribute('[data-test-no-footer]', 'data-locked'), 'true', 'still locked');
        assert.equal(await tab.getAttribute('#hidden-name', 'readonly'), '', 'its controls stay read-only');
        assert.equal((await tab.textContent('[data-test-fieldset-notice] [data-form-notice]')).trim(), 'Read-only while this record is closed.', 'a lone fieldset shows the notice by default');
        assert.equal(await tab.locator('[data-test-fieldset-no-notice] [data-form-notice]').count(), 0, 'a lone fieldset with hideLockFooter renders no notice');
        assert.equal(await tab.getAttribute('[data-test-fieldset-no-notice]', 'aria-describedby'), null);
        assert.equal(await tab.getAttribute('#fieldset-hidden', 'readonly'), '', 'the lone fieldset stays read-only');
    } finally {
        await context.close();
    }
});

// ── ActionBar at phone width (?wrap-bar, `&dark`): `wrap` keeps all six actions visible on more than one row, with no compact copy and no More menu; the same
// actions without `wrap` keep the main action and a More menu as before, and their More menu keeps Cancel destructive and lines up rows without an icon with rows that have one ───────────
const wrapBar = async (theme) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        const warnings = [];
        tab.on('pageerror', error => errors.push(error.message));
        tab.on('console', message => message.type() === 'warning' && warnings.push(message.text()));
        await tab.goto(`${url}?wrap-bar${theme === 'dark' ? '&dark' : ''}`);
        await tab.waitForSelector('[data-default] [data-action-bar]');
        const wrap = tab.locator('[data-wrap] [data-action-bar]');
        assert.equal(await wrap.getAttribute('data-wrap'), 'true');
        assert.equal(await wrap.getAttribute('data-compact'), null, 'wrap never compacts');
        assert.equal(await tab.locator('[data-wrap] [data-action-layout-compact]').count(), 0, 'no compact copy is rendered');
        assert.equal(await tab.locator('[data-wrap] [data-action-menu], [data-wrap] [data-button-group-menu]').count(), 0, 'no More menu');
        const boxes = await tab.$$eval('[data-wrap] [data-action-layout-full] [data-action]', els => els.map(el => {
            const r = el.getBoundingClientRect();
            return { top: Math.round(r.top), right: r.right, visible: getComputedStyle(el).visibility === 'visible', clipped: el.scrollWidth > el.clientWidth };
        }));
        assert.equal(boxes.length, 6, 'all six actions are rendered');
        assert.ok(boxes.every(box => box.visible && !box.clipped && box.right <= 390), 'each is visible, untruncated and on screen');
        assert.ok(new Set(boxes.map(box => box.top)).size > 1, 'the actions wrap onto more than one row');
        const lead = await tab.$$eval('[data-wrap] [data-action]', els => els.map(el => el.classList.contains('primary-action')));
        assert.deepEqual(lead, [true, false, false, false, false, false], 'only the first action is the lead');
        // The default mode is unchanged: the main action, with the rest behind the More menu.
        // The stand-in menu item is reka's bare item; give it the host's shadcn item layout (a flex row, icon and label 8px apart) so the alignment is real.
        await tab.addStyleTag({ content: '[role="menuitem"] { display: flex; align-items: center; gap: 8px; }' });
        await tab.locator('[data-default] [data-button-group-menu]:visible').click();
        const menu = tab.getByRole('menu');
        await menu.waitFor();
        const rows = await menu.locator('[role="menuitem"]').evaluateAll(els => els.map(el => {
            const label = [...el.childNodes].find(node => node.nodeType === 1 && node.tagName !== 'svg' && !node.hasAttribute('data-menu-icon-spacer'));
            return { key: el.dataset.action, colour: getComputedStyle(el).color, left: Math.round(label.getBoundingClientRect().left), icon: Boolean(el.querySelector(':scope > svg')) };
        }));
        const destructive = await tab.$eval('[data-destructive-probe]', el => getComputedStyle(el).color);
        const cancel = rows.find(row => row.key === 'cancel');
        assert.equal(cancel.colour, destructive, 'Cancel keeps the destructive colour');
        assert.equal(cancel.icon, true, 'Cancel has the x default');
        assert.notEqual(rows.find(row => row.key === 'edit').colour, destructive);
        assert.ok(rows.some(row => !row.icon), 'the fixture has a row without an icon');
        assert.equal(new Set(rows.map(row => row.left)).size, 1, 'labels line up with and without an icon');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/actionbar-wrap${theme === 'dark' ? '-dark' : ''}.png`, fullPage: true });
        assert.deepEqual(warnings.filter(text => text.includes('[kit]')), [], 'a production build never warns about mixed icons');
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
test('ActionBar wrap (light): every action wraps onto rows at phone width; the default More menu keeps Cancel red and its labels aligned', () => wrapBar('light'));
test('ActionBar wrap (dark): the same under the dark tokens', () => wrapBar('dark'));

// ── The swapped card names (?card-names, `&dark`): ActionCard is the panel. At 1280px the text is on the left with the description capped at max-w-xl and the
// actions on the right, centred by default and pinned to the top with align="top"; at 390px the actions stack under the text. Slots that render nothing leave
// no wrapper, and a SummaryCard (the header band) with an empty default slot has no body. axe passes with colour contrast checked on the danger panels' title, description and buttons ──────────────────────
const cardNames = async (theme) => {
    for (const width of [1280, 390]) {
        const context = await browser.newContext({ viewport: { width, height: 1000 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?card-names${theme === 'dark' ? '&dark' : ''}`);
            await tab.waitForSelector('[data-case="empty-card"]');
            const box = (selector) => tab.$eval(selector, el => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, middle: r.top + r.height / 2, width: r.width }; });
            for (const name of ['center', 'top', 'danger', 'danger-filled']) {
                const panel = await box(`[data-case="${name}"]`);
                // The text column: the heading and its description.
                const text = await tab.$eval(`[data-case="${name}"]`, el => { const [a, b] = [el.querySelector('h3'), el.querySelector('[data-action-card-description]')].map(n => n.getBoundingClientRect()); return { top: a.top, bottom: b.bottom, right: Math.max(a.right, b.right), middle: (a.top + b.bottom) / 2 }; });
                const actions = await box(`[data-case="${name}"] [data-action-card-actions]`);
                assert.ok(actions.right <= width && panel.right <= width, `${name} at ${width}: nothing off screen`);
                if (width === 1280) {
                    assert.ok(actions.left >= text.right, `${name}: the actions are to the right of the text`);
                    assert.ok(panel.right - actions.right < 40, `${name}: the actions sit at the right edge`);
                    if (name === 'top') assert.ok(Math.abs(actions.top - text.top) < 2, 'align="top": the actions are pinned to the top');
                    else assert.ok(Math.abs(actions.middle - text.middle) < 2, `${name}: the actions are centred on the text`);
                } else {
                    assert.ok(actions.top >= text.bottom, `${name} at 390: the actions stack under the text`);
                }
            }
            const description = await box('[data-case="center"] [data-action-card-description]');
            assert.ok(description.width <= 576 + 1, `the description is capped at max-w-xl (${description.width}px)`);
            if (width === 1280) assert.ok(description.width > 500, 'the long description fills the cap at 1280');
            const body = await box('[data-case="top"] [data-action-card-body]');
            assert.ok(body.top >= (await box('[data-case="top"] [data-action-card-actions]')).bottom, 'the default slot is below the text and actions');
            assert.equal(await tab.locator('[data-case="top"] h3').textContent(), 'Export members', 'the title is a level-3 heading');
            assert.equal(await tab.locator('[data-case="bare"] [data-action-card-actions], [data-case="bare"] [data-action-card-body], [data-case="bare"] [data-action-card-description]').count(), 0, 'slots that render nothing leave no wrapper');
            assert.equal(await tab.locator('[data-case="empty-card"] [data-card-body]').count(), 0, 'a SummaryCard with an empty default slot has no body');
            assert.equal(await tab.locator('[data-case="empty-card"] [data-card-header]').count(), 1);
            await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/card-names-${width}${theme === 'dark' ? '-dark' : ''}.png`, fullPage: true });
            await tab.addScriptTag({ path: axeSource });
            const result = await tab.evaluate(async () => {
                const run = await window.axe.run(document, { elementRef: true });
                const checked = (selector) => run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.matches(selector)).length;
                return {
                    violations: run.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })),
                    details: run.violations.flatMap(v => v.nodes.map(n => `${n.target.join(' ')}: ${n.any.map(c => c.message).join('; ')}`)),
                    titles: checked('[data-action-card-tone="danger"] h3'),
                    descriptions: checked('[data-action-card-tone="danger"] [data-action-card-description]'),
                    buttons: checked('[data-action-card-tone="danger"] [data-action-card-actions] button'),
                };
            });
            if (result.violations.length) console.log(theme, width, JSON.stringify(result.violations, null, 1), result.details.join('\n'));
            assert.deepEqual(result.violations, []);
            assert.equal(result.titles, 2, `${theme}: axe checked both danger titles' contrast`);
            assert.equal(result.descriptions, 2, `${theme}: axe checked both danger descriptions' contrast`);
            assert.equal(result.buttons, 2, `${theme}: axe checked both danger buttons' contrast, quiet and filled`);
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
};
test('ActionCard panel (light): text left, actions right (centred, or pinned top), stacked at phone width; empty slots render nothing, SummaryCard has no empty body; axe with contrast on the danger tone', () => cardNames('light'));
test('ActionCard panel (dark): the same under the dark tokens', () => cardNames('dark'));

// ── ActionBar `item-class` (?item-class): each row of its More menu takes the host's classes (min-h-12, 48px), and Cancel keeps the destructive colour and the
// disabled row its dimming ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
test('ActionBar item-class: every menu row takes the host class; destructive and disabled rows keep their look', async () => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?item-class`);
        await tab.locator('[data-item-class] [data-button-group-menu]:visible, [data-item-class] [data-action-menu]:visible').first().click();
        const menu = tab.getByRole('menu');
        await menu.waitFor();
        const rows = await menu.locator('[role="menuitem"]').evaluateAll(els => els.map(el => ({ key: el.dataset.action, height: el.getBoundingClientRect().height, colour: getComputedStyle(el).color, opacity: getComputedStyle(el).opacity })));
        const destructive = await tab.$eval('[data-destructive-probe]', el => getComputedStyle(el).color);
        assert.ok(rows.length >= 3, `the menu has the bar's other actions (${rows.length})`);
        assert.ok(rows.every(row => row.height >= 48), `every row is at least 48px (${rows.map(row => row.height).join(', ')})`);
        assert.equal(rows.find(row => row.key === 'cancel').colour, destructive, 'Cancel keeps the destructive colour');
        assert.equal(rows.find(row => row.key === 'archive').opacity, '0.5', 'the disabled row keeps its dimming');
    } finally {
        await context.close();
    }
});

// ── ButtonGroup `item-class` on DropdownItem / DropdownLink rows (?item-class): the host's "gap-2 px-4 py-2" lands on each row's own control, so a button, anchor
// and Inertia-link row is the same height and padding as without it, not padded twice ──────────────────────────────────────────────────────────────────────
test('ButtonGroup item-class="gap-2 px-4 py-2": DropdownItem and DropdownLink rows keep a single padding (button, anchor, Inertia link), and DropdownModalLink', async () => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?item-class`);
        const rows = async (which) => {
            await tab.locator(`[data-group-item-class="${which}"] [data-button-group-menu]`).click();
            const menu = tab.getByRole('menu');
            await menu.waitFor();
            const found = await menu.locator('[role="menuitem"]').evaluateAll(els => els.map(el => {
                const control = el.querySelector('button, a');
                const style = getComputedStyle(el);
                return { height: el.getBoundingClientRect().height, rowPadding: [style.paddingTop, style.paddingLeft], controlPadding: [getComputedStyle(control).paddingTop, getComputedStyle(control).paddingLeft], gap: getComputedStyle(control).columnGap };
            }));
            await tab.keyboard.press('Escape');
            await menu.waitFor({ state: 'detached' });
            return found;
        };
        const plain = await rows('unset');
        const styled = await rows('set');
        assert.equal(styled.length, 7, `seven rows: DropdownItem and DropdownLink as button, anchor and Inertia link, and a DropdownModalLink (${styled.length})`);
        styled.forEach((row, i) => {
            assert.equal(row.height, plain[i].height, `row ${i} is as tall as without itemClass (${row.height} vs ${plain[i].height})`);
            assert.deepEqual(row.rowPadding, ['0px', '0px'], `row ${i}: the menu row itself is not padded`);
            assert.deepEqual(row.controlPadding, ['8px', '16px'], `row ${i}: the control has one py-2 px-4`);
            assert.equal(row.gap, '8px', `row ${i}: the host's gap-2 reaches the control`);
        });
    } finally {
        await context.close();
    }
});

// ── ActionBar `fill-all` on a pill surface (?fill-all, `&dark`): the whole group is the primary fill with the primary text and rounded-full, segments py-2, the
// first pl-3.5 and the rest (the ⌄ trigger too) px-3, semibold, with a translucent primary-text divider between segments; disabled segments still dim; axe with contrast passes;
// the default pill keeps its padding and medium labels. A GET action with intent `panel` is a button that emits ───
const fillAll = async (theme) => {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?fill-all${theme === 'dark' ? '&dark' : ''}`);
        await tab.waitForSelector('[data-fill-all] [data-action-group]');
        const probe = await tab.$eval('[data-primary-probe]', el => ({ bg: getComputedStyle(el).backgroundColor, fg: getComputedStyle(el).color }));
        const read = (selector) => tab.$eval(selector, group => {
            const style = getComputedStyle(group);
            const segments = [...group.children].map(el => { const s = getComputedStyle(el); return { action: el.hasAttribute('data-action'), pl: s.paddingLeft, pr: s.paddingRight, pt: s.paddingTop, pb: s.paddingBottom, colour: s.color, opacity: s.opacity, weight: s.fontWeight, divider: { width: s.borderRightWidth, style: s.borderRightStyle, colour: s.borderRightColor } }; });
            return { bg: style.backgroundColor, colour: style.color, radius: parseFloat(style.borderTopLeftRadius), height: group.getBoundingClientRect().height, segments };
        });
        const all = await read('[data-fill-all] [data-action-group]');
        assert.equal(all.bg, probe.bg, 'the whole group takes the primary fill');
        assert.ok(all.radius > all.height / 2 - 1, `the group is rounded-full (${all.radius}px on ${all.height}px)`);
        assert.ok(all.segments.length >= 2 && all.segments[0].action, `the group has its segments and the menu trigger (${all.segments.length})`);
        assert.ok(all.segments.every(seg => seg.colour === probe.fg), 'every segment has the primary text');
        assert.ok(all.segments.every(seg => seg.pt === '8px' && seg.pb === '8px'), 'every segment is py-2');
        assert.deepEqual([all.segments[0].pl, all.segments[0].pr], ['14px', '12px'], 'the first segment is pl-3.5');
        assert.ok(all.segments.slice(1).every(seg => seg.pl === '12px' && seg.pr === '12px'), 'the rest are px-3');
        assert.ok(all.segments.every(seg => seg.weight === '600'), `every segment keeps ButtonGroup's semibold label (${all.segments.map(seg => seg.weight)})`);
        // Computed colours come back in oklab; a canvas pixel puts both in 8-bit RGBA so they compare.
        const rgba = colour => tab.evaluate(c => { const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true }); ctx.clearRect(0, 0, 1, 1); ctx.fillStyle = c; ctx.fillRect(0, 0, 1, 1); const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data; return [r, g, b, a / 255]; }, colour);
        const fg = await rgba(probe.fg);
        for (const [i, seg] of all.segments.slice(0, -1).entries()) {
            assert.deepEqual([seg.divider.width, seg.divider.style], ['1px', 'solid'], `segment ${i} draws ButtonGroup's 1px divider`);
            const [r, g, b, a] = await rgba(seg.divider.colour);
            assert.ok(Math.max(Math.abs(r - fg[0]), Math.abs(g - fg[1]), Math.abs(b - fg[2])) <= 2, `segment ${i}'s divider is the primary text colour (${seg.divider.colour})`);
            assert.ok(Math.abs(a - 0.2) < 0.03, `segment ${i}'s divider is the outline token: the primary text at 20%, as the inverse toolbar's outline (${seg.divider.colour})`);
        }
        assert.equal(all.segments.at(-1).divider.width, '0px', 'the last segment draws no divider');
        assert.deepEqual([all.segments.at(-1).action, all.segments.at(-1).pl, all.segments.at(-1).pr], [false, '12px', '12px'], 'the menu trigger (⌄) segment is px-3, like the others');
        // dividerClass replaces the default divider colour; the divider stays 1px solid between segments and the last draws none.
        const own = await read('[data-fill-all-divider] [data-action-group]');
        const sky = await rgba(await tab.evaluate(() => { const el = document.createElement('span'); el.className = 'border-sky-300'; document.body.append(el); const c = getComputedStyle(el).borderTopColor; el.remove(); return c; }));
        for (const [i, seg] of own.segments.slice(0, -1).entries()) {
            assert.deepEqual([seg.divider.width, seg.divider.style], ['1px', 'solid'], `dividerClass: segment ${i} keeps the 1px divider`);
            assert.deepEqual(await rgba(seg.divider.colour), sky, `dividerClass: segment ${i}'s divider takes the host's colour (${seg.divider.colour})`);
        }
        assert.equal(own.segments.at(-1).divider.width, '0px', 'dividerClass: the last segment draws no divider');
        // triggerClass goes last on the ⌄ trigger: pl-3.5 pr-2 and a 20px chevron make it 42px; dividerClass="border-r-0" drops the default divider.
        // The same held to 170px, where the bar compacts: the compact layout's trigger takes triggerClass too.
        const trigger = (selector) => tab.$eval(selector, group => {
            const [lead, trigger] = group.children;
            return { lead: getComputedStyle(lead).borderRightWidth, width: trigger.getBoundingClientRect().width, menu: trigger.hasAttribute('data-action-menu'), pl: getComputedStyle(trigger).paddingLeft, pr: getComputedStyle(trigger).paddingRight, svg: trigger.querySelector('svg').getBoundingClientRect().width, ring: getComputedStyle(trigger).boxShadow };
        });
        await tab.waitForSelector('[data-fill-all-trigger-compact] [data-action-layout="compact"]');
        for (const [name, selector] of [['full', '[data-fill-all-trigger] [data-action-layout-full] [data-action-group]'], ['compact', '[data-fill-all-trigger-compact] [data-action-layout="compact"]']]) {
            const ringed = await trigger(selector);
            assert.ok(ringed.menu, `${name}: the second segment is the ⌄ trigger`);
            assert.equal(ringed.width, 42, `${name}: triggerClass makes the trigger 42px (${ringed.pl} + ${ringed.svg}px + ${ringed.pr})`);
            assert.equal(ringed.svg, 20, `${name}: [&>svg]:size-5 sizes the chevron`);
            assert.equal(ringed.lead, '0px', `${name}: dividerClass="border-r-0" leaves the segment before the trigger no right border`);
            assert.match(ringed.ring, /inset/, `${name}: the trigger has its inset ring (${ringed.ring})`);
        }
        assert.equal(await tab.locator('[data-fill-all-trigger] [data-action-layout="compact"]').count(), 0, 'the roomy pill stays in its full layout');
        const plainTrigger = all.segments.at(-1);
        assert.deepEqual([plainTrigger.pl, plainTrigger.pr], ['12px', '12px'], 'without triggerClass the trigger keeps px-3');
        const dimmed = await read('[data-fill-all-disabled] [data-action-group]');
        assert.equal(dimmed.bg, probe.bg, 'the disabled pill is still filled');
        assert.ok(dimmed.segments.filter(seg => seg.action).every(seg => seg.opacity === '0.5'), `disabled segments dim on the filled pill (${dimmed.segments.map(seg => seg.opacity)})`);
        const plain = await read('[data-default-pill] [data-action-group]');
        assert.notEqual(plain.bg, probe.bg, 'the default pill is not filled');
        assert.ok(plain.segments.filter(seg => seg.action).every(seg => seg.pl === '14px' && seg.pr === '14px'), 'the default pill keeps px-3.5');
        assert.ok(plain.segments.every(seg => seg.weight === '500'), `the default pill keeps its medium labels (${plain.segments.map(seg => seg.weight)})`);
        const panel = tab.locator('[data-panel] [data-action="details"]');
        assert.equal(await panel.evaluate(el => el.tagName), 'BUTTON', 'a panel-intent GET is a button, not a link');
        assert.equal(await panel.getAttribute('href'), null);
        await panel.click();
        assert.equal(await tab.textContent('[data-emitted]'), 'details', 'clicking it emits @action');
        // Axe with contrast, run on the two filled pills only (the fixture page has no h1, and its panel row is not under test): no violation, and the
        // filled pill's label is checked for contrast, not skipped.
        await tab.addScriptTag({ path: axeSource });
        const audit = await tab.evaluate(async () => {
            const run = await window.axe.run({ include: [['[data-fill-all]'], ['[data-fill-all-disabled]']] }, { elementRef: true, rules: { 'color-contrast': { enabled: true } } });
            return {
                violations: run.violations.flatMap(v => v.nodes.map(n => `${v.id}: ${n.target.join(' ')}`)),
                contrast: run.passes.filter(p => p.id === 'color-contrast').flatMap(p => p.nodes).filter(n => n.element.closest('[data-fill-all]')).length,
            };
        });
        assert.deepEqual(audit.violations, [], 'the filled pills pass axe with contrast');
        assert.ok(audit.contrast >= 1, `axe checked the filled label's contrast (${audit.contrast})`);
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/fill-all${theme === 'dark' ? '-dark' : ''}.png` });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
};
test('ActionBar fill-all (light): one primary pill with py-2, pl-3.5 then px-3, disabled dims; default pill unchanged; a panel-intent GET is a button that emits', () => fillAll('light'));
test('ActionBar fill-all (dark): the same under the dark tokens', () => fillAll('dark'));
// A menu-only ActionBar at phone width (?fill-all, 390px): its ⌄ trigger reads "Actions" unset, the host's text with menuTriggerLabel, and with null is
// icon-only and named "More actions" (ButtonGroup's default). triggerClass reaches ButtonGroup's trigger, full and compact.
test('ActionBar menuTriggerLabel at 390px: unset "Actions", a string replaces it, null is icon-only and named "More actions"; triggerClass on ButtonGroup\'s trigger', async () => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
        const tab = await context.newPage();
        const errors = [];
        tab.on('pageerror', error => errors.push(error.message));
        await tab.goto(`${url}?fill-all`);
        await tab.waitForSelector('[data-menu-label-null] [data-button-group-menu]');
        const trigger = (name) => tab.$eval(`[data-menu-label-${name}] [data-button-group-menu]`, el => ({ text: el.textContent.trim(), svg: Boolean(el.querySelector('svg[aria-hidden="true"]')) }));
        assert.deepEqual(await trigger('unset'), { text: 'Actions', svg: true }, 'unset keeps "Actions ⌄"');
        assert.deepEqual(await trigger('text'), { text: 'Trip', svg: true }, 'a string replaces it');
        assert.deepEqual(await trigger('null'), { text: '', svg: true }, 'null leaves the chevron alone');
        const named = tab.locator('[data-menu-label-null]').getByRole('button', { name: 'More actions', exact: true });
        assert.equal(await named.count(), 1, 'the icon-only trigger is named "More actions"');
        assert.equal(await tab.locator('[data-menu-label-unset]').getByRole('button', { name: 'Actions', exact: true }).count(), 1, 'unset is still named by its "Actions" text');
        await tab.waitForSelector('[data-menu-trigger-class-compact] [data-action-layout="compact"]');
        for (const [name, selector] of [['full', '[data-menu-trigger-class] [data-button-group-menu]'], ['compact', '[data-menu-trigger-class-compact] [data-action-layout="compact"] [data-button-group-menu]']]) {
            const box = await tab.$eval(selector, el => ({ ml: getComputedStyle(el).marginLeft, tl: getComputedStyle(el).borderTopLeftRadius, bl: getComputedStyle(el).borderBottomLeftRadius }));
            assert.deepEqual(box, { ml: '-1px', tl: '0px', bl: '0px' }, `${name}: triggerClass="-ml-px rounded-l-none" reaches ButtonGroup's trigger`);
        }
        const fillTrigger = await tab.$eval('[data-fill-all-trigger-compact] [data-action-layout="compact"] [data-action-menu]', el => { const r = el.getBoundingClientRect(); const s = el.closest('[data-surface]').getBoundingClientRect(); return r.right <= s.right && r.left >= s.left; });
        assert.ok(fillTrigger, 'the compacted fill-all pill keeps its trigger inside the surface');
        await tab.screenshot({ path: `${process.env.SCREENSHOT_DIR ?? '/tmp'}/menu-trigger-label-390.png` });
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
});

// ── Date and time segments: axe target-size (WCAG 2.2 AA) on every kit date/time input, light and dark, at 1280 and 390px ─────────────────────────────────
// Each typed segment (role=spinbutton) is at least 24×24 CSS px, the inputs keep the kit's h-9 height, and nothing scrolls sideways at phone width.
async function dateTargets(scheme) {
    for (const width of [1280, 390]) {
        const context = await browser.newContext({ locale: 'en-US', viewport: { width, height: 1100 } });
        try {
            const tab = await context.newPage();
            const errors = [];
            tab.on('pageerror', error => errors.push(error.message));
            await tab.goto(`${url}?date-targets${scheme === 'dark' ? '=dark' : ''}`);
            await tab.waitForSelector('[data-slot="input-time-range"], [data-slot="input-date-range"]');
            assert.equal(await tab.evaluate(() => document.documentElement.classList.contains('dark')), scheme === 'dark');
            await tab.addScriptTag({ path: axeSource });
            const run = await tab.evaluate(async () => {
                const result = await window.axe.run(document, { runOnly: ['target-size'] });
                const count = (list) => list.reduce((sum, rule) => sum + rule.nodes.length, 0);
                return { violations: result.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), passes: count(result.passes) };
            });
            assert.deepEqual(run.violations, [], `target-size at ${width}px`);
            const boxes = await tab.$$eval('[role="spinbutton"]', els => els.map(el => { const r = el.getBoundingClientRect(); return { label: el.getAttribute('aria-label'), w: r.width, h: r.height }; }));
            assert.ok(boxes.length >= 30, `every date and time segment is on the page (${boxes.length})`);
            assert.ok(run.passes >= boxes.length, `axe measured the segments (${run.passes} passes for ${boxes.length} segments)`);
            for (const box of boxes) assert.ok(box.w >= 24 && box.h >= 24, `${box.label} is ${box.w}×${box.h} at ${width}px`);
            const heights = await tab.$$eval('[data-slot^="input-date"] [aria-labelledby], [data-slot^="input-time"] [aria-labelledby]', els => [...new Set(els.filter(el => el.classList.contains('h-9')).map(el => el.getBoundingClientRect().height))]);
            assert.deepEqual(heights, [36], 'the segmented fields keep the h-9 height');
            assert.ok(await tab.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `no sideways scroll at ${width}px`);
            const shot = process.env.KIT_DATE_TARGETS_SCREENSHOT;
            if (shot) await tab.screenshot({ path: `${shot}-${scheme}-${width}.png`, fullPage: true });
            assert.deepEqual(errors, []);
        } finally {
            await context.close();
        }
    }
}
test('date and time inputs (light): every segment passes axe target-size, is at least 24×24, keeps h-9 and fits at 390px', () => dateTargets('light'));
test('date and time inputs (dark): every segment passes axe target-size, is at least 24×24, keeps h-9 and fits at 390px', () => dateTargets('dark'));

// ── MiddleTruncate (?middle-truncate): cut in the middle to the box's width, refit on resize; the whole value is the name, the title and the copy ─────────
// Each row is a [data-box] of 320px (w-80); a test sets its width and waits for the ResizeObserver's refit. `shown` is what paints: the parts, the drawn
// ellipsis for each cut, and none of the zero-size cut text.
const truncateRow = (row) => `[data-row="${row}"] [data-slot="middle-truncate"]`;
async function middleTruncate(query, check) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 800 }, permissions: ['clipboard-read', 'clipboard-write'] });
    try {
        const tab = await context.newPage();
        await tab.goto(`${url}?${query}`);
        // The URL is long enough to be cut at 320px in any font: the component has mounted and measured.
        await tab.waitForSelector(`${truncateRow('url')}[data-truncated]`);
        await check(tab);
    } finally {
        await context.close();
    }
}
const truncateState = (tab, row) => tab.evaluate((selector) => {
    const el = document.querySelector(selector);
    const walk = (node) => [...node.childNodes].map((n) => (n.nodeType === Node.TEXT_NODE ? n.textContent : n.nodeType !== Node.ELEMENT_NODE || n.matches('[data-cut]') ? '' : n.matches('[data-ellipsis]') ? getComputedStyle(n, '::before').content.replace(/^"|"$/g, '') : walk(n))).join('');
    const range = document.createRange();
    range.selectNodeContents(el);
    return {
        shown: walk(el),
        truncated: el.hasAttribute('data-truncated'),
        title: el.title,
        text: el.textContent,
        // Unrounded: scrollWidth rounds, and 0.2px over already shows the CSS end ellipsis.
        overflows: range.getBoundingClientRect().width > el.getBoundingClientRect().width,
        // How much of the box the painted text fills: a fit to the width, not a fixed count of characters.
        fill: range.getBoundingClientRect().width / el.clientWidth,
        cutWidths: [...el.querySelectorAll('[data-cut]')].map((cut) => cut.getBoundingClientRect().width),
        direction: getComputedStyle(el).direction,
    };
}, truncateRow(row));
// Sets a box's width and waits two frames: the ResizeObserver runs before the next paint and Vue re-renders in a microtask after it.
const setBox = (tab, row, width) => tab.evaluate(([row, width]) => {
    document.querySelector(`[data-row="${row}"]`).style.width = `${width}px`;
    return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}, [row, width]);

test('MiddleTruncate: cuts the middle to fit, keeps the start and the extension, and refits as its box narrows and widens', async () => {
    await middleTruncate('middle-truncate', async (tab) => {
        const file = 'paraframe-brand-guidelines-2026-client-review-final.pdf';
        const at320 = await truncateState(tab, 'file');
        assert.equal(at320.truncated, true);
        assert.match(at320.shown, /^paraframe-brand.*….*-final\.pdf$/, at320.shown);
        assert.equal(at320.overflows, false, 'no CSS end ellipsis: the cut text fits');
        assert.ok(at320.fill > 0.9 && at320.fill <= 1, `the shown text fills the box (${at320.fill})`);
        assert.deepEqual(at320.cutWidths, [0], 'the cut middle paints nothing');
        assert.equal(at320.text, file, 'the cut middle stays in the text');

        await setBox(tab, 'file', 900);
        const wide = await truncateState(tab, 'file');
        assert.equal(wide.shown, file, 'a box wide enough shows the whole value, no ellipsis');
        assert.equal(wide.overflows, false);

        await setBox(tab, 'file', 140);
        const narrow = await truncateState(tab, 'file');
        assert.ok(narrow.shown.length < at320.shown.length, `${narrow.shown} is shorter than ${at320.shown}`);
        assert.match(narrow.shown, /^par.*….*\.pdf$/, narrow.shown);
        assert.equal(narrow.overflows, false);
        assert.ok(narrow.fill > 0.8, `still fills the narrow box (${narrow.fill})`);

        await setBox(tab, 'file', 320);
        assert.equal((await truncateState(tab, 'file')).shown, at320.shown, 'back at 320px, the same cut');
    });
});

test('MiddleTruncate: from 120 to 400px every row fits its box, never falls back to the CSS end ellipsis, and keeps its tail', async () => {
    await middleTruncate('middle-truncate', async (tab) => {
        const tails = { file: '.pdf', url: '?…', path: '.pdf', short: 'logo.svg', rtl: '.pdf', emoji: '.jpeg', tail: '.xlsx' };
        for (let width = 120; width <= 400; width += 10) {
            await tab.evaluate((w) => document.querySelectorAll('[data-box]').forEach((el) => { el.style.width = `${w}px`; }), width);
            await setBox(tab, 'file', width);
            for (const [row, tail] of Object.entries(tails)) {
                // min-tail="16" asks for more than a narrower box holds: its floor is shown and clipped, by design.
                if (row === 'tail' && width < 170) continue;
                const state = await truncateState(tab, row);
                assert.equal(state.overflows, false, `${row} at ${width}px overflows: ${state.shown}`);
                assert.ok(state.shown.endsWith(tail), `${row} at ${width}px: ${state.shown}`);
            }
        }
    });
});

test('MiddleTruncate: a URL keeps its host start and last segment with its query cut, a path its last segment; short, RTL and emoji values', async () => {
    await middleTruncate('middle-truncate', async (tab) => {
        const link = await truncateState(tab, 'url');
        assert.match(link.shown, /^https:\/\/cdn\.para.*….*\/final-v3\.pdf\?…$/, link.shown);
        assert.equal(link.overflows, false);
        assert.deepEqual(link.cutWidths, [0, 0], 'the cut middle and the cut query paint nothing');
        await setBox(tab, 'path', 200);
        const path = await truncateState(tab, 'path');
        assert.match(path.shown, /^\/srv\/.*….*\/final-v3\.pdf$/, path.shown);
        const short = await truncateState(tab, 'short');
        assert.deepEqual([short.truncated, short.shown, short.title], [false, 'logo.svg', 'logo.svg'], 'a short value: whole, no ellipsis');
        await setBox(tab, 'rtl', 180);
        const rtl = await truncateState(tab, 'rtl');
        assert.equal(rtl.direction, 'rtl', 'dir="auto" lays an Arabic name out right to left');
        assert.equal(rtl.truncated, true);
        assert.match(rtl.shown, /^دليل-.*….*\.pdf$/, rtl.shown);
        assert.equal(rtl.overflows, false);
        await setBox(tab, 'emoji', 150);
        const emoji = await truncateState(tab, 'emoji');
        assert.ok(emoji.shown.startsWith('👨‍👩‍👧‍👦'), `the family emoji stays whole: ${emoji.shown}`);
        assert.ok(emoji.shown.endsWith('.jpeg') && !emoji.overflows, emoji.shown);
        await setBox(tab, 'tail', 180);
        const tail = await truncateState(tab, 'tail');
        assert.ok(tail.shown.split('…')[1].length >= 16, `min-tail keeps 16 characters at the end: ${tail.shown}`);
    });
});

test('MiddleTruncate: a new value at the same width drops the overflow correction measured for the old one', async () => {
    const context = await browser.newContext({ viewport: { width: 1024, height: 800 } });
    try {
        const tab = await context.newPage();
        // The canvas under-reports any text with an Ω by 40px, so the first value always overflows once painted and the component corrects for it.
        await tab.addInitScript(() => {
            const measure = CanvasRenderingContext2D.prototype.measureText;
            CanvasRenderingContext2D.prototype.measureText = function (text) {
                const metrics = measure.call(this, text);
                return text.includes('Ω') ? { width: metrics.width - 40 } : metrics;
            };
        });
        await tab.goto(`${url}?middle-truncate`);
        await tab.waitForSelector(`${truncateRow('swap')}[data-truncated]`);
        // A box 10px wider than the second value, in this runner's own font.
        const next = 'quarterly-board-report-2026-final.pdf';
        const width = await tab.evaluate((text) => {
            const probe = Object.assign(document.createElement('span'), { textContent: text });
            document.querySelector('[data-row="swap"] [data-slot="middle-truncate"]').after(probe);
            const measured = probe.getBoundingClientRect().width;
            probe.remove();
            return Math.ceil(measured) + 10 + 18;
        }, next);
        await setBox(tab, 'swap', width);
        const before = await truncateState(tab, 'swap');
        assert.equal(before.truncated, true, 'the first value is cut');
        assert.equal(before.overflows, false, `corrected: ${before.shown}`);
        await tab.click('[data-swap]');
        await tab.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const after = await truncateState(tab, 'swap');
        assert.deepEqual([after.truncated, after.shown, after.overflows], [false, next, false], 'the new value fits whole: no correction is left from the old one');
    } finally {
        await context.close();
    }
});

test('MiddleTruncate: the accessible name, the title and a copied selection are the whole value; axe passes, light and dark', async () => {
    for (const query of ['middle-truncate', 'middle-truncate=dark']) {
        await middleTruncate(query, async (tab) => {
            const file = 'paraframe-brand-guidelines-2026-client-review-final.pdf';
            const urlValue = 'https://cdn.paraframe.studio/clients/acme/2026/brand/guidelines/final-v3.pdf?download=1';
            assert.equal(await tab.getByRole('link', { name: file, exact: true }).count(), 1, 'the link is named by the whole file name');
            assert.equal(await tab.getAttribute(truncateRow('file'), 'title'), file);
            assert.match(await tab.locator('[data-row="file"]').ariaSnapshot(), new RegExp(`link "${file.replace(/[.]/g, '\\.')}"`));
            for (const [row, value] of [['file', file], ['url', urlValue]]) {
                await tab.evaluate((s) => { const r = document.createRange(); r.selectNodeContents(document.querySelector(s)); getSelection().removeAllRanges(); getSelection().addRange(r); }, truncateRow(row));
                await tab.keyboard.press('ControlOrMeta+C');
                assert.equal(await tab.evaluate(() => navigator.clipboard.readText()), value, `copying the ${row} copies the whole value, no ellipsis`);
            }
            await tab.addScriptTag({ path: axeSource });
            const violations = await tab.evaluate(async () => (await window.axe.run(document.querySelector('main'))).violations.map((v) => ({ id: v.id, targets: v.nodes.map((n) => n.target.join(' ')) })));
            if (violations.length) console.log(query, JSON.stringify(violations, null, 1));
            assert.deepEqual(violations, []);
        });
    }
});

test('MiddleTruncate under Vue\'s server renderer: the whole value with a CSS end ellipsis, and no observer, canvas or timer touched', async () => {
    const dev = await createServer({ root: here, configFile: path.join(here, 'vite.config.js'), logLevel: 'error', appType: 'custom', server: { middlewareMode: true, hmr: false } });
    try {
        const { default: MiddleTruncate } = await dev.ssrLoadModule('/.kit/vendor/tey/kit/resources/js/components/Kit/Data/MiddleTruncate.vue');
        const { createSSRApp, h } = await import('vue');
        const { renderToString } = await import('vue/server-renderer');
        assert.equal(typeof globalThis.document, 'undefined', 'node has no DOM, so any access would throw');
        assert.equal(typeof globalThis.ResizeObserver, 'undefined');
        const value = 'paraframe-brand-guidelines-2026-final.pdf';
        const html = (await renderToString(createSSRApp({ render: () => h(MiddleTruncate, { value }) }))).replace(/<!--.*?-->/g, '');
        assert.match(html, /^<span title="paraframe-brand-guidelines-2026-final\.pdf" dir="auto" data-slot="middle-truncate" class="block min-w-0 truncate">paraframe-brand-guidelines-2026-final\.pdf<\/span>$/, html);
    } finally {
        await dev.close();
    }
});

// ── The Text family (?text): copy, truncate and clamp, long text, a scrolling code block, number and address formats, axe, server render ─────────────────
async function textPage(query, check, { clipboard = true, init } = {}) {
    const context = await browser.newContext({ viewport: { width: 1024, height: 900 }, permissions: clipboard ? ['clipboard-read', 'clipboard-write'] : [] });
    try {
        const tab = await context.newPage();
        if (init) await tab.addInitScript(init);
        await tab.goto(`${url}?${query}`);
        await tab.waitForSelector('[data-case="badges"] [data-badge]');
        await check(tab);
    } finally {
        await context.close();
    }
}
const clipboardText = (tab) => tab.evaluate(() => navigator.clipboard.readText());
const nextFrames = (tab) => tab.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));

test('TextCopy, CopyButton and CopyField copy their value by pointer and keyboard, claim it only once written, and name what they copy', async () => {
    await textPage('text', async (tab) => {
        const inline = tab.locator('[data-inline-copy]');
        const press = inline.getByRole('button', { name: 'INV-2026-0042', exact: true });
        assert.equal(await press.count(), 1, 'a real button named by the text it shows');
        assert.equal(await press.getAttribute('title'), 'Copy', 'and described by its tooltip');
        await press.click();
        await tab.locator('[data-inline-copy][data-state="copied"]').waitFor();
        assert.equal(await clipboardText(tab), 'INV-2026-0042');
        assert.equal(await inline.getByRole('status').textContent(), 'Copied.');
        await tab.evaluate(() => navigator.clipboard.writeText(''));
        const order = tab.getByRole('button', { name: '#9', exact: true });
        assert.equal(await order.getAttribute('title'), 'Copy order number');
        await order.focus();
        await tab.keyboard.press('Enter');
        await tab.waitForFunction(() => document.querySelectorAll('[data-text-copy][data-state="copied"]').length === 2);
        assert.equal(await clipboardText(tab), 'ORD-9', 'the slot is shown, the value is copied; Enter copies as a click does');
        await tab.getByRole('button', { name: 'Copy token' }).click();
        await tab.locator('[data-copy-button][data-state="copied"]').waitFor();
        assert.equal(await clipboardText(tab), 'tok_button_value', 'CopyButton copies through the same helper');
        const field = tab.locator('[data-copy-field]');
        assert.equal(await tab.getByRole('textbox', { name: 'API key' }).getAttribute('readonly'), '', 'a labelled read-only field');
        await tab.getByRole('button', { name: 'Copy API key' }).click();
        await tab.locator('[data-copy-field][data-state="copied"]').waitFor();
        assert.equal(await clipboardText(tab), 'key-demo-0000-0000-0000');
        assert.equal((await field.getByRole('status').textContent()).trim(), 'Copied API key.');
    });
});

test('Copy without the async Clipboard API falls back to execCommand with the exact text; a refused write says so and selects the field to copy by hand', async () => {
    await textPage('text', async (tab) => {
        await tab.evaluate(() => {
            window.copiedText = [];
            document.addEventListener('copy', (event) => window.copiedText.push(event.target.value));
        });
        await tab.getByRole('button', { name: 'INV-2026-0042', exact: true }).click();
        await tab.locator('[data-inline-copy][data-state="copied"]').waitFor();
        assert.deepEqual(await tab.evaluate(() => window.copiedText), ['INV-2026-0042'], 'the hidden textarea held the exact value');
        assert.equal(await tab.locator('textarea[aria-hidden="true"]').count(), 0, 'and is gone');
        assert.equal(await tab.evaluate(() => document.activeElement.textContent.includes('INV-2026-0042')), true, 'focus is back on the pressed button');
    }, { clipboard: false, init: () => Object.defineProperty(Navigator.prototype, 'clipboard', { configurable: true, get: () => undefined }) });
    await textPage('text', async (tab) => {
        await tab.getByRole('button', { name: 'Copy API key' }).click();
        await tab.locator('[data-copy-field][data-state="failed"]').waitFor();
        assert.match(await tab.locator('[data-copy-field]').getByRole('status').textContent(), /Could not copy API key\. It is selected in the field/);
        assert.deepEqual(await tab.evaluate(() => [document.activeElement.value, document.activeElement.selectionStart, document.activeElement.selectionEnd]), ['key-demo-0000-0000-0000', 0, 23], 'the whole value is selected');
        await tab.getByRole('button', { name: 'Copy token' }).click();
        await tab.locator('[data-copy-button][data-state="failed"]').waitFor();
        assert.equal(await tab.getByRole('textbox', { name: 'Copy: select to copy' }).count() + await tab.getByRole('textbox', { name: 'Copy token: select to copy' }).count(), 1, 'CopyButton shows the value to copy by hand');
    }, { init: () => Object.defineProperty(Navigator.prototype, 'clipboard', { configurable: true, get: () => ({ writeText: () => Promise.reject(new DOMException('Denied', 'NotAllowedError')) }) }) });
});

test('TextTruncate: one line ends in an ellipsis with the whole text as its title only while cut, a clamp holds two lines, and a resize measures again', async () => {
    await textPage('text', async (tab) => {
        const title = 'Quarterly board report appendix with every table and the minutes of the meeting';
        const state = (selector) => tab.$eval(selector, (el) => ({ cut: el.hasAttribute('data-truncated'), title: el.getAttribute('title'), text: el.textContent.trim(), lines: Math.round(el.getBoundingClientRect().height / parseFloat(getComputedStyle(el).lineHeight)), overflow: getComputedStyle(el).textOverflow }));
        const one = await state('[data-truncate-one]');
        assert.deepEqual([one.cut, one.title, one.text, one.overflow, one.lines], [true, title, title, 'ellipsis', 1], 'cut at the end, the whole text kept in the DOM and the title');
        const two = await state('[data-truncate-two]');
        assert.deepEqual([two.cut, two.lines], [true, 2], 'clamped to two lines');
        const short = await state('[data-truncate-short]');
        assert.deepEqual([short.cut, short.title], [false, null], 'a text that fits has no title');
        await tab.$eval('[data-truncate-box]', (el) => { el.style.width = '900px'; });
        await nextFrames(tab);
        const wide = await state('[data-truncate-one]');
        assert.deepEqual([wide.cut, wide.title], [false, null], 'widened, it fits: the title goes');
        await tab.$eval('[data-truncate-box]', (el) => { el.style.width = '120px'; });
        await nextFrames(tab);
        assert.equal((await state('[data-truncate-one]')).cut, true, 'narrowed again, it is cut again');
    });
});

test('TextTruncate keeps the whole text verbatim, leading, trailing and repeated whitespace included, in its title and its text, from `text` and from the slot', async () => {
    await textPage('text', async (tab) => {
        const padded = '  Quarterly board report:  appendix B, every table and the minutes  ';
        for (const selector of ['[data-truncate-padded]', '[data-truncate-padded-slot]']) {
            const el = await tab.$eval(selector, (node) => ({ cut: node.hasAttribute('data-truncated'), title: node.getAttribute('title'), text: node.textContent }));
            assert.equal(el.cut, true, `${selector} is cut`);
            assert.equal(el.title, padded, `${selector}: the title is the text as given, not trimmed`);
            assert.equal(el.text, padded, `${selector}: the element keeps it as given, so a selection and a copy start from all of it`);
        }
    });
});

test('TextLong: cut with an ellipsis and a Show more button that expands it in place by pointer and keyboard; a short text has none', async () => {
    await textPage('text', async (tab) => {
        const long = tab.locator('[data-long]');
        const toggle = long.getByRole('button', { name: 'Show more' });
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        const controlled = await toggle.getAttribute('aria-controls');
        const shown = () => tab.$eval(`[id="${controlled}"]`, (el) => el.textContent);
        assert.ok((await shown()).endsWith('…') && [...new Intl.Segmenter().segment(await shown())].length === 81, 'eighty characters and the ellipsis');
        await toggle.click();
        assert.equal(await long.getByRole('button', { name: 'Show less' }).getAttribute('aria-expanded'), 'true');
        assert.ok((await shown()).endsWith('ends here. 👨‍👩‍👧‍👦'), 'the whole text, the emoji whole');
        assert.match(await shown(), /quarterly figures\.\n\nSecond/, 'line breaks kept');
        await long.getByRole('button', { name: 'Show less' }).focus();
        await tab.keyboard.press('Enter');
        assert.equal(await long.getByRole('button', { name: 'Show more' }).getAttribute('aria-expanded'), 'false');
        assert.equal(await tab.locator('[data-long-short] button').count(), 0);
    });
});

test('TextCode: a bounded block that scrolls inside as a focusable named region, the text never parsed, Wrap toggled with aria-pressed, Copy copies all of it', async () => {
    await textPage('text', async (tab) => {
        const card = tab.locator('[data-code-card]');
        const region = card.getByRole('region', { name: 'Export log' });
        assert.equal(await card.getByRole('heading', { name: 'Export log', level: 2 }).count(), 1);
        const box = () => region.evaluate((el) => ({ client: el.clientHeight, scroll: el.scrollHeight, width: el.clientWidth, scrollWidth: el.scrollWidth, top: el.scrollTop, bold: el.querySelectorAll('b').length, text: el.textContent }));
        const start = await box();
        assert.ok(start.client <= 322 && start.scroll > start.client * 2, `bounded at 20rem and scrolling: ${start.client} of ${start.scroll}`);
        assert.equal(start.bold, 0, '<b> in the content is text');
        assert.ok(start.text.includes('<b>not markup</b>'));
        assert.ok(start.scrollWidth <= start.width + 1, 'long lines wrap by default');
        await region.focus();
        await tab.keyboard.press('End');
        await tab.waitForFunction(() => document.querySelector('[data-code-card] pre').scrollTop > 0);
        const wrap = card.getByRole('button', { name: 'Wrap' });
        assert.equal(await wrap.getAttribute('aria-pressed'), 'true');
        await wrap.click();
        assert.equal(await wrap.getAttribute('aria-pressed'), 'false');
        const unwrapped = await box();
        assert.ok(unwrapped.scrollWidth > unwrapped.width + 50, 'unwrapped, a long line scrolls sideways');
        await card.getByRole('button', { name: 'Copy' }).click();
        await tab.locator('[data-code-card] [data-text-code-copy][data-state="copied"]').waitFor();
        const copied = await clipboardText(tab);
        assert.equal(copied.split('\n').length, 60, 'every line, also those scrolled out of view');
        assert.equal(copied, start.text);
        assert.equal(await tab.locator('[data-code-plain]').getByRole('region', { name: 'Code' }).count(), 1, 'without a title it is named Code');
    });
});

test('TextCode with slot text: a copy in flight when the slot changes claims nothing, and the next copy is the new text, whether the page or a child changed it', async () => {
    // A clipboard whose writes wait until the test settles them, so the slot can change while one is in flight.
    const init = () => {
        window.writes = [];
        window.settle = () => window.writes.at(-1).resolve();
        Object.defineProperty(Navigator.prototype, 'clipboard', { configurable: true, get: () => ({ writeText: (text) => new Promise((resolve) => window.writes.push({ text, resolve })) }) });
    };
    await textPage('text', async (tab) => {
        const block = tab.locator('[data-code-slot]');
        const copy = block.locator('[data-text-code-copy]');
        const state = () => copy.getAttribute('data-state');
        // Every state the button leaves, so a `copied` that is rendered and reset before the next frame is caught too.
        await copy.evaluate((button) => {
            window.left = [];
            new MutationObserver((records) => window.left.push(...records.map((record) => record.oldValue))).observe(button, { attributes: true, attributeFilter: ['data-state'], attributeOldValue: true });
        });
        for (const [change, before, after] of [['[data-slot-swap]', 'build 1 started', 'build 2 started'], ['[data-slot-tick]', 'tick 0', 'tick 1']]) {
            await copy.click();
            assert.equal(await state(), 'copying');
            assert.ok((await tab.evaluate(() => window.writes.at(-1).text)).includes(before), 'the write holds the slot text shown when pressed');
            await tab.click(change);
            await nextFrames(tab);
            assert.ok((await block.locator('pre').textContent()).includes(after), `${change} changed the slot`);
            await tab.evaluate(() => { window.left = []; window.settle(); });
            await tab.waitForFunction(() => document.querySelector('[data-code-slot] [data-text-code-copy]').dataset.state !== 'copying');
            await nextFrames(tab);
            assert.equal(await state(), 'idle', `${change}: the old write settling is not copied, the text it copied is gone`);
            assert.deepEqual(await tab.evaluate(() => window.left), ['copying'], `${change}: straight from copying to idle, never copied on the way`);
            assert.equal((await block.getByRole('status').textContent()).trim(), '', 'and nothing is announced');
            await copy.click();
            assert.ok((await tab.evaluate(() => window.writes.at(-1).text)).includes(after), `${change}: the next copy is the new text`);
            await tab.evaluate(() => window.settle());
            await tab.waitForFunction(() => document.querySelector('[data-code-slot] [data-text-code-copy]').dataset.state === 'copied');
        }
        // Changed outside Vue's render with nothing in flight: the press copies the text shown now, not the text from the last render.
        await tab.click('[data-slot-tick]');
        await nextFrames(tab);
        await copy.click();
        assert.ok((await tab.evaluate(() => window.writes.at(-1).text)).includes('tick 2'), 'a press after a change outside Vue copies the new text');
        await tab.evaluate(() => window.settle());
    }, { clipboard: false, init });
});

test('TextNumeric and TextAddress: the locale\'s grouping, decimal, compact and accounting forms, nothing for what is not a number, and address lines', async () => {
    await textPage('text', async (tab) => {
        // What is seen: the text without the screen-reader-only words.
        const n = (key) => tab.$eval(`[data-n="${key}"]`, (el) => { const copy = el.cloneNode(true); copy.querySelectorAll('.sr-only').forEach((s) => s.remove()); return copy.textContent.replace(/[\u00a0\u202f]/g, ' '); });
        assert.equal(await n('en'), '1,234,567');
        assert.equal(await n('fr'), '1 234,50');
        assert.equal(await n('compact'), '1.2K');
        assert.equal(await n('suffix'), '42 items');
        assert.equal(await n('minus'), '-42');
        assert.equal(await n('accounting'), '(1,234.50)', 'the accounting form');
        assert.equal(await tab.$eval('[data-n="accounting"]', (el) => el.hasAttribute('data-negative')), true);
        assert.match(await tab.locator('[data-case="numbers"] ul').ariaSnapshot(), /negative 1,234\.50/, 'a screen reader hears negative, not a parenthesis');
        assert.equal(await tab.textContent('[data-n-empty]'), '[]', 'n/a renders nothing, not NaN');
        assert.equal(await tab.$eval('[data-n="en"]', (el) => getComputedStyle(el).fontVariantNumeric), 'tabular-nums');
        const lines = (key) => tab.$eval(`[data-address="${key}"]`, (el) => el.innerText.split('\n').map((line) => line.trim()).filter(Boolean).join(' | '));
        assert.equal(await lines('fr'), '1 Example Way | Unit 4 | Gatineau, Québec J8X 0A1 | Canada', 'the region named in French');
        assert.equal(await lines('lines'), '200 Sample Ave | Suite 3 | Springfield IL 62701', 'a comma-joined string, one line per part');
        assert.equal(await lines('alias'), '1 Example Way | Gatineau, Quebec J8X 0A1', 'AddressText renders TextAddress: hide-country drops the country');
        assert.deepEqual(await tab.$$eval('[data-case="address"] address', (els) => els.length), 3, 'each an <address>');
    });
});

test('The Text family passes axe in light and dark: chips and tags as lists of badges, prose text kept as text, a table region', async () => {
    for (const query of ['text', 'text=dark']) {
        await textPage(query, async (tab) => {
            const chips = tab.getByRole('list', { name: 'Details' });
            assert.deepEqual(await chips.getByRole('listitem').allTextContents(), ['Draft', 'Plan: Pro', 'Overdue']);
            assert.deepEqual(await tab.getByRole('list', { name: 'Tags' }).getByRole('listitem').allTextContents(), ['design', 'q3', 'design'], 'the blank tag dropped, a repeat kept');
            assert.equal(await tab.$eval('[data-prose-text]', (el) => [el.querySelectorAll('b').length, el.innerText.includes('Line one\nLine two <b>kept as text</b>')].join()), '0,true');
            assert.equal(await tab.getByRole('region', { name: 'Totals' }).count(), 1);
            await tab.addScriptTag({ path: axeSource });
            const violations = await tab.evaluate(async () => (await window.axe.run(document.querySelector('main'))).violations.map((v) => ({ id: v.id, targets: v.nodes.map((n) => n.target.join(' ')) })));
            if (violations.length) console.log(query, JSON.stringify(violations, null, 1));
            assert.deepEqual(violations, []);
        });
    }
});

test('The Text family under Vue\'s server renderer: truncation, copy, code, numbers and addresses render with no DOM, observer or timer', async () => {
    // The real @inertiajs/vue3, as on a host's server (the page's stand-in, src/inertia.js, is browser-only); listed first, its alias wins.
    const inertia = decodeURIComponent(new URL(import.meta.resolve('@inertiajs/vue3')).pathname);
    const dev = await createServer({ root: here, configFile: path.join(here, 'vite.config.js'), logLevel: 'error', appType: 'custom', server: { middlewareMode: true, hmr: false }, resolve: { alias: [{ find: /^@inertiajs\/vue3$/, replacement: inertia }] } });
    try {
        const load = async (name) => (await dev.ssrLoadModule(`/.kit/vendor/tey/kit/resources/js/components/Kit/Text/${name}.vue`)).default;
        const { createSSRApp, h } = await import('vue');
        const { renderToString } = await import('vue/server-renderer');
        assert.equal(typeof globalThis.document, 'undefined');
        const render = async (component, props) => (await renderToString(createSSRApp({ render: () => h(component, props) }))).replace(/<!--.*?-->/g, '');
        assert.match(await render(await load('TextTruncate'), { text: 'A long title' }), /^<span class="min-w-0 break-words block truncate"(?: style="")? data-slot="text-truncate">A long title<\/span>$/, 'CSS truncation only: no title or data-truncated until measured');
        assert.match(await render(await load('TextCopy'), { value: 'abc' }), /<button type="button" title="Copy"[^>]*>abc<\/button>/);
        assert.match(await render(await load('TextCode'), { content: '<b>x</b>', card: true, title: 'Log' }), /<pre role="region" aria-label="Log" tabindex="0"[^>]*>&lt;b&gt;x&lt;\/b&gt;<\/pre>/);
        assert.match(await render(await load('TextAddress'), { lines: 'a, b' }), /<address[^>]*>a<br>b<\/address>/);
        assert.match(await render(await load('TextNumeric'), { value: -5, locale: 'en-CA', negative: 'parentheses' }), /<span class="sr-only">negative <\/span><span aria-hidden="true">\(<\/span>5<span aria-hidden="true">\)<\/span>/);
    } finally {
        await dev.close();
    }
});
