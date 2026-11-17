# Avatars

A person's avatar is their photo, or their initials on **their colour**. The colour is one per person and the same everywhere they appear: the shell's user
menu and drawer, notes and comments, the People table, the You widget, and an activity feed.

## The gap ring

Every avatar has a **gap ring**: 2px in the colour of the surface it sits on, the avatar-group pattern. It parts overlapping avatars and keeps a disc off the
edge of whatever it sits on, and it never moves the layout (it is a box-shadow). `:ring="false"` leaves it off.

The colour is `--avatar-ring`, falling back to the page background (`--background`), so light and dark come from the theme's tokens. Each surface sets it to
its own background:

| Surface | `--avatar-ring` | Set by |
| --- | --- | --- |
| The page, a dialog, a modal, a sheet | `--background` | the fallback; Dialog and Modal; kit.css on `[data-slot='dialog-content']`, `[data-slot='sheet-content']` |
| A card: Surface, Card, NoteCard, CommentForm | `--card` | their markup; kit.css on `[data-slot='card']` |
| A menu, popover or select list | `--popover` | MenuContent, Popover; kit.css on the host's `dropdown-menu-content`, `dropdown-menu-sub-content`, `popover-content` and `select-content` slots |
| An inverse Surface (the FloatingToolbar pill) | `--foreground` | Surface |
| The shell's bar (the Layout, FocusShell) | `--shell-bar`, else `--background` | the shell stubs |

A surface of the app's own sets it on its element: `class="bg-muted [--avatar-ring:var(--muted)]"`.

## The initials

The initials are white or black, whichever contrasts more with the colour (`avatarInk()` in `avatarColor.ts`, `AvatarColor::ink()` on the server). Every
palette colour takes white (5.02:1 to 6.47:1); a light stored colour, such as amber `#f59e0b`, takes black. The better of the two is 4.58:1 or more on any
colour, so the initials pass WCAG AA (4.5:1) whatever colour a person stores.

| Colour | Initials | Contrast |
| --- | --- | --- |
| `#0369a1` sky | white | 5.93:1 |
| `#7c3aed` violet | white | 5.70:1 |
| `#be185d` pink | white | 6.04:1 |
| `#b45309` amber | white | 5.02:1 |
| `#047857` emerald | white | 5.48:1 |
| `#b91c1c` red | white | 6.47:1 |
| `#4f46e5` indigo | white | 6.29:1 |
| `#0f766e` teal | white | 5.47:1 |

## The colour

`Tey\Kit\Features\Core\AvatarColor` gives it:

```php
AvatarColor::of($user);            // the user's stored `avatar_color` (#rrggbb) when they have one, else derived from their key
AvatarColor::for($user->getKey()); // derived from any stable identity
```

The derivation is a hash of the identity into an eight-colour palette (sky, violet, pink, amber, emerald, red, indigo, teal). Nothing needs storing and the
colour never drifts. An app that wants people to pick their own adds an `avatar_color` column (or accessor); a valid `#rrggbb` there wins.

Every palette colour carries white initials at 4.5:1 or better and stands at 3:1 or better against both the light and the dark page, so one colour serves
both themes. `tests/js/avatar-color.test.mjs` checks all three.

The kit's JavaScript has the same function, `avatarColor(identity, stored?)` from `@/components/Kit` (Layout/avatarColor.ts), and the test proves both sides
agree, so a page can derive the colour from a user id when the server did not send one.

## Avatar

`<Avatar :initials :src :color />`: with a photo the colour is unused. Without one, the initials sit on `color`, or on the colour derived from `identity`
(a user id). With neither, the neutral accent disc, as before.

The kit sends the colour as `avatar_color` wherever it sends a person: the shell user (`ShellData`, `FocusShellData`), People table members, the You widget's
person. Comment and note authors (`CommentAuthor`) take `avatar_color`; SectionHeader's `avatar` descriptor takes `color`.

## An activity feed

An activity feed that draws its own initials discs should get the same colour: pass `AvatarColor::of($user)` on the actor, as the feed's declared colour
(for example `media.color`) or as `avatar_color`, so it does not hash its own. A declared colour lets a feed pick black or white initials by contrast (`AvatarColor::ink()`); every
kit colour is chosen for white initials.
