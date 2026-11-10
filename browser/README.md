# Browser job

`run.mjs` runs the kit's real-browser checks: Playwright (Chromium) over a Vite build of the core component stubs, with axe-core for accessibility.

```sh
cd browser
npm ci
npx playwright install chromium
node --test run.mjs
```

The pages are in `src/`, picked by query string (`?workbench`, `?workbench-menu`, `?workbench-reorder`: a reorderable Workbench over a stand-in for Inertia v3's HTTP client, whose bodies are strings and whose server refuses a stale or null version); `?radio-form`: RadioCardGroups with and without `name` in a form whose submit shows its FormData; `?form-attrs`: each kit form control given a caller's `aria-describedby`, `invalid` and `required`, read off the focusable control; `?bulk-drop` (`=dark`): BulkDrop idle, busy and disabled with and without a prompt, with `KIT_BULKDROP_SCREENSHOT=<path prefix>` saving each scheme's screenshot; `?switch` (`=dark`): InputSwitch named, with a value, unnamed, in a Field with an error, and disabled off and on, in a form whose submit shows its FormData, with `KIT_SWITCH_SCREENSHOT=<path prefix>` saving each scheme's screenshot; `?form-submit`: a FormCard over useSubmitForm with a fake socket handler; `?workbench-form=submit` or `=inertia`: a Workbench with `:form`, over a useSubmitForm or a stand-in useForm with `save`; `?rhythm` (`&flush`): a Page with in-flow and out-of-flow children, PageGrids of each preset, a Stack and the deprecated BlockStack, measured for the page gap; `?tinted` (`=dark`, `=white`): a DataTable, FormCard, EmptyState and PageSections on the sample tinted ground (`tests/fixtures/tinted-ground-theme.css`), or on the default white one, with `KIT_TINTED_SCREENSHOT=<path>` saving the light run's screenshot; the kit's stubs are copied into `.kit` and built against `kit.css`'s default tokens. `@/components/ui/button` is the host's real shadcn-vue Button (`src/button`, with cva), so a variant paints its real classes (`primary` is `bg-primary`), and `@/components/ui/checkbox` the real Checkbox (`src/shadcn`); the other shadcn pieces are stand-ins in `src/ui.js`.

session-ui's guest pages are a second build (`auth.html`, `vite.auth.config.js`) on the real `@inertiajs/vue3` and shadcn-vue's Input, so `<Form>`'s props, visit options, resets and Inertia's own error modal behave as in an app; the tests answer each submit with Playwright's `page.route` (Laravel's 429 HTML, or an Inertia response with `errors`). `leave.html` is built with them: a useForm page with FormActions and a direct-upload BulkDrop, reached by an Inertia visit, where the leave guard's checks press Back and Forward through Inertia's own history entries, with and without `window.navigation`. `forms.html` is built with them too: settings' General and Email sending pages with the shell's Toaster, whose first load and every visit the test answers from its own store, so a test fills a form, presses Save and reads the value back after a reload (`KIT_FORMS_SCREENSHOTS=<dir>` saves the saved Email sending page). The account and session forms run there too, through `accountPage`: Account › Profile and its photo, Password, Security, Delete account, the welcome flow's timezone and photo steps, and the notes wall; only `navigator.credentials.create` and `crypto.randomUUID` are stubbed, and each recorded payload is posted to the real route by `composer test:account-form-payloads` (`KIT_FORMS_SCREENSHOTS` also saves Profile, Security and the notes wall). `people.html` is its own build (`vite.people.config.js`, into `dist-people`, served at the app's own addresses): People, its server-driven modals (Change role, Invite member, Transfer ownership) on the real `@inertiaui/modal-vue`, its row actions and Delete workspace, answered from the test's own store as inertiaui/modal's server would. `notes.html` is built with them too: the notes composer, posted through Playwright's `page.route`, with `?dark` for the dark tokens.

## Contrast checks need a non-modal surface

Run axe's `color-contrast` on a non-modal surface. A modal menu or dialog sets `aria-hidden` on the rest of the page, and axe then skips that content, so a
contrast failure can pass unseen (a modal menu reports `aria-hidden-focus` and the landmark rules instead). The Workbench row menu is non-modal, so its open-menu
run checks the destructive Delete item's contrast on the popover.

## The suite has a floor

`expected-count` holds the number of browser tests the suite must keep. `node count-check.mjs` fails when `run.mjs` declares fewer, and CI runs it again on the TAP report (`node count-check.mjs browser-tap.txt`), so fewer tests run or passed fails too. Raise the floor with each new test; a merge resolution that drops tests then fails CI instead of passing with a smaller suite.
