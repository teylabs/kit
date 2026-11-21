# List and ListRow

Divided rows, in place of a hand-bordered `<ul class="rounded-md border">`. Inside a kit card (Card, SettingsSection, ConnectionCard, DetailSection, FormCard, a widget) a List draws only its dividers; straight on the page ground it draws its own card (`bg-card rounded-xl border`). Never a card inside a card.

ListRow: `title` and `description` (or the default slot), a `#meta` slot (a Badge, a date) and descriptor `actions` at the end. `label` names the List.

Tailwind and the theme tokens only. Browser specimen: `?section-shapes` (`&dark`), with `KIT_SECTION_SHAPES_SCREENSHOT=<path prefix>` saving each scheme at 1440 and 390px.
