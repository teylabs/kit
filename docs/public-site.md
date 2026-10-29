# Public site

A public site is a host whose pages are mostly public: a landing, help and legal pages, and a sign-in for the few people who run it. It adopts core, shell and session-ui, puts its public pages in the PublicShell, and keeps registration off. It has no workspace pages and sends no invitations.

## Adopting it

```sh
composer require tey/kit
php artisan kit:install
php artisan kit:add core shell session-ui
php artisan kit:add core shell session-ui --apply --expected-plan=<the plan the preview printed>
php artisan migrate
```

Don't add `workspace-ui`. session-ui requires `workspaces` and `access`, so `kit:add` brings them too: they hold the members and roles behind the sign-in. Without workspace-ui the kit serves none of their pages: no home, dashboard, settings, People or account pages under `/me`. See [Auth-only session-ui and workspace-ui](../README.md#auth-only-session-ui-and-workspace-ui).

The `workspaces` feature still needs the `user_model` host step: your `App\Models\User` implements `Tey\Concierge\Contracts\Member` and uses `HasWorkspaces` (the [quick start](../README.md#quick-start) has the lines). Until it does, the plan is not executable.

## Config

- **`KIT_HOME`** (`kit.home`, `/` by default). Without workspace-ui it's your own page, where sign-in lands. If your landing is at `/`, set `KIT_HOME=/app` (or wherever your signed-in page lives) and serve that route yourself.
- **Registration stays off.** The kit has no Register page, and its Fortify defaults don't turn on `Features::registration()`. If you publish `config/fortify.php`, leave it out of `features`. Concierge's `registration.mode` defaults to `closed`; keep it.
- **No invitations.** session-ui still installs the invitation page (`Auth/Invitation/Show`), but without workspace-ui there are no People pages to send one from. Create the few accounts yourself, from a seeder or `php artisan tinker`.

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

`php artisan kit:status` lists the adopted features (core, workspaces, access, shell, session-ui) and any unmet host step.
