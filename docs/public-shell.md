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
