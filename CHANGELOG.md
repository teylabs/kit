# Changelog

## Unreleased

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:592e21c4…7353` → `sha256:c59375ef…dd74`): `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` take the auth-state fixes below. `ResetPasswordViewModel` gains `status`; a host that hand-copies `types/kit.d.ts` copies it again. A host overriding `lang/vendor/kit/{locale}/ui.php` can add `form.throttled` and `form.throttled_retry`.

### Changed

- **The auth forms validate in the card, not in the browser.** `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword` and `Auth/ConfirmPassword` carry `novalidate`, so the server's field errors render in the card (an address like "nobody" gets the "valid email address" error) instead of the browser's own bubble. The fields keep `type="email"`, `required` and `autocomplete`.

### Fixed

- **The guest auth pages keep a rate-limited submit in the card.** A 429 from a limiter (Fortify's login limiter, the verification resend, a reset or reset-link request) opened Inertia's error modal over the page. `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/ConfirmPassword` and `Auth/VerifyEmail` now refuse the modal for a 429 only (`onHttpException` returns false), clear the stale field errors and show an in-card alert (`role="alert"`): "Too many requests. Try again in N seconds." when the response carries `Retry-After`, else "Too many requests. Try again in a moment." The copy is in the kit's `ui` lang file (`form.throttled`, `form.throttled_retry`). Any other HTTP error still gets Inertia's modal; nothing the server allows or limits changes.
- **A failed sign-in, reset or confirmation clears the password and marks the field.** `Auth/Login`, `Auth/ResetPassword` and `Auth/ConfirmPassword` reset their password fields on error, set `aria-invalid` on the fields with an error, and move focus to the first one (the email after bad credentials).
- **A bad or expired reset token is visible.** Fortify reports it on `email`, a hidden field on `Auth/ResetPassword`, so nothing showed. It now shows as an in-card alert with a "Request a new link" link to `/forgot-password`.
- **The reset-password page's status comes from its view model.** `ResetPasswordViewModel` takes `status` (the flashed session status), so the `FormStatus` on that page shows the server's status without the host supplying it.
- **The sign-in page focuses the email field on load**, as `Auth/ConfirmPassword` already focuses the password.

## v0.7.3

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:909d632f…822e` → `sha256:9a290690…555b`): `feature.json` adds `class-variance-authority` and `tw-animate-css` to `requires.npm`. No stub changes.
- The dev floor for tey/standards is now `^0.11.52`, the first release that reads an inventory's `features` block.

### Added

- **CI guards the adoption inventory's completeness** (contributor-facing). `composer test:adoption-completeness` (`tests/AdoptionCompletenessCheck.php`, in the same CI job as the other adoption checks) fails when a page, layout or shell component a feature installs under `resources/js/pages` is named by no `surfaces.json` row's `uses.files`, `uses.components` or `uses.imports` and has no entry in the new `resources/adoption/internal.json`, and when a row with `handRolled` signals has no adoption fixture expecting it `hand-rolled` (or an `unproven` entry there). Stale `internal.json` entries fail too, and every entry needs a reason. Rows `adopted` in no fixture are listed as a note. To pass, `uses.files` now also names the shell partials (`UserMenu`, `WorkspaceSwitcher`, `Breadcrumbs`, `GlobalSearch`, `UserAvatar`, `Wordmark`, `FocusShell` under `shell.workspace`; `GuestShell` and `Public/Markdown` under `shell.public`), the remaining auth screens (`ResetPassword`, `ConfirmPassword`, `VerifyEmail`), the dashboard partials, `Sections/Index`, `Search`, the placeholder, error and no-workspace pages (`workspace.pages`), `Settings/Delete` (`workspace.settings`) and the People pages and invitation page (`access.members`); `internal.json` lists the sample notes pages and the welcome steps. New fixtures pin a hand-rolled outcome for the 19 rows that had none: `public-shell-hand-rolled`, `split-hand-rolled`, `search-hand-rolled`, `action-bar-hand-rolled`, `menu-hand-rolled`, `modal-hand-rolled`, `rich-text-hand-rolled`, `list-hand-rolled`, `badge-hand-rolled`, `avatar-hand-rolled`, `alert-hand-rolled`, `activity-hand-rolled`, `timezone-hand-rolled`, `auth-hand-rolled`, `passkeys-hand-rolled`, `teams-hand-rolled` (`workspace.pages`, `access.members`, `workspaces.tenancy`) and `mcp-hand-rolled`. No stub changes, no re-pin.

### Changed

- **A workspace without the dashboard opens its first navigation page.** With `workspace.dashboard` off, the workspace root (`/@{workspace}`, and `/` in the short form) was a 404. It now redirects (302) to the first primary navigation page the viewer may open, or to `/me/workspaces` when there is none. At the kit's home, a person with several workspaces still goes to the root of the one they last used, so with the dashboard off they land on its first page instead of the workspace list ([docs/dashboard.md › Without a dashboard](docs/dashboard.md#without-a-dashboard)). Give your page `order` < -10 to make it first. Only the capability being off redirects: a role that lacks `dashboard.read`, a non-member and a JSON client are refused as before. An accepted invitation's landing uses the same lookup, unchanged. No stub changes, no re-pin. `composer test:workspace-root` (new, in CI) covers it; `test:capability-guard` now expects the root's redirect.

### Fixed

- **standards:adoption no longer reads the kit's own dependencies and host UI as gaps.** `resources/adoption/surfaces.json` now carries a top-level `features` object: for each feature with `requires.npm` or `requires.host_ui`, its npm package names and its host UI primitives, exactly as its `feature.json` lists them. tey/standards 0.11.52 treats those packages as the kit's (never a package.json gap or a third-party import) and those `components/ui` files as kit-owned (never an app component, gap or hand-rolled hit) for each installed feature. The schema allows the field, and the inventory test fails when it drifts from the `feature.json` files.
- **core declares every npm package its components need.** `Kit/Feedback/alertVariants.ts` (like the shadcn `button` it pairs with) imports `class-variance-authority`, and the kit's menus, toasts and select animate with `tw-animate-css` utilities (`animate-in`, `fade-in-0`, `zoom-in-95`); neither was in `requires.npm`, so a host without them got no plan attention for it. Both are now required, at `^0.7.1` and `^1.4.0`.

- **The adoption inventory reads hand-rolled account and settings pages wherever they live.** `account.pages` and `workspace.settings` applied only when a route URI said `settings`, so an app serving its own account page at `/account`, or workspace settings at `/w/{workspace}/deletion`, read `n/a`. Two facts computed from file names close that: `has-account-pages` (pages under `Account/`, `Me/`, `Profile/`, `Security/`, `Preferences/`, `Settings/{Account,Profile,Security,Password,Preferences}/`, and `*AccountSettings*`, `*ProfileSettings*`, `*UserSettings*`, `*UserPreferences*` pages) and `has-workspace-settings-pages` (`Teams/Show.vue`, `Team*/Settings/`, `Workspace*/Edit`, `Workspace*/Deletion/`, `Organisation`/`Organization` settings, `Settings/Workspace*`, `Settings/Team*`, and `*TeamSettings*`, `*WorkspaceSettings*`, `*OrganizationSettings*` pages). Both read inside tey/mod module page folders too, and never flag the kit's own `Workspace/Account/**` and `Workspace/Settings/**` pages or `components/ui/**`. A domain component such as `SlideSettings.vue` matches none of them. `account.pages` also counts the kit's Password and Delete pages as adoption.
- **New surfaces: `settings.frame` and `mcp.connections`.** `settings.frame` is the account and workspace settings frame (`Workspace/Account/Layout.vue`, `Workspace/Settings/Layout.vue`); an app's own `Account/Layout.vue`, `Settings/Layout.vue`, `Settings/Index` or `layouts/settings/**` reads hand-rolled. `mcp.connections` is the AI assistants page (`Workspace/Settings/Connections/Page.vue`); `*Connection*`, `*ConnectedApp*`, `*Assistant*` and `*Token*` pages under `Settings/`, `Account/`, `Profile/` or `Me/`, `ConnectedApp*/`, `ApiTokens/` and Jetstream's `API/` pages, and an app controller, view model, action or Livewire class referencing Passport's `Token`/`TokenRepository` or Sanctum's `PersonalAccessToken` read hand-rolled.
- **`platform.timezone` flags an app's own timezone picker.** A `*Timezone*.vue` outside the kit's components and Welcome timezone pages reads hand-rolled. The kit ships no passkey management page (only the `PasskeyOffer`), so `auth.passkeys` is unchanged.

The fixtures `account-hand-rolled`, `account-kit-installed` and `slide-settings-domain` pin these outcomes. No stub changes, no re-pin.

## v0.7.2

### Changed

- **A person with several workspaces lands on the one they last used.** The kit's home (`kit.home`) sends a signed-in person with several workspaces to the workspace they last opened (the session's current one), while they are still an active member and may open its dashboard; with none, or a stale one (removed, suspended, not admitted, deleted), it is the workspace list as before. For a signed-in person the logo (`shell.product.url`) and `shell.links.home` now link to `kit.home` too, not `/me/workspaces`; the switcher and a guest's shell are unchanged. One workspace still shows its dashboard there, and a guest still signs in first. Sign-in lands on `kit.home` with workspace-ui too: the kit's `fortify.home` default is now `kit.home` with or without workspace-ui (it was `/workspaces`, the list). A host with its own `config/fortify.php` keeps its `home`; set it to your `kit.home` (`/` by default) to land on the last workspace. No stub changes, no re-pin.

## v0.7.1

### Upgrading

- `StartKitRequest` moved from `Tey\Kit\Features\SessionUi\Http\Middleware` to `Tey\Kit\Features\Core\Http\Middleware`, and core now registers it (see Fixed). The old name is a deprecated alias of the new class, to be removed in a later release: a host that names it (a route's `withoutMiddleware`) keeps working, but should switch to the new namespace. No stub changes, no re-pin.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:6537c1e1…3fa7` → `sha256:909d632f…822e`): `Kit/Form/submitForm.ts` and `Kit/Form/formFrame.ts` are new, and `Kit/Form/FormActions.vue`, `Kit/Form/FormCard.vue`, `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchActions.vue`, `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/workbench.ts`, `Kit/contract.ts` and `Kit/index.ts` change for `useSubmitForm` and the Workbench's `:form` (Added). Nothing changes for a page that doesn't use them: a Workbench without `:form` renders as before.
- **A stale write on an Inertia request is now a JSON 409, not the error page** (Changed). A host whose page or test expected the `Workspace/Error` page (or its own error page) when `updateIfCurrent()` refused a stale save from an Inertia visit now gets `{"error":"stale_write"}` with status 409, which Inertia hands to `onHttpException`. Handle it there (the Workbench's `:form` and `useSubmitForm` already do), or update the test. Every other error code still renders the error page.

### Added

- **`useSubmitForm(initial, handler, options?)`, a form saved by the app's own handler** ([docs/submit-form.md](docs/submit-form.md)): a `fetch`, `useHttp` or a socket, never an Inertia visit. It returns what `FormActions` reads from a `useForm()`, plus `data`, `errors`, `submit()`, `setDefaults()` and `conflict`. A `SubmitError`, a thrown fetch `Response` or `useHttp` error (422 Laravel errors to the fields, 409 `stale_write` to `conflict`, any other status a generic message) and a thrown Laravel 422 object are read in that order; anything else is the generic message. Messages are plain text capped at 300 characters. Options: `timeout`, `resetOnSuccess` (`true` or a list of fields), `transform` and `warnOnLeave`. A double submit is ignored.
- **`FormCard` with a `useSubmitForm()` is the `<form>` itself** (novalidate): Enter and Save submit with no visit, a failure focuses the first invalid field, and the `_form` message shows above the bar. `submit` stays the action descriptor.
- **The Workbench's form frame, `:form`** ([docs/workbench.md › Form frame](docs/workbench.md#form-frame)): an Inertia `useForm()` with `save` (the action descriptor it submits to), or a `useSubmitForm()`. One `<form novalidate>` around the grid, Save as the bar's primary submit (the app's actions go secondary), ⌘S / Ctrl+S, Saving… and Saved from the form, an errors summary that reveals and focuses the first invalid field, the conflict alert on a 409 with the edits kept, and the leave-page guard. `@submit` replaces the kit's submit.
- **`formFrame.ts`**: the form status and the leave-page guard, taken out of `FormActions` and shared with the Workbench. A frame inside one that is guarding registers no second guard.

### Changed

- **A stale write answers JSON on an Inertia request.** When kit's stale-write guard refuses a save (`RuntimeException('stale_write')`), an Inertia request now gets the same JSON 409 `{"error":"stale_write"}` as a JSON one, so the page keeps the person's edits and shows the conflict instead of being replaced by the error page. Only `stale_write`: every other error code, and a 404, on an Inertia request still renders the error page. A PHP check sends a stale save through the real kernel and compares the body to `tests/fixtures/stale-write-409.json`, the file the JS test feeds `useSubmitForm`.

### Fixed

- **The kit's props survive a flush on a host without session-ui.** The per-request start (`StartKitRequest`: the registry's `startRequestUsing` hooks, the request memo and access's memos, and `KitShares::restore()`) was session-ui's, so a core and shell host without it lost `kitUi`, `kitToasts` and `shell` after `Inertia::flushShared()` (pest-plugin-browser's, before each request, or an Octane-style reset), and its own early middleware read around the request memo. Core now prepends it to `web` and to the middleware priority list on every host that adopts core; session-ui keeps `PrepareKitRequest` (the late restore and the session-dependent brand switch) and no longer adds `StartKitRequest`, so it is registered once. The host still wins: restore only fills keys Inertia's factory doesn't hold. `composer test:core-request-start` (new, in CI) runs a core and shell host through the real HTTP kernel; `test:request-order` now counts `StartKitRequest` once in `web` and in the priority list with session-ui, under either name.
- **`kit:status` flags a request start that is off.** Core joins `StartKitRequest` to `web` only when the app defines that group as core boots, so an app that defines `web` later, or not at all, quietly ran without request memos and lost the kit's Inertia props after a flush. With core adopted, status now carries a `web StartKitRequest` host step: met when `web` holds it (the deprecated session-ui name counts), else attention with the fix. `test:status` covers all three cases.
- **Enter in the Workbench's navigator filter no longer submits a form around it.**

## v0.7.0

### Upgrading

- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:29d14952…750e` → `sha256:e35e2879…7ae0`): `Mcp/Authorize/Page.vue` takes the "Also allow" grant checkboxes below. Nothing to do for an app with no explicit tool: the fieldset renders only when there is a grant to offer.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:6b2a9dac…5077` → `sha256:93780271…035d`): the manifest gains the `vite_global_types` host step below; no stub changes.
- The kit's runtime floors are now `tey/mod ^0.11.6`, `tey/standards ^0.11.51`, `spatie/image ^3.9` and `laravel/mcp ^1.0.2` (see Fixed). An app on an older version of any of them gets the new one on `composer update`; nothing else to do.
- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:7e095959…f01b` → `sha256:29d14952…750e`): `Mcp/Authorize/Page.vue` shows the redirect host and the unverified-app note below, and the migration `0001_01_02_000603_create_kit_mcp_registrations_table` is new. Then `php artisan migrate`.
- The `mcp` feature now serves its own `/.well-known/oauth-authorization-server` and `/.well-known/oauth-protected-resource` documents (root and path-suffixed) and no longer calls `Mcp::oauthRoutes()`. A host that re-declared those routes over laravel/mcp's (to advertise its scopes) can drop them: `scopes_supported` is the kit's token scopes, which include the host's. A host that re-declared `POST /oauth/register` over laravel/mcp's controller can drop that too and set `kit.mcp.registration` to true; its existing clients keep working. `token_endpoint_auth_methods_supported` is now `["none"]` only.
- A host's unregistered-`redirect_uri` test that expected Passport's 401 `invalid_client` JSON for a browser request now gets the kit's 400 page; JSON requests keep the 401. The `laravel/mcp` requirement is `^1.0.2`.
- **A client that registered `http://localhost/...` (or `http://127.0.0.1/...`, `http://[::1]/...`) now matches that redirect on any port** at authorize and token time (RFC 8252 §7.3), as Claude Code's per-session port needs. Only the port is free: the scheme, host, path and query still match exactly, and nothing changes for `https` redirects.
- New migration for `mcp`, `0001_01_02_000603_create_kit_mcp_registrations_table` (the `kit_mcp_registrations` table: which OAuth clients came from `POST /oauth/register`). Run `kit:update plan|apply` for `mcp`, then `php artisan migrate`. Until it is migrated, registration answers 500 `server_error` and `kit:mcp:clients` refuses. A client registered before (laravel/mcp's registration, or the kit's before this table) is not in it: `php artisan kit:mcp:clients --adopt=<client id>` records one by hand, so that `--revoke` and `--prune-unused` can touch it; the kit never guesses.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ea1d2e42…3b8c` → `sha256:652e0756…7ac3`): `Kit/Form/Amount.vue` is new, and `Kit/Form/InputNumber.vue`, `Kit/Form/number.ts`, `Kit/contract.ts` and `Kit/index.ts` change for percent, symbols and the shared `kitNumber` defaults.
- **Money inputs no longer default to CAD.** Set `KIT_CURRENCY=CAD` (config `kit.currency`) to keep the old symbol, or pass `currency` per input.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:652e0756…7ac3` → `sha256:ca87cd7a…7242`): `Kit/Form/AddressFieldset.vue`, `Kit/Form/AddressText.vue`, `Kit/Form/RegionSelect.vue` and `Kit/Form/address.ts` are new, and `Kit/contract.ts` and `Kit/index.ts` export them.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ca87cd7a…7242` → `sha256:bc488ba6…e150`): `Kit/Form/InputTags.vue` and `Kit/Form/tags.ts` are new, and `Kit/contract.ts` and `Kit/index.ts` export them. InputTags puts a caller's `aria-describedby`, `aria-invalid` and `required` on its input, as InputSelect does.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:bc488ba6…e150` → `sha256:a3439714…0e66`): this adds `Kit/Data/DateTime.vue`, `dateTime.ts` and `useTimezone.ts`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a3439714…0e66` → `sha256:962f4983…6ecb`): this adds the pickers (`Kit/Form/InputDate.vue`, `InputDateRange.vue`, `InputMonth.vue`, `InputYear.vue`, `InputTime.vue`, `InputTimeRange.vue`, `PeriodPicker.vue` and `dateInput.ts`), and `css/kit.css` gains their phone-sheet rule. `core` now declares `@internationalized/date` (`^3.5.0`, already installed with reka-ui) in its npm requirements.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:962f4983…6ecb` → `sha256:a04c162c…2dd6`): `Kit/Layout/stickyBars.ts` takes the fix below. A bar registered with `register(px)` keeps working: `px` now means its inset from the viewport's bottom, which for a bar flush with the bottom is its height.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a04c162c…2dd6` → `sha256:81fceb81…95fd`) and `notes` (`sha256:d373d438…f1b2` → `sha256:1a0455e7…551b`): `css/kit.css` adds the `--success-text`, `--warning-text` and `--info-text` tokens, `Kit/Form/FormActions.vue` shows "Saved" in `text-success-text`, `Kit/Feedback/alertVariants.ts` gives a pale Alert the theme's foreground for its text and a `*-text` token for its icon, and the Notes page's `colours.ts` gives a coloured note the theme's foreground. No kit stub colours text with a raw palette colour any more.
- A host with its own theme (not importing `kit.css`) must define `--success-text`, `--warning-text` and `--info-text` for light and dark; the kit's AA values are a starting point: `hsl(142 76% 26%)`, `hsl(26 90% 33%)` and `hsl(201 90% 32%)` light, `hsl(142 69% 58%)`, `hsl(43 96% 56%)` and `hsl(199 89% 64%)` dark. Until it does, the kit's theme host step reads unmet. A host theme's value wins over the kit's, as for the other tokens.
- session-ui's confirm-password Cancel is now resolved by the bindable `Tey\Kit\Features\SessionUi\Contracts\ConfirmPasswordCancelUrl` (default `ConfirmPasswordCancel`). Nothing to do unless you want your own: bind it. No stub changes, no re-pin.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:81fceb81…95fd` → `sha256:4a94fc7c…105e`): `Kit/Appearance/useAppearance.ts` takes the storage fix below. No other stub changes.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4a94fc7c…105e` → `sha256:6537c1e1…3fa7`): `Kit/Workbench/Workbench.vue` takes the phone-tabs fix below.
- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:71617f5d…3108` → `sha256:592e21c4…7353`): `Auth/ResetPassword/Page.vue` gains a `FormStatus` (below).

### Added

- **`kit:status` and `kit:add` flag an undefined connection ability.** `kit.mcp.connection_ability` defaults to `settings.read`, which the settings feature declares; a host without it (or with its own value) had every MCP connection refused as `permission_denied`. With the `mcp` feature, the host requirements now carry a `kit.mcp.connection_ability` config step: met when a feature declares the ability through the Registry or the Gate defines it, else attention naming the key, the effect and the three fixes. `test:status` covers the default without settings, an undefined value, a Gate-defined one and a Registry-declared one.
- **Explicit grants for MCP tools** ([docs/mcp-consent.md › Explicit grants](docs/mcp-consent.md#explicit-grants)). `#[Capability(…, scope: 'x', explicit: true, explains: '…')]` marks a tool that a blanket acts-as-me connection never covers: it is listed and callable only under a connection whose consent named its scope, and a call anyway is refused `scope_required` (403), never reported as unknown. The kit's consent page offers each such scope the person may hold as an unticked "Also allow" checkbox showing the scope's label and `explains`; only the tick grants (the OAuth `scope` parameter never does), an existing connection never gains one (skipped consent and refresh keep its scopes), and the approval's audit entry records the grants. `scope` must be a registered token scope and `explains` is required; registration refuses otherwise. On the AI assistants page each grant has its own Remove action (`DELETE …/connections/{id}/grants/{scope}`, audited `workspace.connection.grant_removed`), leaving the connection and its token in place. `Registry::explicitScopes()`, `AuthorizeViewModel::grants()`, `WorkspaceConnection::explicit()` and `removeGrant()` are new. `composer test:mcp-explicit-grants` (34 checks).
- **`kit:status` and `kit:add` check that the Vite config gives @vitejs/plugin-vue the kit's types** (the `shell` feature's `vite_global_types` host step; [README › Types](README.md)). A page typed with `defineProps<Tey.Kit.…>()` is resolved at build time from `script.globalTypeFiles`, so a production build fails without `vendor/tey/kit/types/kit.d.ts` there; a tsconfig include alone serves the editor and vue-tsc. The Vite config (`vite.config.ts`, `.js` or `.mjs`) is read as text, never run: the step is met when an array literal under `globalTypeFiles` names the kit's types (a plain path or a `path.resolve(...)` of it), unmet with the exact snippet `vue({ script: { globalTypeFiles: ['vendor/tey/kit/types/kit.d.ts'] } })` when the key is missing or the list lacks it, and "can't verify" (unmet, never passed) when there is no Vite config, it can't be read, or `globalTypeFiles` is not an array literal. Attention, not blocking, like the other host steps. For a config the kit cannot read, `kit.host.vite_global_types = 'manual'` (`KIT_HOST_VITE_GLOBAL_TYPES=manual`) records that a person verified it; the step then reads "verified manually". `composer test:status` covers each case.
- **`kit.mcp.server.page_size`** ([docs/mcp-tools.md › The server's name and instructions](docs/mcp-tools.md#the-servers-name-and-instructions)). How many tools, resources or prompts one MCP list answer carries, 50 by default (laravel/mcp's maximum; larger values are clamped, a non-number falls back to 50). laravel/mcp's default of 15 hid every tool past the fifteenth from assistants that never follow `nextCursor`. `composer test:mcp-server-config` lists thirty tools in one page by default, pages at a host's 10, and clamps 500 to 50.
- **MCP resources from the host** ([docs/mcp-tools.md › Resources](docs/mcp-tools.md#resources)). `Registry::mcpResources([...])` takes laravel/mcp `Resource` classes or descriptors (`uri`, `name`, `read`, and optionally `mimeType`, `description`, `title`, `capability`), and the workspace server lists and serves them under the same admission as tools: a resource with `#[Capability]` (using `GatedByCapability`) or a descriptor's `capability` is absent while that capability is off or out of plan, and a read of it anyway is refused with the reason (the kit's `resources/read` method), not as unknown; a resource without one (docs) is always listed. A read runs inside the connection's workspace, and a descriptor's closure receives it. Refused at registration: a non-`Resource` class, a gated class without the trait, an undeclared capability, a descriptor missing its uri, name or read, a relative uri, a uri registered twice, or any of it without the `mcp` feature. `composer test:mcp-resources` covers the list, reads, the gate off and on, an unknown URI and each refusal.
- **MCP client registration and discovery, the kit's own** ([docs/mcp-registration.md](docs/mcp-registration.md)). Opt-in OAuth 2.0 Dynamic Client Registration (RFC 7591) at `POST /oauth/register`, `kit.mcp.registration` (`KIT_MCP_REGISTRATION`, off by default: the route does not exist), for public clients only: an assistant such as Claude registers itself with no secret issued and no management credentials. Redirect URIs are checked at registration: `https` with a host, `http` to a loopback host on any port (Claude Code's per-session port, RFC 8252), or a private-use scheme the host lists in `kit.mcp.redirect_schemes` (empty by default); never a wildcard, fragment, userinfo, plain `http` elsewhere, or `javascript`, `data`, `blob`, `file`. A per-IP rate limit (`kit.mcp.registration_per_minute`, 10) and an optional `kit.mcp.registration_policy` (a `RegistrationPolicy` class refusing with a reason, 403 `access_denied`). The client is the Passport row laravel/mcp's registration stored, so a client registered before keeps working, with no column added. The discovery documents (RFC 8414 and RFC 9728, root and `/mcp`) are now the kit's, under laravel/mcp's route names, advertising only what the kit implements: `code`, `authorization_code` and `refresh_token`, S256, public clients, the kit's token scopes, and `registration_endpoint` only when registration is on; `Mcp::oauthRoutes()` is no longer called. The 401 on `/mcp` adds `scope="mcp:use"` to laravel/mcp's challenge. Passport's client bridge matches a loopback `localhost` redirect without its port, as league already does for `127.0.0.1`. `kit:mcp:clients` lists registered clients, `--revoke=<id>` revokes one with its tokens, `--prune-unused=<days>` revokes those with no token in that time (`--dry-run` to look first). `composer test:mcp-registration` covers each RFC requirement, the refusals, the limit, the policy, the metadata shape, a pre-existing client and registration off.
- **The consent page names where the assistant sends you.** It shows the redirect host Passport verified for the request, and for a self-registered client (dynamic client registration, or an owner-less public client shaped like one) adds "Unverified app": the name is the client's own claim, so a person can tell a lookalike from the app they expect. `AuthorizeViewModel` gains `redirect_host` and `registered`; the `Mcp/Authorize/Page.vue` stub shows them.
- **PKCE S256 required at authorize.** Every authorization request must carry a `code_challenge` (43 to 128 unreserved characters) with `code_challenge_method=S256`; a missing challenge or the `plain` method is refused with 400 before league sees it, never redirected. Claude and every MCP client send S256 already.
- **The OAuth error page, brandable.** When an authorization request cannot go back to its app (unknown client, unregistered `redirect_uri`, bad PKCE), a browser now gets a readable 400 page instead of Passport's bare `invalid_client` JSON; JSON callers and errors sent back to a verified redirect URI are unchanged. The kit's `kit::oauth-error` view by default; a host sets `kit.mcp.oauth_error_view` to its own view, or `OAuthErrorPage::using(fn (Request, string $error, ?string $description): Response)` for its own page (an Inertia error page, say). A host that subclassed Passport's authorization controller for this can drop that.
- **Money and percent: `<InputNumber kind="percent">` and `<Amount>`** ([docs/money-percent.md](docs/money-percent.md)). Percentages are canonical in percentage points ("12.5" is 12.5 %). Money and percent inputs show the locale's symbol outside the editable text and name the unit in the label; `negative="parentheses"` gives the accounting form. `<Amount>` displays the same canonical strings in tabular figures. Locale and currency default to the new shared `kitNumber` (config `kit.currency`, the app locale), then the browser.
- **`<AddressFieldset>`, `<RegionSelect>` and `<AddressText>`** ([docs/address.md](docs/address.md)). A postal address as one named group, submitted as `address[line1]` … `address[country]`, with autocomplete tokens, labels that follow the country (Province or territory and Postal code, State and ZIP code), Canadian postal codes written A1A 1A1 on blur, and non-blocking hints for unlikely CA and US codes. Regions are the Canadian provinces and territories and the US states and DC, named in English or French; other countries take free text. `<AddressText>` shows an address in an `<address>` element, with a map link only when the app passes its own `mapUrl`. Labels come from the new `address.*` keys in the kit's `ui` lang file.
- **`<InputTags>`, free-text tags as chips** ([docs/input-tags.md](docs/input-tags.md)). Built on `InputSelect` (multiple and creatable), so the combobox, chips and announcements are the same. A separator (`,` or `;`) or Enter adds the typed tag, and Enter takes a suggestion only after the arrow keys move to it. A paste with separators or line breaks adds each piece. Duplicates (ignoring case in the locale), tags over `maxLength`, tags not matching `pattern` and tags past `max` are refused with the reason under the field, never trimmed. Submitted as `name[]`. Reasons come from the new `tags.*` keys in the kit's `ui` lang file.
- **`DateTime`, dates and times for the viewer** ([docs/date-time.md](docs/date-time.md)). It renders a `<time>` with the machine value and the full date in its `title`, in the formats `relative`, `local`, `date`, `time`, `ymd` and `datetime`. A **calendar date** (`YYYY-MM-DD`) is never converted between zones, so it never shifts across midnight or DST. An **instant** (RFC 3339 with its offset) shows in the viewer's timezone, and one without an offset is shown as given, never guessed. `live` re-renders relative times on one shared minute ticker. `formatDateTime()`, `fullDateTime()` and `useTimezone()` are exported too. It uses `Intl` only, with no date library.
- **session-ui shares `kitTimezone`**, the viewer's zone (the `tz` cookie, then the person's saved zone, then the workspace's, then UTC), through KitShares, so it survives a flush. `DateTime`, and the pickers to come, read it.
- **The adoption inventory knows `data.date-display`** (a new `shows-dates` fact). Hand-rolled timeago.js and moment components, and `TimeAgo`/`DateRelative`-style files, count against it.
- **Date and time pickers** ([docs/date-pickers.md](docs/date-pickers.md)): `InputDate`, `InputDateRange`, `InputMonth`, `InputYear`, `InputTime` and `InputTimeRange`, on reka-ui. Each submits plain ISO strings (`2026-11-01`, `2026-11`, `2026`, `09:30`; a range as `name[start]` and `name[end]`). A date, month, year or time is a wall value, never converted between zones; `kitTimezone` only decides today. The calendars use the APG date picker keys and the month and year grids the APG grid keys; on phones they open as a bottom sheet, and in a locked LockedForm they stay shut. A half-filled or reversed range blocks the native submit. `toInstant()` and `fromInstant()` join a date and time into an RFC 3339 instant with the viewer zone's offset (DST gaps move forward, repeated hours take the earlier moment).
- **`Tey\Kit\Validation\IsoDate` and `IsoRange`**, the server rules for those values: `IsoDate::date()`, `month()`, `year()`, `time()` and `instant()` (an offset required) with inclusive bounds, and `IsoRange::dates()` and `times()` for `{start, end}`.
- **Adoption surfaces `form.date-pickers` and `form.time-input`**, with the facts `takes-dates` and `takes-times`.
- **The adoption inventory reads Blade, Livewire and Filament apps** (tey/standards 0.11.51 or later). Every template-tag signal reads Blade views as well as Vue (`"in": ["vue", "blade"]`). Filament resources under `app/Filament/**` count as hand-rolled through class references: `TextInput`, `Select` and `Textarea` for `form.inputs`, `FileUpload` for `form.files`, `Table` and `TextColumn` for `data.table`, `DeleteAction` for `action.confirm` and `Notification` for `feedback.toast`, mirrored into the `has-forms`, `has-file-uploads` and `has-index-pages` facts. A new `has-filament-destructive-actions` fact lets `action.confirm` apply to a Filament app with no delete route. Blade views count for `shell.workspace` (the conventional app layout files), `layout.page` (`<x-app-layout>`, `<x-layouts.app>`), `action.button` (`<button>`, `<x-button>` and the Breeze button components), `feedback.toast` (toast, flash and action-message views) and `appearance.theme` (theme-toggle, dark-mode and appearance views). `platform.timezone` gains nothing: no signal kind expresses hand-rolled timezone handling in PHP or Blade without guessing.

### Changed

- **Money inputs no longer default to CAD.** Without `currency` or `kit.currency`, `<InputNumber kind="money">` shows no symbol (two decimals, the same canonical minor units) and warns in development. Set `KIT_CURRENCY=CAD` to keep the old symbol.

### Fixed

- **A workspace created in the same request is admitted.** `IsKitWorkspace` read `admitted` as null until the model was read back, so a workspace made and used in one request was refused `admission_required`. The trait now mirrors the column's default (true in the kit's migration) on an unsaved or just-created model whose table has the column, so it answers the way its row will; an explicit `admitted => false` stays false, and admission stays distinct from creation (a host whose adopted column defaults to false sets `admitted` to false in its model's `$attributes`). `test:workspace-guard` covers create-then-check.
- **Leaving a tenant no longer wipes Pennant's array store.** `TenantBoundary` and the `FlushFeatureCacheTask` switch task flushed Pennant's cache on every switch; under the `array` store (the kit's default, and tests) that cache is the store itself, so a flag deactivated earlier in the request came back on, and a test that deactivated a capability then called `/mcp` saw it on. Both now flush only for a store with a backend (`TenantBoundary::flushes()`: the default store's driver is not `array`), where the cache can go stale across tenants. `test:capability-guard` and `test:tenant-tasks` cover both stores.
- **A narrow connection no longer sees tools it cannot call.** `GatedByCapability` listed tools by capability and role ability only, so a connection whose scopes covered `settings.read` alone still saw the write tools and got `connection_denied` on the call. Presence now also requires that the calling connection covers the ability the tool is admitted under, the same `WorkspaceConnection::covers()` the call path applies, so `tools/list` exactly matches what is callable; the refusal codes for a call made anyway are unchanged (`connection_denied`, or `scope_required` for an explicit tool). Covered by `test:mcp-explicit-grants`.
- **A long footer or inspector scrolls inside its phone tab.** Below lg the Workbench's footer and inspector tabs rendered their slot straight into the tab's row, with no scroll container, so a long speaker note ran on behind the pinned actions bar and its end could not be reached. Both now sit in a `PaneScroll`, as their panes do at lg and up. The browser suite checks each at 390×844: the tab scrolls, and after scrolling to the end the last line is fully visible above the bar.
- **The reset-password page shows a form status like its siblings.** `Auth/ResetPassword` had no `FormStatus`, where `Auth/ForgotPassword`, `Auth/Login` and `Auth/VerifyEmail` do. It now has one, first inside the form as on ForgotPassword, showing the `status` page prop when a host app supplies one.
- **Dependency floors are tested.** CI's `tests` job gains a `lowest` entry (PHP 8.4, `composer update --prefer-lowest --prefer-stable`) running every check against the oldest versions `composer.json` allows, so a requirement whose floor predates an API the kit uses fails in CI rather than on a host. Its first run raises `tey/mod` to `^0.11.6` (0.11.0 to 0.11.5 lack the rollback fix `test:adopt` needs) alongside `laravel/mcp: ^1.0.2`, and adds `spatie/typescript-transformer: ^3.3` to require-dev (`bin/kit-types.php` uses `withoutManifest()`, new in 3.3.0, which laravel-typescript-transformer ^3.3 alone does not guarantee), `symfony/event-dispatcher-contracts: ^3.5` and `symfony/polyfill-php83: ^1.32` (older ones emit PHP 8.4 implicit-nullable deprecations at boot, which the checks that require a silent boot read as failures), and `spatie/image: ^3.9` in require (laravel-medialibrary ^11.13 allows 3.3, whose WebP fit does not keep the declared width `test:media-conversions` expects; 3.9.0 is the first that does).
- **FormActions' "Saved" meets WCAG AA.** It was `text-green-600`, about 3.3:1 on a white card (AA is 4.5:1), so axe's `color-contrast` failed on every FormCard just after a save. The new `--success-text` token, with `--warning-text` and `--info-text` beside it, is status text on a surface, as `--destructive-text` is: `hsl(142 76% 26%)` light (5.80:1 on background, card and popover, 5.32:1 on accent and muted) and `hsl(142 69% 58%)` dark (11.36:1, 8.68:1 on accent, 8.35:1 on muted); warning is 6.04:1 and 11.70:1, info 6.06:1 and 9.71:1, and at least 5.32:1 light and 7.13:1 dark on accent and muted. A pale Alert's icon takes these tokens (and `--destructive-text` for danger) instead of raw palette colours, and its text, like a coloured note's, takes the theme's foreground. `tests/js/semantic-text.test.mjs` computes the ratios from `kit.css` and refuses a raw palette text colour (`text-green-600`, `dark:text-sky-300`) in any kit resource, and the browser job's `?form-saved` page runs axe's `color-contrast` over a saved FormCard and the pale Alerts, light and dark.
- **A bottom toast clears a pinned bar that the layout holds above the viewport's bottom.** The sticky-bar registry recorded each bar's height, and the Toaster lifted bottom toasts by that much. A filling Workbench's actions bar sits wherever the layout puts it, which under a shell shorter than its 8rem allowance is above the bottom edge, so a bottom-right toast landed on its right-hand buttons (Present, Save). Each bar now registers its inset (the distance from the viewport's bottom to its top), re-measured on scroll and window resize, and toasts clear the largest one. A browser test asserts that no bar button is under a toast, checking each button's left, centre and right, at 1440×900 with bottom-right toasts and at a phone width.
- **`browser/` and `typecheck/` ignore a symlinked `node_modules`** (the pattern no longer ends in `/`, which matched directories only), so a contributor's linked install can't be committed. The resource checks refuse a tracked symlink.
- **session-ui's login page now shows Concierge's one-click local sign-in.** The login view passed no `localLogin`, so the button never appeared; it now carries `LocalLogin::offer()`, which stays the only gate (null outside `local`, and without Concierge).
- **Confirm-password Cancel falls through instead of jumping home.** A refused `cancel` query (off the app, protocol-relative, backslash, `javascript:`, credentials, encoded) now falls to the referer, then to the intended URL one level up when a real non-guest route answers there, then `kit.home`.
- **`kit:status` flags a missing `kit.home` route.** With session-ui and no workspace-ui, sign-in and Concierge's no-workspace redirect land on `kit.home`; with no route answering GET there it now reports attention instead of letting sign-in 404.
- **Blocked browser storage no longer leaves the page blank.** `useAppearance` read and wrote `localStorage` unguarded (in `initializeTheme`, on mount and in `updateAppearance`), so where storage throws on access (site data blocked, some private modes) the app never mounted. Every read and write is now guarded: a failed or unrecognised read falls back to `system`, and a failed write is ignored (the `appearance` cookie still carries the choice). The other core stubs that use storage (`ComputedValueScope.vue`, `SplitPane.vue`/`splitState.ts`, `DataTable.vue`, `Workbench.vue`/`workbenchLayout.ts`) were already guarded. The browser suite mounts the app with `localStorage` and `sessionStorage` throwing.

## v0.6.13

### Upgrading

- **kit v0.6.10–v0.6.12 shipped unresolved conflict markers in `Kit/Layout/Page.vue`; re-run `kit:update apply` for `core`** (re-pinned `sha256:ce15e8de…e8dd` → `sha256:c97858e5…41da`). The shipped file is a known prior version (history.json records it), so an untouched copy is replaced cleanly. A host that hand-resolved the markers sees its copy as edited: take the release bytes, or keep its resolution if it matches the one below.
- A host that re-shares the kit's props in its browser test case after pest-plugin-browser's flush (a `BrowserCase` workaround) can drop that re-share: the kit puts its props back on every web request.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f3932382…7dbe` → `sha256:6111274d…1cc8`): `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/workbench.ts` take the item menus below. An item without `actions` renders as before.
- Run `kit:update plan|apply` for `shell` (`sha256:56acbf30…3bb0` → `sha256:efd6116b…ba5e`): the Workspace Layout and its partials take the changes below, and FocusShell is new.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:6111274d…1cc8` → `sha256:2646246d…77fe`), `shell` (`sha256:efd6116b…ba5e` → `sha256:6b2a9dac…5077`), `session-ui` (`sha256:be81f458…6f4f` → `sha256:71617f5d…3108`) and `workspace-ui` (`sha256:93e9b61a…7bed` → `sha256:2f226d38…39e7`): `css/kit.css` adds the `--destructive-text` token, and every kit stub that coloured text with `text-destructive` now uses `text-destructive-text` (the menus' destructive items, `Kit/Form/Repeater.vue`'s Remove item, `Kit/Overlay/Dialog.vue`'s danger icon, the form and page error messages, `Kit/Action/HeaderStatus.vue`, `Kit/Search/SearchBox.vue`, `Kit/Workbench/WorkbenchActions.vue`, the login error, the workspace switcher's Leave item and the People page's expired date).
- A host with its own theme (not importing `kit.css`) must define `--destructive-text` for light and dark; the kit's AA values are a starting point: `hsl(0 72% 42%)` light and `hsl(0 91% 71%)` dark. Until it does, the kit's theme host step reads unmet. A host theme's value wins over the kit's, as for the other tokens.
- A host that builds navigator items on the server: send `actions` on the PHP `WorkbenchItem` (a list of `Action`) to get row menus, and regenerate or re-copy `types/kit.d.ts`, whose `WorkbenchItem` now has `actions`. A host that sends plain arrays can switch to `WorkbenchItem` unchanged.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:2646246d…77fe` → `sha256:e0090454…0845`): `Kit/Form/FormStatus.vue` and `Kit/contract.ts` take the FormStatus fix below, and `FormStatus` takes an optional `flashKey`. Pages that pass no `flashKey` keep reading the `status` page prop.
- **An app that flashes its own confirmation with `Inertia::flash()`** can show it with `<FormStatus flash-key="notice" />`: Inertia v3 keeps flash data at `usePage().flash`, not in `page.props`. Fortify's `status` stays a shared prop and needs no change.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e0090454…0845` → `sha256:93b89e36…51e0`): `Kit/Workbench/Workbench.vue` takes the slot and the phone layout fix below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:93b89e36…51e0` → `sha256:ca1f9248…7179`): `Kit/http.ts` is new, and `Kit/Workbench/useWorkbench.ts`, `Kit/Workbench/workbenchLayout.ts` and `Kit/Feedback/passkeyOffer.ts` take the fix below. A host whose own code reads `response.data.x` from Inertia v3's `http` client can read it through `responseJson(response)` from `@/components/Kit/http` instead.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ca1f9248…7179` → `sha256:ce15e8de…e8dd`): `Kit/Action/actionPlacement.ts`, `Kit/Action/ActionMenuItems.vue`, `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchNavigator.vue` and `Kit/Workbench/workbench.ts` take the checkbox menu items and `#navigator-item` below. ActionMenuItems now imports `DropdownMenuCheckboxItem` from the host's shadcn-vue `dropdown-menu` (DataTable already did). A descriptor without `checked` and a Workbench without `#navigator-item` render as before.
- No changes needed. A host that relied on its `HandleInertiaRequests`' position for the request memo, or that wraps a kit prop in its own share, keeps working. A workaround that moved kit middleware earlier (such as `prependToPriorityList` for `PrepareKitRequest`) can be removed.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c97858e5…41da` → `sha256:12a237c5…e9f8`): `Kit/Form/InputCheckbox.vue`, `Kit/Form/Select.vue`, `Kit/Form/FileInput.vue`, `Kit/Form/RadioCardGroup.vue`, `Kit/Form/InputRichText.vue`, `Kit/Form/InputSelect.vue`, `Kit/Form/InputNumber.vue` and `Kit/contract.ts` take the attribute fix below. InputCheckbox's row is now wrapped in a grid (for its `description`), so a host that styled its row by structure should check it.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:12a237c5…e9f8` → `sha256:da818a98…6ef1`): `Kit/Layout/StickyBar.vue`, `Kit/Layout/stickyBars.ts`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/WorkbenchActions.vue` take the fix below. `StickyBar` takes an optional `pinned`.
- **A host with its own `home` route or its own invitation flow** can now keep it. Set `kit.home_name` (for example `app.home`) to rename the kit's home route, and set `kit.routes.session-ui.invitations => false` to leave out the kit's invitation link (transitional; see [docs/catalog.md](docs/catalog.md)). Nothing changes by default.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:da818a98…6ef1` → `sha256:fa5d0296…4815`): `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/Workbench.vue` and `Kit/Workbench/workbench.ts` take the thumbnails and filmstrip layout below. An item without `thumbnail` in the default `list` layout renders as before. A host that copies `types/kit.d.ts` re-copies it: `WorkbenchItem` has `thumbnail`, and `WorkbenchThumbnail` is new.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:fa5d0296…4815` → `sha256:ea1d2e42…3b8c`): `Kit/Form/SegmentedToggle.vue` is new, and `Kit/index.ts`, `Kit/contract.ts` and `Kit/Layout/PaneTabs.vue` take the changes below. **PaneTabs looks different:** its pressed pane is no longer a primary-filled button but a neutral raised segment in a muted track. Its semantics, props and events are unchanged. A host that styled around the old primary button, or asserted its classes, should check its phone tabs.

### Added

- **Workbench navigator thumbnails and a filmstrip layout** ([docs/workbench.md › Thumbnails and filmstrip](docs/workbench.md#thumbnails-and-filmstrip)). A navigator item takes an optional `thumbnail` (`{ src, alt }`, exported as `WorkbenchThumbnail`; `alt` is required, `''` for a decorative image that repeats the label), rendered as a lazy, async-decoded `<img>` inside the item's `role="option"` row in a fixed-aspect box, so the row doesn't shift when it loads. `navigator-layout="filmstrip"` on the Workbench (`layout` on WorkbenchNavigator) lists the items as a Slides-like editor's left rail: the item's number, a 16:9 thumbnail (an empty placeholder box without one) and the label; `list` stays the default. `#navigator-thumbnail` (`{ item, index, selected }`, typed as `WorkbenchNavigatorThumbnailSlotProps`; `thumbnail` on WorkbenchNavigator) fills each filmstrip box with the host's own render, such as a scaled live slide, ahead of `thumbnail.src` and the placeholder. Its wrapper is `inert` and `aria-hidden`, and the box has `contain: strict` with overflow hidden, so the render can't take focus, change the option's name or move the layout. List rows take no thumbnail slot. The listbox semantics, keys, Alt+↑/↓ reorder, drag, filter, F6, `#navigator-item` (the label part) and the row menus beside the listbox are unchanged. The PHP `WorkbenchItem` takes `thumbnail` (a new `WorkbenchThumbnail` data class, default null) and `types/kit.d.ts` is regenerated; the MCP `WorkbenchReadTool` still gives exactly `{ id, label, type, description }`, leaving the thumbnail out. `composer test:workbench-item-parity` covers the new key through the Inertia round trip, and the browser job checks a filmstrip: images inside the options with their alt, nothing focusable in the listbox, Alt+↓ reorder, filter and F6, axe clean with color-contrast, each thumbnail box sized before its image loads, and a live `#navigator-thumbnail` holding a button and a link: inside the box at the image box's size, never focused or tabbed to, the option named by its label only, axe clean, and Alt+↓ working.
- **Checkbox menu items** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). An action descriptor with `checked` (true or false) renders in ActionMenuItems, and so in every kit menu built on it including the Workbench row menu, as `role="menuitemcheckbox"` with `aria-checked` and the host's check indicator (shadcn-vue's DropdownMenuCheckboxItem). Choosing it emits the action as today; the host flips `checked`. Without `checked`, or with `null`, the row stays a plain menuitem. The PHP `Action` takes `checked` (nullable bool, default null), and `types/kit.d.ts` is regenerated. A row menu opened from the item focuses its first row, checkbox rows included.
- **Workbench `#navigator-item` slot** ([docs/workbench.md › Custom rows](docs/workbench.md#custom-rows)). The Workbench forwards WorkbenchNavigator's `item` slot as `#navigator-item` (`{ item, selected, index }`, typed as `WorkbenchNavigatorItemSlotProps`) in every layout: columns, the lg drawer layout, phone tabs and the overlay navigator. A host customises row content without replacing the navigator. The kit keeps the `role="option"` row, its keys and the row menus outside the listbox; the slot holds content only. WorkbenchNavigator now declares its `item` slot with `defineSlots`. The browser job checks a row menu with a checked and an unchecked item and a Workbench using `#navigator-item`, with axe clean on both.
- **SegmentedToggle, a neutral view switch** ([docs/segmented-toggle.md](docs/segmented-toggle.md)). `Kit/Form/SegmentedToggle.vue` picks one of a few views ("Slides | Outline", grid or list) with `v-model`, `options` (`{ value, label, icon?, disabled? }`), `label` and `size`. Its selected segment is a bordered `bg-background` surface in a `bg-muted` track, never brand-filled. It's a `role="radiogroup"` of `role="radio"` buttons with one tab stop: the arrows move and select, Home and End jump, and disabled options are skipped. The selected border keeps 3:1 against the track and the segment in light and dark. `tests/js/segmented-toggle.test.mjs` checks the source and the ratios; the browser job drives the keys, compares the selected background with the primary colour and runs axe with colour contrast, light and dark.
- **Workbench navigator item menus** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). A navigator item can carry its own kit action descriptors in `actions` (Move up, Move down, Duplicate, Delete, as the host decides per item), and gets a row menu: a button named "Actions for <label>", shown on hover and focus and always on a touch pointer, plus right-click, Shift+F10 or the context-menu key on the item, which open the same menu anchored to the row. A chosen descriptor is emitted as the Workbench's `action` event with the item as its second argument. Escape returns focus to the item, or to the button the menu opened from. The buttons sit in a column beside the listbox, never inside it, since a listbox may own only options. The listbox's arrows, filter and F6 are unchanged, and so is Alt+↑/↓ reordering. An item without actions has no button and keeps the browser's context menu. The browser job (Playwright with axe-core) checks a three-item Workbench: no axe violations, no focusable element inside the listbox, and the Shift+F10, right-click and Escape paths.
- **Server-side item menus: `actions` on the PHP `WorkbenchItem`** ([docs/workbench.md › Item menus](docs/workbench.md#item-menus)). `WorkbenchItem` takes `actions`, a list of the kit's `Action` (default empty, no menu), and a WorkbenchViewModel sends them to the page unchanged; the generated type has `actions: Action[]`. The MCP `WorkbenchReadTool` still gives each item exactly `{ id, label, type, description }`: row-menu actions are UI affordances, and no MCP tool exposes action descriptors. `composer test:workbench-item-parity` pins the PHP keys to the TS `WorkbenchItem`, the generated type, the read tool's keys and the Inertia round trip of Move up, Duplicate and Delete.
- **`#navigator-actions` on the Workbench** ([docs/workbench.md › Navigator actions](docs/workbench.md#navigator-actions)). The app's own controls, such as "+ New slide", render first in the navigator, before its filter and list, at every width (columns, lg, phone tabs and the overlay sheet). So the visual and tab order match without `order-first`. With the slot empty, the navigator renders as before.
- **session-ui route groups and `kit.home_name`.** `kit.routes.session-ui.<group> => false` switches off one group of session-ui's routes, and their names, at a time: `invitations`, `welcome`, `passkey-offer` or `demo-login`. Every group is on by default, and the kit's own redirects and props for a group that is off are guarded. An unknown key or a non-boolean value stops the boot with an error that names the groups. `kit.home_name` (`KIT_HOME_NAME`, default `home`) names the kit's home route, so an app that already owns `home` keeps it; the kit's breadcrumbs follow the new name. Checked by `composer test:route-groups`.

### Fixed

- **`laravel/mcp` 1.0.0 and 1.0.1 broke every route load.** The kit calls `Mcp::oauthRoutes(registration: false)`, whose `registration` parameter arrived in laravel/mcp 1.0.2; on an older install every request failed with "Unknown named parameter $registration". The requirement is now `laravel/mcp: ^1.0.2`, the first release with every laravel/mcp API the kit uses.
- **The Workbench reorder kept a null version token after Inertia v3 returned a string body, so the next write got a 409.** Inertia v3's HTTP client resolves `data` as the raw response text, and `sendOrder` read `response.data.version` from it, so after every reorder the token was lost and the next stale-safe write (another move, an add, a duplicate, a delete) was refused as `stale_write`. Bodies now go through core's new `responseJson`, which returns an object `data` as it is and parses a string as JSON, and throws on an empty or non-JSON body instead of handing back null (`allowEmpty` returns null where a caller expects no body); `errorJson` reads a refused request's body, as null when it isn't JSON, since the status has already decided. The same string-body bug lost the stored layout and version on a 409 from the Workbench layout endpoint (`sendLayout`), and the creation options in the passkey offer (`registerPasskey`); both read through it now. A 2xx reorder answer that isn't JSON is a failed save, so the old order comes back. `tests/js/response-json.test.mjs` covers the helper, `workbench.test.mjs` and `onboarding.test.mjs` string bodies, and a browser check makes two moves and a Duplicate against a stand-in server that refuses a stale or null version.
- **The kit's Inertia props survive `Inertia::flushShared()`.** `kitUi`, `kitToasts`, `shell`, `environment`, `kitPassword` and `passkeyOffer` were shared once at provider boot, so anything that flushed Inertia's shared props afterwards dropped them: pest-plugin-browser flushes before each request, so a host's browser tests rendered blank pages, and an Octane-style reset or a host's own flush would too. Each provider now hands its closures to `KitShares`, which shares them at boot and again at the start of every web request (now first in `web`, see below). They stay lazy closures, one producer each. The host still wins: the kit puts back only the keys Inertia's factory doesn't already hold, and the host's own `HandleInertiaRequests` merges over them, so a key the host shares (in its middleware, or on the factory before the request) is never overwritten. The kit still never flushes. `test:inertia-shares` and `test:request-hooks` (through the real HTTP kernel) flush between requests and check every kit prop, the host's own `shell` and `environment`, and two people in turn.
- **`Kit/Layout/Page.vue` is a valid component again.** A merge in v0.6.10 left `<<<<<<<`, `=======` and `>>>>>>>` lines around the page header, and they shipped in v0.6.10, v0.6.11 and v0.6.12. The header is now the status and actions version (HeaderStatus, `status`, `actions`) with the `font-display` heading face, as both sides intended. A new CI check, `composer test:conflict-markers`, fails on any conflict marker (indented ones included) under `resources/` or `src/`.
- **The kit's request start now runs first in `web`.** `PrepareKitRequest` was appended, so it ran last: a host's own middleware earlier in `web` (a tenant resolver) read memberships before the request memo began, and repeated their queries; after an `Inertia::flushShared()` a host share wrapping a kit prop (`Inertia::getShared('shell')`) captured null, so the page fell back to the guest layout; and a route-model-binding 404 never reached it, so the 404 page had no kit props. session-ui now prepends a new `StartKitRequest` to `web` and to the middleware priority list. It begins the request's memos (the registry's new `startRequestUsing` hooks: the request memo and access's permission, capability and entitlement memos) and puts the kit's props back (`KitShares::restore()`), neither of which needs the session or the signed-in person. The session-dependent hook (the local brand switch) stays late in `PrepareKitRequest`, which also restores once more. The host still wins: restore only fills keys Inertia's factory doesn't hold, and the host's own `HandleInertiaRequests` merges over them. Each memo is scoped to its request, so nothing carries over. `composer test:request-order` (new, in CI) runs a host through the real HTTP kernel and checks that the host's second membership lookup adds no query, that the wrapped shell and a binding 404 after a flush both have the kit's shell, that a host `shell` share still wins, and that a membership removed between two requests isn't answered from the first's memo.
- **Kit form controls put your attributes on the control itself, not a wrapper.** `aria-describedby`, `aria-invalid`, `required`, `aria-label` and `data-*` passed to InputCheckbox landed on its wrapper `<div>`; on Select they went nowhere (Reka's SelectRoot renders no element); FileInput dropped them; RadioCardGroup and InputNumber replaced or ignored your `aria-describedby`; InputRichText put them on its frame; and InputSelect dropped a passed `required`. Each now forwards them to its focusable control (the checkbox button, the select trigger, the file input, the radiogroup, the editable textbox, the combobox input), joins your `aria-describedby` with its own ids, de-duplicated, and sets `aria-required` where a native `required` doesn't apply. `class` and a style's custom properties stay on the wrapper. InputCheckbox, FileInput, RadioCardGroup and InputRichText take `invalid` (`aria-invalid`), and InputCheckbox takes `description`, shown under the label as `<id>-description` and joined to its `aria-describedby`.
- **A toast no longer covers a filling Workbench's actions bar.** The bar is a StickyBar at the viewport's foot by layout, not by scroll, so it never counted as pinned and never registered with useStickyBars: a bottom toast sat on top of its buttons (Save) and took their clicks. `StickyBar` takes `pinned` for a bar the layout holds at the foot, and the Workbench passes it while it fills. `useStickyBars().track()` also re-measures when its `enabled` state changes, not only on resize, so a bar that becomes pinned by scrolling registers at once. FloatingToolbar (and BulkBar on it) already registered while floating.
- **Mail text is escaped exactly once.** `FluentMailMessage` HTML-encoded user text and the markdown render encoded it again, so an apostrophe showed as a literal `&#039;` ("Ada&#039;s talk") in the HTML part, and the text part showed the entities and markdown backslashes (`\*x\*`). `escape()` now only backslash-escapes markdown punctuation and leaves HTML encoding to the render, and each block carries its text as given for the text part. `<script>` and `<b>` still show as inert text in every builder (headline, paragraph, facts, table, panel, callout, note, bullets, quote, section, button labels) in both layouts. **A host that passed `FluentMailMessage::escape()` output to `markdown()`** gets the same HTML; its text part shows that block's backslashes, as before.

### Changed

- **`layout.page` reads hand-rolled pages in apps on the kit shell again.** The `ui.kit-shell` rule is back beside the PageHeader file-name signal: the rule reads an app that adopted the kit shell, the file name a legacy app. Where the rule can't run (no Pest beside standards), plain `standards adoption` reports it as `skipped` and the other signals decide the row; `--ci` and `--compliance` still fail closed. Kit's fixture check now asserts each case's expected skips, and fails on one it doesn't list.
- **PaneTabs' pressed pane is neutral.** The Workbench's phone tabs (`Kit/Layout/PaneTabs.vue`) took the primary button for the pressed pane, so it read as a second primary action. They now take SegmentedToggle's look: a bordered raised segment in a muted track. They stay a single-choice toggle group (`role="group"`, `aria-pressed`).
- **The adoption inventory is calibrated against legacy apps.** A `*Panel`, `*History` or `*Chip` file, a drag-and-drop library on its own, and a `<table>` with no header row no longer count as hand-rolled; cards count only outside public pages. `layout.page` reads hand-rolled from an app's own page header without the kit shell, `shell.workspace` from a starter-kit `AppLayout`, and page actions in an app layout's `#header` slot count. `overlay.modal` replaces `@inertiaui/modal-vue`. Each change has an adoption fixture, and the fixtures run on tey/standards 0.11.47.
- **The adoption inventory no longer reads the app's shadcn primitives or content renderers as hand-rolled.** Every `resources/js/**` file-name signal skips `resources/js/components/ui/**`, the primitives the kit builds on, so `ui/dropdown-menu/*` no longer marks `action.menu` hand-rolled. The `*Card.vue` signal for `layout.blocks` and `has-content-panels` also skips `body/`, `content/`, `render/` and `renderers/` folders, where a card draws content rather than framing a page. New fixtures `cards-content-renderer` and `ui-primitives-only` cover both. A `ui/button/Button.vue` still reads hand-rolled through standards' generic-name scan of `replaces.components`, which has no path filter yet.

### Fixed

- **The adoption schema accepts what tey/standards 0.11.50 accepts.** `resources/adoption/surfaces.schema.json` gains the `class-reference` signal kind (`classes`, optional `paths`, `min` and `scope`), file-name `except` and template-tag `in` (`vue`, `blade` or both; default `vue`), so packages that copy the schema can use them. A new CI check, `composer test:adoption-schema`, compares the schema with the installed standards' signal kinds, fields, required fields, groups and analyses, and fails on any drift. The kit's tey/standards dev dependency is now ^0.11.50.
From teylabs.com's adoption of the Workspace Layout:
- **A host without workspaces gets its navigation.** PageShell sent an empty navigation and account menu on a core and shell host. It now lists the pages registered with `Registry::navigation()` at root-relative URLs, current by the same `active` rules, each shown while the viewer passes its ability as a Laravel Gate ability. An undefined or denied ability hides the link (fail closed). `user-menu` links, such as a Settings item, join the account menu.
- **The Layout no longer crashes on a focus shell, and FocusShell ships.** For a `kind: 'focus'` shell (welcome steps, no workspace yet), the Workspace Layout renders the new FocusShell: the product, an optional "Step n of m", and the account cluster with Switch account and Sign out. It's also exported from `@kit/shell`, and takes `:toaster`.
- **The home crumb is recognised by path.** gretel's breadcrumb URLs are absolute (`https://host/@slug`), so the root crumb never matched and showed as text. It now shows as the house icon.
- **The `extra` slot says where it's rendered.** It's rendered in both the account dropdown and the phone drawer, now with a `place` slot prop (`menu` or `drawer`), so an app can render a DropdownMenuItem in one and a plain link in the other. A new `drawer-extra` slot replaces it in the drawer.
- **The current page stands out in PrimaryNav.** It now gets a tinted pill and a 3px marker beside full-strength text, not only an underline in the brand marker's colour.
- **Destructive menu items and error text meet WCAG AA.** They used `--destructive` as text, 3.76:1 on the white popover in light mode and 4.00:1 on a focused item in dark mode (AA is 4.5:1). The new `--destructive-text` token is destructive text on a surface; `--destructive-foreground` keeps its meaning, text on a destructive background, and solid destructive backgrounds are unchanged. Its defaults are `hsl(0 72% 42%)` light (6.48:1 on background, card and popover, 5.94:1 on accent and muted) and `hsl(0 91% 71%)` dark (7.20:1 on background, card and popover, 5.51:1 on accent, 5.29:1 on muted). `tests/js/destructive-text.test.mjs` computes those ratios from `kit.css` and refuses a raw `text-destructive` in any stub, and the browser job's open Workbench row menu now runs axe's `color-contrast` with the destructive Delete item showing ([browser/README.md](browser/README.md) says why contrast runs use a non-modal surface).
- **`FormStatus` mounted before a visit now shows the message the visit brings back.** It kept a `hidden` flag set by the router's `start` and by a watcher on `form.processing`, and cleared only by `finish`, so a form whose `processing` changed without a router visit left it hidden for good. Visibility is now derived from state: it shows when there is a message and no visit is in flight (the router between `start` and `finish`, or `form.processing`), so after each visit it shows exactly when the new page carries a message. The browser job mounts it before the visit and drives visits through the Inertia stub in v3's order (`start`, the page swap, `flash`, `success`, `finish`).
- **The Workbench's phone navigator is a flex column**, as on wider screens. It was only `min-h-0`, so a host's `order-first` child sat under the actions bar on phones.

## v0.6.12

### Changed

- **The adoption inventory's expected outcomes run in kit CI.** `composer test:adoption-fixtures` runs tey/standards' `standards adoption --json` (now a dev dependency, ^0.11.46) on each fixture app under `tests/fixtures/adoption` with this checkout's `surfaces.json`, and fails when a row or fact differs from `expected.json`.

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:703d8cdc…a15c` → `sha256:f3932382…7dbe`): `Kit/Workbench/Workbench.vue`, `Kit/Workbench/WorkbenchNavigator.vue`, `Kit/Workbench/useWorkbench.ts` and `Kit/Workbench/workbench.ts` take the empty state below.

### Added

- **A Workbench empty state** ([docs/workbench.md › Empty workspace](docs/workbench.md#empty-workspace)). While a workspace has no items (`useWorkbench(key).isEmpty`), the stage renders the host's new `#empty` slot in place of `#stage`, with `{ actions, act, label }`, so a host can offer "Create draft · Import · + New slide". It's opt-in: without an `#empty` slot the stage renders `#stage` as before and the kit adds no default, so a stage-only Workbench with no items (such as a presenter console) keeps its stage. The navigator shows a plain, focusable "Nothing here yet." message in place of an empty listbox, which fixes axe's `aria-required-children` and `scrollable-region-focusable` with zero items (or a filter that matches nothing); F6 and `focusRegion('navigator')` land on it.

## v0.6.11

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:debbdb0f…e62d` → `sha256:e95f0424…5afc`): `Kit/Overlay/ModalPanel.vue`, `Kit/Overlay/Dialog.vue`, `Kit/Overlay/Popover.vue`, `Kit/Form/Select.vue`, `Kit/Form/InputSelect.vue`, `Kit/Feedback/Toaster.vue` and the kit's menus (`Kit/Menu/MenuPanel.vue`, `Kit/Action/ActionBar.vue`, `Kit/Action/HeaderActions.vue`, `Kit/Table/RowActions.vue`, `Kit/Table/DataTable.vue`, `Kit/Form/Repeater.vue`, `Kit/Layout/FloatingToolbar.vue`) take the fix below; `Kit/Overlay/layer.ts` and `Kit/Menu/MenuContent.vue` are new.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:04c61611…7379` → `sha256:554cfe94…56eb`): `Workspace/Layout.vue` takes the props below. Every default is unchanged.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:5e32938b…3328` → `sha256:debbdb0f…e62d`): `css/kit.css` defines the stacking scale below.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:554cfe94…56eb` → `sha256:56acbf30…3bb0`): `Workspace/Partials/WorkspaceSwitcher.vue` takes the change below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e95f0424…5afc` → `sha256:703d8cdc…a15c`) and `session-ui` (`sha256:02200ec2…49b4` → `sha256:be81f458…6f4f`): `Kit/Form/FormStatus.vue` is new, and the ForgotPassword, VerifyEmail and Login pages use it. Hosts with their own `@kit/ui` module must export `FormStatus`.

### Added

- **The Workspace Layout can be used whole by a host with its own root shell** ([README › The shell](README.md#the-shell)). Three new props, each defaulting to today's behaviour:
  - **`:toaster="false"`** leaves the toasts to the host's single Toaster, as `GuestShell` and `PublicShell` allow, so toasts don't double and sticky ones survive a layout swap.
  - **`:document-title="false"`** leaves the `<title>` to the pages' own `<Head>`.
  - **`fill`** is for editor pages such as a Workbench. The shell takes the viewport's height with no page scroll, and the content area fills what the header leaves: unpadded, `min-h-0` and overflow hidden, and still capped by `widthClass` (the host's measure). The page scrolls its own panes.
- **Adoption inventory.** `resources/adoption/surfaces.json` lists what the kit offers an app (UI and backend surfaces, with the app facts that decide when each applies, the evidence of adoption, the signals of a hand-rolled equivalent and the libraries it replaces), in the format `resources/adoption/surfaces.schema.json` defines, for `standards:adoption`. Each fact says how standards computes it (one of its route and model analyses, or signals in app code), and a surface such as Dialog or Avatar applies only to an app that shows one. A test fails when a barrel export or feature has no entry.
- **`<FormStatus>`, a form's persistent confirmation** ([docs/form-status.md](docs/form-status.md)). It composes `Alert` with `live="status"` (polite) for a status that must stay visible after a submit, such as a reset link sent or a waitlist sign-up; it isn't a toast and never times out. It shows the flashed `status` page prop unless `message` is given, maps `tone` (`success` by default, `info`, `warning`) to Alert's variants, and hides on the next Inertia visit's start or while `form.processing`, showing again only if the next response still carries a status. session-ui's ForgotPassword, VerifyEmail and Login pages use it in place of their own markup.

### Changed

- **Passkeys are optional per app in the adoption inventory.** `auth.passkeys` applies only when the app opts in (the new `uses-passkeys` fact: its user model implements Fortify's `PasskeyUser`, or a page uses `PasskeyOffer`), so an app without passkeys reads n/a and never fails the zero-exemption gate for them.

### Fixed

- **Overlays stack above a host's sticky bars.** `Dialog` uses `z-(--z-dialog)` and the workspace header `z-(--z-header)`, but neither variable was defined, so their z-index was `auto` and a dialog could sit under a host's sticky bar. `kit.css` now defines the scale on `:where(:root)`, lowest first: `--z-sticky` 20, `--z-header` 30, `--z-dropdown` 40, `--z-dialog` 50, `--z-toast` 60. A host `:root` overrides any of them. `tests/js/z-scale.test.mjs` fails on any `--z-*` a stub uses that `kit.css` doesn't define.
- **Overlays inside a Modal open over it and take clicks.** The package opens a Modal as a native `<dialog>` in the browser's top layer, and the kit teleported the unsaved-edits confirmation, selects, popovers and menus to `<body>`, under it: "Discard your changes?" painted behind the Modal and could not be clicked. Inside a Modal they now render into its `<dialog>`; outside one, into `<body>` as before. Escape in one of them closes only that overlay, and the Modal stays open (the browser's own close of the `<dialog>` is held back). A toast raised while a Modal is open shows over it. A real-browser check (Playwright, `browser/`) covers the confirmation, a select and a menu inside a native `<dialog>`, including the Escape order.
- **The workspace switcher no longer repeats "Workspaces".** With no current workspace (the chooser, for someone with two or more), its label was "Workspaces", the same as the nav link beside it. It's now "Choose a workspace", and a host can word it with the switcher's new optional `placeholder` prop (through the Layout's `workspace-switcher` slot).

## v0.6.10

### Upgrading

- Hosts that own `config/multitenancy.php` must list `ConciergeContextTask` and `FlushFeatureCacheTask` in `switch_tenant_tasks`; `kit:status` now fails without them.
- `kit:status` now exits 1 when a host requirement fails; CI scripts that run it will fail on a misconfigured host.
- Run `kit:update plan|apply` for `core` (`sha256:35010308…2c02` → `sha256:5e32938b…3328`), `shell` (`sha256:93849b84…4a0d` → `sha256:04c61611…7379`): kit.css, the Page heading, GuestShell and the public Markdown page take `font-display`.

### Added

- **The theming policy, documented and advised** ([README › Theming](README.md#theming)).
  - **Surfaces.** An app may remap the surface tokens (`background`, `card`, `popover`, `muted`, `sidebar`, and `--shell-ground`) in `:root` and `.dark`, each with its foreground.
  - **Kit tokens.** The kit owns its `--kit-*` tokens, which an app sets only on the named shell surfaces.
  - **Advisory.** `kit:status` and every plan add "Theming (advisory)" lines for a surface without its foreground, a pair below 4.5:1 (hex, `rgb()` or `oklch()`), or a `--kit-*` override elsewhere. They are attention only and never block or fail.
- **`--font-display`, the kit's heading face.** The Page heading and the GuestShell and public Markdown page titles were hard-coded to `font-serif`; they now use `font-display`. Through `@theme default` in kit.css, it's the serif stack unless the host sets `--font-display` (a sans app: `--font-display: var(--font-sans)`), so nothing changes by default. It's listed in the README's Theming section.

### Fixed

- **A host-owned `config/multitenancy.php` without the workspaces switch tasks is caught.** The kit's `switch_tenant_tasks` default applies only when the app has no `config/multitenancy.php` of its own, and without `ConciergeContextTask` a tenant switch never enters the Concierge context. Every workspaces plan (`kit:add`, `kit:adopt`, `kit:update plan`) now carries a blocking `multitenancy.switch_tenant_tasks` host step naming each missing task and the exact `\Tey\Kit\Features\Workspaces\Tasks\…::class,` lines to add, and their applies refuse until both are listed, in any order beside the app's own tasks. `kit:status` fails too (exit 1, `FAILED:` and `requirements.failures` in JSON). A value that is unset, unreadable or not an array counts as missing. Apps that keep the kit's multitenancy defaults are unaffected.
- `bin/catalog-history.php` no longer records a newer line's releases on a maintenance branch, even when their tags are fetched: it lists the tags that are ancestors of `HEAD` and any older than the newest of them. Main still lists a maintenance line's patches, such as v0.5.56, so `kit:adopt` identifies an app installed from one. `composer test:catalog-history` checks both on a throwaway repository.

## v0.6.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c2c5d46e…325f` → `sha256:35010308…2c02`): `Kit/Layout/Page.vue`, `Kit/Layout/FloatingToolbar.vue`, `Kit/Workbench/WorkbenchActions.vue`, `Kit/Action/actionPlacement.ts` and `Kit/Action/ActionBar.vue` take the page header actions, and `Kit/Action/headerSplit.ts`, `Kit/Action/actionRunner.ts`, `Kit/Action/HeaderActions.vue` and `Kit/Action/HeaderStatus.vue` are new. Copy `types/kit.d.ts` again if you keep a copy (`Action` gains `priority`).
- Pages with more than 3 header actions passed through `actions` now fold the extra ones into More; set `priority` to keep one visible. The same goes for a Workbench with more than 3 `actions`. Three or fewer with no `priority` render as before.

### Added

- **Page header actions.** `<Page :actions>` splits server action descriptors into one filled primary, up to two outline secondaries and a "More" menu, with one rule (`headerSplit`) shared by the FloatingToolbar's new `actions` prop and the Workbench's actions row. `Action` gains a trailing `priority` argument (`primary`, `secondary`, `overflow` or null): a declared priority wins; otherwise, past three actions, the first non-destructive one is primary and the next two are secondaries, and a destructive one is never promoted. Below `sm` only the primary stays and the rest move into More. The `#actions` slot stays as an escape hatch with no rule applied. See the README's "Page header actions".
- **Page header status.** A `#status` slot and a `status` prop (`{ state, label }`) on `<Page>` and FloatingToolbar, before the actions, in a muted polite live region (`role="status"`).

## v0.6.8

### Upgrading

- session-ui no longer needs an Inertia middleware of its own. A host that shared `kitPassword`, `passkeyOffer` or `environment` from its own `HandleInertiaRequests` to work around the fix below can drop them. A host that listed `Tey\Kit\Features\SessionUi\Http\Middleware\HandleInertiaRequests` in `web` can keep it (it's now a plain Inertia middleware) or remove it when it has its own. Gretel's `breadcrumbs` prop is no longer cleared, so it now reaches pages, which don't read it.

### Fixed

- **session-ui's Inertia props reach the page, and the host's own shares survive them.** session-ui pushed its `HandleInertiaRequests` onto the router's `web` group, and the next `appendMiddlewareToGroup` rebuilt the router's groups from the kernel's and dropped it, so a host never got `kitPassword`, `passkeyOffer` or `environment`, nor the registry's request hooks (the local `?brand=` switch, and forgetting the last request's memo). Had it run after the host's own middleware, its `flushShared()` would have dropped the host's shares. session-ui now shares those props from its provider as closures, evaluated when a page renders, so they sit beside the host's `HandleInertiaRequests` in any order and nothing outlives its request under Octane. Nothing in the kit flushes shared props, and `shell` keeps its one producer. The request hooks run in a new `PrepareKitRequest`, added to `web` through the HTTP kernel so a re-sync can't drop it. The kit's `HandleInertiaRequests` is now a plain Inertia middleware, added only when the host's `web` group has no Inertia middleware. `composer test:inertia-shares` covers the props, a host share, order, the re-sync, a host with no Inertia middleware, no `flushShared` in src, and sequential requests as two people. `composer test:request-hooks` sends two requests through the real HTTP kernel after a host's late `appendMiddlewareToGroup` and checks the hooks ran on each and the request, permission, capability and entitlement memos started afresh. Both run in CI.

## v0.6.7

### Upgrading

- A host that relied on session-ui branding mail with placeholders still unresolved: fill in `kit.brand.*`, or set `kit.brand_mail` to `true` to brand regardless (and keep the `/up` placeholder check).

### Fixed

- **`kit.brand_mail` no longer turns on silently with session-ui, and a default never takes `/up` red.** Unset (`null`), session-ui now brands mail only once the brand is filled in (no `brand.*` placeholders left), and `/up` fails on unresolved placeholders only when the host set `brand_mail` to `true`. Before, adopting session-ui turned branded mail and the production placeholder health check on by default, so a host with its own branding got a red `/up`.

## v0.6.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b1715a1c…7496` → `sha256:c2c5d46e…325f`): `css/kit.css`, `Kit/Overlay/Dialog.vue`, the `Kit/Menu` dropdowns and `Kit/Action/ButtonDropdown.vue` take the theming below. In light mode, the Modal and drawer backdrop goes from 75% to 50% black.

### Fixed

- **Overlays take the host's theme and dark mode.** Every scrim is now one kit token, `--kit-scrim` (50% black, 70% in dark mode, mapped to `bg-scrim`). That covers the kit's Dialog, the modal package's backdrop behind Modal sheets and drawers (the package hard-codes black, so kit.css restyles it; a stacked modal's transparent backdrop is kept), and the host's shadcn Sheet overlay (`[data-slot='sheet-overlay']`). The panels, headers and footers were already on the semantic tokens (`bg-background`, `text-foreground`, `border-border`), and a test now keeps them there.
- **Menus follow the theme too, so a dropdown opened inside a themed or dark sheet matches it.** The Dropdown family (`Dropdown`, `DropdownMenu`, `DropdownItem`, `DropdownLink`, `DropdownModalLink`, `MenuPanel`) and `ButtonDropdown` hard-coded white and grey in light mode, with `dark:` patches. They're now on `bg-popover`, `text-popover-foreground`, `text-muted-foreground`, `divide-border` and `ring-border`, with `bg-accent` for the highlighted item.

## v0.6.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e45e114a…6eba` → `sha256:b1715a1c…7496`): `Kit/Form/Button.vue` takes the fix below.

### Fixed

- **The destructive Button's hover now passes WCAG AA.** The host button's `hover:bg-destructive/90` is translucent, so on a light page white text fell to about 4.3:1. Kit's Button now sets a solid hover, `--color-destructive-hover` or red-700 (6.5:1), in light and dark, as the `success` variant does.

## v0.6.4

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e7f64947…bac5` → `sha256:e45e114a…6eba`): `Kit/Overlay/Dialog.vue`, `Kit/Feedback/Alert.vue` and `Kit/contract.ts` take the changes below. An app that styled the alert's corner dismiss button with its own classes should check it.

### Added

- **Dialog `icon` prop** (boolean, default `true`). `:icon="false"` hides the icon circle on any variant, and the title, description, typed confirmation, `body` fields and buttons sit flush, with no gutter. `variant="danger"` keeps its destructive action. By default the Dialog renders as before. `DialogProps` in `contract.ts` declares it.

### Fixed

- **An Alert's icon and dismiss × line up with its first line of text.** Each sits in a box one line tall, centred in it, on an `items-start` row, so both stay level with the first line in every variant and tier, for one line or many, with a title, with actions, and in a slim banner with its own padding. They were 2px (icon) and 4px (×) off. The × is in the row now, not pinned to the corner, and keeps its 44px target. The title, body and actions share the text column.
- **An Alert holds its own height in a stretching row.** The root is `self-start`, so an alert in a host's grid or flex row beside a taller one no longer grows and leaves empty space below its text.

## v0.6.3

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:a6a3ea4f…9739` → `sha256:93849b84…4a0d`): `GuestShell.vue` takes the change below.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:e18eab84…3f90` → `sha256:e7f64947…bac5`): `Kit/Form/Button.vue` takes the `success` variant below.

### Added

- **Button `variant="success"`**, a positive or go action (Start, Publish): filled green on the host's default button. A host themes it with `--color-success`, `--color-success-foreground` and `--color-success-hover`; the fallbacks are green-700 on white (5.0:1, AA) and green-800 on hover (7.1:1), in light and dark.
- **`GuestShell` takes `:toaster="false"`**, as `PublicShell` does. A host whose own app shell already mounts the persistent Toaster keeps one, so toasts aren't shown twice. Defaults to `true`, so nothing changes without it.

## v0.6.2

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:5caaf5d8…ca94` → `sha256:a6a3ea4f…9739`).

### Added

- **Shell Layout opt-ins** ([README](README.md#the-shell)). Every default is unchanged.
  - `wordmarkLabel` names the wordmark link in place of the product's name.
  - `guestHeader` draws the header for a guest, with no user menu.
  - The page ground is the `--shell-ground` token, which falls back to the theme's background.

### Fixed

- **No empty drawer.** For a signed-in user with no pages to list (no `nav` or `drawer-nav` slot and no navigation), the Layout draws no drawer or Menu button. The account menu stays in the bar at every width, so Sign out is still reachable on a phone.
- **No false @inertiajs/core warning when core is pinned to exactly vue3's version.** Before, a checkout without `node_modules` (a production `kit:status`) warned if `@inertiajs/vue3` was declared as a range. Now the check reads vue3's pin from `package-lock.json`, or from the base of vue3's declared range. It warns only when core is a range or a different version.

## v0.6.1

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:d9875358…0a11` → `sha256:e18eab84…3f90`): `Kit/Form/richText.ts` takes the fix below.

### Fixed

- **InputRichText no longer locks a document with a unitless font size like 2.03.** `isSafeFontSize` checked three decimals with `Math.round(value * 1000) === value * 1000`, which floating point breaks (2.03 × 1000 is 2029.9999999999998), so a valid mirrored-deck size refused the whole field as an unsafe text style. It now checks `Number(value.toFixed(3)) === value`. A test sweeps 28 real deck sizes.

## v0.6.0

### Upgrading

- **0.6.0: session-ui needs only core and shell.** It keeps the sign-in: `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/TwoFactor`, `Auth/VerifyEmail` and `Auth/ConfirmPassword`, the `Welcome/Timezone` and `Welcome/Photo` steps, and its security-events and user-state migrations. It no longer requires workspaces or access, and loads no workspace, access or Concierge class. Re-pinned: `session-ui` `sha256:bfeb3751…b94c` → `sha256:02200ec2…49b4`, `workspace-ui` `sha256:ff1f114d…1c06` → `sha256:93e9b61a…5bed`.
- **Moved to workspace-ui:** the invitation page `Auth/Invitation/Show` (with its routes `invitations.show` and the acceptance and account posts, the branded invitation email and Concierge's invitation URL), the owner's `Welcome/WorkspaceTimezone` step, and the first-run demo's sign-in route `demo.login`. Each file moves byte for byte. Route names and `kit.routes.session-ui` options stay as they were.
- **An app with session-ui and workspace-ui both installed re-pins once, and nothing is deleted.** The two files change owner, not disk:
  1. `php artisan kit:update plan --lineage=session-ui`, then its `apply`. The plan shows the move: both files are `release`, and none is written or deleted.
  2. `php artisan kit:update plan --lineage=workspace-ui`, then its `apply`. workspace-ui claims both files, without a write, when they are exactly the bytes session-ui released. A file you have edited, even by one byte, is not claimed: the plan shows it as a conflict and isn't executable until you review it.
- **An app with session-ui alone (no workspace-ui) updates in one run, and nothing is deleted or rewritten.** `php artisan kit:update plan --lineage=session-ui` reports each moved file as "no longer owned by session-ui; kept as the host's" (state `release`, listed under `kept`), then its `apply`. Afterwards both files are byte-identical to before and session-ui's installed record no longer lists them: they're yours to keep or remove. No `--lineage=workspace-ui` step follows. workspace-ui is needed only if the app uses invitations: then add it with `kit:add workspace-ui`. Only the 0.5 split's workspace pages carry workspace-ui on for an older install; these two don't.
- **Workspace and account deletion come with workspace-ui.** Their routes (and `kit.lifecycle.workspace_delete` / `kit.lifecycle.account_delete`, off by default, with their purge handlers) are workspace-ui's; account deletion needs Concierge to refuse while you own a workspace. An app with session-ui alone has no deletion route.
- **The shell feature shares the `shell` Inertia prop itself.** A core and shell host gets it without session-ui, so the PublicShell's footer and CTA have their data. It's built by the bound `Tey\Kit\Features\Shell\ShellSource`: `PlainShell` by default, session-ui's `PageShell` when that is adopted. session-ui's middleware keeps the shell's share across its flush, so the prop has one producer and is never shared twice. A host that shared its own `shell` prop for a core and shell site can drop it, or bind its own `ShellSource`.

### Added

- `composer test:session-ui-core`: a core and shell host with no workspaces, access, Concierge, Pennant or multitenancy runs `kit:add session-ui`, boots from its lineages, renders `Auth/Login`, signs a user in with an authenticated session, and loads no workspace, access or Concierge class.
- `Tey\Kit\Features\Shell\ShellSource` and `PlainShell`, and `Tey\Kit\Features\Core\PersonalPages` (a personal page's breadcrumb, which `WorkspaceRoutes::personalPage()` now calls).

### Changed

- `docs/public-site.md` adopts core, shell and session-ui only: no workspaces or access.
- `kit:update --lineage=<receiver>` claims, with no write, a file another installed feature's `moved` entry released to it while both were installed, only when the bytes on disk are exactly the target's. Before, the receiver's plan listed such a file as `claimable` and stopped.
- A `moved` file whose receiver the app neither has nor carries is released and kept as the host's (never deleted), and the update waits on no receiver. `kit:update plan --lineage=<id>` lists such files under `kept`, with `kept_note`.
- Without workspaces, `PageShell` builds the app shell with no workspace, switcher or navigation, and `PageTitle` adds no workspace name.

## v0.5.52

### Upgrading

- **Workspace and account deletion are now off by default.** If your app has relied on either since 0.5.40, it must now do two things: set `kit.lifecycle.workspace_delete` / `kit.lifecycle.account_delete` (`KIT_WORKSPACE_DELETE=true` / `KIT_ACCOUNT_DELETE=true`), AND bind its purge handler, `Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData` / `Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData`. A handler that does nothing must be bound explicitly. Without the flag, the pages are 404 and hidden. With the flag but no handler, the deletion refuses and deletes nothing ([docs/lifecycle-events.md](docs/lifecycle-events.md)).

### Fixed

- **Deletion no longer runs without the app's own data purged first.** The bound purge handler runs inside the deletion's transaction, before the kit's own rows go. If it throws, everything rolls back. While deletion is off, its routes return 404 and the settings pages hide it. On without a handler, it refuses with a configuration error. `kit:status` and the workspace-ui / session-ui plans report both flags and their contracts: off, on, or on without a handler. Tested with foreign keys enforced, on SQLite and PostgreSQL.

## v0.5.51

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:265de09b…fa4b` → `sha256:5caaf5d8…ca94`): the kit-types host step is also met by `vendor/tey/kit/types/kit.d.ts` in plugin-vue's `script.globalTypeFiles` (your Vite config), not only a `tsconfig.json` include.

### Fixed

- **Plans recommend `kit.mcp.consent=host` for more hosts that run their own consent.** The check now also reads the app's Composer PSR-4 roots (`src/`, say) beside `app/`, `bootstrap/`, `routes/` and `config/`. It recognises `Passport::authorizationView(…)` and a bind or subclass of Passport's `AuthorizationController` or `ApproveAuthorizationController`, as well as `AuthorizationViewResponse` and approve middleware. `kit:update plan --lineage=mcp` carries the same non-blocking step as `kit:add` and `kit:adopt` (tested).
- **`kit:permissions:sync` says when the app has no role a default names.** It printed "Default role not configured, skipped: admin", which read like a misconfiguration. It now says "No 'admin' role in this app (concierge.roles does not name it), so nothing to grant it: …" and lists the grants that role would have had. Owners still get theirs. The report gains `skipped_grants` (role → permissions).
- **A `mention` host step may name an `or_file`**, a second place the same text may be. The shell's kit-types step uses it to accept the Vite config.
- **Documented: in `consent=host` mode, the mcp lineage's `Mcp/Authorize/Page.vue` stays generated and inert** ([docs/mcp-consent.md](docs/mcp-consent.md)). The kit sets no authorization view, so nothing renders it. It isn't conditional on config, because that would change the plan's identity with the config value.

## v0.5.50

### Added

- **`MoveToWorkspace` honours a model's own, row-aware move ability** ([docs/models.md](docs/models.md)). A model may define `workspaceMoveAbility(): string`, a Gate ability or policy method checked in each workspace with the row, `Gate::allows($ability, [$row, $workspace])`. That lets an app's policy allow an owner to self-move their own row without `members.manage`. The actor must still be an active member of both workspaces, and an ability nobody defines is refused (`ability_unknown`). Models without the method keep `kit.workspaces.move_ability`.

## v0.5.49

### Added

- **`MoveToWorkspace`** (`Tey\Kit\Features\Access\Operations`, [docs/models.md](docs/models.md)) moves one `BelongsToWorkspace` row from the current workspace to another and keeps its id, so rows that reference it stay attached. Use it, for example, to re-bind an app to a new workspace instead of deleting and re-creating it. It's refused for:
  - another workspace's row, or a row changed concurrently (`workspace_mismatch`);
  - the same or a missing target (`workspace_same`, `workspace_not_found`);
  - an actor without the move ability in both workspaces (a 403). The ability is `kit.workspaces.move_ability`, by default `members.manage`.

  It rewrites the column through one guarded query (`where` the source, so a concurrent move changes nothing), with no bypass of the updating guard, and audits `workspace.row.moved`, in one transaction. CI runs this check on PostgreSQL too.

## v0.5.48

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:3286f349…6f3e` → `sha256:d9875358…0a11`): `kit.css` gains `.kit-theme-light` (below).
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:cb8d242c…3409` → `sha256:265de09b…fa4b`): `Partials/PublicShell.vue` takes the opt-in branded-site props below. Its defaults are unchanged.

### Added

- **PublicShell can host a branded site** ([docs/public-shell.md](docs/public-shell.md#a-branded-site)). Every option is opt-in:
  - `width="site"` (`max-w-6xl`), with `align-bars` putting the header and footer rows on the same measure;
  - `tone="bar"`, a header in the shell's `--shell-bar` tokens rather than `--primary`;
  - `:pin-footer="false"` for a footer that flows after the content, and `:padded="false"` for an unpadded main;
  - `footer-links` and `footer-note`, with a `footer-mark` slot, for a footer without session-ui's shared shell;
  - a `mark` slot that links to `home-url` (named by `home-label`) when there's no product;
  - `theme="light"`, which keeps a page with no dark styling light under `.dark` by restating the neutral surface tokens, not the brand's primary (kit.css's `.kit-theme-light`).

## v0.5.47

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:b45f675f…5b7b` → `sha256:3286f349…6f3e`): `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts` take `families` (below).

### Added

- **InputRichText `families`** ([docs/input-rich-text.md](docs/input-rich-text.md)): with `allow` naming `textStyle`, `:families="['sans', 'display']"` limits a style's font family to the app's own names. Every name must be in the list, or the document gives `content-error`. Without it, the character-class rule stands, unchanged. `isSafeFontFamily(value, allowed?)` takes the same list.

## v0.5.46

### Added

- **`BelongsToWorkspace::SCOPE`** (`'workspace'`), the global scope's name, pinned by a test: `withoutGlobalScope(BelongsToWorkspace::SCOPE)` is the stable opt-out for code that must cross workspaces.

### Fixed

- **A route that binds a workspace model outside any workspace is a 404, not a 500.** `BelongsToWorkspace` now answers `resolveRouteBinding` and `resolveChildRouteBinding` with nothing when there's no current workspace, instead of throwing `workspace_required`. Inside a workspace, another workspace's row is still a 404 through the scope, and every other query outside a workspace still throws, so it stays fail-closed.

## v0.5.45

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:371e614a…b004` → `sha256:b45f675f…5b7b`): `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts` take `allow` (below). Core now also lists `@tiptap/extension-text-align` and `@tiptap/extension-text-style` (`^3.32.0`) under `requires.npm`, so add them to `package.json`.

### Added

- **InputRichText `allow`, for mirrored content** ([docs/input-rich-text.md](docs/input-rich-text.md)): `:allow="['textAlign', 'textStyle']"` preserves and round-trips a paragraph's or heading's alignment (`left`, `center`, `right`, `justify`) and the `textStyle` mark (`color`, `fontFamily`, `fontSize`). Each value is checked strictly (`isSafeColor`, `isSafeFontSize`, `isSafeFontFamily`) and never passed through as CSS. A font size may also be a bare number, a scale the app resolves (kept a number), and a family name may be quoted. Anything else is still refused with `content-error`. The default is none, so the strict subset is unchanged, and there are no new toolbar controls.

## v0.5.44

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:0e3e40c2…b16c` → `sha256:cb8d242c…3409`): new host steps for the `@kit/ui` alias (`vite.config.ts`, `tsconfig.json`) and for the kit's types. The types step asks for `"vendor/tey/kit/types/kit.d.ts"` in `tsconfig.json`'s `include` and replaces the step that asked your transformer to read `vendor/tey/kit/src`. If your transformer already writes the `Tey.Kit` namespaces, leave the include out: both would declare each type twice.
- Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:715b4511…8b49` → `sha256:ff1f114d…1c06`): the dashboard page is typed `Tey.Kit.Features.SessionUi.ViewModels.WorkspaceDashboardViewModel`. **A page of your own typed with the kit's `DashboardViewModel` TypeScript name moves to `WorkspaceDashboardViewModel`.** The PHP class is unchanged.
- **Defining kit page props from the shipped types (`defineProps<Tey.Kit.…>()`):** list `vendor/tey/kit/types/kit.d.ts` in `@vitejs/plugin-vue`'s `script.globalTypeFiles` as well as in `tsconfig.json`, so Vue's compiler resolves the props.
- **A host that points `@kit/ui` at its own module** must export every name the kit's pages import from it. The README now lists them all, including `InputSelect`, which mcp's Authorize page uses.

### Fixed

- **The kit's `Tey.Kit.*` types have one supported source: `vendor/tey/kit/types/kit.d.ts`** ([README](README.md)). A host whose transformer writes one flat module couldn't produce the global namespaces the kit's pages use. Transforming all of the kit's `src` also collided on `DashboardViewModel`. Including the shipped file in `tsconfig.json` covers every kit page, and stays in step with the installed kit.
- **The shell's host steps include the `@kit/ui` alias** (in `vite.config.ts` and `tsconfig.json`), which its pages import through.
- **The dashboard view model's TypeScript name is `WorkspaceDashboardViewModel`**, so it no longer collides with an app's own `DashboardViewModel` in one generated module.
- **`types/kit.d.ts` resolves in Vue's SFC compiler.** Its nested namespaces had no `export`. TypeScript accepts that in an ambient declaration, but `defineProps<Tey.Kit.Features…>()` failed to compile ("Unresolvable type reference"). Every nested level is now `export namespace`. `bin/typecheck-components.sh` compiles two pages' `defineProps` against it (`typecheck/sfc-kit-types.mjs`), and a vue-tsc fixture types a page with it.
- **`types/kit.d.ts` includes the kit's enums.** `ShellKind` was missing, so `FocusShellData.kind`, `GuestShellData.kind` and `ShellData.kind` were typed `undefined`. They're now `'app' | 'guest' | 'focus'`.

## v0.5.43

### Upgrading

- **A host with its own OAuth consent sets `kit.mcp.consent` to `host`** (`KIT_MCP_CONSENT=host`). Since v0.5.40 the kit has run Passport's consent itself, so an app whose consent page posts another field (`workspace_id`, say) and keeps its own grants got 403 on every approve. Nothing changes for a host on the default, `kit`. Any value other than `kit` or `host` is refused when the mcp feature boots. See [docs/mcp-consent.md](docs/mcp-consent.md#a-host-with-its-own-consent).

### Fixed

- **A host with its own consent can set kit.mcp.consent=host.** The kit then attaches no `BindConsentWorkspace` to `passport.authorizations.approve`, doesn't bind `KitAuthorizationController` over Passport's `AuthorizationController` or set its Authorize page as the authorization view, and a code exchange binds no kit connection, so Passport's approve behaves as it did before v0.5.40 and no second connection row is written. `kit:adopt mcp plan` and `kit:add` show a `kit.mcp.consent` step (attention, not blocking) for a host that binds `AuthorizationViewResponse` or adds its own approve middleware. `composer test:mcp-host-consent` covers it.

## v0.5.42

### Upgrading

- **Transfer ownership asks for the workspace's name.** `PUT /@{workspace}/settings/ownership` now requires `confirmation` (the workspace's name, typed) alongside `password`, and only the owner gets past it: an admin gets 403 and a non-member 404 before anything else is checked. Settings › People's Make owner now opens the new Transfer ownership modal instead of a password dialog, so `Workspace/Settings/People/Page.vue` changed. A client that sends the transfer itself must add `confirmation`. `ManagePeople::transferOwnership()` takes it as a fourth argument. Re-pin: workspace-ui `sha256:28486124a95a6a6ea04e5b20229460de4a98a268d36786514e56447c9af88da0` to `sha256:715b451103ce91ee3a3bd4e34b98e4f27f00e8e5188ca42c0a8896546bd48b49`. `kit:update --lineage=workspace-ui` brings the changed People page and the three new pages.
- **Settings has a Danger zone group.** Delete workspace (listed to whoever manages members, opened only by the owner) and Delete account (under Account) are registered settings sections. A host that asserts its exact section list sees two more entries.
- **Purge in a plain queued listener.** `WorkspaceDeleted` and `AccountDeleted` are dispatched after the commit, once the workspace or user is gone, so a `TenantAware` listener can't make the workspace current. See [docs/lifecycle-events.md](docs/lifecycle-events.md#a-queued-purge-listener).

### Added

- **Transfer ownership modal** ([docs/lifecycle-events.md](docs/lifecycle-events.md#transfer-ownership)): `Workspace/Settings/People/Transfer/Page`, at `GET /@{workspace}/settings/people/members/{membership}/transfer`, is for the owner only. The workspace's name is typed to confirm and the password asked for again, and both are checked on the server. A scoped guest is never offered and is refused on the server, whenever Concierge has `isScopedGuest()` (0.9). The kit still requires `tey/concierge` ^0.8.4.
- **Delete workspace** ([docs/lifecycle-events.md](docs/lifecycle-events.md#delete-a-workspace)): Settings › Delete workspace (`Workspace/Settings/Delete/Page`, `DELETE /@{workspace}/settings/delete`) is for the owner only, with the workspace's name typed. `DeleteWorkspace` deletes the workspace in one transaction, with its Concierge, spatie, settings, access, MCP and notes rows. `audit_events` rows are never deleted.
- **Delete account** ([docs/lifecycle-events.md](docs/lifecycle-events.md#delete-your-account)): `/me/delete` (`Workspace/Account/Delete/Page`) takes your current password. It's refused while you own any workspace, and the refusal names them. Otherwise you leave every workspace through Concierge's `LeaveWorkspace`, your account and its rows are deleted, and the session is signed out and invalidated.
- **Lifecycle events** ([docs/lifecycle-events.md](docs/lifecycle-events.md#lifecycle-events)): `WorkspaceDeleting` and `AccountDeleting` are dispatched inside the transaction and are informational, with no veto. `WorkspaceDeleted` (`workspaceId`, `slug`, `memberUserIds`, `actorId`) and `AccountDeleted` (`userId`, `workspaceIds`) implement `ShouldDispatchAfterCommit`: they're dispatched only after the commit and never on a rollback. `composer test:lifecycle-pages` covers it.

## v0.5.41

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:58df39ce…6562` → `sha256:0e3e40c2…b16c`): its PublicShell host step now looks for the kit's import path. A layout callback that imports `@/pages/Workspace/Partials/PublicShell.vue` still meets it. One that uses a PublicShell of your own no longer does, and is pointed out as attention.
- **Adopting hosts, before a full rollback:** in your `create_workspaces` migration's `down()`, drop any `audit_events` foreign key on `workspace_id` before dropping the table (the snippet is in the README, under Rolling back). The kit's audit key points at your table, and `migrate:reset` runs your `down()` before the kit's.

### Fixed

- **The shell's PublicShell host step no longer reports "met" for a PublicShell of the host's own.** It matched the bare word, so `import PublicShell from '@/layouts/PublicShell.vue'` met it. It now matches the kit's import path, `pages/Workspace/Partials/PublicShell`, in the `@/` or relative form, with or without `.vue`.
- **`kit:adopt` with no arguments lists what can be adopted** instead of failing on a missing feature and action. It names the features already adopted and, for each other feature, how many of its files are on disk and the plan command to run. `--json` gives `{status: "list", adopted, adoptable}`. A feature without an action asks for one.
- **Rolling back an adopting host is documented, and the plan says it.** The README's Rolling back section gives the `create_workspaces` `down()` that drops the kit's `audit_events` key first. A plan that adopts the host's `workspaces` table lists it as a host step.
- **CI runs the audit-key check on PostgreSQL.** `composer test:audit-workspace-fk` sat in the PostgreSQL job with no database settings, so it ran on SQLite. It now gets the job's PostgreSQL connection, covering 000204 to 000209 and the rollback `down()` on PostgreSQL.

## v0.5.40

### Upgrading

- Run `kit:update plan|apply` for `mcp` (re-pinned `sha256:d1a28359…36f3` → `sha256:7e095959…f01b`): new page `Mcp/Authorize/Page.vue` and migration `0001_01_02_000602_add_oauth_client_to_kit_mcp_connections` (a nullable `connections.oauth_client_id`). Run the migration.
- **The consent screen is now an Inertia page, not the `kit::mcp.authorize` Blade view**, which is removed. An app that overrode it in `resources/views/vendor/kit/mcp` should move its changes to the generated page. Approving now needs a workspace: the page posts `workspace`, and an approve without one is refused (403).
- **Revoking another member's connection** is new and needs `kit.mcp.manage_ability`, by default the new `connections.manage` ability (permission `workspace.connections.manage`, held by owners and admins). Run `php artisan kit:permissions:sync` so existing workspaces' roles get it. Revoking now also revokes the connection's access and refresh tokens, unless another live connection runs on the same token.
- **Skipped consent is narrower.** Passport's `AuthorizationController` is bound to the kit's `KitAuthorizationController`. A live token alone no longer skips the consent page: consent is skipped only when a live connection for the client exists in the current workspace, and that connection is reused. A connection made before this release is never reused, so its first re-authorization shows the page. Reuse goes through the cache, so use a shared store across servers.
- **A new access token binds only its own connection.** A refresh moves only the refreshed token's connection, recognised by the access token Passport's refresh grant revokes. A code exchange binds only its consent's connection. A connection whose token expired without a refresh is no longer picked up by the person's next token for the client: the assistant authorizes again. Before, every spent connection for the person and client moved onto any new token.
- **`kit.mcp.server_url` (default `/mcp`) is now a real MCP endpoint.** Point assistants at it. `/mcp/connections/{connection}` keeps working.

### Added

- **MCP consent page** ([docs/mcp-consent.md](docs/mcp-consent.md)): Passport's authorization view is the kit's `Mcp/Authorize/Page`. It shows the client's name, the requested scopes in plain words (an unlabelled scope verbatim), and a choice among the person's active workspaces, defaulting to the current one, with Approve and Deny as CSRF-protected posts. Deny is Passport's `access_denied` redirect. Approve refuses (403) a workspace the person can't open a connection in, then makes the connection in the chosen workspace, and the code exchange binds it to the new token.
- **One MCP address**: `POST /mcp` opens the connection the access token is bound to, and answers 401 when there is none or the token is revoked, expired or another person's. A connection's own address must match the token's connection (403). The AI assistants page shows the address with a copy button.
- **Revoking from the AI assistants page**: your own connection with the connection ability, any member's with `kit.mcp.manage_ability` (owners and admins), who also see every member's connection; others see only their own. `composer test:mcp-authorize` covers consent and revoke through Passport's real routes.

## v0.5.39

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:642ca1e4…60e9` → `sha256:40fdd76c…81c4`), then `php artisan migrate`: the new `0001_01_02_000209_add_missing_audit_events_workspace_id_index` migration below. It does nothing where the index exists.

### Fixed

- **`audit_events.workspace_id` is indexed on every host.** Core creates `audit_events_workspace_id_index`, but a database that reached the column another way could lack it, and each workspace's audit query then scanned the table. The additive migration `000209` creates it only when no index covers `workspace_id` alone (a MySQL foreign key brings its own), never a second one. Its `down()` leaves the index. The impact is performance only. `composer test:audit-workspace-fk` covers a database with it and one without, on SQLite and, in CI, PostgreSQL.

## v0.5.38

### Fixed

- **`kit:add` preview no longer crashes on a host without a feature's packages.** On a single-tenant app without `spatie/laravel-multitenancy`, `kit:add session-ui --json` died with `Trait "Spatie\Multitenancy\Models\Concerns\ImplementsTenant" not found`, because the preview loaded the workspace model to check it. The plan is now what it always was for missing packages: the `composer require` among its host steps, not executable until they're installed. The host model checks (`user_model`, `workspace_model`) for that feature are skipped and listed under `deferred`, with a step to re-run the preview after `composer require`. Every preview (kit:adopt's plan too) also carries a `status`, `ready` or `blocked`. A genuine refusal under `kit:add --json` (an unknown feature, say) prints `{status: "refused", error, hint}`, the shape kit:adopt prints.

## v0.5.37

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:59db0c23…6c64` → `sha256:371e614a…b004`): `Kit/Form/InputSelect.vue` takes the fix below.

### Fixed

- **InputSelect no longer leaves `aria-controls` and `aria-activedescendant` pointing into a closed list** (axe critical, `aria-valid-attr-value`). After open→close, or a search then Escape, Reka kept the unmounted list's id and the last highlighted item's id on the input. Both are now absent while the list is closed, and Reka's own values stand while it's open. A caller's own `aria-controls` still wins. A test that runs axe only before any select opens can now run it after.

## v0.5.36

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:4ebd502f…3e95` → `sha256:bfeb3751…b94c`): new `Auth/ConfirmPassword/Page.vue`. Fortify's password confirmation needs no feature flag (its `password.confirm` routes are always registered), so the kit's fortify defaults are unchanged. The kit now binds Fortify's `PasswordConfirmedResponse`: after confirming, an intended URL off the app goes to `kit.home` instead.

### Added

- **Password confirmation page** ([docs/onboarding.md](docs/onboarding.md#password-confirmation)): `Auth/ConfirmPassword` for Fortify's `password.confirm`, with a Cancel link back to a same-origin `cancel` query or referer (else `kit.home`), and a return to the intended URL only when it is on the app. Both use `SafeRedirect::sameOrigin()`. `composer test:onboarding` and `composer test:auth-only` cover it.

## v0.5.35

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9c6bdc8c…922d` → `sha256:59db0c23…6c64`): new `Kit/Form/InputRichText.vue` and `Kit/Form/richText.ts`, exported from `@kit/ui`. **Core now lists `@tiptap/core`, `@tiptap/extension-code`, `@tiptap/extension-placeholder`, `@tiptap/pm`, `@tiptap/starter-kit` and `@tiptap/vue-3` (`^3.32.0`) under `requires.npm`**: add them to `package.json`, or your build and `vue-tsc` fail on the new field. The plan flags any that are missing, as attention.
- **Moving to InputRichText from an app's own tiptap field:** private links are a pattern the host passes, `privateLinks` (a RegExp, matched by `isSafeLink`), not an `allowPrivateLinks` boolean. A boolean can't say which links are the app's own. An app that passed `allow-private-links` passes its own scheme instead, as in `:private-links="/^app:\/\/record\/[0-9]+$/"`, and its server enforces the same pattern. `isSafeLink(value, privateLinks?)` takes the pattern too.

### Added

- **`InputRichText`** ([docs/input-rich-text.md](docs/input-rich-text.md)): a rich-text field on tiptap. Its `v-model` is a strict, portable ProseMirror JSON subset (`RichDocument`: paragraphs, H2 and H3, blockquotes, lists, and bold, italic, strike, inline code and links), validated both ways, so a document outside the subset locks the field and emits `content-error` instead of being silently dropped. It has a configurable toolbar (`tools`) with a Link settings popover that refuses unsafe links (`isSafeLink`, with an optional host `privateLinks` pattern), a placeholder, `read-only` and `disabled`. Pasted or dropped files become a `file-intent` for the host to upload as its own image blocks, so no URL enters the document.

## v0.5.34

### Upgrading

- **On adoption, seed operators before deploy or the platform owner loses tenant admin.** Workspaces gates `concierge.manage-tenants` on the `operators` table, not Concierge's `concierge.tenant_invitations.platform_owners`. Run `php artisan kit:operators:seed --from-platform-owners` (`--dry-run` first) after migrating. Seeding between a `kit:add` preview and `--apply` changes the plan identity: re-run the preview and apply with the new plan.

### Added

- **`kit:operators:seed --from-platform-owners`**: seeds `operators` from the configured platform owners, matching verified, active users by trimmed, lower-cased email, with the Gate's own verified-and-active test (`Operators::refusal`). Idempotent; `--dry-run` writes nothing; reports matched, already-operator, skipped (with why) and unmatched emails.
- **Empty-operators attention**: when platform owners are configured and `operators` is empty, `kit:add`/`kit:adopt` plans for workspaces carry a non-blocking `operators` step naming the users that would be seeded and the command, `kit:status` warns, and boot logs a warning in `local`/`testing` only. Addresses are masked (`j***@example.com`) with counts everywhere except an interactive `--dry-run`'s unmatched emails. Both read `config('concierge.tenant_invitations.platform_owners')`, never `env()`. `composer test:operators-seed` covers it.

## v0.5.33

### Upgrading

- `workspaces` is re-pinned (`sha256:ba061f45…4212` → `sha256:642ca1e4…60e9`) for its manifest only: no file changes. Run `kit:update plan|apply` to record the new pin.

### Fixed

- The `workspaces` feature now accepts `tey/concierge` 0.9 (`^0.8.4 || ^0.9`). Before, a host on concierge 0.9 failed the compatibility check for `workspaces` and every feature that requires it (`access`, `session-ui`, `workspace-ui`).

## v0.5.32

### Upgrading

- core's identity changes (`.env`/`.env.example` are now host-owned in Mod): run `kit:update core apply`; no files change, only core's installed record.

### Fixed

- Every declared `host_owned` path, dot-prefixed ones such as `.env` and `.env.example` included, now reaches Mod's `hostOwned`, so Mod itself refuses another lineage that owns or claims them (`host_owned:tey/kit:core`). The kit-side filter for Mod 0.9's dot-prefix limit is gone (#32).

## v0.5.31

### Added

- **`types/kit.d.ts` for hosts without a TypeScript generator**: the kit ships its generated `Tey.Kit.*` types. A hand copy at `resources/js/types/kit.d.ts` that a release has outdated is pointed out in the plan and `kit:status`, naming the types it lacks (a new view model). A host whose own `spatie/laravel-typescript-transformer` writes its types is never told to copy. `composer test:kit-types` keeps the file in step with the kit's classes.
- **Every PHP stub is Pint-clean** under Laravel's preset, so a host that runs Pint on the files an update wrote passes. `composer test:pint-stubs` checks it in CI (`bin/pint-stubs.sh --fix` applies it).

### Fixed

- **A schedule host step no longer reports `model:prune` as unscheduled when it is.** Laravel quotes option values when it compiles an event, so `Schedule::command('model:prune', ['--model' => [McpIntent::class]])` (or a string value, or several models) never matched the step. The command's name and options are now matched as tokens, wherever the schedule is defined.
- **The `@inertiajs/core` advice keeps a declaration your code imports.** A host that imports its types (`Method`, `PageProps`) must declare it (the standards' `js-imports` rule), so the advice is now "keep it declared at `@inertiajs/vue3`'s version (exactly X)", not "remove it". Hosts that don't import it still get the old advice.
- **Host steps read `vite.config.js` and `.mjs`, and the app's real entries.** The shell's alias check reads whichever Vite config exists. The layout-callback check (`resources/js/app.ts`) also reads every script entry the Vite config names (`next.ts`), and the message names the file it read.

## v0.5.30

### Added

- **CI checks the changelog on every pull request** (`bin/changelog-check.php`, the `changelog` job): `## Unreleased` must be the top section and appear once, and every released `## vX.Y.Z` section must match the base branch byte for byte, so a merge that splices lines under a released heading fails. A release (a new version at the top in place of `## Unreleased`) and a pull request that leaves the changelog alone pass. `composer test:changelog-guard` covers the rules.

## v0.5.29

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:6c30a7c8…85de` → `sha256:ba061f45…4212`): `0001_01_02_000201_create_permission_tables.php` and `0001_01_02_000203_add_slug_to_kit_workspaces_table.php` take the changes below. An app that already made these edits by hand matches the new stubs, so the update reconciles it.

### Fixed

- The workspaces slug migration stub sorts its `use` imports, so a host running Pint (`ordered_imports`) passes on the generated file. No schema change.
- The permission tables migration stub checks that spatie/laravel-permission's published migration has `up()` and `down()` before calling them, so a host's PHPStan (paths including `database/`) no longer reports `method.notFound` on the generated file. If either method is missing, the migration throws instead of failing silently.

## v0.5.28

### Upgrading

- **An app with stale journals:** if `kit:update` or `kit:add` refuses with `pending:tey/kit:<feature>` although every update completed, run `php artisan kit:update status`. A journal it lists as `stale, the target identity is already installed` is safe to clear: run `php artisan kit:update status --clear-stale`. A `Pending` one is a genuinely unfinished update: resume it with the printed command, and never move it aside.

### Added

- **`kit:update status`** lists each journal Mod reads as unfinished, as `Pending` (the target identity is not installed: resume it) or `Stale` (the target identity is already installed), and exits nonzero while any remains. **`--clear-stale`** removes the stale ones only, printing each one it cleared; a pending journal is never touched. `composer test:adopt` covers both.

### Fixed

- **`kit:adopt` no longer reports an older release's files as identical.** A file whose bytes match an older known release, not this one, is now `behind` (listed in `behind` with that release, and an `UPDATE:` line in the text plan). The adoption still claims it as it is, without writing, and the `kit:update plan --lineage=<feature>` that follows brings it up to date. Before, files 0.5.19 had changed (`index.ts`, `RadioCardGroup`, the Workbench) read as identical on a host installed at 0.5.13.
- **A no-op update no longer leaves an earlier no-op update's journal pending.** Mod reads a journal as unfinished while the installed record has not committed its plan and is still at its baseline. A no-op update's baseline is its target, so once a later update of the lineage committed a different plan (a changed input, such as the dependency lock, gives it a new identity), the earlier journal read as unfinished forever, and every plan was refused with `pending:<lineage>`. A completed `kit:update apply --lineage` now clears its lineage's stale journals, and `kit:update resume --lineage --intent` on a journal whose target identity is already installed clears it and reports `completed` with `cleared` and a message, instead of leaving it. The `pending:` hint no longer offers a stale journal to resume. A journal whose target is not installed is never removed.

## v0.5.27

### Upgrading

- **Laravel starter-kit hosts** (auth pages in `resources/js/pages/auth`): `kit:add session-ui` now reports the case conflict instead of failing. Run `php artisan kit:add session-ui --adopt-case` to review the rename, then `--adopt-case --apply` to make it, then preview and apply `kit:add session-ui` as usual. Search for `auth/` page names it does not rewrite (see [docs/catalog.md](docs/catalog.md)).
- **If `.gitignore` ignores `/.mod-maintenance`, or `.mod-maintenance/installed/` was never committed**, plans now point it out: commit `.mod-maintenance/installed/` and `installed.json`, and ignore only `/.mod-maintenance/journal-*.json`, `/.mod-maintenance/lock` and `/.mod-maintenance/owner.json`.

### Added

- **Case conflicts are named in the plan.** A declared path the host has under another case is listed in `case_conflicts` (declared and existing path), its feature's row is not executable, and a blocking host step says how to fix it. `kit:add --json` returns that plan; before, the plan threw Mod's "Case alias or collision at declared path."
- **`kit:add --adopt-case`** renames each conflicting host directory to the declared case (`git mv` in a git checkout, in two steps for case-insensitive filesystems) and rewrites the Inertia render names under it in `routes/` and `app/Http/`, then stops: it installs nothing and prints the next commands (the `kit:add` preview and its apply with `--expected-plan`). Without `--apply` it lists the changes and writes nothing. It refuses file conflicts and a target that already exists under the other case.
- **`kit:adopt plan --json`** prints only JSON, in `kit:add`'s plan schema (one `adopt` row), with the adoption's own keys beside it (`current`, `from`, `adopted`, `kept`, `orphans`, `removals`, `apply`). `apply`/`rollback --json` print no text lines, and a refusal is JSON too. Without `--json` the output is unchanged.
- **An attention host step when `.gitignore` ignores `.mod-maintenance/installed/`**, with the replacement lines; never blocking. In a git checkout it also fires when the installed records are untracked, and says to commit them. `kit:add`, `kit:adopt` and `kit:status` show it.
- **`kit:update` with kit files but no lifecycle records** names uncommitted records from another checkout as the first cause, then the `kit:adopt` route. `composer test:starter-case` covers all four.

## v0.5.26

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:bc8a9ad6…fa87` → `sha256:9c6bdc8c…922d`): `Kit/Feed/ActivityEntryRow.vue`, `Kit/Feed/activity.ts`, `Kit/Layout/Collapsible.vue` and `Kit/index.ts` take the changes below.

### Added

- **ActivityStream `effectiveOn`**: an entry's optional `effectiveOn` (ISO date) and `effectiveOnLabel` render "effective <label> · recorded <timestamp>" (`ActivityEntryData` takes both).
- **ActivityStream accepts a host's own intents and a null headline.** An `intent` outside the kit tones shows as neutral (`activityTone`), with a warning in development, instead of being refused. `ActivityEntryData` now accepts any intent. A `null` headline renders the row with its time and meta only.

### Fixed

- **The theme host step reads every CSS entry.** It reported missing colour tokens when `kit.css` was imported from a stylesheet other than `resources/css/app.css`. It now also reads each CSS entry named in `vite.config.{ts,js,mjs}` and follows local `@import`s transitively (inside the app, never through a link, at most 50 files). The Vite config is read as text, never run.
- **The Collapsible chevron no longer shows as a half-turned "┘" when open.** It rotated one ChevronDown, which a host's transition setup could leave stalled mid-turn. It now swaps ChevronRight (closed) for ChevronDown (open), with no rotation, and follows the trigger's colour. An ActivityStream group's toggle reads "Show fewer" while open, instead of "Show all 31".

## v0.5.25

### Added

- **Host guides**: a public-site recipe (docs/public-site.md), upgrading from kit 0.2.x (docs/upgrading-from-0.2.md), and which `.mod-maintenance` files a host commits, with fresh-checkout setup (docs/host-repository.md).

## v0.5.24

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:09e9f8af…8824` → `sha256:bc8a9ad6…fa87`): `Kit/Layout/FloatingToolbar.vue`, `Kit/Layout/toolbarAction.ts` and `Kit/Action/actionIcons.ts` take the changes below. **A FloatingToolbar with `items` now keeps one row at every width**: items that don't fit go into its ⌄ menu instead of wrapping to a second row.

### Added

- **FloatingToolbar client items overflow, group and go icon-only** ([docs/workbench.md](docs/workbench.md)). When `items` don't fit the width the pill has, they move into a ⌄ "More actions" menu: whole trailing groups first, then trailing items, keeping the first N. Measuring is a ResizeObserver with hysteresis (`fitToolbarItems`), and a menu row reaches `onAction` after the menu closes. A `group` per item draws a rule between groups, inline and in the menu. `iconOnly` makes the label the accessible name and tooltip.
- **`actionIcons` covers the common editor set**: `align-left`, `align-center`, `align-right`, `arrow-up`, `arrow-down`, `trash`, `image`, `eye`, `eye-off`, `chart` and `poll` (with `plus` and `copy`), each imported by name.

## v0.5.23

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:3b960b2a…e0ee` → `sha256:6c30a7c8…85de`): the workspaces and permission migrations work under `migrate --pretend`, and `000204` to `000207` say they are forward-only. No schema change.
- Rolling back leaves two things in place, on purpose. `audit_events.workspace_id` stays nullable with its `ON DELETE SET NULL` key, since `000204` to `000207` are forward-only. The access seed (the permissions `kit:permissions:sync` created and their owner and admin grants) also survives; only `kit_permission_defaults` is dropped. The README's "Rolling back" explains why and how to remove the permissions by hand.

### Fixed

- `php artisan migrate --pretend` no longer throws at `0001_01_02_000200` ("kit.workspaces.adopt is on … does not exist yet") on an adopting host. Pretend runs no query, so `Schema::hasTable()` can't see the host's tables. With `kit.workspaces.adopt` or `kit.access.adopt` on, the workspaces and permission migrations now take the adoption path and list its statements. `composer test:adopt-fresh` covers it.

### Documentation

- `docs/models.md` lists what each kit table with a `workspace_id` key does when the workspace is deleted. `entitlements` and `complimentary_grants` stay restrict as commercial records, so a workspace with them is deleted deliberately, after removing or archiving them.

## v0.5.22

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:3f155ade…11fd` → `sha256:58df39ce…6562`): `Partials/PublicShell.vue` takes the `toaster` prop below.

### Added

- **`<PublicShell :toaster="false">`**: the PublicShell mounts the kit Toaster by default (as it has since v0.5.17), so a public form's flashed toast shows after its redirect. `toaster` false leaves it out, for a page that mounts its own.

## v0.5.21

### Fixed

- **A table search or filter with a comma no longer returns a 500.** TableQuery registered search and filters with Spatie's default `,` delimiter, so `?filter[search]=Find, Member` arrived as an array and was cast to a string. Search and filters now take one value whole (`delimiter('')`; no kit filter is multi-value), and an array smuggled in as a search, filter or sort value is ignored instead of cast. A choice filter given `admin,viewer` refuses it as one unknown value (`InvalidFilterValue`), as before.

## v0.5.20

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:86dec1df…8ce7` → `sha256:09e9f8af…8824`): new `Kit/Feed/ActivityStream.vue`, `Kit/Feed/ActivityEntryRow.vue`, `Kit/Feed/activity.ts` and `Kit/Feed/useCursorFeed.ts`, exported from `@kit/ui`.
- Run `kit:update plan|apply` for `workspace-ui` (re-pinned `sha256:e5f861f9…6950` → `sha256:28486124…8da0`): the dashboard's `Partials/ActivityWidget.vue` draws through ActivityStream. **The activity widget's `items` are now `ActivityEntry` objects** (`id`, `kind`, `headline: { html }`, `occurredAt`…), not `{ id, actor, event, at }`. A host that edited that partial or reads the widget's items moves to the new shape.

### Added

- **`ActivityStream`** ([docs/activity.md](docs/activity.md)): a read-only, source-agnostic stream of activity entries, newest first, cut into days (`days`), with an `empty` state, `dense` rows and an opt-in `live` region. Entries carry a headline that is exactly one of `{ text }` or `{ html }`. HTML is rendered only through the stream's trusted path, and the host's server must have escaped it. Entries also carry a `<time datetime>`, a kit tone, `{ label, value }` meta, and groups that open in a Collapsible and say when they were cut short.
- **`useCursorFeed`** pages a stream over one Inertia prop: partial reloads with the cursor, pages that accumulate, an empty page with a cursor followed, a changed sync token reading the head page again, and Load more keeping focus. `createCursorFeed` is the same rules without Vue.
- **`ActivityEntryData`** (`Tey\Kit\Features\Core\Data`): the PHP shape hosts map their records into, with `text()`, `trustedHtml()` and `group()`. It refuses a headline with both kinds or neither, an unknown tone, and malformed meta.

## v0.5.19

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:31138a46…dc77` → `sha256:86dec1df…8ce7`): `Kit/Form/RadioCardGroup.vue` and the `Kit/Workbench/` files take the changes below.
- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:0f324262…f54c` → `sha256:3f155ade…11fd`): three new host steps (below). `kit:update` and `kit:status` point out any the host lacks, as attention.

### Added

- **`RadioCardGroup` cards carry `data-value`**: each card's option value, as a hook for tests, automation and styling.
- **`<Workbench :actions-bar="false">`** renders no actions bar, for a dense surface that drives the panes with `useWorkbench().togglePane()` and `focusRegion()`. With `headings` off too, the panes have no toggle of their own, so in development the Workbench warns if no pane has been toggled a few seconds after mount.
- **The shell's host prerequisites are host steps**: the `@kit/shell` alias in `vite.config.ts` and `tsconfig.json`, and the kit's classes in the TypeScript transformer (`base_path('vendor/tey/kit/src')` in `app/Providers/TypeScriptTransformerServiceProvider.php`) for the generated data types. Each is checked and gives the exact snippet.
- **`kit:status` reports the adopted features' host steps and npm requirements**, checked now, under an ATTENTION block when one is unmet. `--json` adds them as `requirements`, only when an adopted feature declares any.
- **The host contract is typed for PHPStan**: `LifecycleHost::inputPaths()` and the `Lifecycle` methods a host calls declare their array shapes, so a host at level 8 or max gets no iterable-type errors. `composer test:host-contract` runs PHPStan at level max on the contract and a fixture host.

### Fixed

- **`TableQuery` redirects an empty result past page 1 to page 1.** The last-page redirect skipped empty results, so a filter that matched nothing at `?page=9` rendered a blank page 9. It now keeps the query and the flashed data, as for a non-empty result.

## v0.5.18

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:a1adff6f…3c22` → `sha256:3b960b2a…e0ee`), then `php artisan migrate`. Audit rows whose workspace_id names a workspace that no longer exists are set to null, and the migration prints and logs how many (a warning with `orphaned_audit_events`). Core alone is unchanged.

### Fixed

- **Audit events outlive their workspace.** A host with orphaned audit rows no longer fails `0001_01_02_000204` at deploy (it now leaves the key to the new migrations when orphans exist), and deleting a workspace that has audit events no longer fails on the foreign key. New workspaces migrations make `audit_events.workspace_id` nullable (000205), null orphaned ids with a count (000206), and replace the key with one that sets null on delete, converting 000204's key where it already ran (000207). A new nullable `audit_events.workspace_name` (000208) is backfilled from `workspaces` and filled by `Audit::write`, so a row still says which workspace it was. All are idempotent. `composer test:audit-workspace-fk` covers it on SQLite and, in CI, PostgreSQL.

## v0.5.17

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:8adcf2e2…2024` → `sha256:0f324262…f54c`): new `Partials/PublicShell.vue`, `Partials/PublicMeta.vue` and `pages/Public/Markdown/Page.vue`. Shell now owns `resources/js/pages/Public/Markdown`.
- **Optional:** route the public pages to the PublicShell in your layout callback (`Public/*`, and `Workspace/Error/Page` for a guest); see [docs/public-shell.md](docs/public-shell.md). `kit:update` points this out as attention (the shell's new `mention` host step), never blocking. Until then those pages keep the GuestShell.

### Added

- **`PublicShell`** ([docs/public-shell.md](docs/public-shell.md)) for sessionless public pages (landing, help, legal, a guest error), built like the GuestShell:
  - a skip link to `#content`;
  - a header with the lockup, a `nav` slot and an auth-aware CTA ("Sign in" / "Open your workspace" from the shared shell's kind; the host passes the URLs);
  - `main` at `width` prose, wide or full;
  - a footer from the shared `shell.footer`;
  - `tone` default or brand, dark-mode aware, with no tracking.
- **`PublicMeta`**: a page's title, description, canonical link, Open Graph and Twitter card.
- **`Public/Markdown/Page`** with `PublicMarkdown::page()`: legal or help markdown rendered and sanitized on the server (raw HTML stripped, unsafe links refused).
- **A `mention` host step kind**: attention when a host file doesn't mention something (the shell's: `resources/js/app.ts` mentions `PublicShell`). `composer test:public-shell` covers the step and the sanitizing.

## v0.5.16

### Added

- **The workspace MCP server can be named** (#103): `kit.mcp.server.name` (or `KIT_MCP_SERVER_NAME`) and `kit.mcp.server.instructions` set what assistants see. Unset, the kit's "Workspace operations" and its instructions stand. `composer test:mcp-server-config` covers it.
- **`<x-kit::fonts />` supports a CSP nonce** (#45): a `nonce` attribute, else `Vite::cspNonce()`, on both links and the style tag. Without one, the output is unchanged.

## v0.5.15

### Added

- **`TolerantImage`**, a raster generator for PNGs with malformed metadata ([docs/media.md](docs/media.md#conversions)): before GD loads a PNG, it removes the `iCCP`, `iTXt`, `zTXt` and `tEXt` chunks from a temporary copy (the original is untouched). Where the host's libpng treats a bad colour profile as an error, the conversion then succeeds. A corrupt PNG (a bad critical chunk, a truncated file) is passed on unchanged and still fails loudly. It replaces `Image::class` in `media-library.image_generators`; `kit.media.tolerant_png` false opts out.
- [docs/media.md](docs/media.md#where-failed-jobs-live): where failed jobs live. On Laravel Cloud's managed queue they're in the queue's own store, not the `failed_jobs` table.

### Known limitations

- **TolerantImage's "fails without" half isn't proven in CI.** CI's GD builds print libpng's warnings to stderr instead of raising them in PHP, so under Laravel's error handler the malformed-iCCP PNG can't fail there and the check prints SKIP. That half is proven only on GD builds that raise the warning in PHP: verified locally on PHP 8.4 and 8.5, and by the client's own repro. CI proves the conversion succeeds.

## v0.5.14

### Fixed

- **Adopting your own workspaces table on a fresh database.** The kit's migrations (`0001_01_02_*`) sort before a host's own (real dates), so on an empty database (CI, a new developer, tests) the kit created `workspaces` and the permission tables first and the host's own migration then failed with "already exists"; the access, settings, mcp and notes tables' foreign keys to `workspaces` ran too early as well. Adopt-in-place only worked where the host's tables already existed at migrate time. Now, when `kit:add` plans an adoption, it records it in the workspaces lineage's installed record (`adopt`: the stamp and the adopted tables) and publishes every migration of each feature it adds that reaches workspaces as `<stamp>_<order>_<name>.php`. The stamp is one second after the host's latest migration under `database/migrations` (and no earlier than today, UTC), so these run after the host's own. `kit.workspaces.adopt` and `kit.access.adopt` (null by default, which reads the record; `KIT_WORKSPACES_ADOPT` and `KIT_ACCESS_ADOPT` override it) make the workspaces and permission migrations refuse, rather than create, a table the host owns that doesn't exist yet. A host that adopts nothing keeps the catalog's file names. An existing install keeps its file names on `kit:update`, so nothing it already ran is renamed or re-run. A migration a later release adds takes the install's stamp. `composer test:adopt-fresh` covers it (SQLite, and PostgreSQL in CI).

### Upgrading

- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:0c427f2f…92f8` → `sha256:a1adff6f…3c22`): the workspaces and permission migrations read `kit.workspaces.adopt` and `kit.access.adopt`.
- **A host that already adopted its own tables (installed before this release):** production is unaffected, and its migrations keep their names. Set `KIT_WORKSPACES_ADOPT=true` (and `KIT_ACCESS_ADOPT=true` when you own spatie's tables) so the kit never creates those tables. A fresh database still runs the kit's migrations before your own: there, they now refuse with a clear message instead of creating a second table. Load a schema dump (`php artisan schema:dump`) for fresh databases. Or, if production has not run the kit's migrations yet, revert the `kit:add` (its files and its `.mod-maintenance` record) and run `kit:add` again with this release, so it publishes stamped migrations. With the flags set, `kit:add` of a further feature also publishes stamped migrations.
- **A host adopting for the first time on a fresh database:** commit your own `create_workspaces` (and spatie permission) migrations first, then run `kit:add`. The stamp follows the latest migration in your codebase. If the database you plan against doesn't have your tables yet, set `KIT_WORKSPACES_ADOPT=true` / `KIT_ACCESS_ADOPT=true` before the plan.

## v0.5.13

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:88a4470f…0348` → `sha256:31138a46…dc77`): DataTable, RowActions, ActionSegment, ActionMenuItems, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Feedback/Badge.vue` and `Table/rowLabel.ts`.
- **A `get` action descriptor (without a `confirm`) is now a link** in RowActions, ActionBar and the ⌄ menus, not a button. A test or stylesheet that looked for a `<button>` there needs updating.

### Added

- **`<Badge>`** ([docs/data-table.md](docs/data-table.md#status-cells-badge)): a status label with tones neutral, info, success, warning and danger on the kit tone tokens (AA in light and dark), and sizes sm and md.
- **DataTable `row-label`** (a column key or a function): row controls read "Actions for Ana Lopez" and "Select Ana Lopez", falling back to the row number.
- **`get` descriptors render as links** in RowActions (a kit Button `href` inline; the ⌄ item is the link), ActionBar segments and its menu rows (`rendersAsLink()`): an Inertia Link, or an `<a target="_blank" rel="noopener noreferrer">` with `newTab`. Non-GET, confirm and dialog actions stay buttons.
- [docs/data-table.md](docs/data-table.md): TableQuery, row labels, links, Badge, and that several lists on one page means tabs.

### Fixed

- **TableData's generated TypeScript is precise:** a row is `{ id: string | number; actions?: Action[] } & Record<string, unknown>` (not `Record<string, any>`), and `emptyState` is `'first-use' | 'no-results' | null` (not `unknown | null`).
- **A GET action that asks a confirmation now asks before it navigates.** ActionBar segments rendered any GET descriptor as a bare Link, so a GET with a `confirm` navigated without asking. Such an action is now a button that opens the confirmation and visits once confirmed, in RowActions, ActionBar and the ⌄ menus alike.

## v0.5.12

### Fixed

- **PerformConversions fails loudly instead of finishing with nothing made** (found in production: 2 of 407 images).
  - An original it can't read (Media Library writes 0 bytes and returns on a refused read when the disk doesn't throw) throws `media.conversions.unreadable_original`.
  - An image that ends with none of its requested conversions throws `media.conversions.none_generated`.
  - Both are retried. A media row deleted before the job runs still drops the job, now with a `media.conversions.missing_model` warning naming the media id.

## v0.5.11

### Fixed

- **TableQuery is generic over its model** (`@template TModel of Model`): `for(Builder<TModel>)`, `search`/`filter` closures over `Builder<TModel>`, `build(Closure(TModel))`, and the fluent methods return `$this`. So a typed caller passes PHPStan for any model.
- **`TableQuery::defaultSort($key)` sorts by the key's declared SQL column.** It passed the key itself to spatie, so a key that differs from its column (`joinedAt` → `created_at`) sorted by a column that doesn't exist.
- **A page past the last redirects to the last page**, keeping the rest of the query and the session's flashed data (a toast). Before, it showed an empty page.

## v0.5.10

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:046a13b2…10c0` → `sha256:88a4470f…0348`): `css/kit.css` changes.

### Fixed

- **Toasts meet WCAG AA.** The Toaster used Sonner's own rich colours, and its success toast failed AA (4.26:1, axe color-contrast). kit.css now maps every Sonner rich-colour variable (`--success-*`, `--info-*`, `--warning-*` and `--error-*` for bg, border and text) onto new kit tone tokens (`--kit-tone-<tone>-bg|border|fg`), light and dark. The text contrast is now at least 6.8:1 (light) and 10.3:1 (dark). A host theme overrides the tokens. `toaster-contrast.test.mjs` checks the mapping and the ratios.

## v0.5.9

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ff8ac070…63ae` → `sha256:046a13b2…10c0`): Workbench and `workbenchLayout.ts` change.

### Added

- **Dense Workbench surfaces** ([docs/workbench.md](docs/workbench.md#dense-surfaces-default-collapsed-panes-no-headings-an-overlay-navigator)):
  - `:default-collapsed="{ navigator: true }"` collapses panes the first time a layout shows, only without a remembered layout (a saved one wins).
  - `:headings="false"` drops the region headings; the landmarks keep their names, and the pane toggles move to the actions bar.
  - `navigator-mode="overlay"` opens the navigator as a sheet over the stage (a kit Modal: focus trap, Esc, focus back), so the stage never resizes; `close-on-select` closes it on a choice.

## v0.5.8

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:9223364e…f59e` → `sha256:ff8ac070…63ae`): RadioCardGroup and `contract.ts` change; new `Form/radioCommit.ts`.

### Added

- **RadioCardGroup `commit="click"`** ([docs/radio-card.md](docs/radio-card.md#commit-on-click-commitclick)) for live polls: the arrow keys only move focus (a roving tabindex over `role="radio"` buttons), and Space, Enter, a click or a tap commits, setting `v-model` and emitting `commit` once. Re-selecting the current option emits nothing, and the choice is announced politely. This is a documented deviation from the APG's auto-select. The default (`change`) is unchanged.

## v0.5.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4585ea42…da76` → `sha256:9223364e…f59e`): Dialog, ActionBar, RowActions, BulkBar, `actionPlacement.ts`, `index.ts` and `contract.ts` change; new `Overlay/confirmText.ts`.
- **ActionBar now runs a descriptor itself when the host does not listen to `@action`**: its `confirm` first, then an Inertia visit. Before, such an action did nothing. A host that listens is unchanged.

### Added

- **Typed confirmation** ([docs/typed-confirmation.md](docs/typed-confirmation.md)) for destructive actions:
  - Dialog `confirm-text` (with `case-sensitive` and `confirm-text-label`): a labelled input, and Confirm disabled until the text matches, announced politely; paste allowed. The typed text is emitted with `confirm` and sent as `confirmation` with the `action` visit.
  - A descriptor's `confirm.typed` (`TypedConfirmation::confirm()`) is rendered by RowActions, BulkBar and ActionBar, which send `confirmation`.
  - The server re-checks with `TypedConfirmation::require($request, $text)`; a missing or mismatched `confirmation` is a validation error.
  - `composer test:typed-confirmation` and `typed-confirmation.test.mjs` cover it.

## v0.5.6

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2bf2341…fcb2` → `sha256:4585ea42…da76`): Workbench, `workbench.ts`, `useWorkbench.ts`, `workbenchStage.ts`, FloatingToolbar and `index.ts` change.

### Added

- **Workbench element attribute:** the host names the attribute its stage elements carry their id in, with `<Workbench element-attribute="data-block-id">` or `useWorkbench(key, { elementSelector })` (a name or `[name]`). The stage toolbar's selection rects (`selectedRects`, which now takes the attribute), FloatingToolbar `mode="stage"` and `reposition()` use it. The default stays `data-workbench-element`. `elementAttributeName()` and `DEFAULT_ELEMENT_ATTRIBUTE` are exported, and an invalid name falls back to the default.
- **Workbench `:keys="false"`** for live sessions and transports (the app owns the keyboard): none of the Workbench's bindings fire (↑/↓, j/k, Alt+↑/↓, `/`, F6, Esc, ⌘[ / ⌘], zoom). Focus management and the region landmarks stay. `enabledCommand()` is the pure gate.

## v0.5.5

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f1e58788…5cf7` → `sha256:a2bf2341…fcb2`): new `Form/RadioCardGroup.vue`, `Form/EmojiTray.vue` and `Form/emojiTray.ts`; Button, Field, `index.ts` and `contract.ts` change.
- Field's label now has the id `<id>-label`. A host test that selected the label by an exact attribute list may need updating.

### Added

- **Media delivery** ([docs/media.md](docs/media.md#delivery)): `MediaDelivery::respond($request, $resolve, $conversion, $public, $mode)` serves an item behind the app's own route, and the app's resolver decides what's a 404. `proxy` streams the bytes: public items are immutable with an ETag and a 304, private ones are `private, no-store`, everything is nosniff, and anything that isn't an image downloads under a sandbox CSP. `redirect` answers a 302 to a signed bucket URL, and the redirect's own max-age is at most half the TTL (`kit.media.redirect_ttl`). `kit.media.delivery` sets the default mode. `composer test:media-delivery` covers it.
- **Adopting existing objects** ([docs/media.md](docs/media.md#adopting-existing-objects)): `AdoptObject` makes an object already on a disk a Media item over its key (via `KeyPathGenerator`), probed by size and its first bytes, with no copy and idempotent. `AdoptMediaCommand` is the command pattern: an app subclass names the owners and each one's key. It's tenant-aware through spatie's `TenantAware` command trait (every tenant, or `--tenant=`) and refuses to run with no tenant. It runs in id chunks (`--chunk`), one `TenantAware` queued job per chunk on `kit.media.queue` that fails loudly with no tenant (`--sync` runs them inline). `--dry-run` prints per-tenant counts only, and a tenant whose source has rows but no owners is a failing warning. Re-runs are no-ops. `ConfirmUpload`'s header read is now `ObjectProbe`. `composer test:media-adopt` covers it.
- **`<RadioCardGroup>`** ([docs/radio-card.md](docs/radio-card.md)): large tappable choices (a poll's options) as native radios in cards, so it has one tab stop and arrow keys and submits with a form. A `radiogroup` named by its Field's label, with `v-model`, selected and disabled states, and an optional result bar and percentage (`show-results`, or the `result` slot).
- **`<EmojiTray>`** ([docs/emoji-tray.md](docs/emoji-tray.md)): a compact reaction picker. A labelled `toolbar` with a roving tabindex, a spoken label per emoji, and a `react` event throttled by `throttle` ms (presses inside the window are dropped). It has `DEFAULT_REACTIONS` and reduced motion.
- **Button `size="lg"` and `size="xl"`** ([docs/button.md](docs/button.md)): `lg` is the host button's; `xl` is the host's lg made a ~96px tap target with kit classes.
- **Button `href`:** a link styled as the button, never a `<button>` inside an `<a>`. It's an Inertia `<Link>` (`method`, `data`, `preserve-scroll`; a non-GET method renders as a `<button>`), or a plain `<a>` with `external` (`rel="noopener noreferrer"` for `target="_blank"`). `VisitData` is exported.

## v0.5.4

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:598510bf…b128` → `sha256:f1e58788…5cf7`): new `Layout/DescriptionList.vue` and `Feedback/EmptyState.vue`; `index.ts` and `contract.ts` change.

### Added

- **`<DescriptionList>`** ([docs/description-list.md](docs/description-list.md)): term and description pairs as a real `<dl>`, from `items` or slots. Inline (side by side from `sm`, stacked on a phone) or stacked, with an optional copy-to-clipboard button per value and a polite status.
- **`<EmptyState>`** ([docs/empty-state.md](docs/empty-state.md)): an icon, a title, a body and the next step, as an `action` slot or server action descriptors. Has a `compact` form for panels and lists, and is a plain labelled region, never a live region. Both components are exported from the barrel, with `DescriptionListProps` and `EmptyStateProps` in the contract.

## v0.5.3

### Upgrading

- **Upgrading from v0.4.7 or earlier, with your own dashboard widgets:** since v0.4.8, `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an app override that injected its own widgets breaks. Register them with `Registry::widget()`, or set `kit.dashboard.widgets` to `[]` (now also noted under v0.4.8 and in [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets)).
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:a2d9f179…bf85` → `sha256:598510bf…b128`): `Form/FormActions.vue` changes.

### Added

- **`kit.mcp.tools`** turns off individual kit MCP tools by name, for an app that serves its own: `['workspace_dashboard' => false, 'workspace_search' => false]`. A tool that is off is neither listed nor callable. The default (`[]`) leaves every tool on. `composer test:dashboard` covers it.
- **Responsive conversions** ([docs/media.md](docs/media.md#conversions)): `HasResponsiveImages::responsiveWebp($collection, $widths)` declares `w<width>` WebP conversions, fit within the width and never upscaled. `PerformConversions` runs them one image per job on `kit.media.queue` (default `media`), with tries, backoff, a timeout and a memory limit; a retry does only what's missing. It implements spatie's `TenantAware` (it carries and runs in its tenant) and fails loudly with no current tenant. `Conversions::regenerate()` is idempotent. `Srcset::for()` lists only generated widths, with the original as the fallback, over the app's own URLs. `AttachUpload` queues an item's conversions. `composer test:media-conversions` covers it.

### Fixed

- **ViewModel props come in the same order on every PHP version.** `propMethods()` (and so `toArray()` and the generated TypeScript) kept reflection's order, which PHP 8.4 and 8.5 give differently for trait and inherited methods. The order is now ancestors first (root to child), then within each class its traits' methods in `use` order and its own by line; an override keeps its parent's slot. `composer test:viewmodel-order` covers it on both versions.
- **A typed `useForm<T>()` passes `vue-tsc` on FormActions and FormCard.** `FormActions`' `FormLike` declared `reset` and `clearErrors` as function-typed properties, so a form whose `reset` takes only `T`'s keys failed with TS2322. They are now methods (bivariant). The components type-check job compiles a fixture passing `useForm<{ a: string; b: { c: number } }>()` to both.

## v0.5.2

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:35504c18…cd67` → `sha256:ceb00b2e…ce1e`): BulkDrop changes; new `Form/directUpload.ts`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:ceb00b2e…ce1e` → `sha256:a2d9f179…bf85`): ActionBar and `Action/actionFit.ts` change.

### Added

- **BulkDrop `direct`:** with `request` and `confirm` routes, BulkDrop uploads each file straight to the bucket with progress and emits `uploaded`. `directUpload()` is the same flow without the dropzone. A POST ticket falls back to multipart, and retries reuse each File's mutation id.

### Fixed

- **ActionBar no longer triggers a ResizeObserver loop.** Its observer measured and re-laid out the bar inside its own callback, so flipping between the compact and full layouts resized the observed root in the same frame ("ResizeObserver loop completed with undelivered notifications", which fails browser tests). It now measures in one coalesced animation frame, skips a measure that hasn't changed, and cancels the frame and disconnects on unmount. The fit has hysteresis: a compact bar returns to the full layout only with `ACTION_FIT_HYSTERESIS` (8px) to spare, so a width at the boundary can't oscillate. `fitActions()` takes the previous state as an optional third argument, and `sameMeasure()` is new.

## v0.5.1

### Upgrading

- Run `kit:update plan|apply` for `workspaces` again (`sha256:9d17553b…b75f` → `sha256:9243696e…5a0f`): its workspaces, permission and slug migrations now adopt an existing table in place, and it declares the `workspace_model` host step. Nothing changes for an existing kit host: the migrations already ran, the kit's `Models\Workspace` stays the default binding, and the step is met by the defaults.
- **Binding your own workspace model** (optional): set `concierge.models.workspace` and `multitenancy.tenant_model` to the same class, which extends spatie's `Tenant`, implements `Tey\Concierge\Contracts\Workspace` and uses `Tey\Kit\Features\Workspaces\Concerns\IsKitWorkspace`. See the README's "Adopting into an existing workspaces table".
- Code that type-hinted `Tey\Kit\Features\Workspaces\Models\Workspace` against a kit method's return value now gets the Concierge contract type; the object is still the bound model.
- Kit signatures that took the kit model now take the contract, so an extender overriding one widens its parameter to match: `public function handle(Models\Workspace $workspace)` → `public function handle(\Tey\Concierge\Contracts\Workspace $workspace)` (likewise `Workspace::query()` → `Workspaces::query()`, `Workspace::required()` → `Workspaces::required()`).
- Run `composer update tey/concierge`: the kit now requires `tey/concierge` ^0.8.4, for the workspace context's `enter()`/`leave()`. Then run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:9243696e…5a0f` → `sha256:0c427f2f…92f8`). An app with its own `WorkspaceContext` implementation must add `enter()` and `leave()` (see Concierge's upgrade notes).
- **A host with its own `config/multitenancy.php`** (or its own `switch_tenant_tasks` list) must add the kit's two tasks, or a TenantAware job or command runs without the Concierge context and with a stale Pennant cache:

  ```php
  'switch_tenant_tasks' => [Tey\Kit\Features\Workspaces\Tasks\ConciergeContextTask::class, Tey\Kit\Features\Workspaces\Tasks\FlushFeatureCacheTask::class],
  ```

### Added

- **Adopt the workspaces feature into an existing workspaces table.** A host with its own `workspaces` table and spatie/laravel-permission's tables (with teams) gets planned adoptions (`adoptions`, `HostRequirements::adoptions()`) instead of collisions. The workspaces migration adds only `admitted` (existing rows set to true), `settings_version` (set to 1), `provisioning_invitation_id` and `slug` where missing, and never drops or alters a host column. The permission migration creates nothing when the five tables exist, and refuses tables without the configured teams column. Each adoption is recorded in a kit-owned `kit_adoptions` table (table, columns added, when); `down()` reads it, never drops an adopted table, and drops only the columns the kit recorded adding.
- `Tey\Kit\Features\Workspaces\Workspaces` resolves the bound workspace model (`model()`, `query()`, `current()`, `required()`, `refusal()`). Every kit reference goes through it, and `composer test:workspace-adopt` fails if anything under `src/` names `Models\Workspace` again.
- `IsKitWorkspace` carries the kit model's behaviour (slugs, `memberOf`, Concierge naming and admission, `required()`) for a host model to use. The kit model keeps only its no-timestamps overrides.
- The `workspace_model` host step: a plan refuses a binding where `concierge.models.workspace` and `multitenancy.tenant_model` differ, or the model misses the contract, `IsTenant` or the trait.
- `composer test:workspace-adopt` (SQLite memory, synthetic host schema), and a CI job running it against a PostgreSQL service container (`DB_CONNECTION=pgsql`). Not covered: MySQL, and an end-to-end `kit:add` against a real host app.
- **Switch tenant tasks:** making a workspace the current tenant (spatie's `makeCurrent`, `Tenant::execute`, a TenantAware job or command) now does what TenantBoundary did:
  - `ConciergeContextTask` enters the workspace in Concierge's context (the current workspace and the permission team), and `forgetCurrent` leaves it, restoring what was there before;
  - `FlushFeatureCacheTask` flushes Pennant's cache both ways.

  They're registered in the kit's multitenancy defaults. `composer test:tenant-tasks` covers them, including that a TenantAware job with no tenant fails loudly.

## v0.5.0

### Upgrading

- **`SurfaceContext`:** the barrel's type export for a surface's tone and shape (`Surface` from `Surface/surface`) is now `SurfaceContext`. Exporting it as `Surface` as well as the `<Surface>` component was a duplicate identifier (TS2300) under vue-tsc. Change `import type { Surface } from '@kit/ui'` to `import type { SurfaceContext } from '@kit/ui'`. `provideSurface`/`useSurface` and `Surface/surface` are unchanged.
- Run `kit:update plan|apply` for `core` (index.ts, Layout/Collapsible.vue, Surface/Surface.vue, contract.ts).
- **Surface clips by default** (`clip`, default `true`, is `overflow-hidden`). If something inside a Surface must overflow it or stick (a sticky header, an absolutely positioned popover), pass `:clip="false"`.
- **session-ui splits into session-ui (the sign-in) and workspace-ui (the workspace pages).** Re-pinned: `session-ui` `sha256:c0730ecd…9e0d` → `sha256:cc31f07b…ae1f`, `notes` `sha256:1d5fe0eb…9370` → `sha256:d373d438…f1b2` (it now requires workspace-ui); new `workspace-ui` `sha256:e5f861f9…6950`. No file's bytes change and nothing is deleted: 22 files change owner, from session-ui to workspace-ui. After `composer update`, and before any of the steps below, the app keeps its workspace pages on: workspace-ui is carried while session-ui's installed lineage still owns them. Move them over with these runs, in this order. Each `plan` prints the `apply` command to run (with its `--expected-plan`, `--exclusive-checkout` and `--intent`), and each result's `next` names the following run:
  1. `php artisan kit:update plan --lineage=session-ui`, then its `apply`. session-ui releases the 22 workspace pages (each file's state is `release`; none is written or deleted) and goes to an interim version. This is also the update that makes it own only its three welcome-step folders under `pages/Welcome`, not the whole folder.
  2. `php artisan kit:update plan --lineage=workspace-ui`, then its `apply`. workspace-ui is installed at exactly the released bytes: it claims the 22 files and writes nothing.
  3. `php artisan kit:update plan --lineage=workspace-ui` again, then its `apply`: workspace-ui goes to its target (nothing to write).
  4. `php artisan kit:update plan --lineage=session-ui` again, then its `apply`: session-ui finishes.
  5. If the app has notes: `php artisan kit:update plan --lineage=notes`, then its `apply`. It requires workspace-ui, so it runs after step 2.
  6. If `config/kit.php` sets `kit.features`, add `'workspace-ui'` after `'session-ui'` (the app works without it, as workspace-ui is carried once its lineage is installed).

  If you have edited one of the moved pages (under `pages/Workspace/` or `pages/Provision/`), step 1 writes nothing and prints the diff from the installed file to yours: put your changes aside, restore the installed bytes, run the steps, then reapply your changes. Needs tey/mod with removals (main, 0.11), as the shell split did.
- Nothing else changes unless you set `kit.home`: the kit's home stays at `/`.

### Added

- **Surface `clip` and `translucent`** ([docs/surface.md](docs/surface.md)). `clip` (default `true`) is `overflow-hidden`; set it `false` for a sticky child. `translucent` is an 80% fill with a backdrop blur, solid without `backdrop-filter` support and under `prefers-contrast: more` or `prefers-reduced-transparency`, and unblurred under `prefers-reduced-motion`. Surface now clips by default: a host relying on a Surface's content overflowing it (a popover inside, a sticky child) passes `:clip="false"`.
- **CI type-checks the core components with vue-tsc** (`bin/typecheck-components.sh`, versions pinned in `typecheck/package.json`). The host's shadcn-vue components are typed `any` there (`typecheck/shims.d.ts`), so the check covers the kit's own code, not its use of them.
- **Auth-only session-ui.** A host can adopt only the sign-in: `kit:add session-ui` brings `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword`, `Auth/TwoFactor`, `Auth/VerifyEmail` and `Auth/Invitation/Show`, the welcome steps and the routes around them, and none of the workspace pages. Without workspace-ui the kit serves nothing at `kit.home`, and Fortify's `home` (where sign-in lands) defaults to `kit.home`, the app's own page; an accepted invitation and the last welcome step land there too. `composer test:auth-only` and `composer test:host-boot` cover it.
- **`workspace-ui`**, a new feature requiring session-ui: the kit's home, the dashboard and its widgets, search, the settings and account landing pages, Settings › People, the account pages under `/me`, the placeholders, the error page, `Workspace/Index` and `Provision/NoWorkspace`, moved from session-ui byte for byte. Its routes keep their names and `kit.routes.session-ui` options, and its classes keep their `SessionUi` names. `composer test:workspace-ui` covers the split and the move.
- **`kit.home` (`KIT_HOME`):** where workspace-ui serves the kit's home, `/` by default. An app with its own public landing at `/` sets it elsewhere (`/app`); the kit's home and the breadcrumbs' root move there, and the app's `/` is untouched.

### Fixed

- **vue-tsc failed inside the kit's core components** (TS2300 in `index.ts`; TS2769 and TS2322 in `Layout/Collapsible.vue`). Collapsible's `open` model is now `defineModel<boolean | undefined>('open')`: bound, the host's value wins; unbound, it starts from `defaultOpen`, and nothing is emitted until the user toggles (it used to write the model while setting up).
- **An auth-only app has no links to workspace pages.** Without workspace-ui, `/me/workspaces` (and the old `/workspaces`) redirects to `kit.home` instead of rendering a chooser page the app does not have; Concierge's `no_workspace_url` defaults to `kit.home` (an app's own `config/concierge.php` decides for itself); and the shared shell's home, switcher, product, account and "All workspaces" links point at `kit.home`, with no "Account settings" item. The workspace guard's and leave-workspace redirects use the same `WorkspaceUrl::chooser()`. With workspace-ui nothing changes. `composer test:auth-only` and `composer test:host-boot` cover it.
- **session-ui no longer owns a host's landing page.** It owned all of `resources/js/pages/Welcome`, so an app whose landing lives at `pages/Welcome/Page.vue` (with `Partials/`) collided with the kit there. It now owns only `Welcome/Timezone`, `Welcome/Photo` and `Welcome/WorkspaceTimezone`. `composer test:host-boot` covers the ownership and `kit.home`.

## v0.4.33

### Added

- **Direct-to-bucket uploads** (`Tey\Kit\Media`, [docs/media.md](docs/media.md)): `RequestUpload` reserves a pending upload from a declared type, size and SHA-256 and returns a presigned PUT that binds them (or a multipart `POST` ticket when the disk cannot presign); `ConfirmUpload` checks the object by size and its header bytes (a hash on a local disk), and `AttachUpload` makes it a Media item over the existing key with no byte copy. Mutation replay, dedupe by SHA-256 within a scope. `KeyPathGenerator` and `KeptFileRemover`: deleting a Media item over a key never deletes the object. A library, not a catalog feature, so no re-pin. `composer test:media-uploads` covers it.

## v0.4.32

### Upgrading

- Run `kit:update plan|apply` for `session-ui` again (`sha256:f2cc2fd1…3670` → `sha256:cd013c29…abae`): it adds `Auth/ForgotPassword` and `Auth/ResetPassword`, and the sign-in page links to them.
- **Password reset is on:** the kit's fortify defaults now include `Features::resetPasswords()`. The host needs Laravel's `password_reset_tokens` table and mail that can send the reset notification. An app that publishes its own `config/fortify.php` keeps its own feature list.
- Run `kit:update plan|apply` for `core` (Button, FormActions, FormCard, ActionBar, ActionMenuItems, InputCheckbox; new `Surface/Surface.vue`, `Form/formSubmit.ts` and `Action/menuSelect.ts`) and `session-ui` (its form buttons now say `type="submit"`).
- **Behaviour change: a kit `Button` is `type="button"` by default.** It's a submit only inside Inertia's `<Form>` or a FormCard, or when you pass `type="submit"`. Give any of your own kit Buttons that submit a plain `<form>` (or a form outside FormCard and `<Form>`) an explicit `type="submit"`. A toolbar or menu Button inside a form no longer submits it by accident.
- Run `kit:update plan|apply` for `workspaces` (re-pinned `sha256:8aa53c04…717f` → `sha256:9d17553b…b75f`): it now requires `tey/concierge` ^0.8.3. Run `composer update tey/concierge`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:4e8ab9c4…960a` → `sha256:519f5f64…afb0`): ActionBar, FloatingToolbar, WorkbenchStage, `index.ts` and `contract.ts` change; new `Action/actionFit.ts`, `Layout/toolbarAnchor.ts`, `Layout/toolbarAction.ts`, `Layout/Collapsible.vue` and `Workbench/workbenchStage.ts`.
- ActionBar's root is now a wrapper `div` carrying `data-action-bar` (around the Toolbar), and it no longer uses `sm:`/`max-sm:` classes to compact. A host stylesheet or test that targeted the old structure needs updating.

### Added

- **Forgot your password?** Kit pages for Fortify's password reset: `Auth/ForgotPassword` asks for the emailed link, and `Auth/ResetPassword` chooses the new password with the rules shown, under the same policy and breach message (`Fortify\ResetPassword`). The sign-in page links to it. With Concierge's reset-verifies rule, this is also how the first owner from `concierge:owners` sets a password and lands verified. `composer test:onboarding` covers it.
- **`<Collapsible>`** (`Kit/Layout/Collapsible.vue`, [docs/collapsible.md](docs/collapsible.md)): a disclosure section for inspector sections. Its toggle is a real `<button>` with `aria-expanded` and `aria-controls` naming the content region, the content is hidden while closed, it has `v-model:open` (else `defaultOpen`), a `title` prop or `summary` slot, an `actions` slot and `level`, and its transition is off under reduced motion. `CollapsibleProps` is in the contract.
- **FloatingToolbar client actions:** `items` (`ToolbarAction`: `key`, `label`, optional `icon`, `disabled`, `reason`; no URL) handed to the host's `onAction` (`@action`) by the toolbar's own handler (`runToolbarAction`). Server descriptors through a slotted ActionBar work as before.
- **FloatingToolbar `mode="stage"` and `mode="docked"`** inside a Workbench: `stage` floats over the bounding box of the selected stage elements (`wb.elements`, found by `data-workbench-element`), above them, flipping below and clamped inside the stage (`anchorToolbar`, `unionRect`); `docked` renders in the stage toolbar row. A `reposition()` is exposed.
- **The Workbench stage context:** WorkbenchStage provides `provideWorkbenchStage({ workbenchKey, viewport, toolbar })`; `useWorkbenchStage()` and `selectedRects()` are exported ([docs/workbench.md](docs/workbench.md#editing-toolbars-and-narrow-actions)).
- `fitActions`, `ToolbarAction`, `AnchorRect`, `ToolbarAnchor` and `WorkbenchStageContext` are exported from the barrel.
- **`<Surface>`:** a card-like container (padding, border, radius, `bg-card`/`text-card-foreground`) that provides its tone and shape to what's inside (`provideSurface`), so a kit Button or ActionBar on it blends in. Props: `tone` (default, inverse), `shape` (default, pill), `padding` (none, sm, md), `as`. Exported from the barrel; `SurfaceProps` is in the contract.
- **Button:**
  - `ghost` and `link` variants (the host button's own, so the host needs no new variant);
  - a `size` prop (default, sm, icon; the host button's sizes).
- **FormActions (and FormCard):** `dirtyLabel`, `errorsLabel` and `upToDateLabel` override the status lines, alongside `savedLabel`.

### Fixed

- **ActionBar didn't compact inside a Workbench at narrow widths.** It compacted on the viewport's `sm` breakpoint, so a narrow Workbench actions bar on a wide viewport (or a 390px one whose bar is narrower still) wrapped instead. It now measures its own width with a ResizeObserver and falls back to its main action and a ⌄ menu whenever its full layout doesn't fit (`fitActions`), for the pill and the default surfaces alike.
- `composer test:js` (`tests/js/workbench-editor-gaps.test.mjs`) covers each item: the pure fit, anchor and action logic, and the components' sources. There's no browser test.
- **A menu action chosen from ActionBar's ⌄ menu reached nothing.** `ActionMenuItems` emitted the action on a timer after the menu closed, but the menu had already unmounted it, so Vue dropped the emit: every non-dialog, non-document menu action (click or Enter) was lost. The rows now call an `onAction` handler captured when the row is chosen; ActionBar passes its own, which stays mounted. A host's `@action` on ActionMenuItems still works.
- Documented:
  - `warnOnLeave` false registers no leave guard at all (no router `before` listener, no `beforeunload`), so a host can own its own guard;
  - InputCheckbox requires an `id`.
- `composer test:js` (`tests/js/editor-primitives.test.mjs`) covers each item.

### Changed

- **Concierge verifies the invited address.** Concierge 0.8.3 verifies an unverified account whose email is the invited one inside the accept transaction, for member and tenant invitations, and shows such an invitation as ready to accept. The kit's own stopgap (`Workspaces\InvitedEmail`, v0.4.31) is removed. Opening an invitation no longer verifies on its own; accepting it does. `composer test:onboarding` still proves every kit invite path verifies.

## v0.4.31

### Upgrading

- Run `kit:update plan|apply` for `core` (`sha256:7c1ae722…8b43` → `sha256:4e8ab9c4…960a`), `shell` (`sha256:cf7ec863…ded3` → `sha256:8adcf2e2…2024`), `session-ui` (`sha256:30fb141d…8a8e` → `sha256:f2cc2fd1…3670`). It adds `Kit/Form/PasswordRules.vue`, `Kit/Feedback/PasskeyOffer.vue` and `passkeyOffer.ts`; `PasswordInput` gains `rules`; the invitation sign-up and change-password pages show the rules; and the shell layout renders the passkey offer.
- **New password policy:** `Password::defaults()` is now `Password::min(8)->uncompromised()` with no composition rules (NIST 800-63B), set by session-ui. Existing users aren't asked to change their password; the rule applies the next time a password is set. An app that sets its own `Password::defaults()` keeps it, and the rules under the field follow it.
- Passkeys after sign-up are optional: see `docs/onboarding.md` (Fortify's `passkeys` feature, laravel/passkeys' migration, and `PasskeyUser` on the User). Without them nothing changes.

### Added

- **One onboarding path, one email** (docs/onboarding.md): invitation email → invitation page → create the account → welcome → workspace, all on kit screens.
  - An invited sign-up sends exactly one email, the invitation. The account is created verified, so a host's `Registered` → `SendEmailVerificationNotification` sends nothing.
  - An unverified account whose email is the invited one is verified when it opens or accepts a member invitation, or accepts a workspace (tenant) invitation. This is a stopgap until Concierge does it upstream.
- **Password rules up front:** `<PasswordInput rules />` (`PasswordRules`) shows "At least 8 characters", ticked once met, and "Not found in a known data breach (checked when you submit)". The values come from `Password::defaults()`, shared as `kitPassword`.
- **A plain breach message:** "This password has appeared in a data breach, so it isn't safe. Pick another.", from `kit::validation.password.uncompromised`.
- **"Add a passkey for next time"** after an invited sign-up, on Fortify's passkey registration. It's dismissible and shown only where the app has passkeys.
- `composer test:onboarding` and `composer test:onboarding-js`, both in CI.

## v0.4.30

### Upgrading

- Run `kit:update plan|apply` for `core`: it adds `Kit/Feedback/StageNotice.vue` and `stageNotice.ts`.

### Added

- **`<StageNotice>`** (docs/stage-notice.md): an announcement laid over a stage or slide, for example "Live demo: <label>" on a projector.
  - It's positioned inside its container, sized in container-query units, and `pointer-events: none`; its content is the default slot.
  - It has Alert's tones (info, success, warning, danger; solid or pale) and a top, bottom or corner placement.
  - Its enter and leave transition only moves when motion is allowed.
  - It's exported from the kit barrel, with `StageNoticeProps` in the contract. `composer test:js` covers it.
## v0.4.29

### Upgrading

- Run `kit:update plan|apply` for `core`. `resources/css/kit.css` gains the default colour tokens, and core declares a `theme` host step. A host whose stylesheet already imports kit.css, or defines the tokens, sees no change: its theme overrides the defaults.

### Added

- **Default colour tokens in kit.css.** The kit's components are coloured with shadcn-vue's semantic tokens, so a host theme without them (a host without the Laravel starter kit's shadcn layer) rendered them uncoloured. kit.css now defines all 18 at the lowest precedence:
  - an `@theme default inline` mapping (`--color-primary: var(--primary)` and so on), which any host `@theme` replaces;
  - the starter kit's neutral light and dark values on `:where(:root)` and `:where(.dark)`, which any host `:root` or `.dark` overrides.
- **A `theme` host step (core: `resources/css/app.css`).** `kit:add` and `kit:update` plans check that the host's stylesheet imports kit.css or defines every token. If not, attention names the missing tokens and the import; it never blocks.
- `composer test:plan-steps` covers the step on a host fixture without the tokens, with kit.css, with a partial theme and with a full one. It also checks that kit.css maps every token with light and dark values, and that every semantic token a kit stub uses has a default.

## v0.4.28

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:8050a73e…65d3` → `sha256:f902dba8…a247`): it adds `Kit/Workbench/WorkbenchCollapsedBar.vue`, and `Workbench.vue` and `WorkbenchStage.vue` render it.

### Fixed

- **A collapsed Workbench pane keeps a way back.** Collapsing the footer (or the navigator or inspector) also hid its header, which held the only Expand toggle, leaving just a hairline resize handle. A collapsed pane now keeps a slim 2rem bar with its label and an Expand button. The button is in the tab order, with `aria-expanded="false"` and `aria-controls` naming the pane's region. It sits beside a side pane at xl and lg, or under the stage for the footer. Expanding goes through the panel's own `expand()`, which restores its remembered size, and ⌘[ / ⌘] work as before. `composer test:workbench-js` checks each region's bar, its wiring and its aria.

## v0.4.27

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:3aed4d27…db5f` → `sha256:8050a73e…65d3`): the `Kit/Workbench/` files take the four fixes below, and `@kit/ui` exports `InspectorScope`.
- An app that reset the Workbench selection itself on each visit, or added its own document Esc listener, can drop that workaround.

### Fixed

- **Esc after a stage click.** A click on a plain stage element left focus on `<body>`, so the Workbench never saw the Esc. The document listener now handles Esc when focus is on `<body>` or inside the Workbench. It never acts from a field that takes typing, nor while another dialog or a menu is open.
- **Item and element inspectors no longer collide.** `defineInspector(type, component, { for: 'elements' | 'items' | 'any' })`: element selections consider only `elements` and `any` inspectors, item selections only `items` and `any`, so a slide layout and a block type of the same name each get their own. `any` stays the default.
- **No duplicate save status.** `<Workbench save-status="none">` renders no kit status region or conflict alert, for an app whose own form bar in `#actions` announces the save state. `wb.saveState` stays readable, and `kit` stays the default.
- **The selection doesn't outlive an Inertia visit.** The per-key store outlives a visit, so arriving at another item by back or a link kept stale elements. Each Workbench mount, and every change of the server's `current` prop, now selects `current` again and clears the stage elements. Pane prefs and zoom stay per key.
- `composer test:workbench-js` covers each fix.

## v0.4.26

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:38d62d72…7573` → `sha256:3aed4d27…db5f`): the `Kit/Workbench/` files gain stage element selection, and `@kit/ui` exports `WorkbenchElement`.

### Added

- **Workbench stage elements** (docs/workbench.md): the stage can select elements inside the current item (blocks on a slide), and they pick the inspector, so the inspector follows the stage's selection and not only the navigator's.
  - `wb.selectElement(id, type, mode | event)`, with ⌘, Ctrl or Shift to multi-select; `wb.selectElements([{ id, type }])`; `wb.clearElements()`; and a read-only `wb.elements`.
  - Elements aren't pruned by `items`, and they're cleared when the current item changes.
  - **Inspector resolution:** selected elements decide first, by element type (one type → that inspector; mixed types → a common inspector or `@fallback`). Otherwise the item selection decides, as before, and with neither it's `@document`.
  - **Inspector props:** inspectors now receive `{ element, elements, ids, items, types, current }`. The existing props are unchanged.
  - **Esc** closes the drawer, otherwise clears the elements, otherwise clears the item selection (not while typing). F6 is unchanged.
  - `composer test:workbench-js` covers it.

## v0.4.25

### Fixed

- **No Mod warning on every boot under another layout.** Under a layout other than `laravel`, Mod logged `Scaffold [tey/kit] makes a starter-policy, which the … layout doesn't have, so mod:tey/kit is disabled.` each time it resolved commands, because the kit registered its legacy v0.1 `kit:foundation` scaffold anyway. The kit now registers that scaffold only under `laravel`, or when `kit.scaffold_features` is configured. Otherwise a stand-in `kit:foundation` refuses with the same message as before and writes nothing. Hosts don't need a fake `starter-policy` generator. No stub changed, so there is nothing to re-pin. `composer test:layout` now checks that boot and command resolution log nothing under both layouts.

## v0.4.24

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:30386b0a…ee06` → `sha256:38d62d72…7573`) and `notes` (`sha256:640b093e…53d2` → `sha256:1d5fe0eb…9370`).
  - core: `Kit/Form/InputSelect.vue` and `inputSelect.ts` keep only the custom properties of a caller's `style`, and `InputSelectProps` gains `popoverClass`.
  - core adds `Kit/utils.ts` (the kit's own `cn`, which BlockStack and Toolbar import instead of `@/lib/utils`) and `Kit/types.ts`.
  - `Kit/types.ts` holds the two Inertia types the kit uses (`Method`, and a minimal `InfiniteScrollRef`). splitState, useScrollList and the Workbench import them from there instead of `@inertiajs/core`. The notes composer's form transform is generic over Inertia's own form-data type, so it names none.
- **The host needs the UI primitives core imports.** `kit:add`, `kit:update plan` and `kit:adopt plan` now block without them and print the command, for example `npx shadcn-vue@latest add button checkbox dropdown-menu input label sheet spinner`. An app built from the Laravel starter kit already has them.
- core's npm requirements now name `@inertiajs/vue3` (not `@inertiajs/core`), `clsx` and `tailwind-merge`. If your `package.json` declares `@inertiajs/core` beside `@inertiajs/vue3`, remove it (the plan points it out).
- A caller that styled an InputSelect with ordinary properties through `style` should move them to `popoverClass` (for the list) or `class` (for the field). `--*` custom properties still pass through.

### Changed

- **InputSelect forwards only custom properties.** It forwarded a caller's whole `style` to its popover wrapper, the one inline-style exemption left after v0.4.23. It now keeps only the `--*` custom properties, from a string, an object or an array (the new `customProperties()` in `inputSelect.ts`), and sets them on the field's wrapper and on the portaled list, so a caller can theme the list through variables. The new `popoverClass` prop styles the list otherwise. The style-conventions check in `composer test:stubs` now has no exemptions: a caller's style may only reach a stub through `customProperties(...)`. `composer test:input-select` checks that a caller's `--x` is kept and `color: red` is dropped.

### Fixed

- **`kit:add core` no longer previews clean on a host without shadcn-vue's primitives.** core imports `button`, `checkbox`, `dropdown-menu`, `input`, `label`, `sheet` and `spinner/Spinner.vue` from `@/components/ui`, but declared only `spinner`, and nothing checked `host_ui`. So such a host got a clean preview, then 17 vue-tsc errors and a broken build.
  - core now declares every primitive, and the library check fails when a stub imports one its feature doesn't declare.
  - Each missing primitive (`<name>/index.ts`, or the declared file) is a blocking `host_ui` step with the exact command, in kit:add and in update and adopt plans (from the primitives the kit's files import).
  - Each primitive's outcome is bound into the plan identity and reported under `host_ui`.
- **No dependency on the host's `@/lib/utils`.** The kit ships its own `cn` (clsx, then tailwind-merge) at `resources/js/components/Kit/utils.ts`.
- **No second copy of `@inertiajs/core`.** `@inertiajs/vue3` pins `@inertiajs/core` exactly, so a host told to add `@inertiajs/core ^3.0` could install a newer copy beside vue3's own, which broke PageProps typing. core now requires `@inertiajs/vue3 ^3.0` instead, and no stub imports `@inertiajs/core`: it would be an undeclared import, which standards' js-imports rule flags and pnpm can't resolve. A declared `@inertiajs/core` at another version than vue3 pins, or two installed copies, raises attention with the fix (an exact match, like the starter's, is one copy and passes). The library check fails on any `@inertiajs/core` import in a stub.
- `composer test:plan-steps` and `composer test:library` cover all of this.

## v0.4.23

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:0e1405b6…593b` → `sha256:30386b0a…ee06`), `shell` (`sha256:03039592…65da` → `sha256:cf7ec863…ded3`) and `session-ui` (`sha256:76d9be3d…be1f` → `sha256:30fb141d…a8e`): `Kit/Feedback/Toaster.vue`, `Kit/Form/FormActions.vue`, `Workspace/Layout.vue` and `Workspace/Dashboard/Page.vue` set their geometry and colour through custom properties.

### Changed

- **No inline property styles in kit stubs.** The anchored toast's position, the upload progress bar's width, the dashboard meter's width and the environment edge's colour were set with inline `style` properties. Each binding now sets only a custom property (`--kit-toast-top`, `--kit-toast-left`, `--kit-form-progress`, `--kit-meter`, `--kit-environment`), and a utility class reads it (`top-(--kit-toast-top)`, `w-(--kit-form-progress)`, `bg-(--kit-environment)`). What renders is the same. `composer test:stubs` now also fails on any `style=` or `:style` in a kit stub that sets anything but `--*` custom properties. InputSelect's forwarding of the caller's own `style` is the one exception.

## v0.4.22

### Fixed

- **The kit boots under any tey/mod layout.** `KitServiceProvider::boot()` threw `Blueprint requires the unmodified laravel layout.` on every request when `mod.layout` wasn't `laravel`, so a host with its own layout (a layout extending `modules`, say) couldn't boot with the kit at all. Kit-owned files always use their canonical paths, never the host's layout, so the boot guard is gone. Only the legacy v0.1 `kit:foundation` scaffold still refuses another layout, when it is run, with a message that says kit features work under any layout. No stub changed, so there is nothing to re-pin. The new `composer test:layout` runs `kit:install`, `kit:add core`, `kit:status`, `kit:update plan` and `kit:adopt plan` under a layout extending `modules` (with an `app/UI` mount), and checks the files and the lineage identity match a `laravel` host. `docs/catalog.md` says which paths kit files use.

## v0.4.21

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:c3a4a8af…d8dd` → `sha256:0e1405b6…593b`): `Kit/Form/TimezoneSelect.vue` is now an InputSelect, and `TimezoneSelectProps` gains `autofocus`.
- Run `kit:update plan|apply` for `settings` (re-pinned `sha256:d3afc44b…a193` → `sha256:fb83eb1d…f1e`): it adds the migration `0001_01_02_000402_adopt_display_name_as_workspace_name.php`.
- An app that overrode `Settings\Operations\WorkspaceProfile` to read and rename the workspace's own name can drop that override and its binding: the kit now does the same.
- A workspace's General display name now becomes its workspace name: `php artisan migrate` runs the settings migration `adopt_display_name_as_workspace_name`, which copies a non-empty `display_name` that differs from `workspaces.name` into it, once, and moves `settings_version`. Empty or equal values are left alone, and a rerun does nothing.

### Fixed

- **One workspace name.** General read and saved a `display_name` setting, while the shell and its workspace switcher showed the workspace's own name, so the two could disagree. General now reads the workspace's name and renames the workspace inside the same version-checked transaction (row lock, `settings_version`, intent replay, `stale_settings`). `display_name` stays as a compatibility mirror, and the settings cache is republished after a rollback. The save redirects, and the `shell` share is computed per request, so the next page's switcher shows the new name. `composer test:permissions-http` covers the rename, a newer rename surviving a replay, a stale rename, and a stale mirror.
- **Searchable timezone picker.** `TimezoneSelect` was on the deprecated Reka `Select`, with no search over about 400 zones. It now renders the searchable InputSelect, with the current zone first, then the browser's zone, then the rest A to Z. It is used on General and the two welcome timezone steps. `composer test:stubs` now also runs a conventions check that fails on a native `<select>` or a timezone on the deprecated `Select` in any kit stub.

## v0.4.20

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:fbca3794…c3f4` → `sha256:c3a4a8af…d8dd`): it adds `Kit/Workbench/` and the Toaster's `kit:toast:show` listener. session-ui and mcp change in `src` only.

### Added

- **Workbench** (docs/workbench.md): `<Workbench workbench-key>`, the kit's multi-pane editing surface for a sequence of items with a live preview.
  - **Regions:** `navigator`, `stage` (with an optional sticky `toolbar`), an optional resizable and collapsible `footer`, `inspector`, and an actions bar on StickyBar.
  - **Layout per width:** three resizable, collapsible columns from xl; navigator and stage with an inspector drawer at lg; tabs below lg (the footer's tab only when provided).
  - **Remembered layout:** pane sizes, collapsed panes, zoom and the phone tab, per person and key.
  - **`useWorkbench(key)`:** single and multi-select with the current item and next/prev; dirty and save state; `onRecalculate` with a stale guard; `defineInspector` (selection-driven: `@document`, per type, a common inspector for mixed types, `@fallback`); optimistic reorder through the app's action with a version token, rolled back with a toast on a conflict; stage zoom and fit.
  - **Keyboard:** ↑/↓ and j/k, Alt+↑/↓, ⌘[ / ⌘], `/`, Esc, F6, ⌘+ / ⌘- / ⌘0, with ARIA landmarks.
  - **Server:**
    - `WorkbenchViewModel`, with `WorkbenchItem`, `WorkbenchInspector` and `WorkbenchLayout` data.
    - session-ui's `PUT /me/workbenches/{key}`: versioned, with 409 `stale_write`, kept in `kit_user_state`, so no migration.
    - mcp's optional, read-only `WorkbenchReadTool` base.
  - **Checks:** `composer test:workbench` and `composer test:workbench-js`, both in CI.
- **`kit:toast:show`:** the Toaster renders a toast sent by a kit component that acts without a page visit. Pages still never toast.

## v0.4.19

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned `sha256:d35bf35a…1248` → `sha256:fbca3794…c3f4`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` deduplicate by toast id.

### Fixed

- **A toast on a full page load shows once.** On a non-Inertia redirect that carries a flash (an OAuth return, say), the Toaster showed the flash on mount and again from the router's first `flash` event, because that event carries an equal but different object and the check was identity-only. Each flashed toast now carries a unique `id` (a ULID, kept across its own re-flashes), and the Toaster shows each id once, remembering a few recent ones. It doesn't compare content, so two identical saves in a row still both toast. A toast without an id falls back to the identity check. `composer test:toast` and `composer test:js` cover it.

## v0.4.18

### Upgrading

- **Warning and error toasts now stay until dismissed.** They used to close after 4 seconds (8 with an action). To keep the old timing, set `kit.toasts.durations` to `['success' => 4000, 'info' => 4000, 'warning' => 4000, 'error' => 4000]` in `config/kit.php`.
- Run `kit:update plan|apply` for `core` (re-pinned `sha256:f19d1c3a…9e8f` → `sha256:d35bf35a…1248`): `Kit/Feedback/Toaster.vue` and `toastPlacement.ts` gain the duration and away handling.

### Added

- **Toast durations per type:** `kit.toasts.durations` maps each type to milliseconds, or `null` to stay until dismissed. The server resolves it at flash time and sends it as `duration`. Defaults: success and info 4000, warning and error `null`. A timed toast with an action stays at least 8000. An unusable value falls back to the type's default.
- **Away rule:** a timed toast shown while the tab is hidden or the window is unfocused starts its timer only once the window is visible and focused again. Persistent toasts are unaffected.
- `composer test:toast` covers config resolution per type and the action rule; `composer test:js` covers the away logic.

## v0.4.17

### Fixed

- **Saving workspace settings confirms with a toast.** `SettingsController::update` flashed `status`, which nothing renders, so saving General gave no feedback. It now flashes `Toast::success('Workspace settings saved.')->from('save')`, which pulses the Save button. JSON responses are unchanged. `composer test:library` covers it.
- **Revoking an invitation confirms with a toast.** `PeopleInvitationController::destroy` redirected back with no feedback. It now flashes a plain `Toast::success('Invitation revoked.')`, with no `from`, since the row it came from is gone. JSON responses are unchanged.

## v0.4.16

### Fixed

- **Expected refusals are no longer reported.** Core already answered a `RuntimeException` whose message is a registered error code with that code's status, but the handler still reported it, so a refusal such as `bench_unavailable` (404) showed in error trackers as unhandled. A code registered with a status below 500 is now never reported. An unregistered message, or a code registered as 5xx, still is. `composer test:host-boot` covers it, and CI now runs that check.

## v0.4.15

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:73daee1d…3df9` → `sha256:03039592…65da`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `drawer-nav` slot.

### Added

- **Shell `drawer-nav` slot:** `<Layout>` forwards it into the phone drawer, which renders it in place of the kit's pages (props `navigation` and `label`, as for `nav`). An app that uses `nav` can keep a page list in the drawer by passing `drawer-nav` too. Without it, nothing changes: the kit's pages, or none when `nav` is used. `composer test:shell` checks both cases.
- **Workspace-owned model concerns (workspaces).**
  - `Concerns\BelongsToWorkspace` scopes every query to the current workspace, and fails closed with `workspace_required` outside one. A new row takes the current workspace. Saving, moving or deleting another workspace's row throws `workspace_mismatch`.
  - `Concerns\GuardsStaleWrites` adds `updateIfCurrent($expected, $attributes)`: one conditional update on `lock_version` that bumps it, and throws `stale_write` on an old version without writing anything.
  - `stale_write` is registered as a 409 and as an MCP refusal code.
  - See `docs/models.md`. Covered by `composer test:workspace-models`.
  - No stub changed, so there is nothing to re-pin.

## v0.4.14

### Upgrading

- Run `kit:update plan|apply` for `shell` (re-pinned `sha256:e39f0c88…aa00` → `sha256:73daee1d…3df9`): `Workspace/Layout.vue` and `Partials/ShellDrawer.vue` gain the `nav` slot.

### Added

- **Shell `nav` slot:** `<Layout>` takes a `nav` slot (props `navigation` and `label`) that replaces PrimaryNav at every width, rendered once, and the drawer then lists no pages. An app with its own module switcher can stop keeping its own `Layout.vue` (then `kit:update claim` it back). This is not the existing `bar` slot, which holds compact controls beside the nav. `composer test:shell` checks the stub.

### Fixed

- **`kit:update keep` reports a pending upstream change on the first plan.** Keep recorded the kit's current bytes as already seen, so a change that landed before the keep never showed: a `Layout.vue` kept since adoption planned `held: []` after the v0.4.10 search mount. Keep now records what the host last saw: the release a kept-since-adoption edit was made against, or an owned file's installed bytes. The next plan lists the change under `held` (with the stub to diff and the claim command), once. `composer test:adopt-in-place` covers it.
## v0.4.13

### Fixed

- **Global search ignores case on PostgreSQL.** The people provider matched with `like`, which PostgreSQL treats as case-sensitive, so "view" missed "Viewer" there (SQLite hid it). The new `SearchQuery::matches($builder, $columns)` ORs the columns through Laravel's case-insensitive `whereLike` (`ilike` on PostgreSQL), and `PeopleSearch` uses it. `docs/search.md` now tells providers to use it. No stub changed, so there is nothing to re-pin.

## v0.4.12

### Added

- **`kit:update keep <path>` and `kit:update claim <path>`:** a file the app keeps no longer blocks the lineage's updates. Before, a file kept since `kit:adopt` (an app's own `Layout.vue`) had no original in the lineage, so every later re-pin that changed it was a blocked conflict.
  - `keep` records the file as host-kept (`held` in the lineage record), releasing it if the lineage owned it. Later updates skip it and still apply the rest (new partials are written). Each plan lists, under `held`, an attention line naming what the kit changed upstream and the stub to diff against.
  - `claim` records the file's current bytes as the lineage's original, so later updates replace it while it's unchanged and conflict once it's edited.
  - Both are a reviewed plan, then an apply that writes no file.
  - `--lineage` is optional when only one adopted feature delivers the path.
  - `composer test:adopt-in-place` covers a kept file, keeping an owned edit, a re-pin that adds a partial, the attention, and claiming back. The lineage-record format is in `docs/catalog.md`.

## v0.4.11

### Upgrading

- After upgrading tey/mod, run `php artisan kit:update refresh-executor` once. An app with an explicit `kit.features` list gets each newly adopted feature added to it.

### Fixed

- **`kit:adopt` switches the feature on, as `kit:add` now does:** with an explicit `kit.features` list in `config/kit.php` (which overrides the lineages), an adopted feature stayed off. Both commands now add each adopted feature to that list, in canonical order. They edit only a literal list of quoted ids, verify by reading the file back, and undo otherwise; a computed list is left alone and named as attention. With `kit.features` null, nothing changes: the lineage switches the feature on. `composer test:install` and `test:adopt-in-place` cover it.
- **A stale `config/kit-executor.json` is named, not opaque:** after a tey/mod upgrade, every plan failed with "Executor provenance input must match the currently verified runtime". It now stops once with `executor_stale`, naming the file, the recorded and the running tey/mod, and the fix. The new `php artisan kit:update refresh-executor` rewrites only that file, and only when it holds a recorded runtime. `composer test:install` covers it.

## v0.4.10

### Upgrading

- Run `kit:update plan|apply` for `notes` (re-pinned `sha256:f174d87e…167a` → `sha256:640b093e…53d2`): the composer and the card editor are now Inertia `<Form>`s ending in `FormActions`.
- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (re-pinned). They add:
  - core: `Kit/Layout/StickyBar.vue`, `Kit/Layout/PageEnd.vue`, `Kit/Search/SearchBox.vue` and `Kit/Search/search.ts`, and update `index.ts` and `contract.ts`;
  - shell: `Workspace/Partials/GlobalSearch.vue`, and update `Workspace/Layout.vue`;
  - session-ui: `Workspace/Search/Page.vue`.
- Regenerate TypeScript types afterwards: `ShellData` gains `search`, and there are new `SearchViewModel` and `SearchResult` types.
- Global search is off until you switch on `workspace.search` in `kit.capabilities`. Until then nothing changes for anyone.

### Added

- **Global search (session-ui, opt-in):**
  - `Registry::searchProvider($key, $class, $ability, $label, $order)` registers a provider. Each one returns typed `SearchResult`s for the query in the current workspace, and is asked only for a viewer who holds its ability (through `Access`).
  - A provider that throws, or returns anything else, is skipped and logged without its message or the term. The others still answer, marked `partial`.
  - Surfaces: a results page at `/@{workspace}/search`, and a header field (`GlobalSearch`, ⌘K, Ctrl+K or `/`) with a live palette, in a sheet below `lg`.
  - A read-only MCP `workspace_search` tool returns the same results for the same person, with the new `search.read` scope.
  - Each person has a shared limit, `kit.search.per_minute` (30 by default): a 429 on the page and palette, `rate_limited` over MCP.
  - The kit ships one provider, `kit.people`: the workspace's members.
  - See `docs/search.md`. Covered by `composer test:search` and `test:search-js`.
- **StickyBar (core):** a full-width bar pinned to its container's foot until the end of the content. While pinned it registers with `useStickyBars`, so the Toaster and BulkBar stay clear of it. It is the page-controls sibling of FloatingToolbar, which stays the place for page actions.
- **PageEnd (core):** the end of a long page: Back to top, an optional jump list of sections, and `pagination` and `actions` slots. It shows only past one and a half screens, and hides under a pinned form bar.
### Fixed

- **Notes forms follow the page-form standard:** the composer and the card's editor were `useForm` with a raw `<form>`, so a host's form standard (every page form is Inertia's `<Form>` ending in `FormCard` or `FormActions`) failed. Both are now uncontrolled `<Form>`s with named controls and `FormActions`. The composer's files and intent key join the request through the form's `transform`, so a fresh intent key after a save doesn't read as an unsaved edit, and the reset composer is clean again (`set-defaults-on-success`). Proven in the starter with its form tests and a browser spec.

## v0.4.9

### Upgrading

- `notes` is a new opt-in feature: `php artisan kit:add notes`, then `migrate` and `kit:permissions:sync`. It creates `kit_notes` and `kit_note_intents` and never touches a `notes` or `app_notes` table you already have. To copy those, run `php artisan kit:notes:import --from=<table>` (see `docs/notes.md`).
- Re-pinned: base `sha256:9e6ee91d…cc75cf` → `sha256:c513b68d…f079` (`blueprint.json` names the notes migration block). New pin: `notes` `sha256:f174d87e…167a`. No other feature's pin moves.

### Added

- **Notes (`notes`):**
  - Workspace notes with an author, a colour, a pin, an optional subject and medialibrary attachments, and a stale-write guard and intent replay.
  - Three abilities: `notes.read`, `notes.write`, and `notes.moderate` for changing other people's notes. Moderation is its own permission, never implied by writing; the owner always has it.
  - One principal-first operation (`WorkspaceNotes`), used by the wall page (composer, colour filter, a ScrollList of cards with edit, pin and delete with a confirm, and a save toast), the `notes.recent` dashboard widget, and four MCP capability tools (`notes_list`, `notes_read`, `notes_write`, `notes_delete`).
  - Extension points: `Registry::noteSubject()` (notes about an app's records; visibility follows the record) and `Registry::noteComposer()` (app-side composer options and a record search).
  - `kit:notes:import --from=<table>` copies a legacy notes table idempotently, reports what it skipped, and never changes the source.
  - `composer test:notes` covers it. See `docs/notes.md`.
- **`legacy_table` host step:** a feature can name a table an earlier release or the app kept its data in. When the table exists, `kit:add` and `kit:adopt` show it as attention that doesn't block, with the step's `why` (the notes import). `kit:adopt <feature> plan` now prints a feature's unmet host steps as attention too.

## v0.4.8

### Upgrading

- Run `kit:update plan|apply` for `session-ui` (re-pinned `sha256:59b0fc72…74c1` → `sha256:20c52d96…69f8`): it adds `resources/js/pages/Workspace/Dashboard/Partials/` and updates the dashboard page. Regenerate TypeScript types afterwards.
- The dashboard now shows the kit's three widgets (You, Recent activity, People). To keep fewer, set `kit.dashboard.widgets` in `config/kit.php` (for example `['you']`, or `[]` for none).
- Run `kit:update plan|apply` for `core` (re-pinned): it adds `Kit/Table/IndexToolbar.vue`, `Kit/Table/BulkBar.vue` and `Kit/Table/selection.ts`, and changes `DataTable.vue`, `index.ts` and `contract.ts`.
- **An app that overrode the dashboard controller to inject its own widgets** (noted after the release): from this version `DashboardController` renders the kit's Registry widgets itself and returns `toResponse()`, so an override that added its own widget props, or wrapped the response, no longer works. Register the app's widgets with `Registry::widget()` instead, or set `kit.dashboard.widgets` to `[]` to drop the kit's own. See [docs/dashboard.md](docs/dashboard.md#upgrading-an-app-with-its-own-dashboard-widgets).

### Added

- **Dashboard widgets (session-ui):** `Registry::widget($key, $class, $order, $ability)` adds a card to the dashboard, and `Registry::widgets()` lists them in order. A widget extends `Dashboard\Widget`, a typed ViewModel with a frame (`title`, `component`, `span`, `state`, `empty`, `error`, `actions`) and its own props. Each widget is gated by its ability through `Access` (so its capability, entitlement and permission apply), and one that throws becomes an error card while the dashboard still answers 200. `DashboardViewModel` gains `widgets`. The kit ships `kit.you`, `kit.activity` (behind `members.manage`) and `kit.people` (a ScrollList). The page renders them in a responsive, span-aware grid (`WidgetGrid`, `WidgetCard`), and an app's widget body lives in `resources/js/widgets/`. With mcp adopted, the read-only `workspace_dashboard` tool returns the same frames, and session-ui declares the narrow token scope `dashboard.read` (now grantable to a connection). See `docs/dashboard.md`. `composer test:dashboard` covers it; there is no browser test.
- **IndexToolbar:** the DataTable's toolbar is now its own component: search plus one choice per filter the server declares with a label and options (`TableQuery::filter($name, $apply, $label, $options)`, sent as TableData `choices`). A value outside a filter's options is a 400 (`InvalidFilterValue`), like an undeclared filter.
- **Bulk actions and selection:** `TableQuery::bulk(Action)` makes the rows selectable (a checkbox column and a page checkbox, `selection.ts`). While rows are selected, `BulkBar` floats at the viewport's foot (a FloatingToolbar, so it registers with useStickyBars) with the count, the actions and Clear; an action posts `ids[]`, after its `confirm`. The selection survives pages, sorts and filters until it is cleared or acted on.
- **`TableSelection::from($request, $scope)`:** resolves a bulk action's `ids[]` inside the handler's own scope and fails closed: no ids, more than 100, a malformed id or any id outside the scope refuses the whole request (422).

## v0.4.7

### Upgrading

- Run `kit:update plan|apply` for `core` (re-pinned).

### Added

- **`kit:toast` event:** the Toaster dispatches a `kit:toast` window event with the toast as `detail` whenever it shows one, so an app can keep its own history (a notification bell) of server-flashed toasts.

### Fixed

- **Form saves are a toast trigger:** `FormActions` renders `data-action` on its submit button (the submit descriptor's `key`, else `save`), so `->from('save')` pulses the Save button instead of falling back to a plain toast.

## v0.4.6

### Changed

- **Saves pulse by default:** a `success` toast without an explicit `style()` is now `pulse` (a shell toast plus a brief ring on the triggering button) instead of `fly-out`. Fly-out stays available with `->style('fly-out')`. Server-side only; no stub changed, so no `kit:update` is needed.

## v0.4.5

### Upgrading

- Run `kit:update plan|apply` for `core`, `shell` and `session-ui` (all three are re-pinned), then `npm install`: core now requires `vue-sonner` and declares `@inertiajs/core`.
- If your app renders its own toaster from the `toast` flash, remove it: the kit shell now mounts one. A FocusShell or custom layout mounts `<Toaster />` itself.

### Added

- **Headless toasts:** `Tey\Kit\Features\Core\Toast` and the `Toastable` concern flash Inertia's `toast`; the controller decides, the frontend only renders. One non-destructive action at most (8s with an action, 4s without). See `docs/toasts.md` for when a controller toasts.
- **Anchored toasts:** `->from('<action id>')` links a toast to the descriptor button that triggered it, with `->style('fly-out'|'anchored'|'pulse')`; fly-out is the default for saves. Falls back to a plain toast when the trigger is gone; `prefers-reduced-motion` gets no flight.
- **Shell-level placement:** `kit.toasts.position` is `auto`, `top-center`, `top-right`, `bottom-right` or `bottom-center`. `auto` picks bottom-center on phones, top-center when sticky bars and right-aligned header actions are present, top-right with sticky bars only, else bottom-right.
- **Sticky bar registry:** `useStickyBars()`; `FloatingToolbar` registers, and toasts keep clear of registered bars.

### Fixed

- The People settings page reads role labels from the server-provided cell value, so it passes tey/standards' descriptors rule.

### Known limitations

- The fly-out and anchored animations are covered by unit tests only; the kit has no browser harness yet.

## v0.4.4

### Fixed

- **`WorkspaceUrl` resolves an id over MCP:** `WorkspaceUrl::slug()` (and so `to()`, `full()`, `shareable()`) read only the web guard, so a bearer-authenticated MCP call (Passport) saw no one and refused a member's workspace id as `workspace_not_found`. It now falls back to the request's authenticated user, still scoped to that person's active memberships; a non-member and a guest are still refused. Web and console behaviour is unchanged.

## v0.4.3

### Added

- **Per-call destructive:** `CapabilityTool::destructive(array $validated): bool` is overridable and defaults to the tool-level flag, and `ConsentGate::confirm()` takes it, so one tool can confirm only some calls. Keep the tool-level flag for the MCP `destructiveHint`.
- **`notFoundCode()`:** an overridable `CapabilityTool::notFoundCode(): string` (default `not_found`) names the code an uncoded `ModelNotFoundException` is refused with, for example `workspace_not_found`. `ConsentGate::refusal()` takes it as its second argument.

### Fixed

- **`operation_failed` is reported:** an unexpected exception that `ConsentGate::refusal()` maps to `operation_failed` is now passed to `report()`, so the host's exception handler logs it. Coded refusals, validation and not-found are not reported.
- **Control arguments no longer reach `call()` (behaviour fix):** `intent_key` and `confirm_token` are stripped from the arguments passed to `call()`, `summary()` and `ToolCalled`; they stay available to the gate. A host that handed arguments whole to an operation no longer needs to strip them.

## v0.4.2

### Added

- **Host MCP refusal codes:** `Registry::mcpRefusalCodes([...])` registers a host's domain codes (`stale_write`, `report_blocked`), which a `CapabilityTool` refusal now passes through as they are, alongside `ConsentGate::CODES`. Unregistered codes are still `operation_failed`.
- **Validation detail:** `validation_failed` carries the field errors as structured content, `{error: 'validation_failed', errors: {field: [messages]}}`. The error text is still the bare code; `ConsentGate::refusal()` returns an array that may carry `errors`.
- **`Tey\Kit\Features\Mcp\Events\ToolCalled`:** dispatched once after a `CapabilityTool`'s `call()` succeeds, with the tool name, class, principal, workspace id, validated arguments and result, inside the intent's transaction. Not dispatched on a refusal, `confirmation_required` or an intent replay.

### Fixed

- **Coded refusals win over `not_found`:** a `RuntimeException` whose message is a known code, including a `ModelNotFoundException` subclass carrying `workspace_not_found`, passes through instead of becoming `not_found`. v0.4.0 checked `ModelNotFoundException` first; v0.4.1 already reordered it, and a regression check now pins the order.

## v0.4.1

### Upgrading

- Run `kit:update` for `core` (the changed `Layout/splitState.ts`) and `mcp`. If your app commits a route snapshot, update it: the `oauth/authorize` routes gain the `RefuseCookieSessions` middleware.
- Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment.

### Fixed

- **MCP consent on cookie sessions (#108):** Passport keeps the consent request in the session, which outgrows a cookie, so consent failed silently on `SESSION_DRIVER=cookie`. The consent routes now refuse there with a 503 page naming the fix and an error log line. A new `config` host step kind (`{key, not, why}`, attention only) lets the mcp plan flag a cookie session driver. Set `SESSION_DRIVER=database` (or `redis`) explicitly in every environment. Re-pins `mcp`.
- **`kit:adopt <feature> plan` exit code (#114):** a blocked plan exits non-zero with its output unchanged. This shipped in v0.4.0; v0.4.1 adds no change beyond the check that covers it.
- **SplitPane storage import (#117):** `splitState` imports Inertia's `http` statically, so Vite no longer warns INEFFECTIVE_DYNAMIC_IMPORT on every build. The localStorage fallback is unchanged. Re-pins `core`.

## v0.4.0

### Upgrading

- **Behaviour change for hand-written gated tools:** a tool using `GatedByCapability` is now refused by its `ABILITY` before its operation runs. When the person's roles do not allow the `ABILITY`, the call returns `permission_denied` and `handle()` is not reached, so an operation that refused the same call with its own error code no longer gets to. This is intended. A host that relied on its own code for that case should check for `permission_denied` instead.
- **Regenerate TypeScript types** after updating with the host's typescript-transformer command, so the kit's changed view models and props reach the frontend.
- **Migration needed for `mcp`:** run `php artisan kit:update plan --lineage=mcp`, then `apply`, then `php artisan migrate`. This adds `database/migrations/kit/mcp/0001_01_02_000601_create_kit_mcp_intents_table.php` (the `kit_mcp_intents` table). Until it is migrated, a tool call carrying `intent_key` fails as `operation_failed`. Calls without one are unaffected. Then schedule `model:prune` for `Tey\Kit\Features\Mcp\Models\McpIntent` daily, or expired intents are never deleted (the snippet is in `docs/mcp-tools.md`). Set `kit.mcp.intent_ttl` in `config/kit.php` to change the 24-hour lifetime.
- **Connections (#86) need no migration.** Existing connections keep the narrow scopes they were approved with and behave as before; nothing re-prompts. To give an assistant acts-as-me, the person approves `mcp:act` for the workspace; an assistant whose token asked for narrow scopes must reconnect without them first. The Passport consent screen's wording for `mcp:use` changes to "Act as you in the workspaces you approve…". An app whose own MCP tools caught `RuntimeException` and called `Context::principal()` keeps working; moving them to `ConsentGate` adds the confirmation and the refusal mapping. Intent replay for create/copy tools is not in this release.

- **Apps installed before feature lineages** adopt each feature first with `kit:adopt <feature> plan|apply`, in the order `kit:update` prints, then update. `kit:adopt` plans also run the existing-table check and print their apply command.
- **The `mcp:use` scope label changed** to "Act as you in the workspaces you approve…", and `mcp:act` was added. Update any test that asserts the scope labels, and regenerate any committed MCP capabilities file.
- **Optional:** register `Tey\Kit\TypeScript\ExplanationTypesExtension` in the typescript-transformer config, so page props carrying an Explanation are typed instead of `undefined`.

### Added

- Plans spell out what the host must do beyond the files the kit writes (#8).
  - **What `kit:add` and `kit:update plan` report:**
    - the features' host steps;
    - their npm requirements, checked against the host's `package.json` and `node_modules`;
    - their optional integrations;
    - per feature, which migrations are new, changed or already there.
  - **New `host_steps` kinds:**
    - `env`, checked against `.env.example` only: the kit never reads `.env`. An optional `or_file` also meets it when that file exists; the kit checks existence only and never reads it;
    - `provider`, checked against the registered providers;
    - `schedule`, checked against the scheduled events.
  - **New `optional` list:** integrations a host may add, printed as manual steps.
  - **What blocks:** only `user_model` still blocks a plan. Every other unmet step, and an npm requirement that's missing, outdated or unverifiable, keeps the plan executable, opens the text preview with an ATTENTION block and sets `attention` and `warnings` in JSON.
  - **JSON:** the output is additive: `host_steps`, `unmet`, `missing_packages` and `changed_migrations` keep their shape.
  - **Plan identity:** a `kit:add` plan identity now binds the step statuses and each npm requirement's constraint, declared range and status, but not the installed version.
  - **Declared steps:**
    - mcp: `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` (env; each is also met by its `storage/oauth-*.key` from `passport:keys`), and `model:prune --model=Tey\Kit\Features\Mcp\Models\McpIntent` (schedule);
    - core: `ExplanationTypesExtension` as an optional integration.
  - **Check:** `composer test:plan-steps` covers it and runs in CI.
  - Re-pinned: package `core` `sha256:7aa65789…daae5` → `sha256:d3f0c696…3ebfc`, `mcp` `sha256:9077a379…aa9a` → `sha256:d2135421…bc07` (manifests only; no stub changed).

- `Registry::navigation()` and `modulePage()` take `active` as a list of path patterns, and an entry `route:<name pattern>` matches the current route's name (#93). A module that spans several route prefixes, or one found by route name (`route:trips.entries.*`), lists them all; a link is current when any entry matches. A single string stays valid, and an empty entry still means the workspace root only. An empty list or a bare `route:` refuses at registration. The match is `PageShell::matches()`, covered by `composer test:shell` and `test:library`. No stub changed, so no pin moves.

- `Tey\Kit\TypeScript\ExplanationTypesExtension` types a page's Explanation props as the kit's own (#83). A view model with a `list<ExplanationNode>` or an `ExplanationDocument` prop was emitted by typescript-transformer as `undefined[]` or `undefined`, so the page cast it. Register the extension in the host's transformer config (`$config->extension(new ExplanationTypesExtension)`) and those props become `import('@kit/ui').ExplanationNode[]` and `import('@kit/ui').ExplanationDocument`. It maps every Explanation data class and enum to the type `explanation.generated.ts` exports. `composer test:typescript` generates types for a fixture view model, proves the gap without the extension and the fix with it, and fails if the mapped names drift from the generated file; it runs in CI. `spatie/laravel-typescript-transformer` is now a dev dependency (hosts install it already). No stub changed, so no pin moves.

- `Popover` is exported from `@kit/ui` (#82): contextual detail anchored to a trigger, on Reka's Popover and never modal on desktop. Props `title` or `label` (one names the panel; with neither the trigger does), `side`, `align` (`start`), `offset` (8), `width` (`auto`, `sm`, `md`, `lg`, `trigger`), `closeButton`, `collisionPadding`, `collisionBoundary` and `sheet`; slots `trigger`, `anchor`, `title`, `default` and `footer`; `v-model:open` and a `dismiss` event (`escape`, `outside`, `close`, `navigate`). Collision handling is Reka's alone, so it no longer measures a host shell element. An Inertia navigation closes it, Tab past the last control closes it and moves on, and Popovers nest through Reka's dismissable layers. Below 640px it renders in `Modal` as a bottom sheet, through Modal's public props; it will move onto the shared modal base when that exists. The close button's name is `popover.close` in the kit `ui` lang file ("Close"), overridable per use with `messages`. `composer test:popover` covers the helpers. `ComputedValue`'s overlay is a consumer (width `lg`, close label "Close working") and behaves as before. Re-pinned: package `core` `sha256:0f1835ad…7c2b` → `sha256:0f2545ad…3ff2`.
- MCP tools declared by capability (#85). `#[Capability('<capability>', destructive: false, scope: null)]` names the workspace capability a laravel/mcp tool belongs to, and `Registry::capabilityTools([...])` registers it.
  - A registered tool is absent from the MCP list while its capability is off or out of plan. A call to it anyway is refused as `capability_unavailable` / `entitlement_unavailable` instead of "tool not found".
  - `capabilityTools()` fails closed at boot: no attribute, no `GatedByCapability`, an undeclared capability, no `ABILITY` or `scope` to admit the call under, an `ABILITY` another capability gates, or no `mcp` feature.
  - `CapabilityTool` is a base class for one principal-first operation. It admits the call through `ConsentGate`, refuses unknown arguments, validates, asks for confirmation and maps refusals to codes. Its name defaults to the capability with `.` as `_`.
  - `GatedByCapability` reads the attribute before `ABILITY`. A `CAPABILITY` constant is never read: hosts use that name for their own MCP tool ids.
  - A tool's `ABILITY` stays its permission: the tool is also hidden from a person whose role lacks it, and refused as `permission_denied`. This applies to the kit's own settings tools too, so `workspace_settings_update` is no longer listed for a member who cannot update settings. `Access::permits()` is the role rule `authorize()` uses.
  - `composer test:capability-guard`, `test:entitlements` and the new `test:capability-tool` cover attribute-registered tools and the base class's main path.
  - See `docs/mcp-tools.md`, which includes moving a hand-written tool onto the attribute. No stub changed.
- **MCP consent: one "acts as me" grant, optional narrow grants, and in-chat confirmation (#86).** The new `mcp:act` scope is the default grant: a connection that holds it may call any tool, and Access's role, policy, capability and entitlement checks decide each call, as in the app, so a new tool never needs re-approval. The per-ability scopes (`settings.read`, …) stay as optional narrow grants and only cover their own ability. Assistants request the one scope laravel/mcp advertises, `mcp:use`, so a token with no narrow scope acts as the person (the consent screen now says so), and a token that asked for narrow scopes is held to them. Both the token and the connection must cover the ability, or the call is refused with `connection_denied`.
- `Tey\Kit\Features\Mcp\ConsentGate`, the one gate MCP tools go through: `admit($ability)` returns the delegated principal and the bound workspace; `confirm($tool, $principal, $request, $summary)` is the two-step for destructive tools (a tool is destructive when its `#[Capability]` says so, else by laravel/mcp's `#[IsDestructive]`): the first call returns `confirmation_required` with a single-use `confirm_token` bound to the person, the connection, the tool and the exact arguments (5 minutes, in the cache); the same call carrying it runs, and a reused, expired, forged or mismatched token is `confirmation_invalid`; `refusal($e)` passes known codes through, maps validation to `validation_failed` and a missing model to `not_found`, and hides anything else as `operation_failed`. The settings tools use it.
- Audit entries made through an MCP connection carry `via: {kind: assistant, connection_id, client}`, so they read as "<person> via <assistant>". A `via` the caller already put in the details is kept as given.
- `composer test:mcp-consent` covers the grants, the confirmation, the refusal codes and the audit tag on SQLite memory with real Passport tables, and runs in CI with `composer test:mcp-refresh`, which now runs there too.
- **MCP intent replay** (follow-up to #86). A `CapabilityTool` whose `rules()` accept `intent_key` stores each call's result under it. A retry with the same person, connection, `intent_key`, tool and arguments replays the stored result instead of running again, including a destructive call that was already confirmed, which is not asked again. The same key with other arguments or another tool is `intent_conflict`. A call without `intent_key` runs every time, and a call that fails stores nothing. The operation and its stored row commit in one transaction, so two racing calls with one key run the operation once: the second's work rolls back and it returns the first's result. The new `ConsentGate::recall()` and `remember()` do this for a hand-written tool. The results live in the new `kit_mcp_intents` table for `kit.mcp.intent_ttl` seconds (24 hours by default). After that a key is treated as new, and `model:prune` on `McpIntent` deletes expired rows; see `docs/mcp-tools.md`. `composer test:capability-tool` covers it. Re-pinned: package `mcp` `sha256:f507a2bb…117b` → `sha256:9077a379…aa9a`.

### Changed

- `php bin/catalog-pins.php` prints each catalog feature's current identity (and the base's) next to its pin in `src/Catalog.php`, marked `ok` or `changed`, so re-pinning after a stub change no longer means reading `Blueprint` internals. `--check` exits 1 while a pin is stale, and runs in CI.
- CI runs every `composer test:*` script. `test:tables`, `test:lists`, `test:explanation`, `test:input-select`, `test:js` and `test:split-drop` existed but were never run there. `php bin/ci-scripts-check.php`, itself a CI step, fails when a test script is in neither `tests.yml` nor its `NOT_IN_CI` list (script => reason; empty today).
- Core states `reka-ui` `>=2.11.0` in `requires.npm` (#84). `InputSelect` needs 2.11.0 or later to typecheck and relies on its non-modal behaviour. Still informational: `kit:add` does not install it, so the host's `package.json` must carry it. Re-pinned: package `core` `sha256:c0635fd3…f65a` → `sha256:0f1835ad…7c2b`.

### Fixed

- `Audit::write()` keeps a `via` the caller supplied. Through an MCP connection it overwrote any host `details['via']` (for example `'assistant'`) with the kit's `{kind, connection_id, client}`, so the host's value was lost. The kit now writes its `via` only when the details have none. `composer test:mcp-consent` covers both.
- The `ComputedValue` overlay trigger has `aria-controls` again. The fix in this release still bound `id` and `aria-controls` as `undefined`, which won over Reka's, so the trigger pointed at nothing. `ComputedValueRoot` now exposes `popoverTriggerAttrs`, the trigger attributes without `id`, `aria-controls` and the click handler, and the Popover trigger binds that, so Reka's `aria-controls` points at the panel. `composer test:explanation` checks the source; the browser behaviour is proven in a host. Re-pinned: package `core` `sha256:6c4487fb…342f` → `sha256:93462cac…6fbe`.
- `kit:adopt <feature> plan`, the path a pre-lifecycle host takes:
  - a new migration creating a table the host database already has makes the plan not executable and is listed in `unmet`, as in `kit:add` and `kit:update plan`, including the skip for a migration the host already ran;
  - the plan prints the full apply command (`kit:adopt <feature> apply --intent=… --exclusive-checkout --expected-plan=…`) above its JSON and carries it as `apply`;
  - `composer test:plan-steps` covers both.
- The `kit:add` ATTENTION banner said "Nothing here blocks the apply" above a plan that was not executable. It now says the plan is not executable when it is not. `composer test:plan-steps` covers both banners.
- The plan's existing-table check no longer blocks a migration the host already ran. Re-adding or restoring a kit migration whose tables exist because that same migration was applied before is skipped by `migrate`, so it is not a collision. The check reads the migrations table; when it cannot, it checks as before.

- MCP tools are gated by the `#[Capability]` attribute only. `Capability::of()` and `GatedByCapability` also read a tool's `CAPABILITY` constant, so a host whose hand-written tools keep their MCP tool id there (`assignments.list`) had every such tool hidden and refused as `capability_unavailable`. The constant fallback is gone: a tool without the attribute is gated by its `ABILITY` as before 0.4, and `CapabilityTool`'s default name comes from the attribute alone. A tool moving onto the attribute adds it (see `docs/mcp-tools.md`). `composer test:capability-tool` covers a tool whose constant names an undeclared id.
- The MCP intents table is `kit_mcp_intents`, kit-prefixed like the kit's other tables, so it no longer collides with a host's own `mcp_intents` (migrate failed with 42P07). The migration is unreleased, so it is changed in place. Re-pinned: package `mcp` `sha256:d2135421…bc07` → `sha256:e1bfc73a…ac43`.
- A plan whose new migration creates a table the host database already has is not executable: `kit:add` and `kit:update plan` list it in `unmet`, naming the table and the migration, instead of leaving migrate to fail. The table is read from `Schema::create('<name>'` in the migration and checked with `Schema::hasTable`; a database the kit cannot reach is not checked.
- Plan output:
  - the `kit:add` text plan prints the exact apply command, with `--expected-plan` and `--intent`; `kit:update plan` adds `apply`, the exact command with `--expected-plan`, `--exclusive-checkout` and `--intent`;
  - a `schedule` step names `routes/console.php or bootstrap/app.php (withSchedule)`;
  - `kit:update plan --lineage=<feature>` whose requirement has an update still pending lists it in `depends_on` and gives the order in `order`.
  - `composer test:plan-steps` covers each.

- The desktop `Popover` has an accessible name and takes focus on open. Reka 2.11 merges `aria-labelledby = triggerId` after forwarded props, so the kit's heading id never applied; the panel is now named after mount (by its heading, else `label`, else the trigger), which Reka does not patch again. Open auto-focus moves focus to the panel (`tabindex="-1"`) on the next tick, found by its element rather than the content ref, whose `$el` left focus on the trigger. `ComputedValue` no longer overrides Reka's trigger `id` and `aria-controls` in its Popover, so `aria-controls` points at the panel. Restored for hosts, both removed in #82: the `data-kit-popover` attribute on the desktop panel, and `overlayKey` from `Explanation/scope`, now a deprecated alias of `sheetKey` (removed in 0.5). `composer test:explanation` checks the source; the kit has no browser test, and the browser behaviour is proven in a host. Core re-pinned: `sha256:d3f0c696…bfc` → `sha256:6c4487fb…342f`.

- The MCP authorization screen shows that it is working: Authorize and Deny each show a busy state on submit (the button is disabled, with a spinner, "Connecting…" or "Cancelling…", and `aria-busy`), a second submit is refused, and both reset when the browser restores the page from the back-forward cache. The approve and deny posts end in a redirect to the external client, so the page gets no response to react to. The view is package code, so no pin moves and no `kit:update` is needed. `composer test:mcp-consent` checks the view's source; there is no browser test.

- A `SplitPane` aside never has a scrollbar of its own (#105). With `stickyAside` it used a fixed `100dvh` height and an inner `PaneScroll`, so the page and the aside each scrolled. Now it is `sticky` only while its content fits the viewport below the shell header (measured by a ResizeObserver and the window resize, in the new `Layout/useStickyWhenFits.ts`, fit rule in `Layout/stickyFit.ts`); taller, it sits in the flow and scrolls with the page. `mainScroll` and the phone `sheet` are unchanged. Audit of every stub for an aside or sidebar with its own `overflow-y-auto` and a viewport height: only this one; `SettingsFrame`'s sidebar and the shell header have none. `composer test:js` checks the fit rule and that the aside source has no fixed viewport height or inner overflow. Core re-pinned : package `core` `sha256:6d8a05fb…b1db` → `sha256:7aa65789…daae5`.

- The Modal's `role="dialog"` element is named by the modal's title (follow-up to #82). The package renders that element unlabelled; `ModalPanel` now sets `aria-labelledby` on it, so every Modal, drawer and the Popover's phone sheet is a named dialog. Re-pinned: package `core` `sha256:ee389558…7791` → `sha256:6d8a05fb…b1db`.

- The Popover's phone sheet starts focus on the top of its content again (follow-up to #82). `Modal` takes `initialFocus` (`field`, the default, or `body`); `body` focuses the scrolled-to-top body instead of the first field or button, so a text-only sheet no longer leaves focus on the package's Close button. The Popover passes `body`. Re-pinned: package `core` `sha256:0f2545ad…3ff2` → `sha256:ee389558…7791`.

## v0.3.2

### Fixed

- The dashboard greeting follows the workspace's timezone until the browser has sent its own (#87). With no `tz` cookie and no zone saved for the person, `Good morning, …` used the UTC hour even when the workspace had a timezone set; `DashboardController` now passes the workspace's zone as the fallback (UTC when Settings isn't installed). `composer test:greeting` covers it over HTTP with a frozen clock and runs in CI.
- `DataTable` shows its row count on a single page (#88). The footer's `1–5 of 5` line showed only past one page; it now shows whenever there are rows, and only the page links wait for a second page. `composer test:table-count` checks the stub's template and runs in CI. Re-pinned: package `core` `sha256:42c9c901…ddd9` → `sha256:c0635fd3…f65a` (`kit:update` delivers the fixed stub).

## v0.3.1

### Fixed

- `contract.ts` in v0.3.0 does not parse: the SplitPane props comment lost its opening `/**`, so a host's `vue-tsc` fails on the generated `resources/js/components/Kit/contract.ts`. The comment is restored. `composer test:stubs` now parses every TypeScript stub with Node's type stripper and runs in CI. Re-pinned: package `core` `sha256:4ac9554d…1d1a` → `sha256:42c9c901…ddd9` (`kit:update` delivers the fixed stub).

## v0.3.0

### Upgrading

- BulkDrop's DOM hooks are renamed. A host that copied the earlier dropzone and selects on its attributes (tests, styles, scripts) moves from the `data-capture-*` hooks to `data-dropzone` (the surface), `data-dropzone-overlay`, `data-dropzone-prompt`, `data-dropzone-hint`, `data-dropzone-browse` and `data-dropzone-input`, plus `data-dragging` while a drag is over it. `data-paste-target` is removed and not kept as an alias: paste is handled on the focusable `data-dropzone` surface itself, so focus that element and paste (a page-level paste adapter is not part of the kit).
- **Requires tey/mod ^0.11 and tey/concierge ^0.8.2.** Update with `composer require tey/kit tey/mod:^0.11 tey/concierge:^0.8.2 -W` (the `-W` lets Composer move tey/mod past a locked 0.9 or 0.10).
- **The shell moves out of session-ui.** An app that installed session-ui before 0.3 moves the shell's files onto the new `shell` feature with `kit:update`, one reviewed run per step: `kit:update plan --lineage=session-ui` (releases `pages/Workspace/Layout.vue` and `Partials/` at an interim version), then `--lineage=shell` (installs shell at exactly those bytes, with no write), then `--lineage=session-ui` again to finish, and `--lineage=shell` once more to take shell's own changes. Each run prints the next. An edited moved file stops the first run before any write and prints its diff; edit it back or keep it as the app's. Don't retry the first session-ui run after shell has taken the files: on tey/mod 0.11.0 that retry fails recovery (`Third file value blocks maintenance recovery.`); the fix ships in tey/mod 0.11.1.
- **Replace the user menu and workspace switcher with slots, not aliases (#59).** The shell's `user-menu` and `workspace-switcher` slots default to the kit's partials with the same props. An app that shadowed `./Partials/UserMenu.vue` or `WorkspaceSwitcher.vue` with Vite aliases can fill the slot instead and drop the aliases.
- **`resources/css/kit.css` changed** (the gutter rule, see Fixed). Run `kit:update` to take it; an app that edited the file keeps its edits through the usual three-way plan.
- **Migrate for entitlements:** the `access` feature has a new migration, `kit_entitlements`. Run `kit:update` for `access`, then `php artisan migrate`. Nothing is gated until a feature declares an entitlement, so existing workspaces are unchanged. The old `entitlements` table and `Access::authorize(..., paid: true)` still work for now; move a "paid" check to an entitlement on the capability it gates.
- **People is absent while `workspace.settings` is off.** Settings › People, its modals and its writes now return 404 when the capability behind `members.manage` is off for the workspace or person, like every other surface of an off capability. Before, the section was hidden but the page still opened for anyone holding `workspace.members.manage`.
- **Apps installed before feature lineages** can now update. `kit:update` on such an app refuses and prints the commands to run first. Adopt each feature in place, in the order printed: `php artisan kit:adopt <feature> plan`, then `kit:adopt <feature> apply --intent=<name> --exclusive-checkout --expected-plan=<plan>`. After that, update each feature as an adopted app does: `kit:update plan|apply --lineage=<feature>`. This replaces hand-copying release changes, such as the core `0001_01_02_000100` migration change in v0.2.0.
- **Changed migrations need review:** when a `kit:update` plan would replace a file under `database/migrations/`, the plan lists it in `changed_migrations` with a host step. The apply is refused until you pass `--accept-changed-migrations`. A migration that already ran is not run again, so for each one add a new migration that makes the same schema change.
- A staging deploy now shows the indicator by default. Set `kit.environment_indicator` to `[]` to turn it off, or add an entry for another environment name (`'uat' => ['label' => 'UAT', 'colour' => '#0e7490']`). `session-ui`'s `Workspace/Layout.vue` changed: run `kit:update`.
- Nothing changes for an app that sets none of the new keys. `session-ui`'s `Workspace/Layout.vue` changed (dark logomark and lockup): run `kit:update`. A host that overrides the header's mark through the `mark` slot is unaffected.

### Added

- `Tey\Kit\Mail\FluentMailMessage`: the fluent mail composer (all mail = a Notification composed in PHP as ordered blocks, never a template). Escaped by default; `markdown()`, `markdownLines()` and `markdownView()` are the explicit raw escape hatches; `preheader()`, `headline()`, `facts()`, `table()`, `panel()`, `callout()`, `note()`, `bullets()`, `quote()`, `divider()`, `button()` and `section()` compose the body, and stock `greeting()`, `line()` and `action()` land in order. It needs no service provider: the constructor registers the `kit-fluent-mail` view namespace and appends `resources/views/fluent-mail` to `mail.markdown.paths` (the host's paths keep priority), so it renders through the kit's branded layout when `mail.brand` is applied and Laravel's layout otherwise. `Tey\Kit\Mail\MailConformance` scans a directory for a Mailable, the Mail facade or a `toMail` that skips the composer; `docs/mail.md` shows the host's architecture test. `composer test:mail` covers rendering, the text part, macros and the scanner; run in CI. No change to a feature pin. Graduated from a client app.
- A `shell` feature that needs only core (the Shell spec). It owns the generated `pages/Workspace/Layout.vue` and `Partials/`, which move out of session-ui unchanged: every selection with session-ui generates the same files with the same bytes, and session-ui now requires shell, so `kit:add session-ui` adds it. A host with its own tenancy adopts `core` and `shell` without Concierge, Pennant or Fortify. A selection made before the split (a session-ui lineage or a `kit.features` list without shell) boots with shell carried. Mod keeps a lineage's files, so a host that already installed session-ui cannot hand them to shell: `kit:update --lineage=session-ui` refuses and names the files and their new owner, with nothing written. Pinned: package `shell` `sha256:1b05c57a…9f97` (new); re-pinned `session-ui` `sha256:e4181ca3…df85` → `sha256:0e9134ce…3bdc` (`kit:update` delivers the move).
- ShellData and ShellKind move to `Tey\Kit\Features\Shell\Data`. The `SessionUi\Data` names stay as PHP class aliases, and shell generates `resources/js/types/kit-shell.d.ts` aliasing the old TypeScript names.
- The shell's `bar` slot (the Shell spec): a control that stays in the bar at every width, such as a search opener. It renders once and never in the drawer, so it replaces teleporting into the shell's markup.
- SubNav (the SubNav spec): `ShellData::$subNavigation` lists the active module's pages as a strip of links under the header, with the server's current page, sideways scrolling on a phone and no tab roles. Features register pages with `Registry::modulePage()`; `PageShell` fills it with the pages the viewer may open.
- GuestShell (the Shell spec, GuestShell section): the guest/auth layout, in the core-only shell feature. It has the bar's lockup (the new shared `Partials/Wordmark.vue`), a card, a narrow or wide measure, a title and description, a labelled way back from the host and the appearance switch. It needs no ShellData, user or auth route.
- Capability guard (#54). `composer test:capability-guard` walks every capability in the Registry, kit and app alike. It switches each one off for a workspace and checks that every page from its navigation links, settings sections and placeholders is a 404. It checks that its links, sections and MCP tools leave the shared shell, page props, settings listings, the capability listing and the MCP tool list, and that its abilities are refused as `capability_unavailable`. Switched back on, everything returns. A capability with nothing the guard can see fails it. The kit has no dashboard-widget, command-palette or per-feature action-descriptor registry yet, so those are not checked, and a route served without a navigation link, settings section or placeholder is not discovered. Runs in CI.
- Page actions: `FloatingToolbar` (with `FloatingToolbarDot` and the `provideSurface` / `useSurface` surface context), `ActionBar` (with `ActionLabel`, `layoutActions`, `placeActions` and `isNavigation`) and `SectionHeader`, the one header for sections, widgets, cards and panes (built to the FloatingToolbar, ActionBar and SectionHeader specs). FloatingToolbar takes its actions and status through its slot and reads no store. `Button` takes the pill shape and inverse tone inside a FloatingToolbar and is unchanged elsewhere. `Section` renders its title through SectionHeader; its props are unchanged. `Tey\Kit\Features\Core\Data\Action` gains optional `intent`, `group`, `menu`, `icon` and `newTab` (defaults keep existing payloads valid). `composer test:js` runs the placement and number tests. Graduated from a client app. Re-pinned: package `core` `sha256:234e79df…38b4` → `sha256:d82d47dd…966f` (new stubs; `kit:update` delivers them).
- Page-action follow-ups (spec amendments). FloatingToolbar's slot is ActionBar and status only (every action is a descriptor, so it overflows into the ⌄ menu on phones); a button or link slotted directly warns in development. `ButtonGroup` now takes its look from `paper` and neutral outline tokens in `kit.css` (`bg-paper`, `ring-outline`, `text-outline-foreground`, `text-outline-muted`, `bg-outline-wash`), defaulting to its old gray and white values so the default theme is unchanged; a theme or a surface overrides the `--kit-*` variables, and the FloatingToolbar pill does. ActionBar keeps its own segments on the pill (ButtonGroup has no hook for the trigger's name, focus hold, pill shape or a filled segment).
- ButtonGroup's menu panel now uses `bg-popover` and `text-popover-foreground` in every theme (it was `bg-white`; same pixels in default light). The `--kit-paper` / `--kit-outline*` aliases stay: none of the shadcn surface tokens (popover, border, popover-foreground, muted-foreground, accent) equals the group's old gray/white values in the neutral sheet, so mapping them would change the default look. Hosts must load kit.css. Core re-pinned (see the todo).
- Menu family (built to the Dropdown, DropdownItem, DropdownLink, DropdownConfirm, DropdownAction, DropdownDelete, DropdownModalLink and ButtonDropdown specs, over the shared MenuPanel spec). `@kit/ui` exports `Dropdown`, `DropdownItem`, `DropdownLink`, `DropdownConfirm`, `DropdownAction`, `DropdownDelete`, `DropdownModalLink` and `ButtonDropdown`. The menu is not modal; it opens by pointer or keyboard (ArrowUp opens on the last item), never wraps, closes on Escape, Tab and outside presses with the focus rules the spec sets, and an item's confirmation dialog holds the menu open behind it. Enter and Space on an item do what a click does. As specced, `active` (DropdownItem, DropdownLink) and `noun` (DropdownConfirm) do nothing, DropdownLink's button form is always `type="submit"`, and an item with no `href` is an Inertia link to `#`. `Kit/Menu/DropdownMenu.vue` (an auto-wrapping icon menu) is shipped but not exported, because `DropdownMenu` is the shadcn primitive's name in `@kit/ui`. `ButtonGroup`'s menu now runs on the same MenuPanel (the default theme renders the same pixels; the panel is non-modal, so its trigger keeps its hover look while open). The confirmation dialogs use the kit's `Dialog`; the delete and danger icons use `text-destructive`. `composer test:js` runs the edge-placement test. Graduated from a client app's clean-room rebuild. Re-pinned: package `core` `sha256:b71a9bba…41e5` → `sha256:0d00abfe…c64b` (new stubs; `kit:update` delivers them).
- **Form inputs take the `Input*` names (0.3 rename).** `NumberInput` is now `InputNumber` and `Checkbox` is now `InputCheckbox`, in `@kit/ui` and as `Form/InputNumber.vue` and `Form/InputCheckbox.vue`. The old names stay for one minor as deprecated aliases and are removed in 0.4: the `@kit/ui` exports, the `NumberInputProps` type, and the `Form/NumberInput.vue` and `Form/Checkbox.vue` files, which are now the same component re-exported. `Select` is deprecated in favour of `InputSelect`, the kit's rich select, and stays unchanged until 0.4. It isn't an alias, because the props don't map one to one: InputSelect has no `autofocus`, and it turns search on past 8 options. The kit's own Login and Connections pages now use `InputCheckbox`. Run `kit:update` for `core`, `session-ui` and `mcp`, then rename your own imports before 0.4.
- **FormFieldset's title now sits inside its `<legend>`, as a heading.** FormFieldset is a native `<fieldset>`: the title is a real heading at `level` (default 2) inside the legend, and the description follows the legend, tied to the fieldset by `aria-describedby`. That is a UX change for screen readers: what they announce changes. The title is now the group's name as well as a heading, so moving focus into any field in the group announces the group's name, and the description is read as the group's description, not as part of its name. The prop is `title`; `legend` keeps working as a deprecated alias until 0.4. The title and description move from raw gray steps to the `foreground` and `muted-foreground` tokens, the grid gaps grow to 6 across and 8 between rows, and groups after the first are set apart by a rule (`separator="space"` for space only), decided by position among sibling fieldsets. Run `kit:update` for `core`.
- **LockedForm, EditOnly and `useLocked()`** (`Form/LockedForm`, `Form/EditOnly`, `Form/useLocked`, exported from `@kit/ui`; built to the LockedForm spec). `<LockedForm :save="save">` wraps one or more FormCards, or a lone FormFieldset, and locks them when the page's save descriptor is `null`: the server decides, never the client. Locked, every kit control turns read-only through the context, never `disabled`: native `readonly` on Input, PasswordInput, Textarea and InputNumber, `aria-readonly` on InputCheckbox, InputSelect (still a combobox, showing the chosen label and opening nothing) and the deprecated Select, with the `muted` fill and the value at full contrast. Edit actions are hidden, not disabled: Repeater's add, remove and move, FileInput's picker (it shows the current file's name, size and link from the new `current` prop, or "No file"), ActionBar descriptors with `intent: 'edit'`, and anything inside `<EditOnly>`. Each FormCard shows the notice in place of FormActions ("You can view these settings but not change them.", `quantity="one"` for the singular, `notice` to replace it, `reason` for a second muted line), nothing submits (Enter included), a nested LockedForm inside a locked one stays locked, and a FloatingToolbar inside the area shows the reason or "View only" as status text. `Tey\Kit\Features\Core\Data\Action` documents `intent: 'edit'`. `composer test:js` runs the new `tests/js/locked.test.mjs`.
- **FormFieldset rework** (built to the FormFieldset spec): a native fieldset and legend, `title` (`legend` stays as an alias until 0.4), `description`, `level` (2 to 6), `legendHidden`, `separator` (`rule` or `space`), `fullwidth`, the `title` and `description` slots, attributes on the fieldset, and a development warning for a fieldset with no title. Locking comes from LockedForm only: there is no `locked` or `disabled` prop and `<fieldset disabled>` is never used. A lone fieldset in its own LockedForm shows the notice under its legend and description, before its grid. Re-pinned: package `core` `sha256:20b806a1…71a6` → `sha256:4ac9554d…1d1a` (`kit:update` delivers the new and changed stubs).

- Entitlements per feature (#53). A feature or app declares that a capability is part of a plan: `$registry->entitlement('workspace.projects', limits: ['projects'], upgrade: ['label' => 'Upgrade to add projects', 'suffix' => 'settings/billing'])`. `Entitlements` resolves it per workspace: the workspace's row in `kit_entitlements`, then the app default (`kit.entitlements`), then not included. It is read once per request like capabilities. `Entitlements::enforce()` checks a limit on the server and refuses with `limit_reached` (403, with a plain message). A billing integration only calls `Entitlements::set()`; the kit has no billing provider. Entitlements are a separate gate from capabilities and permissions: `Access::authorize()` checks the entitlement after the capability. A capability left out of the plan is absent by default (`entitlement_unavailable`, 404, gone from the shell, settings and the MCP tool list). An entitlement that opts into `upgrade` refuses with `entitlement_required` (403) instead, and `Entitlements::upgrade()` gives the page a server-built upgrade action. There is no plan check in Vue or TypeScript. `composer test:entitlements` covers two features with different plans on one workspace, a limit over HTTP, and the separation from capabilities and permissions. Runs in CI. Re-pinned: package `access` `sha256:25d5b8be…38548` → `sha256:a179066f…26d98` (a new migration stub; `kit:update` delivers it).

- `kit:adopt <feature> plan|apply [--from=<release>]` (#3) records a feature that is already on disk as its own lineage. It overwrites nothing. Each file is handled by what it holds:
  - **Identical** to a known release: claimed with no write.
  - **Missing:** created.
  - **Edited:** kept by the app, against the release it was edited from. That is the `--from` release, or by default the closest one (the release most files match). A later `kit:update` leaves a kept file alone until a release changes it. It is then a conflict, as an edited owned file is. Once the app's bytes match the release, the file is claimed.

  A failed apply rolls back, as `kit:update` does. If an apply is interrupted (a crash), every plan refuses with the pending hint, which names `kit:adopt <feature> rollback --intent=<intent> --exclusive-checkout`. The rollback removes the files the adoption created, and refuses, removing nothing, if one has changed since. `resources/features/history.json` holds the file hashes of each tagged release; regenerate it with `php bin/catalog-history.php` before tagging a release. Its `current` entry must match the live catalog, and the check fails otherwise, so a stub change regenerates it in the same PR. `composer test:adopt-in-place` covers it: a clean-room pre-lifecycle host, adopt, update and a follow-up update, with the edit kept throughout.

- **Environment indicator (#50).** Outside production the shell shows where you are without a badge: `[Staging] ` before the document title and a 2px edge across the top of the signed-in shell, in the environment's colour (with its name for a screen reader). It is configured per environment in `kit.environment_indicator` (`label`, `colour`), ships with a `staging` entry only (`#b45309`, 5.0:1 on white and 3.9:1 on the dark shell), carries no product name, and is never shown in production whatever the config says. A label that is not plain text, or a colour under 3:1 on the light or dark shell (the brand's own page colours when set), is not shown. `PageTitle` applies the prefix; the server shares `environment` (null where there is none) for a host's own layouts, such as a guest page, to draw the same edge.
- **Brand theming (#49).** `kit.brand` now expresses a whole theme, and every key is optional: unset, the CSS is byte-for-byte what v0.2.2 printed (a check asserts it).
  - **Surfaces:** `background`, `card`, `border`, `muted` and their `_dark` twins print `--background`, `--card`, `--border` and `--muted`.
  - **Roles:** `button` (`accent` or `ink`), `button_dark` (`accent` or `theme`), `header` (`theme` or `ink`) and `marker` (`strong` or `accent`) split what `style` bundled. `style=mono` is the same as `button=ink, button_dark=theme, header=ink, marker=accent`, so ink buttons in light mode and accent buttons in dark mode on the theme's own header is `button=ink, button_dark=accent, header=theme`.
  - **Links:** `link` and `link_dark` set `--brand-link`; nothing is printed unless set.
  - **Header rule:** `header_rule` (`accent` or a `#rrggbb`) draws a 2px line under the header (`accent_dark` in dark mode).
  - **Logomark and lockup:** `logomark_dark` is shown under `.dark`; `lockup` shows the `wordmark` image (`wordmark_dark` in dark mode), or the product's name when there is none, beside the mark from the `sm` breakpoint up. The shell's `product` gains `logomark_dark`, `wordmark`, `wordmark_dark` and `lockup`; a host's own guest layout reads them from `shell.product`.
  - **Fonts:** `KIT_FONTS=inter-serif` pairs Inter (text) with Instrument Serif (display).
  - **Contrast:** `contrastProblems()` reads the accent and the links against the configured page, card and dark surfaces instead of `#ffffff` and `#09090b`, and checks `link` and `link_dark`.

### Changed

- Renamed `NumberInput` to `InputNumber` and `Checkbox` to `InputCheckbox`, with deprecated aliases until 0.4, and deprecated `Select` in favour of `InputSelect` (see Upgrading). Re-pinned: package `core` `sha256:0d00abfe…c64b` → `sha256:2507f221…b510`, `session-ui` `sha256:0e9134ce…3bdc` → `sha256:241edd9b…c934`, `mcp` `sha256:e422e9e4…daa4` → `sha256:f507a2bb…117b` (`kit:update` delivers them).

- SplitPane, PaneScroll, PaneTabs, PaneToggle and BulkDrop in the core Kit stubs (`resources/js/components/Kit/Layout/`, `Kit/Form/BulkDrop.vue`). SplitPane is work beside its context on Reka's Splitter, with the aside in a drawer below the breakpoint; its layout state is server-first through an optional preference descriptor, with a guarded localStorage fallback. The preference write goes through Inertia's HTTP client (`http` from `@inertiajs/vue3`), so a host's `http.onRequest`/`onResponse`/`onError` interceptors apply and the client sends the XSRF token; a failed write falls back to localStorage. BulkDrop is the one dropzone (drop, paste on the focused surface, or browse) and emits files only: no upload transport, FileViewer or preference endpoint ships. `composer test:split-drop` covers the storage, the sender and the selection rules. Graduated from a client app. Re-pinned: package `core` `sha256:2507f221…b510` → `sha256:20b806a1…71a6` (new stubs; `kit:update` delivers them).

### Fixed

- `FluentMailMessage` no longer fails with "View [preheader] not found" when `Illuminate\Mail\Markdown` was resolved before the first message (a queue worker, an earlier mail). The kit's mail paths now also reach an already-resolved renderer. `composer test:mail` resolves the renderer first, then renders a message with a preheader.
- The kit's branded mail views are appended to `mail.markdown.paths` instead of prepended, so a host's published `resources/views/vendor/mail` (header, footer, themes) wins over them; the kit's views still apply where the host has none. `composer test:mail` covers both.
- `mail.brand` carries the `accent_foreground`, `accent_strong` and `ink` tokens; the branded button's text uses `accent_foreground`. `docs/mail.md` lists the keys and how a host adds its own.
- The kit's invitation email (`InviteNotification`) is composed with `FluentMailMessage`; the `kit-mail::invitation` view and the `kit-mail` view namespace are gone. `composer test:mail` runs `MailConformance` on the kit's own `src/`.
- Generated TypeScript types no longer read `undefined` for actions in a host that has laravel-actions. Its global `Action` alias won over the docblocks' imported `Action`, so the transformer typed the shell's workspace `settings`, `all` and `leave`, list items' `actions` and the workspace index's `leave` as `undefined`. The kit's docblocks now name `Tey\Kit\Features\Core\Data\Action` fully qualified, and `composer test:library` refuses a bare one. Regenerate the app's types (`composer types:generate` in the starter).
- Settings › People stayed reachable by URL with `workspace.settings` off (found by the capability guard). `WorkspacePolicy::manageMembers` now denies as not found while the capability is off.
- **ComputedValue's phone sheet spans the viewport.** On a 390px phone the sheet stopped at 375px: the modal package's scroll lock reserves a scrollbar gutter on `<html>`, which narrowed the sheet's fixed container by the scrollbar's width. `kit.css` now releases the gutter below 640px while a modal is open, so every phone-width sheet and modal is edge to edge (verified at 360, 390 and 430px).

## v0.2.2

### Added

- **InputSelect**, the kit's rich select (`Form/InputSelect`, exported from `@kit/ui`), built to the InputSelect spec on Reka's Combobox. It picks one value or many (as chips, or a count past `maxChips`) and stays non-modal while open. Search turns on past 8 options. It handles groups, remote search (debounced, from 2 characters), clear, opt-in select all, chosen first and create, read-only, and the phone sizes. With `name` it submits native `name` / `name[]` inputs. Its strings ship in the kit's new `lang/en/ui.php` (`kit::ui`, shared with pages as `kitUi`). Override them for the app in `lang/vendor/kit/en/ui.php`, or for one use with `messages`. The existing `Select` is unchanged.
- **Known limitation (InputSelect):** Reka's combobox has no supported way to stay non-modal, so InputSelect relies on an undocumented Reka behaviour (no page hiding inside a closed popover), verified on reka-ui 2.11.0. A Reka update could make the open list hide the rest of the page from screen readers again; re-check on each Reka bump.

## v0.2.1

### Fixed

- **MCP connections survive OAuth token refresh.** A connection was bound to one access token, so every MCP client lost its connection (and its workspace) at the first refresh, about an hour in. The `mcp` feature now moves a live connection to the new access token when Passport issues it. It moves only when the connection's old token was spent (revoked by the refresh, or expired) and belonged to the same user and client. A revoked connection stays revoked, and the old token is refused.

## v0.2.0

v0.2.0 is the kit proven in a mature production app. It adds the signed-in shell, people, settings and account pages, workspace permissions and per-workspace capabilities, and graduates Repeater, NumberInput, ScrollList and ComputedValue into the core Kit. It also makes `kit:update` safe on an app that adopted features one by one, and closes a workspace-name disclosure.

### Upgrading

- **Repeater row errors:** the kit's `rowErrorsKey` is its own `Symbol`. If your app has a local `repeaterState` (or a local copy of the key), import the kit's instead: `import { rowErrorsKey } from '@/components/Kit/Form/repeaterState'`. Otherwise fields inside kit Repeater rows (NumberInput included) stop receiving their row errors. The key is not exported from `@kit/ui`.
- **ScrollList:** the default row is slimmer (title, description, meta, badges). Pass the `row` slot (`{ item }`) for a richer row. The row hook `data-queue-row` is now `data-list-row` (rows also carry `data-list-item`). `useScrollList` no longer returns a total, because the list is count-free (cursor only).
- **ComputedValue:** the `data-candidate-*` hooks are now `data-kit-*` (`data-kit-popover`, `data-kit-sheet`). Update selectors and tests.
- **Repeater:** drag-to-reorder is removed. Rows move with the move buttons, which are always visible.
- **kit:update on an app that adopted features:** update each feature with `kit:update plan|apply --lineage=<feature>`, in dependency order, giving each lineage its own `--intent`. Plain `kit:update` and `--target` (release fixtures) are refused there and print the commands. A failed `kit:update apply` now rolls back: no files or journal stay behind. Fix the cause, then plan and apply again.
- **Run `kit:add` and `kit:update` from the app's main checkout,** not from a git worktree.
- **Apps installed before feature lineages** cannot `kit:update` yet (#3).
- **Workspace reads are member-only:** `MemberWorkspaces::workspace()` returns null for a workspace the person is not in. Use `WorkspaceUrl::currentMemberId()` for anything rendered, and pass a `Workspace` model (not an id) when building a link outside a session, such as mail or a queued job.
- **Brand mail is opt-in:** mail carries the brand only with the session-ui feature or when `kit.brand_mail` is `true`.
- **Migrate before `/up` is healthy:** the health checks need the tables of the features you adopted (new migrations: `kit_capability_overrides`, `kit_permission_defaults`, `constrain_audit_events_workspace_id`). Run `php artisan migrate` and `kit:permissions:sync` on deploy.
- **Dialog and Modal** replace `ConfirmDialog` and `FormModal`; **Modal** now returns focus to its opener, and Dialog gains `@closed`.

### Added

- Repeater and NumberInput graduate into the core Kit (`Form/Repeater`, `Form/NumberInput`): repeating rows with add, remove and move buttons and per-row errors, and a number field that keeps its text while typed. `kit:update` delivers them with `core`. Graduated from a client app.
- ScrollList graduates into the core Kit: a cursor-only list with an `Load more` button, client-side or server-driven (`useScrollList`), a slim default row and a `row` slot (`{ item }`) for a richer one. Graduated from a client app.
- ComputedValue: a figure that opens its working (the ComputedValue spec and conformance rules 17 and 70). `@kit/ui` exports `ComputedValue`, `ComputedValueRoot` (headless), `ComputedValueScope` (show or hide all, optionally remembered) and `ComputedValueToggle`, plus the `Explanation*` types. `core` generates them into `resources/js/components/Kit/Explanation/`. The server sends a `Tey\Kit\Explanation\ExplanationDocument` (Laravel Data). It refuses JSON floats, unsupported versions, dangling operands, unsafe links, malformed decimals, cycles and documents deeper than 16, larger than 1000 nodes or 256 KiB. The browser only displays the strings it is given and does no arithmetic. `php bin/generate-explanation-ts.php` writes `explanation.generated.ts` from the PHP types, and `--check` fails when it is stale. The desktop popover and phone sheet that ComputedValue opens are internal and not exported. `composer test:explanation` covers the contracts, the generator and the working text. Graduated from a client app. Re-pinned: package `core` `sha256:748e41c2…fe0d6` → `sha256:f3c7a801…81a2d` (new stubs; `kit:update` delivers them).
- Permissions. A feature (or an app) declares a permission and its default Concierge roles on the registry: `$registry->permission('workspace.settings.update', ['owner', 'admin'], capability: 'workspace.settings', abilities: ['settings.write'])`. The kit declares `workspace.settings.update` and `workspace.members.manage`. `php artisan kit:permissions:sync` creates the missing permissions and grants the defaults, re-runs change nothing, never revokes, and reports custom grants, undeclared permissions and default roles the app does not configure (skipped), and default roles it configures but has not created (exits nonzero). Roles stay Concierge's, per workspace; the kit adds no role system. `WorkspacePolicy` (`view`, `update`, `manageMembers`) checks active membership, the role grant in the workspace's team context and the permission's Pennant capability, all three. `Permissions::flags()` gives a page its `can` map from those checks. `Access::authorize()` decides an ability a permission replaces (`settings.write`) by the permission, not by ownership; other `.write` abilities stay owner-only. The settings page's `canWrite` comes from the policy. Run the sync after `migrate` on deploy. A default is granted once: the `access` feature's new `kit_permission_defaults` migration records which defaults the sync has applied, so a default someone removed is not granted again, and one that is new since the last sync is. `HandleInertiaRequests` shares the current workspace's `can` map. `composer test:permissions` (registry, sync, policy, flags) and `composer test:permissions-http` (settings update over HTTP on real Concierge tables and spatie teams: admin allowed after the sync, member 403, capability off 403) run on SQLite memory. Run in CI. Re-pinned: package `access` `sha256:5030011a…e38b0` → `sha256:26bbe931…86528` (a new migration stub; `kit:update` delivers it).
- Capabilities per workspace and person (#51, #52). A capability can be turned on or off for one workspace, or for one person in it, over the global switch. `php artisan kit:capability <workspace> [capability] [on|off|clear] [--user=] [--operator=] [--reason=]` lists or changes the overrides; the `access` feature's new `kit_capability_overrides` migration stores them. A capability that is off is absent everywhere, never shown as forbidden, and a registry guard checks the permissions that name one. Membership, permissions and overrides are read once per request.
- The shell: a dark bar with ghost controls, a split workspace switcher (one list, with leave in its menu), a back strip and breadcrumbs as a sub-bar (gretel, one crumb per page route in both URL forms), page titles as `{Page} · {Product}`, `FocusShell` for signed-in pages outside a workspace, `GuestShell` for guest pages and `FocusPage` for the page measure. The mark is the platform identity (`APP_NAME` and a configurable logomark), never a workspace name.
- Workspace URLs: tenants are marked with `@` (`/@{workspace}/...`, short paths, `/me/workspaces`), with one URL helper. The dashboard is the workspace root (`/@{workspace}`, short form `/`); `/dashboard` redirects (301).
- Pages as typed ViewModels: one shell share, `layout()` with navigation, titles and breadcrumbs, persistent section layouts, action descriptors in place of `can` maps and flags, and the `Page` container (boolean widths, states).
- Settings: a landing page of cards with a section sidebar, sections registered by providers, a `Placeholder` component and `Registry::placeholder` for a planned section, and a save that is always shown. Connections and the AI assistants page (approval descriptors and assistant cards) are Inertia pages.
- People: members and invitations in Settings with invite, change role, remove, resend and revoke through Concierge; transfer ownership (owner only, password confirmed again, the old owner becomes an admin); leave workspace; a transfer and leave in the General danger zone.
- Invitations: a branded email with the inviter's personal message, a specific subject and a preheader; an accept page that shows the workspace and inviter, signs in or creates the invited account, joins as the current account and says who invited you.
- Account pages under `/me`: profile with email re-verification, password, security with two-factor, devices and a security log. Deleting an account deletes what the kit remembers about the person.
- Avatars: a Media Library collection on the user, upload, replace and remove on `/me/profile`, served from the private media disk by a cached `/avatars/{token}` route; initials are the fallback.
- Brand: brand config, `kit:brand` assets, head links, branded mail with a footer from the registry, a private brand mode, accent companions, fill and mono styles, config-driven presets, a local-only `?brand=` switch and a 4.5:1 contrast guard. Mail address roles: replies go to support, with a privacy contact in the footer.
- First run: welcome steps, a dashboard welcome card, invitation forks and a one-click demo; the timezone is captured with a `tz` cookie, an auto-saving wizard step and the owner's workspace timezone; the no-workspace page names the product in the bar.
- Deploy: `kit:preflight` (with `--env-file` to rehearse a deploy, and Passport keys in any form a dashboard can hold), `/up` health checks, optimize registrations, and roles and permissions synced when migrations end.
- `Dialog` and `Modal` replace `ConfirmDialog` and `FormModal`; Invite and Change role are server modals. `Dialog` gains `@closed`, which fires once it has closed and returned focus. Also new: `Alert`, `Toolbar`, `BlockStack` settings cards, read-only input styling and an `autofocus` prop, a kit stylesheet with the pointer cursor, and release blueprint E for tey/concierge 0.8.

### Changed

- The workspaces feature requires tey/concierge `^0.8.1`; the default Concierge local sign-in is limited to the local environment.
- Forms are `FormActions` and `FormCard` on Inertia `Form` pages with a sticky status bar, a leave warning whenever a form holds unsaved input and confirmations on destructive actions; tables are `DataTable` over Concierge's `MembersIndex`; listings use the `QueryBuilder` and resource controllers, and invitations live under `/me`. Controller input-limit errors map to 400.
- Workspaces are ordered by their key, and a layout can name its own icon links.
- `kit:update` on an app that adopted features works per feature. Update each with `kit:update plan|apply --lineage=<feature>`, in dependency order; plain `kit:update` and `--target` are refused there and print the commands.
- `Modal` returns focus to its opener when it closes, and its Close button is 44px on phones. The phone sheet behind ComputedValue is labelled by its title, takes focus once it has opened and returns it on close.

### Fixed

- `kit:update apply` fails closed. A fresh apply that does not complete restores every planned file (created files and their empty directories are removed, changed files get their prior bytes back) and drops the intent's journal, so no files stay behind. A reopened intent still resumes.
- ScrollList keeps focus while it loads more: `Load more` uses `aria-disabled` and ignores clicks while busy instead of becoming `disabled`, so keyboard focus is not dropped to the page.
- Modal and Discard & close: the confirmation closes first and the modal closes after it, so focus returns to the opener rather than the page body (a plain Escape close did the same before).
- DataTable phone cards show their field labels (#55). The label is chosen by visible column position instead of being the first child of every cell; the title column and the action labels stay hidden.
- A security log that cannot be written never blocks a sign-in; a scrubbed security-log failure is reported. Input-limit errors register only on the framework handler, so artisan boots under Collision.
- Fixes to the first release of these pages: modal layering, the People member sort, bad table queries and modal redirects; the home crumb is the workspace root; breadcrumb links, the personal chooser and settings sections.
- A host that adopted only some features boots only those. `FeaturesServiceProvider` registered the `/up` health checks, the mail brand and the post-migrate `CatalogSync` whatever was adopted, so a core-only host answered `/up` with 500 ("the table workspaces is missing"), had `mail.from` and `mail.reply_to` rewritten and ran a sync it had no catalog for. Now the health checks require `workspaces` and `concierge_memberships` only with the workspaces feature, `roles` only with access and `kit_meta` only with core, and skip the brand placeholders on an unbranded host; the brand reaches mail (and is judged by `/up`) only with session-ui, or when `kit.brand_mail` is `true` (`false` turns it off for session-ui); `CatalogSync` is registered only with access. Nothing adopted registers no health listener. `php tests/HostBootCheck.php` boots core-only, nothing-adopted and full hosts on SQLite memory. Re-pinned: package `core` `sha256:77383e5f…ac422` → `sha256:07be2974…4983c`.
- Core migrates alone. `audit_events.workspace_id` was `foreignId(...)->constrained()` in the core migration, so a core-only host failed on Postgres (no `workspaces` table; SQLite let it through). Core now creates it as an indexed `unsignedBigInteger`, and the `workspaces` feature's new migration `0001_01_02_000204_constrain_audit_events_workspace_id` adds the foreign key to `workspaces` when it is missing. A database that already has the key (every host that ran the old core migration with workspaces) is left as it is; `down` changes nothing. `Audit::write` is unchanged. Checked on Postgres: core alone migrates with no key, adding workspaces adds it (an unknown workspace is refused), a re-run is a no-op, and an existing full host's keys are unchanged.
- `Overlay/Modal` types its package instance by the one method it calls (`close`), so `vue-tsc` no longer fails where `@inertiaui/modal-vue` 3.1.2 ships no declaration for its Modal component; unused refs and optional-prop defaults that tripped stricter compiler options are gone.
- `Form/Button` no longer needs a `bar` variant in the host's `components/ui/button`: `bar` renders the standard `ghost` variant with the shell bar's foreground colour classes. Props and `ButtonProps` are unchanged.
- Core's `requires.npm` carries real ranges instead of `*`: `@inertiaui/modal-vue` `^3.1.2`, `@lucide/vue` `^1.0.0`, `@tanstack/vue-table` `^8.0.0` (9 breaks `DataTable`). Still informational: `kit:add` does not install them. Re-pinned: package `core` `sha256:e84aceae…` → `sha256:8354c2cf…`, `workspaces` `sha256:ed0a9139…` → `sha256:8aa53c04…` (a new migration stub; `kit:update` delivers it).
- Core declares what its components import. `requires.npm` lists `@lucide/vue` and `@tanstack/vue-table` (informational; `kit:add` does not install them) and `requires.host_ui` lists the host-owned `spinner` component (`@/components/ui/spinner`). `DataTable` stays exported from `@kit/ui`, so existing imports keep working.

### Security

- A workspace the person is not a member of is no longer read for display. `MemberWorkspaces::workspace()` used to fall back to any workspace by id, so a page could show the name or slug of a workspace the signed-in person does not belong to. It now returns only the person's own active workspaces (null otherwise, and for no person). `WorkspaceUrl::currentId()` is documented as not authorization; the new `WorkspaceUrl::currentMemberId()` is the same id only for an active member, and the title, crumbs, shell, settings nav and home redirect use it. `WorkspaceUrl::slug()` (and so `full`, `to` and `shareable` with an int) refuses an id the signed-in person is not in with `workspace_not_found` instead of building a link with an empty slug; in the console with no one signed in (mail, queue, commands) the id resolves unscoped and is refused only when unknown, while a guest HTTP request, or any matched route in a long-lived worker, is refused. Pass a `Workspace` for a link outside a session. Operations still authorize, and a non-member and an unknown slug are refused alike (404). `composer test:workspace-guard` runs on SQLite memory. Run in CI.

## v0.1.5

### Added

- Font sets (#43). `kit.fonts` (`KIT_FONTS`, default `instrument`) names one of three sets: `instrument`, `instrument-inter` or `hanken`. `<x-kit::fonts />` in the layout head emits that set's fonts.bunny.net links and a `:root` block with `--font-sans`, `--font-serif` (display), `--font-mono` and `--font-display-weight`, with system fallbacks. An unknown set name is refused. `composer test:fonts` renders each set, checks the refusal and checks that pages name no font family but those variables. Run in CI.

## v0.1.4

### Fixed

- The `user_model` host step also checks the trait (#40). A user model that implements `Tey\Concierge\Contracts\Member` without using `HasWorkspaces` (through `class_uses_recursive`) is unmet, and the step says which half is missing. `composer test:install` covers a User with the contract only: the preview lists the step as unmet and the apply refuses.
- Host step validation accepts the `implements` and `uses` keys in any order (#41), and still refuses a missing, extra or non-string key. `composer test:catalog` covers both orders and each refusal.

## v0.1.3

### Fixed

- `kit:add workspaces` names the user model host step (#36). The `workspaces` manifest declares it in a new optional `host_steps` field: the user model must implement `Tey\Concierge\Contracts\Member`, through the kit's `HasWorkspaces` trait. `kit:add` checks it by reflection. It checks that `kit.models.user` (else the `users` auth provider's model) and the `concierge.models.user` the app will run with name the same model, and that the model implements the contract. Until then the preview lists the step as unmet (`host step (unmet)`, and `unmet` in `--json`) with the exact lines to add, and `--apply` and `--resume` refuse. Kit never writes `app/Models/User.php`. Re-pinned: package `workspaces` `sha256:7d57c61a…87926` → `sha256:e3b16619…3976a`.
- Adopted features register in an app set up by `kit:install`. Package providers register before the app's own, so `KitHostServiceProvider` was not yet bound when the kit read the adopted features, and nothing registered. Concierge then failed `php artisan migrate`. `FeaturesServiceProvider` now falls back to `kit-lifecycle.app_id`.
- `composer test:install` covers both: on a fresh app the step is listed and the apply refused. With the lines added, the apply completes and `php artisan migrate` succeeds on SQLite memory. The fresh-app fixture gains Laravel's users and cache migrations. The AdoptCheck host app's `User` now implements `Member`.

## v0.1.2

### Added

- `php artisan kit:install` sets up the host binding, so `kit:add` works in a fresh app (#34). It writes app-owned files from stubs in `resources/install`: `app/Kit/KitHost.php` (a `LifecycleHost` and `CatalogHost` on the package catalog), a fail-closed operation check in `app/Kit/operations.php`, `config/kit-lifecycle.php` (verification policy; app id from `app.name`), `config/kit-executor.json` from `Lifecycle::runtime()`, and `app/Providers/KitHostServiceProvider.php`. It appends the provider to `bootstrap/providers.php` only when missing, prints what it wrote and what to review, refuses to overwrite any existing file, and a second run changes nothing.
- `composer test:install` on a fresh-Laravel-shaped fixture with no host binding: `kit:install` then `kit:add workspaces` previews, applies on SQLite memory, a second install is a no-op, an existing `app/Kit/KitHost.php` is refused, and `kit:add` still refuses without it. Run in CI.
- README: a quick start at the top.

## v0.1.1

### Changed

- `kit:add` claims existing files only from an explicit, reviewed list (#26). v0.1.0 claimed every identical pre-existing file implicitly. Now the preview lists them as `claimable`, and the apply is refused until each one is listed with `--claim=<path>` (repeatable or comma-separated). Only listed paths reach Mod's `claim:`. The claim list is part of the plan identity, so a reviewed plan can't change silently. A listed file with different bytes stays a conflict, and a claim no feature being added delivers is refused.
- `kit:add --resume --intent=<id>` finishes the interrupted feature, then every remaining feature of the original request in requires order (#28). It prints which features are done and which remain, and exits nonzero unless every one completed. A resume whose intent has no pending record for the next feature is refused plainly.

### Fixed

- A `pending:<lineage>` refusal from `kit:add` or `kit:update` now prints the pending lineage, its intent and the exact `--resume --intent=<id>` command to run, or where to find the intent, and exits nonzero (#27).
- Tests cover a differing unowned pre-existing file (refused, no write), `--resume` without `--intent` (nonzero, clear refusal), explicit claims, the pending hint and multi-feature resume (#29).

## v0.1.0

### Add starter features to an existing app

The first tey/kit release. `composer require tey/kit`, then `php artisan kit:add <features>` previews and applies the starter's features (core, workspaces, access, settings, session-ui, mcp) one Mod 0.9 lineage per feature, claiming every identical existing file without asking (explicit from v0.1.1) and refusing to overwrite edits. `kit:update` upgrades each feature after `composer update tey/kit`; `kit:status` and `kit:list` report.

### Changed

- Requires released `tey/mod` ^0.9 and `tey/concierge` ^0.7.3. Release fixtures A–D and the v0.1 feature fixture keep the single-lineage `Lifecycle` path, which now reads Mod 0.9's per-lineage installed records.
- Re-pinned for Mod 0.9: the A–D manifests and the v0.1 `foundation` feature now require `tey/mod` ^0.9 instead of ^0.8. The package `workspaces` feature requires `tey/concierge` ^0.7.3 instead of exactly 0.7.2. Old to new: A `sha256:686b417b…5c74b` → `sha256:a59a38d4…c8260`, B `sha256:857fc187…2b3b` → `sha256:53e31b60…74d59`, C `sha256:18e53f09…7bdb` → `sha256:8699fd47…caf73`, D `sha256:777333b1…dd0f` → `sha256:0bab54b8…83672`; v0.1 `foundation` `sha256:40bbc9be…3541` → `sha256:a8f47c15…21312`, v0.1 all features `sha256:db4358fe…956675` → `sha256:40df4082…c9b98`; package `workspaces` `sha256:068735ea…45d5ba` → `sha256:7d57c61a…87926`.
- Mod 0.9 rejects dot-leading `hostOwned` prefixes (`.env`, `.env.example`). Until Mod 0.9.1, those are left out of the Mod artifact and enforced kit-side only.
- Renamed from `tey/starter-blueprint` to `tey/kit`. This package has never been published and nothing depends on it, so there are no aliases. Namespace `Tey\StarterBlueprint` is now `Tey\Kit`, `BlueprintServiceProvider` is `KitServiceProvider`, the config key `starter-blueprint` is `kit`, `starter:maintain` is `kit:update` (same arguments and options), and the scaffold `tey/starter-foundation` (command `starter:foundation`) is `tey/kit` (command `kit:foundation`). Other class names are unchanged.
- Pinned identities changed with the scaffold id: A `sha256:21bd1704…45e2` → `sha256:686b417b…5c74b`, B `sha256:70af6427…726c` → `sha256:857fc187…2b3b`, C `sha256:85405850…a3ff` → `sha256:18e53f09…7bdb`, D `sha256:a85ea6fe…7aaf` → `sha256:777333b1…dd0f`; feature `foundation` `sha256:8e2e55ee…4054` → `sha256:40bbc9be…3541`, `defaults` `sha256:fb252b8b…2e43` → `sha256:71662b1b…ec7f6`; all features `sha256:a7324f25…475b9e` → `sha256:db4358fe…956675`.
- CI runs every `composer test:*` script on PHP 8.4 and 8.5, and a Privacy job.

### Added

- Per-feature lineages on Mod 0.9 (`Lineages`): each feature installs as its own lineage, `tey/kit:<feature>`. Feature requires are checked kit-side against the installed versions and passed to Mod as `requires:<id>` outcomes; `host_owned` becomes Mod's `hostOwned`; `conflicts` stay kit-side.
- `kit:add <features...>`: previews by default (plan identity, per-file create/claim/conflict, missing packages, host steps) and writes nothing. `--apply --expected-plan=<id> [--intent=<id>]` adds the features in requires order, one lineage each, and stops at the first failure. `--resume --intent=<id>` finishes it. Every identical pre-existing file is claimed implicitly, without a write; v0.1.1 claims only listed files. Re-adopting is a no-op, and nothing is ever removed.
- `kit:update --lineage=<feature>`: plan, apply or resume one adopted feature's lineage. Refused while any lineage has an unfinished update. The original is rebuilt from the installed record, so an app upgrades to a new catalog version after `composer update tey/kit` without the old catalog.
- `FeaturesServiceProvider` adopts the installed lineages (read for `kit.app_id`, else the bound host's app id); `kit.features` stays an explicit override.
- `composer test:adopt` on a minimal non-starter host app fixture, run in CI.
- `kit:status` reports every installed lineage (`lineages`: name, version, identity, features, file states) instead of one `artifact`; `blueprint_features` is the union.
- `kit:list`: prints the catalog's features with what each requires and owns. Read-only.
- `Blueprint::entries()`: the same listing as data.
- `bin/privacy-check.sh`, `AGENTS.md` and `CLAUDE.md`.
- `Catalog`: a feature catalog outside the package, with its own pinned identities. `Catalog::at($root, $pins, $basePin)` builds one, `Catalog::package()` is this package's own. `Blueprint::features()`, `Blueprint::all()` and `Blueprint::featureIds()` take an optional catalog and default to the package catalog. An external catalog's identities are checked as strictly as the package's.
- `files_from` in a feature manifest: a tree of stubs expands into generated files, sorted, with symlinks, escapes and duplicate targets refused. The feature identity covers every expanded stub.
- `CatalogHost`: a `LifecycleHost` that also implements it supplies the catalog `kit:update` builds its blueprints from.
- A seven-feature fixture catalog and `tests/CatalogCheck.php`, run in CI.

## starter-blueprint v0.1.0 (predecessor package)

### Added

- Per-feature manifests, `foundation` and `defaults`, each declaring its owned files, requirements, conflicts and host-owned paths. Every feature is content-addressed into the blueprint's source reference, and the whole app is all features.
- First adoption with no existing baseline, and add-only upgrades as the feature selection grows. Removing a feature is refused with a clear message.
- `starter:maintain {plan|apply|resume}` with `--original`, `--target`, `--features`, `--original-features`, `--intent`, `--expected-plan` and `--exclusive-checkout`. Every refusal or incomplete run exits nonzero.
- The `tey/starter-foundation` scaffold for previewing and generating the files through Mod's public scaffolds.
- The `LifecycleHost` contract an app implements for its app ID, bindings, input files and operation check, plus executor provenance and input fingerprints bound into each run.
- An upgrade's original must equal the installed artifact exactly, metadata included, or it is refused before Mod runs.
- Pinned release fixtures A–D: A `sha256:21bd1704…45e2`, B `sha256:70af6427…726c`, C `sha256:85405850…a3ff`, D `sha256:a85ea6fe…7aaf`; feature `foundation` `sha256:8e2e55ee…4054`, `defaults` `sha256:fb252b8b…2e43`.
- Requires `tey/mod` ^0.8 and `tey/concierge` ^0.7.2. CI proves the lifecycle on PHP 8.4 and 8.5 against the released packages: first adoption, plan and apply, additive upgrade, refused removal, an unowned path, resume and a cold retry. Adopting into an app that already has files the blueprint would own is refused until Mod can claim existing files.
