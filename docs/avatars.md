# Avatars

A person's avatar is their photo, or their initials on **their colour**. The colour is one per person and the same everywhere they appear: the shell's user
menu and drawer, notes and comments, the People table, the You widget, and an activity feed.

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

A feed that draws its own initials discs (Storyfeed) should get the same colour. Pass it on the actor entity: Storyfeed's `Avatar::color()` uses a declared
`media.color` first, then `data.avatar_color`, and only then hashes the entity id into its own palette. Prefer `media.color`: Storyfeed then picks black or
white initials by contrast, while a `data.avatar_color` disc always gets white initials (every kit colour is chosen for white, so either works).
