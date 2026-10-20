# UI provenance log

The kit's UI set is clean-room: written from cleared sources only (the Laravel Vue starter kit and shadcn-vue, both MIT; Reka UI; or fresh), with Tailwind utilities and no hand-rolled focus or keyboard logic. One entry per component. Cookbook will set the format and location; until then this file is the log.

Every entry below states: **author** (this lane, Claude Code working for Jasper Tey), **source**, the **Reka UI part** it sits on (none for non-interactive pieces), and **"no Tailwind Plus or held @/UI code read"**.

## Form set (`core`: `resources/js/components/Kit/`, behind the `@kit/ui` alias)

| Component | Source | Reka UI part | Statement |
| --- | --- | --- | --- |
| Field | fresh; wraps the starter's `Label` (shadcn-vue, MIT) | `Label` (via the starter's wrapper) | No Tailwind Plus or held @/UI code read |
| Input | fresh wrapper over the starter's `Input` (shadcn-vue, MIT) | none (native input) | No Tailwind Plus or held @/UI code read |
| Textarea | fresh; Tailwind classes follow the starter's `Input` (shadcn-vue, MIT) | none (native textarea) | No Tailwind Plus or held @/UI code read |
| Select | fresh | `SelectRoot`, `SelectTrigger`, `SelectValue`, `SelectPortal`, `SelectContent`, `SelectViewport`, `SelectItem`, `SelectItemText`, `SelectItemIndicator` | No Tailwind Plus or held @/UI code read |
| TimezoneSelect | fresh (over Select and the browser's `Intl.supportedValuesOf`) | as Select | No Tailwind Plus or held @/UI code read |
| Checkbox | fresh wrapper over the starter's `Checkbox` (shadcn-vue, MIT) | `CheckboxRoot`, `CheckboxIndicator` (via the starter's wrapper) | No Tailwind Plus or held @/UI code read |
| Button | fresh wrapper over the starter's `Button` (shadcn-vue, MIT); `primary`, `secondary`, `destructive` map to its variants | none | No Tailwind Plus or held @/UI code read |
| FormActions | fresh | none | No Tailwind Plus or held @/UI code read |
| Section | fresh (a bordered card on the theme tokens) | none | No Tailwind Plus or held @/UI code read |

The menu and sheet pieces behind `@kit/ui` (`DropdownMenu*`, `Sheet*`) are the starter's shadcn-vue wrappers (MIT) over Reka UI's `DropdownMenu*` and `Dialog*`, re-exported unchanged for now. Each becomes a fresh, owned wrapper when it is rebuilt to Cookbook's spec.

## Shell and page stubs (`session-ui`, `notes`, `settings`)

| Piece | Source | Reka UI part | Statement |
| --- | --- | --- | --- |
| Breadcrumbs, EnvPill, UserAvatar, PrimaryNav, Sections index page, Note show page, the settings and notes forms | fresh | none (links and static markup) | No Tailwind Plus or held @/UI code read |
| UserMenu, WorkspaceSwitcher, ShellDrawer | see the flag below | `DropdownMenu*`, `Sheet*` (through `@kit/ui`) | See the flag below |

**Flag, to review before these are called clean-room.** Before the clean-room rule, the shell convergence brief had this lane read a reference app's shell components (its layout, product switcher and user menu; read-only, with names and paths kept out of the repos). `UserMenu`, `WorkspaceSwitcher`, `ShellDrawer` and the header layout in `Layout.vue` were written after reading those, so their provenance is "fresh, informed by a reference app's shell", not "cleared sources only". Tailwind Plus code and Cookbook's held `@/UI` code were not read. These pieces should be rewritten from cleared sources, or accepted explicitly, when the shell is rebuilt to Cookbook's spec.

## Order of work

Form set first (live on arbitter.test now), then Dialog, DropdownMenu, Tabs, Switch, RadioGroup, Combobox, Popover, Tooltip and Toast, each on Reka UI. Cookbook's spec list and baselines (cookbook #192, batch 1) set the order and the details when they arrive.
