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

### UserMenu, WorkspaceSwitcher, ShellDrawer, and the header layout in Layout.vue (`resources/features/session-ui/pages/Workspace/`) — FLAGGED
- Author: starter · live shell, 2026-10-20
- Spec: none (not in batch 1)
- Sources: fresh; shadcn-vue (MIT, dropdown-menu, sheet) over Reka UI (DropdownMenu*, Dialog*), re-exported through `@kit/ui`. Also informed by a reference app's shell components, read-only, before the clean-room rule (that app's names and paths are kept out of the repo).
- Statement: Tailwind Plus and the cookbook's @/UI source were not read. The reference-app reading means these are not "cleared sources only": rewrite from cleared sources or accept explicitly.
- Tests: tests/UrlConventionTest.php, tests/SettingsLandingTest.php, tests/Browser/SignIn.php (starter)
- Reviewed: pending
