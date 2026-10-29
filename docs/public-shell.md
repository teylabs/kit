# PublicShell

`PublicShell` (`pages/Workspace/Partials/PublicShell.vue`, the shell feature) is the chrome for public, sessionless pages: a landing, help, legal documents and a guest's error page. It's built like the GuestShell and shares its Wordmark. A host keeps its own landing (`kit.home` stays optional) and puts it in this shell.

## Using it

The host's one layout callback picks the shell for each page. Route the public pages to PublicShell:

```ts
// resources/js/app.ts
import PublicShell from '@/pages/Workspace/Partials/PublicShell.vue';
// Give it its CTA's URLs once: a small wrapper, or `h(PublicShell, { signInUrl: '/login', workspaceUrl: '/me/workspaces' }, …)`.

layout: (name: string, page) =>
    name.startsWith('Public/') || name === 'Landing' || (name === 'Workspace/Error/Page' && page.props.shell?.kind === 'guest') ? PublicShell
    : name.startsWith('Auth/') ? GuestShell
    : Layout,
```

`kit:update` points this out: the shell feature's `mention` step is met when `resources/js/app.ts` (or another Vite entry) imports the kit's PublicShell, `pages/Workspace/Partials/PublicShell`. A layout of your own named PublicShell (`@/layouts/PublicShell.vue`) doesn't meet it. It's attention, never blocking; without it these pages keep the GuestShell.

## What it renders

- **A skip link** to `#content`. It's the first focusable element, and `main#content` takes focus.
- **The header:** the product lockup (Wordmark; the `mark` slot replaces the symbol), a `nav` slot for the page's own links (a labelled `nav`), and a call to action.
  - The CTA is "Sign in" for a guest and "Open your workspace" for a signed-in viewer. It reads only the shared shell's `kind`, nothing else about the viewer.
  - The shell knows no session route, so pass the URLs (`sign-in-url="/login"`, `workspace-url="/me/workspaces"`). It shows only when the URL for the viewer's state is given.
  - The `cta` slot replaces it, and `:cta="false"` hides it.
- **Main**, at `width`:
  - `prose`, a reading measure for legal and help pages;
  - `wide` (the default);
  - `full`.
- **The footer:** the shared `shell.footer` (identity, links and credit, from the brand config through `Registry::webFooter()`). It's the same source as the app's footer, so they never disagree. The `footer` slot adds to it.
- **`tone="brand"`** puts the header and footer on the primary colour. It follows dark mode, and `appearance` shows the switch.
- **Toasts:** it mounts the kit Toaster, as the app shell does, so a public form's flashed toast shows after its redirect (a contact form's `Toast::success("Thanks, we'll be in touch")`). Pass `:toaster="false"` when the page mounts its own.
- **Nothing personal, and no tracking:** no user, workspace or permission is read, so the page can be cached.

## A branded site

Everything here is opt-in; without it PublicShell renders as above.

```vue
<PublicShell width="site" tone="bar" align-bars :pin-footer="false" :cta="false" :appearance="false" :theme="page.followsSystemTheme ? 'system' : 'light'"
    home-label="Example home" footer-note="© 2026 Example Ltd" :footer-links="[{ label: 'Privacy', url: '/privacy' }, { label: 'Contact', url: '/contact' }]">
    <template #mark><Symbol /></template>
    <template #nav><Link href="/work" class="py-3">Work</Link></template>
    <template #cta><ProfileLinks class="hidden sm:flex" /></template>
    <template #footer-mark><Symbol class="size-4" /></template>
    <slot />
</PublicShell>
```

- **Measure.** `width="site"` is `max-w-6xl`, beside `prose`, `wide` (the default) and `full`. With `align-bars`, the header and footer rows sit on the same measure as main, with the same `px-6`. `:padded="false"` drops main's padding, for an edge-to-edge hero.
- **Header.** `tone="bar"` colours it with the shell's own tokens, `--shell-bar` and `--shell-bar-foreground`, set in your `app.css`. Unlike `tone="brand"`, it doesn't use `--primary`, so a brand accent stays an accent. A transparent 1px border keeps the 65px pages are laid out against. With no `product` (a host without session-ui), the `mark` slot alone links to `home-url`, named by `home-label`. Put profile icons in the `cta` slot, which sits at the right.
- **Footer.** `:pin-footer="false"` lets it flow straight after the content instead of sitting at the viewport's foot. `footer-note` (the © line) and `footer-links` give it without session-ui's shared shell, and the `footer-mark` slot leads the row. With `align-bars` it's one row on the measure, without a top rule.
- **Theme.** `theme="light"` keeps a page with no dark styling yet light under `.dark`: it restates the neutral surface tokens (background, card, popover, muted, accent, border, input) with their light values, through kit.css's `.kit-theme-light`. A brand's own `--primary` stands. Pass `theme="system"` (the default) on pages that follow the system theme.
- **Toaster.** Keep `:toaster="false"` if your app already mounts one, so toasts don't show twice.

## Page meta: PublicMeta

```vue
<PublicMeta title="Pricing" description="Plans for every team." url="https://example.com/pricing" image="https://example.com/og/pricing.png" />
```

It sets the title, the description, the canonical link, and the Open Graph and Twitter cards through Inertia's `Head`.

## Legal documents: `Public/Markdown`

```php
use Tey\Kit\Features\Shell\PublicMarkdown;

Route::get('/legal/terms', fn () => PublicMarkdown::page('Terms of service', file_get_contents(resource_path('legal/terms.md')), updated: '1 November 2026'));
```

- The markdown is rendered and **sanitized on the server**: raw HTML in the source is stripped, and `javascript:` and other unsafe links are refused. The page receives only that HTML.
- `Public/Markdown/Page` shows the title, an optional "Last updated" and the body at a reading measure.

## Coverage

- `composer test:public-shell`: markdown sanitizing (script tags, event handlers and `javascript:` links), and the host step (met, unmet, missing file, malformed).
- `composer test:js` (`public-shell.test.mjs`): the skip link, the footer from the shared shell, the CTA states, the Toaster and its `toaster` opt-out (and that a flashed public toast renders), the phone width (wrapping, gutters, 44px targets), landmarks, no tracking, the meta tags, and the Markdown page's single `v-html` and lack of a layout. All by source.
- Not covered: no browser renders it at 390px or runs axe, or shows a toast after a real redirect.
