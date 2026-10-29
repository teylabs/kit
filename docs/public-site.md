# Public site

A public site is a host whose pages are mostly public: a landing, help and legal pages, and a sign-in for the few people who run it. It adopts core, shell and session-ui, puts its public pages in the PublicShell, and keeps registration off. It has no workspaces, no workspace pages and sends no invitations. The shell feature shares the `shell` prop itself, so the PublicShell's footer and CTA get their data.

## Adopting it

```sh
composer require tey/kit
php artisan kit:install
php artisan kit:add core shell session-ui
php artisan kit:add core shell session-ui --apply --expected-plan=<the plan the preview printed>
php artisan migrate
```

Don't add `workspace-ui`, `workspaces` or `access`. Since 0.6.0 session-ui needs only core and shell, so `kit:add` brings nothing else: no workspace or permission tables, no Concierge, Pennant or multitenancy. Your `App\Models\User` stays a plain Laravel user. Without workspace-ui the kit serves no home, dashboard, settings, People, account pages under `/me` or invitation page. See [Auth-only session-ui and workspace-ui](../README.md#auth-only-session-ui-and-workspace-ui).

session-ui's avatars are Media Library items, so run spatie/laravel-medialibrary's `media` migration if your app doesn't have it yet.

An app that adopted this recipe before 0.6.0 has workspaces and access installed. They keep working, and can stay.

## Config

- **`KIT_HOME`** (`kit.home`, `/` by default). Without workspace-ui it's your own page, where sign-in lands. If your landing is at `/`, set `KIT_HOME=/app` (or wherever your signed-in page lives) and serve that route yourself.
- **Registration stays off.** The kit has no Register page, and its Fortify defaults don't turn on `Features::registration()`. If you publish `config/fortify.php`, leave it out of `features`. Concierge's `registration.mode` defaults to `closed`; keep it.
- **No invitations.** The invitation page (`Auth/Invitation/Show`) is workspace-ui's, so it isn't installed. Create the few accounts yourself, from a seeder or `php artisan tinker`.

## The PublicShell

Route your public pages to the PublicShell in your layout callback, and the auth pages to the GuestShell:

```ts
// resources/js/app.ts
layout: (name: string, page) =>
    name.startsWith('Public/') || name === 'Landing' || (name === 'Workspace/Error/Page' && page.props.shell?.kind === 'guest') ? PublicShell
    : name.startsWith('Auth/') ? GuestShell
    : Layout,
```

Pass the CTA's URLs (`sign-in-url="/login"`, and `workspace-url` set to your `kit.home`). See [docs/public-shell.md](public-shell.md) for the slots, widths and PublicMeta.

The shell's host steps apply too: the `@kit/shell` alias in `vite.config.ts` and `tsconfig.json`, and the kit's classes in the TypeScript transformer. `kit:add` and `kit:status` name any you lack.

## Checking it

`php artisan kit:status` lists the adopted features (core, shell, session-ui) and any unmet host step.
