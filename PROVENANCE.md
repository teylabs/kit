# Provenance

One section per UI component, in the format the specs set. The form set below was written before the batch 1 specs arrived, so each entry is **interim**: the component is not yet built to its spec (surface, browser tests, `useFormControl` wiring, baselines) and is renamed onto the spec's name when it is (Input to InputText, Textarea to InputTextarea, Checkbox to InputCheckbox, Select to SimpleSelect). "Reviewed" stays pending until Jasper's OK is relayed.

### Field (`resources/features/core/components/Kit/Form/Field.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/label); fresh wrapper; Reka UI part: Reka UI (MIT, Label, via the starter's wrapper)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Input (`resources/features/core/components/Kit/Form/Input.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/input); fresh wrapper; Reka UI part: none (native input)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Textarea (`resources/features/core/components/Kit/Form/Textarea.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; classes follow the Laravel Vue starter kit (MIT, components/ui/input); Reka UI part: none (native textarea)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Select (`resources/features/core/components/Kit/Form/Select.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: Reka UI (MIT, SelectRoot, SelectTrigger, SelectValue, SelectPortal, SelectContent, SelectViewport, SelectItem, SelectItemText, SelectItemIndicator)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### TimezoneSelect (`resources/features/core/components/Kit/Form/TimezoneSelect.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh (over Select and Intl.supportedValuesOf); Reka UI part: Reka UI (MIT, as Select)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Checkbox (`resources/features/core/components/Kit/Form/Checkbox.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/checkbox); fresh wrapper; Reka UI part: Reka UI (MIT, CheckboxRoot, CheckboxIndicator, via the starter's wrapper)
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Button (`resources/features/core/components/Kit/Form/Button.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/button); fresh wrapper; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### FormActions (`resources/features/core/components/Kit/Form/FormActions.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Section (`resources/features/core/components/Kit/Form/Section.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

## Shell pieces (`session-ui`)

### Breadcrumbs, EnvPill, UserAvatar, PrimaryNav (`resources/features/session-ui/pages/Workspace/Partials/`)
- Author: starter · live shell, 2026-10-20
- Spec: none (not in batch 1)
- Sources: fresh
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed: written fresh.)
- Tests: tests/BreadcrumbsTest.php, tests/UrlConventionTest.php (starter)
- Reviewed: pending

### UserMenu, WorkspaceSwitcher, ShellDrawer, and the header layout in Layout.vue (`resources/features/session-ui/pages/Workspace/`)
- Author: starter · live shell, 2026-10-20
- Spec: none (not in batch 1)
- Sources: Tey Labs original: an internal design pass (2026-10-14), built on Laravel Vue starter kit (MIT) shadcn-vue primitives (dropdown-menu, sheet), over Reka UI (DropdownMenu*, Dialog*), re-exported through `@kit/ui`
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (No spec existed: written from the sources above.)
- Tests: tests/UrlConventionTest.php, tests/SettingsLandingTest.php, tests/Browser/SignIn.php (starter)
- Reviewed: Jasper, 2026-10-20 (kept as is; no rewrite)

### FocusPage (`resources/features/core/components/Kit/Layout/FocusPage.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the page-measure primitive Jasper asked for)
- Sources: Tey Labs original (internal app), ported; a size lookup (`xs` to `xl`) from the original, converted to a static class map with the shell owning the gutters, so no padding of its own
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used." (Ported from Tey Labs code, which Jasper pointed at to read.)
- Tests: tests/KitUiTest.php › test_focus_page_sizes_are_a_static_lookup (starter)
- Reviewed: pending

### Page (`resources/features/core/components/Kit/Layout/Page.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the page container Jasper asked for: title, actions, width, states)
- Sources: Tey Labs original (internal app), ported: the FocusPage size lookup above, now internal, under a header and state slots written fresh
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_page_widths_are_a_static_lookup, test_every_kit_page_roots_in_page_and_fixed_content_declares_a_width (starter)
- Reviewed: pending

### PasswordInput (`resources/features/core/components/Kit/Form/PasswordInput.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (a password input with a show/hide toggle, on Reka's Toggle)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### AppearanceToggle (`resources/features/core/components/Kit/Appearance/AppearanceToggle.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the header's light/dark switch, on Reka's Toggle)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### AppearanceChoice (`resources/features/core/components/Kit/Appearance/AppearanceChoice.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the light/dark/system choice, on Reka's ToggleGroup)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### useAppearance (`resources/features/core/components/Kit/Appearance/useAppearance.ts.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the appearance setting (storage, cookie, theme class))
- Sources: Laravel Vue starter kit (MIT), moved into the kit
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### SectionIcon (`resources/features/core/components/Kit/Layout/SectionIcon.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Settings design (landing card grid and a sidebar inside sections); screenshots used for comparison only, no reference-library source opened
- Sources: fresh, with Lucide icons (ISC) and Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/SettingsLandingTest.php and the browser sign-in test (starter)
- Reviewed: pending

### SettingsFrame (`resources/features/core/components/Kit/Layout/SettingsFrame.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Settings design (landing card grid and a sidebar inside sections); screenshots used for comparison only, no reference-library source opened
- Sources: fresh, with Lucide icons (ISC) and Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/SettingsLandingTest.php and the browser sign-in test (starter)
- Reviewed: pending

### Avatar (`resources/features/core/components/Kit/Layout/Avatar.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Avatar upload (the photo or initials; a file chooser)
- Sources: fresh, with Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/AvatarTest.php and the browser sign-in test (starter)
- Reviewed: pending

### FileInput (`resources/features/core/components/Kit/Form/FileInput.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Avatar upload (the photo or initials; a file chooser)
- Sources: fresh, with Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/AvatarTest.php and the browser sign-in test (starter)
- Reviewed: pending

### People page (`resources/features/session-ui/pages/Workspace/Settings/People/Page.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: People (settings): members, pending invitations, invite with a role, change role, remove, resend, revoke; Concierge owns the rules
- Sources: fresh, from the kit's own components (Page, SettingsFrame, Section, Field, Input, Select, Button, Avatar); no reference-library source opened
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/PeopleTest.php and tests/SettingsFormsTest.php (starter)
- Reviewed: pending

### Invitation page (`resources/features/session-ui/pages/Auth/Invitation/Show/Page.vue.stub`)
- Author: starter · live shell, 2026-10-21
- Spec: the invite-accept screen: the workspace and inviter, sign in or create the account the invitation is for, accept; a clear page for an expired, withdrawn, used or unknown link
- Sources: fresh, from the kit's own components (Page, Field, Input, PasswordInput, Button); no reference-library source opened
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/InvitationAcceptTest.php (starter)
- Reviewed: pending

### FormActions, FormCard, FormFieldset and ConfirmDialog (`resources/features/core/components/Kit/Form/`)
- Author: starter · live shell, 2026-10-21
- Spec: the form patterns (a six-column card, a status-then-Cancel-then-Save bar, sticky and tinted only while pinned, a leave-page warning, a confirmation for every destructive action)
- Sources: Tey Labs original: internal form patterns (2026-10), built on Reka UI
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/SettingsFormsTest.php and tests/Browser/FormActions.php (starter)
- Reviewed: pending

### Account pages (`resources/features/session-ui/pages/Workspace/{Profile,Password,Security}/Edit/Page.vue.stub`, `Auth/TwoFactor`, `Auth/VerifyEmail`)
- Author: starter · live shell, 2026-10-21
- Spec: Account pages under /me: profile with email re-verification, password, security (two-factor with a confirmed setup, recovery codes, signed-in devices, the user's own security log) and the two-factor challenge
- Sources: fresh, on Fortify's own actions and features (MIT) and the kit's own components; no reference-library source opened
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/AccountTest.php and tests/Browser/Account.php (starter)
- Reviewed: pending

### DataTable, RowActions and Dialog (`resources/features/core/components/Kit/{Table,Overlay}/`) and the server TableQuery (`src/Features/Core/Tables/`)
- Author: starter · live shell, 2026-10-21
- Spec: a server-driven index table (search, sort, filter, tabs with counts and pagination in the URL; each row's actions decided on the server; stacked cards on a phone), from a written pattern and `DataTable-spec.md`
- Sources: TanStack Table (MIT, headless, `@tanstack/vue-table`), spatie/laravel-query-builder (MIT), Reka UI (MIT) and the kit's own components; fresh Tailwind utilities on the markup; no reference-library source opened
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/TableQueryCheck.php (kit), tests/PeopleTest.php and tests/Browser/People.php (starter)
- Reviewed: pending

### People index (`resources/features/session-ui/pages/Workspace/Settings/People/Page.vue.stub`)
- Author: starter · live shell, 2026-10-21
- Spec: Members and Invitations tabs rendering Concierge's MembersIndex as the kit's table, a change-role dialog and an invite dialog on Form + FormActions; no membership logic in the kit
- Sources: fresh, on Concierge's published table props and the kit's own components; no reference-library source opened
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/PeopleTest.php and tests/Browser/People.php (starter)
- Reviewed: pending

### Repeater and NumberInput (`resources/features/core/components/Kit/Form/{Repeater,RepeaterRow,repeaterState,NumberInput,number}`)
- Author: a client app's proven candidates, graduated into the kit (2026-10-24); pointer drag removed so the kit takes no extra dependency
- Spec: the Repeater and NumberInput specs (native bracket-path rows with staged deletion and a nested level; exact-number strings with integer minor units, fixed-scale decimals and BigInt arithmetic)
- Sources: Tey Labs original on Vue, Inertia and Reka UI (MIT) and the kit's own Field and Dialog; fresh Tailwind utilities; no Tailwind Plus source
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/js/number.test.mjs (node --test: number codec and Repeater error mapping)
- Reviewed: pending

### ScrollList (`resources/features/core/components/Kit/List/` and `src/Features/Core/Lists/`)
- Author: a client app's proven candidate, graduated into the kit (2026-10-24), with its app-specific row, empty state and dashboard classes replaced by kit-local ones
- Spec: the ScrollList target, cursor-only subset. NOT included from the spec: sorts, segments, totals, polling, page and simple paging modes, `ScrollWidgetViewModel`, `HasListItem` and typed-resource rows. The server half is a fixed-selection, count-free cursor list whose encrypted continuation binds viewer, workspace, list, selection and mode and fails closed (400) on replay or tampering
- Sources: Tey Labs original on Laravel cursor pagination, Inertia's infinite scroll and the kit's own Alert, Button and RowActions; fresh Tailwind utilities; no Tailwind Plus source
- Statement: "Written without reading Tailwind Plus or the reference library's source; only the spec and baseline screenshots were used."
- Tests: tests/ScrollListCheck.php (cursor round trip, replay across viewer/workspace/list/selection, tampering, scope, empty and error states)
- Reviewed: pending

### ComputedValue and the Explanation contracts (`resources/features/core/components/Kit/Explanation/`, `Kit/Overlay/Popover.vue.stub`, `src/Explanation/`)
- Author: kit graduation 0.10.1, 2026-10-24
- Spec: the ComputedValue spec and conformance rules 17 and 70
- Sources: graduated by copy from a client app's clean-room candidate, itself written from those prose contracts only; Vue, Reka UI (MIT), Laravel Data (MIT) and the kit's own Modal. No other explanation renderer was consulted. The fixture is synthetic. Changes on graduation: kit namespace and import paths, the kit Modal's own sheet and labelling in place of the candidate's host-specific Modal props, and a reflection generator in place of the TypeScript transformer, emitting the same output byte for byte.
- Host responsibilities: authorising descriptors and source links, exact arithmetic and reconciliation, immutable snapshots, preference POST authorisation, workspace scoping. The tests prove none of these, nor clipboard permissions, screen-reader quality or rendered layout.
- Tests: tests/ExplanationCheck.php, tests/explanation/working.test.mjs
- Reviewed: pending
