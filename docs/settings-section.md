# SettingsSection

One block of a settings page: `title`, `description`, the body in the default slot, and footer `actions` (descriptors, emitted as `action`) or a `#footer` slot. It draws its own card on any ground, so a List, EmptyState or DescriptionList inside draws no second one. `level` is the heading level (2 by default).

```vue
<SettingsSection title="Accounts" description="Where each kind of sale is booked." :actions="[save]">
    <List label="Accounts"><ListRow title="Room hire" description="4010 · Rental income" /></List>
</SettingsSection>
```

Tailwind and the theme tokens only. Browser specimen: `?section-shapes` (`&dark`), with `KIT_SECTION_SHAPES_SCREENSHOT=<path prefix>` saving each scheme at 1440 and 390px.

## Section icons

A settings section's `icon` (`Registry::settingsSection(…, icon: 'receipt')`) is a lucide name in kebab case, drawn on the settings landing's card and in the sidebar by `SectionIcon`. The kit keeps an explicit allow-list, one import per name, so the bundle stays bounded; `Registry::ICONS` is the same list: `badge-check`, `bell`, `book-user`, `building`, `building-2`, `calendar`, `calendar-check`, `calendar-clock`, `calendar-days`, `chart-column`, `chart-pie`, `clipboard-list`, `clock`, `coffee`, `contact`, `credit-card`, `door-open`, `file-signature`, `files`, `frame`, `gauge`, `hand-heart`, `inbox`, `key-round`, `landmark`, `layout-grid`, `mail`, `mailbox`, `map-pin`, `message-circle-question`, `newspaper`, `package-plus`, `palette`, `percent`, `plug`, `receipt`, `repeat`, `router`, `settings`, `shield-check`, `shopping-cart`, `sliders-horizontal`, `sparkles`, `tablet-smartphone`, `tags`, `ticket`, `timer`, `trending-up`, `user`, `user-check`, `user-plus` and `users`. A name outside the list stops the boot in local and testing (`Registry::checkIcons()`), naming the section or page; elsewhere it draws the generic `settings` gear. A module page (`modulePage(…, icon:)`, shell.md) draws its icon in its sidebar item, tab or pill, and none when it has none.

A section landing with exactly one group whose label equals the page title (Admin's landing, grouped as Admin) draws no group heading, so the title doesn't read twice. A landing with several groups keeps every heading. Adding a name is a kit change to `SectionIcon.vue`.
