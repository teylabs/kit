# Changelog

## Unreleased

### Upgrading

- **People is absent while `workspace.settings` is off.** Settings › People, its modals and its writes now return 404 when the capability behind `members.manage` is off for the workspace or person, like every other surface of an off capability. Before, the section was hidden but the page still opened for anyone holding `workspace.members.manage`.

### Added

- Capability guard (#54). `composer test:capability-guard` walks every capability in the Registry, kit and app alike. It switches each one off for a workspace and checks that every page from its navigation links, settings sections and placeholders is a 404. It checks that its links, sections and MCP tools leave the shared shell, page props, settings listings, the capability listing and the MCP tool list, and that its abilities are refused as `capability_unavailable`. Switched back on, everything returns. A capability with nothing the guard can see fails it. The kit has no dashboard-widget, command-palette or per-feature action-descriptor registry yet, so those are not checked, and a route served without a navigation link, settings section or placeholder is not discovered. Runs in CI.
- Page actions: `FloatingToolbar` (with `FloatingToolbarDot` and the `provideSurface` / `useSurface` surface context), `ActionBar` (with `ActionLabel`, `layoutActions`, `placeActions` and `isNavigation`) and `SectionHeader`, the one header for sections, widgets, cards and panes (Cookbook 0c7151a `FloatingToolbar.md`, `ActionBar.md`, `SectionHeader.md`). FloatingToolbar takes its actions and status through its slot and reads no store. `Button` takes the pill shape and inverse tone inside a FloatingToolbar and is unchanged elsewhere. `Section` renders its title through SectionHeader; its props are unchanged, and its title now uses the heading font. `Tey\Kit\Features\Core\Data\Action` gains optional `intent`, `group`, `menu`, `icon` and `newTab` (defaults keep existing payloads valid). `composer test:js` runs the placement and number tests. Graduated from a client app. Re-pinned: package `core` `sha256:e84aceae…` → `sha256:167bc301…c5a00` (new stubs; `kit:update` delivers them).

### Fixed

- Settings › People stayed reachable by URL with `workspace.settings` off (found by the capability guard). `WorkspacePolicy::manageMembers` now denies as not found while the capability is off.

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
