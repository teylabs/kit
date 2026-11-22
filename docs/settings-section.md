# SettingsSection

One block of a settings page: `title`, `description`, the body in the default slot, and footer `actions` (descriptors, emitted as `action`) or a `#footer` slot. It draws its own card on any ground, so a List, EmptyState or DescriptionList inside draws no second one. `level` is the heading level (2 by default).

```vue
<SettingsSection title="Accounts" description="Where each kind of sale is booked." :actions="[save]">
    <List label="Accounts"><ListRow title="Room hire" description="4010 · Rental income" /></List>
</SettingsSection>
```

Tailwind and the theme tokens only. Browser specimen: `?section-shapes` (`&dark`), with `KIT_SECTION_SHAPES_SCREENSHOT=<path prefix>` saving each scheme at 1440 and 390px.

## Section icons

A settings section's `icon` (`Registry::settingsSection(…, icon: 'receipt')`) is a lucide name in kebab case, drawn on the settings landing's card and in the sidebar by `SectionIcon`. The kit keeps an explicit allow-list, one import per name, so the bundle stays bounded: `bell`, `building-2`, `calendar`, `chart-column`, `credit-card`, `file-signature`, `hand-heart`, `key-round`, `mail`, `newspaper`, `palette`, `plug`, `receipt`, `settings`, `shield-check`, `sliders-horizontal`, `user` and `users`. Any other name draws the generic `settings` gear on a settings section. A module sidebar item (`modulePage(…, icon:)`, shell.md) draws no icon when it has none.

A section landing with exactly one group whose label equals the page title (Admin's landing, grouped as Admin) draws no group heading, so the title doesn't read twice. A landing with several groups keeps every heading. Adding a name is a kit change to `SectionIcon.vue`.
