# Provenance

One section per UI component, in the format Cookbook set. The form set below was written before the batch 1 specs arrived, so each entry is **interim**: the component is not yet built to its spec (surface, browser tests, `useFormControl` wiring, baselines) and is renamed onto the spec's name when it is (Input to InputText, Textarea to InputTextarea, Checkbox to InputCheckbox, Select to SimpleSelect). "Reviewed" stays pending until Jasper's OK is relayed.

### Field (`resources/features/core/components/Kit/Form/Field.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/label); fresh wrapper; Reka UI part: Reka UI (MIT, Label, via the starter's wrapper)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Input (`resources/features/core/components/Kit/Form/Input.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/input); fresh wrapper; Reka UI part: none (native input)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Textarea (`resources/features/core/components/Kit/Form/Textarea.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; classes follow the Laravel Vue starter kit (MIT, components/ui/input); Reka UI part: none (native textarea)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Select (`resources/features/core/components/Kit/Form/Select.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: Reka UI (MIT, SelectRoot, SelectTrigger, SelectValue, SelectPortal, SelectContent, SelectViewport, SelectItem, SelectItemText, SelectItemIndicator)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### TimezoneSelect (`resources/features/core/components/Kit/Form/TimezoneSelect.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh (over Select and Intl.supportedValuesOf); Reka UI part: Reka UI (MIT, as Select)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Checkbox (`resources/features/core/components/Kit/Form/Checkbox.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/checkbox); fresh wrapper; Reka UI part: Reka UI (MIT, CheckboxRoot, CheckboxIndicator, via the starter's wrapper)
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Button (`resources/features/core/components/Kit/Form/Button.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: Laravel Vue starter kit (MIT, components/ui/button); fresh wrapper; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### FormActions (`resources/features/core/components/Kit/Form/FormActions.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

### Section (`resources/features/core/components/Kit/Form/Section.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none yet (interim; will map to the spec of the same role)
- Sources: fresh; Reka UI part: none
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed yet: written from the cleared sources above.)
- Tests: tests/KitUiTest.php › test_kit_pages_import_ui_only_from_the_kit_alias (starter); the kit's starter catalog check for classes and imports
- Reviewed: pending

## Shell pieces (`session-ui`)

### Breadcrumbs, EnvPill, UserAvatar, PrimaryNav (`resources/features/session-ui/pages/Workspace/Partials/`)
- Author: starter · live shell, 2026-10-20
- Spec: none (not in batch 1)
- Sources: fresh
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed: written fresh.)
- Tests: tests/BreadcrumbsTest.php, tests/UrlConventionTest.php (starter)
- Reviewed: pending

### UserMenu, WorkspaceSwitcher, ShellDrawer, and the header layout in Layout.vue (`resources/features/session-ui/pages/Workspace/`)
- Author: starter · live shell, 2026-10-20
- Spec: none (not in batch 1)
- Sources: Tey Labs original: an internal design pass (2026-10-14), built on Laravel Vue starter kit (MIT) shadcn-vue primitives (dropdown-menu, sheet), over Reka UI (DropdownMenu*, Dialog*), re-exported through `@kit/ui`
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (No spec existed: written from the sources above.)
- Tests: tests/UrlConventionTest.php, tests/SettingsLandingTest.php, tests/Browser/SignIn.php (starter)
- Reviewed: Jasper, 2026-10-20 (kept as is; no rewrite)

### FocusPage (`resources/features/core/components/Kit/Layout/FocusPage.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the page-measure primitive Jasper asked for)
- Sources: Tey Labs original (internal app), ported; a size lookup (`xs` to `xl`) from the original, converted to a static class map with the shell owning the gutters, so no padding of its own
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used." (Ported from Tey Labs code, which Jasper pointed at to read.)
- Tests: tests/KitUiTest.php › test_focus_page_sizes_are_a_static_lookup (starter)
- Reviewed: pending

### Page (`resources/features/core/components/Kit/Layout/Page.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the page container Jasper asked for: title, actions, width, states)
- Sources: Tey Labs original (internal app), ported: the FocusPage size lookup above, now internal, under a header and state slots written fresh
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_page_widths_are_a_static_lookup, test_every_kit_page_roots_in_page_and_fixed_content_declares_a_width (starter)
- Reviewed: pending

### PasswordInput (`resources/features/core/components/Kit/Form/PasswordInput.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (a password input with a show/hide toggle, on Reka's Toggle)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### AppearanceToggle (`resources/features/core/components/Kit/Appearance/AppearanceToggle.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the header's light/dark switch, on Reka's Toggle)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### AppearanceChoice (`resources/features/core/components/Kit/Appearance/AppearanceChoice.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the light/dark/system choice, on Reka's ToggleGroup)
- Sources: Reka UI (MIT) and fresh; the starter's own hand-rolled version it replaces
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### useAppearance (`resources/features/core/components/Kit/Appearance/useAppearance.ts.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: none (the appearance setting (storage, cookie, theme class))
- Sources: Laravel Vue starter kit (MIT), moved into the kit
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/KitUiTest.php › test_hand_rolled_controls_live_only_in_kit_components (starter), the browser sign-in test
- Reviewed: pending

### SectionIcon (`resources/features/core/components/Kit/Layout/SectionIcon.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Settings design (landing card grid and a sidebar inside sections); screenshots used for comparison only, no Cookbook source opened
- Sources: fresh, with Lucide icons (ISC) and Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/SettingsLandingTest.php and the browser sign-in test (starter)
- Reviewed: pending

### SettingsFrame (`resources/features/core/components/Kit/Layout/SettingsFrame.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Settings design (landing card grid and a sidebar inside sections); screenshots used for comparison only, no Cookbook source opened
- Sources: fresh, with Lucide icons (ISC) and Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/SettingsLandingTest.php and the browser sign-in test (starter)
- Reviewed: pending

### Avatar (`resources/features/core/components/Kit/Layout/Avatar.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Avatar upload (the photo or initials; a file chooser)
- Sources: fresh, with Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/AvatarTest.php and the browser sign-in test (starter)
- Reviewed: pending

### FileInput (`resources/features/core/components/Kit/Form/FileInput.vue.stub`)
- Author: starter · live shell, 2026-10-20
- Spec: Avatar upload (the photo or initials; a file chooser)
- Sources: fresh, with Tailwind utilities
- Statement: "Written without reading Tailwind Plus or the cookbook's @/UI source; only the spec and baseline screenshots were used."
- Tests: tests/AvatarTest.php and the browser sign-in test (starter)
- Reviewed: pending
