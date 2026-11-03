# Upgrading from 0.5.x to 0.7

The 0.5.x line is frozen (2026-11-02): it takes no new features, and fixes land on main first. This page takes an app from v0.5.97 or later straight to the latest 0.7 in one pass (a first app went 0.5.116 → 0.7.8). The per-release Upgrading notes in the [CHANGELOG](../CHANGELOG.md) from v0.6.0 on still apply. This page collects the ones that need a person rather than `kit:update`.

Several of these changes were backported to 0.5.x, so an app on a late 0.5.x has some of them already. Each item says what to check, and an item whose check already passes needs nothing.

## Procedure

1. Branch the app. On the branch, require `tey/kit:^0.7.8` (or the latest 0.7) and run `composer update tey/kit tey/mod tey/standards tey/concierge spatie/image laravel/mcp --with-dependencies`.
2. Read the [two ownership moves](#060-the-session-ui-split) below before running anything. They decide which `--lineage` runs come first.
3. For each lineage the app has, run `php artisan kit:update plan --lineage=<feature>`, read the plan, then `apply`. **Follow the `order` line each plan prints**: core and shell first, then session-ui, access, and then workspace-ui, settings and mcp. A file you edited shows as a conflict, never an overwrite: merge it by hand, then run `apply` again.
   - **"Changed migration" stops `apply`** (for example session-ui `000501`/`000503`, access `000300`, settings `000400`), with a host step to add a new migration. First diff the app's copy against `vendor/tey/kit/resources/features/<feature>/migrations/<file>.php.stub`. If only docblocks, types or adoption-only logic changed, an existing database needs nothing: re-run with `--accept-changed-migrations`. Write a new migration only when the diff changes the schema.
4. Run `php artisan migrate`.
5. Run `php artisan kit:status` until it exits 0. Since v0.6.10 it exits 1 on any unmet host requirement, and since v0.7.7 on declared permissions missing from the database (`php artisan migrate --force` puts them back).
6. Work through the [checklist](#checklist), then run the app's suite and browser tests.
7. Deploy to staging, check sign-in, a workspace page, settings and an MCP connection, then deploy to production.

## From v0.5.97 to v0.5.115

`kit:update` and the checklist below cover most of these releases. An app on an earlier 0.5.x also checks these:

- [ ] **PaneTabs looks different (v0.5.106).** The pressed pane is a neutral raised segment in a muted track, not a primary-filled button. Its props, events and semantics are unchanged. A host that styled around the old button, or asserted its classes, checks its phone tabs.
- [ ] **A `useSubmitForm` handler over Inertia's `useHttp` moves to `httpSubmit` (v0.5.105).** `useHttp` resolves `undefined` on a 422, so a handler that awaited it showed Saved for a refused save. See [docs/submit-form.md › With Inertia's useHttp](submit-form.md#with-inertias-usehttp).
- [ ] **core's `requires.npm` adds `class-variance-authority` and `tw-animate-css` (v0.5.100).** Add them to package.json; `kit:status` names any that are missing.
- [ ] **Its own `resources/views/vendor/mail` (v0.5.110)** keeps its views. To leave the manage-notifications link off security mail there too, give the `message` and `footer` views the `manage` prop ([docs/mail.md › Account mail](mail.md#account-mail)). Mail links are now built on `app.url`, so they read https behind a TLS-terminating proxy.
- [ ] **Deleting a workspace or an account asks for the current password (v0.5.114),** unless `kit.lifecycle.confirm_password` is `false`. A host that edited `Workspace/Settings/Delete/Page.vue` or `Workspace/Account/Delete/Page.vue` adds the password field itself, and a client calling `DELETE /@{workspace}/settings/delete` sends `password`. An app that keeps deletion off has nothing to do.
- [ ] **The stale-write 409 (v0.5.99)** and the other behaviour changes are in the [checklist](#behaviour-changes-that-can-break-tests) below.

## 0.6.0: the session-ui split

session-ui now needs only core and shell. The invitation page (`Auth/Invitation/Show` and its routes), the owner's `Welcome/WorkspaceTimezone` step, the first-run demo's `demo.login`, and workspace and account deletion belong to **workspace-ui**.

- **With session-ui and workspace-ui both installed:** run `kit:update --lineage=session-ui` first. It releases the moved pages. Then `--lineage=workspace-ui` claims them at their current bytes. Nothing is deleted and nothing is rewritten.
- **With session-ui alone:** one run. The moved files stay on disk as the app's own (listed as "kept").

## Checklist

### Dependencies

- [ ] Kit's floors are `tey/mod ^0.11.6`, `spatie/image ^3.9`, `laravel/mcp ^1.0.2` and `tey/standards ^0.11.54` (v0.7.0 to v0.7.5). Step 1's `composer update` raises them.
- [ ] `kit.people.hide_grants` with any hidden grants needs `tey/concierge` 0.9.3 or later. On an older one, the app refuses to boot and names the setting.

### Build and types

- [ ] **An app that aliases `@kit/ui` to its own module** (a barrel re-exporting kit components by name) exports every name kit's barrel does (`resources/js/components/Kit/index.ts` after `kit:update`, from `vendor/tey/kit/resources/features/core/components/Kit/index.ts.stub`). Updated kit pages import new ones (`DateTime`, `Breadcrumbs`), and a missing name breaks the build.
- [ ] **`kit.d.ts` comes from vendor.** Point `tsconfig.json`'s `include` and Vite's `globalTypeFiles` at `vendor/tey/kit/types/kit.d.ts`, and delete any copy under `resources/js` (a stale copy hides new types).
- [ ] **New kit pages** (Register in v0.7.7): an ESLint config that ignores kit pages one by one adds them, and an MCP/route parity test (such as `tests/Arch/McpParityTest`) excludes the Register route.
- [ ] **CI step timeouts:** a kit upgrade touches most of the app, so test impact analysis selects nearly the whole suite. A first app needed about 12 minutes with pcov on 2 cores, and raised its step timeout to 16.

### Config and environment

- [ ] **Money inputs no longer default to CAD.** Set `KIT_CURRENCY=CAD` (`kit.currency`) to keep it.
- [ ] An app that owns `config/multitenancy.php` lists `ConciergeContextTask` and `FlushFeatureCacheTask` in `switch_tenant_tasks`. `kit:status` fails without them.
- [ ] **Its own `home` route or invitation flow:** set `kit.home_name`, or `kit.routes.session-ui.invitations => false`.
- [ ] **Mail:** set `KIT_BRAND_MAIL_LOCKUP` to show the brand lockup. Since v0.6.7, mail is branded only once `kit.brand.*` has no placeholders left, or when `kit.brand_mail` is `true`.
- [ ] **MCP:** `kit.mcp.limits` (in-flight and per-minute caps on `/mcp`, from v0.7.7) has safe defaults. Set `total` below the app's PHP worker count.

### Theme (only for an app with its own theme that doesn't import `kit.css`)

- [ ] Define `--destructive-text`, `--success-text`, `--warning-text` and `--info-text` for light and dark. The CHANGELOG's v0.6.13 and v0.7.0 entries give the kit's AA values as a starting point. Until they're defined, the theme host step reads unmet.

### Code the app may have written around kit

These can usually be deleted.

- [ ] An Inertia middleware of the app's own that shared `kitPassword`, `passkeyOffer` or `environment`. session-ui shares them itself since v0.6.8.
- [ ] A `prependToPriorityList` for `PrepareKitRequest`, or a re-share of kit props in a browser test case. Kit puts its props back on every request since v0.6.13.
- [ ] Re-declared `/.well-known/oauth-*` routes. The mcp feature serves them since v0.7.0.

### Behaviour changes that can break tests

- [ ] **A stale write on an Inertia request is a JSON 409** (`{"error":"stale_write"}`), not the error page (v0.7.1).
- [ ] **An unregistered `redirect_uri` from a browser** gets kit's 400 page, not Passport's 401 JSON. JSON requests keep the 401 (v0.7.0).
- [ ] **Loopback redirect URIs match on any port** (RFC 8252), as Claude Code needs (v0.7.0).
- [ ] **More than 3 header or Workbench `actions`** fold the rest into More. Set `priority` to keep one visible (v0.6.9).
- [ ] **`StartKitRequest` moved** to `Tey\Kit\Features\Core\Http\Middleware`. The old name still works as a deprecated alias (v0.7.1).
- [ ] **Breadcrumbs (v0.6):** the shell no longer renders `[data-shell-breadcrumbs]`, and `<Page>` draws the ancestors only. A test that asserts the current page's crumb fails.
- [ ] **Boot refuses an ability gated on an undeclared capability**, and names the ability, the capability and the feature (v0.7.7).

### New migrations

- [ ] mcp's `kit_mcp_registrations` table (v0.7.0). Until it's migrated, dynamic client registration answers 500.
- [ ] workspaces' `deleting_at` columns and session-ui's account deletions table (v0.7.7). These only matter for an app whose purge handler opts into deferred deletion.

### Optional, new since 0.5

- Navigator row menus from a PHP `WorkbenchItem` with `actions` (v0.6.13). Regenerate `types/kit.d.ts` for it.
- `<FormStatus flash-key="notice" />` for an app that flashes its own confirmation with `Inertia::flash()` (v0.6.13).
- The Register page, deferred deletion, the keep-the-user-row option and password-confirmed workspace deletion (v0.7.7).
