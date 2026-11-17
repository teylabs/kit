# Avatars

A person's avatar is their photo, or their initials on **their colour**. The colour is one per person and the same everywhere they appear: the shell's user
menu and drawer, notes and comments, the People table, the You widget, and an activity feed.

`ring` draws a white ring around an avatar. The shell's account avatar (the bar, the account menu, the drawer) uses it, so every colour and a photo read on a dark bar as well as a light page.

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
(for example `media.color`) or as `avatar_color`, so it does not hash its own. A declared colour lets a feed pick black or white initials by contrast; every
kit colour is chosen for white initials, so either works.
