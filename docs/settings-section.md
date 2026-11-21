# SettingsSection

One block of a settings page: `title`, `description`, the body in the default slot, and footer `actions` (descriptors, emitted as `action`) or a `#footer` slot. It draws its own card on any ground, so a List, EmptyState or DescriptionList inside draws no second one. `level` is the heading level (2 by default).

```vue
<SettingsSection title="Accounts" description="Where each kind of sale is booked." :actions="[save]">
    <List label="Accounts"><ListRow title="Room hire" description="4010 · Rental income" /></List>
</SettingsSection>
```

Tailwind and the theme tokens only. Browser specimen: `?section-shapes` (`&dark`), with `KIT_SECTION_SHAPES_SCREENSHOT=<path prefix>` saving each scheme at 1440 and 390px.
