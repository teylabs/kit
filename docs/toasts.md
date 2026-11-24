# Toasts

Toasts are headless. The controller decides whether to toast; the frontend never does. It renders what the server flashed, in one place the shell owns. There is no page-level `useToast()`.

```php
use Tey\Kit\Features\Core\Concerns\Toastable;
use Tey\Kit\Features\Core\Toast;

class CaseController
{
    use Toastable;

    public function update(UpdateCase $request, CaseFile $case)
    {
        // ...
        $this->toast('Changes saved')->from('save');

        return back();
    }
}

Toast::info('Import finished', '42 rows added');
Toast::success('Moved to Closed', null, ['label' => 'Undo', 'href' => route('cases.reopen', $case), 'method' => 'post']);
Toast::success('Role changed')->from('change-role')->style('anchored');
```

`Tey\Kit\Features\Core\Toast` flashes Inertia's `toast`: `{id, type, message, description?, action?, from?, style, duration}`. The type is `success`, `info`, `warning` or `error`.

`id` is a ULID that's unique per toast and kept when the same toast re-flashes (`->from()`, `->style()`). The Toaster shows each id once. So a full page load that carries a flash (a non-Inertia redirect such as an OAuth return), where the flash is read on mount and again from the router's first `flash` event, shows one toast. Two identical saves in a row still show two. A toast without an id, from an older server, is deduplicated by identity. `toastGate()` in Kit/Feedback/toastPlacement.ts implements this, and tests/js/toast.test.mjs covers it.

- **An action** is a single item: a label and an `href`, with an optional `get`, `post`, `put` or `patch` method. It is never destructive. A label starting with a verb such as delete, remove, discard or revoke is refused, as is a `delete` method. A destructive step needs a confirmation, not a toast. A timed toast with an action stays up at least 8 seconds (see [How long a toast stays](#how-long-a-toast-stays)).
- **Bad input is refused, not repaired.** An unknown type or style, a blank message or an action with extra keys throws `InvalidArgumentException`.

## When a controller toasts

- **Saves: yes.** A short confirmation such as "Changes saved".
- **Background and async results: yes.** For example, "Import finished" or "Export ready".
- **Errors: not as the only signal.** Anything the person must fix is a validation error or an inline response next to the field or row. An `error` toast can add to that, but it never replaces it.
- **Navigation: no.** The new page is the confirmation.

## How long a toast stays

`kit.toasts.durations` maps each type to milliseconds, or to `null` for a toast that stays until it's dismissed. The server resolves it when it flashes the toast and sends it as `duration`, so there's no `->duration()` to call.

```php
'toasts' => [
    'durations' => ['success' => 4000, 'info' => 4000, 'warning' => null, 'error' => null],
],
```

- **Defaults:** success and info stay 4 seconds. Warning and error stay until dismissed, because they report something to act on.
- **An action:** a timed toast with an action stays at least 8 seconds (`Toast::ACTION_DURATION`). A longer configured value is kept, and a persistent toast stays persistent.
- **A missing or unusable value** (not a positive integer, or absent from the map) falls back to that type's default.
- **Away:** if a timed toast is shown while the tab is hidden or the window isn't focused, its timer doesn't start until the person comes back (the window is visible and focused again). Then the normal duration runs, so nobody misses a toast they never saw. Persistent toasts aren't affected. `toastTiming()`, `isAway()` and `whenBack()` in Kit/Feedback/toastPlacement.ts implement this, and tests/js/toast.test.mjs covers it.

## Where it renders

The shell mounts one `Toaster` (Kit/Feedback/Toaster), in the Workspace Layout and the GuestShell. `kit.toasts.position` (`KIT_TOASTS_POSITION`, shared with every page as `kitToasts`) sets where it sits:

| Position | Where |
|---|---|
| `auto` (default) | Resolved as described below |
| `top-center` | Just below the shell's header and breadcrumb bar, offset by their height so it never overlaps them |
| `top-right` | Top right, below the header |
| `bottom-right` | Bottom right |
| `bottom-center` | Bottom centre, above the sticky bars |

`auto` resolves in this order:

1. Under `sm`: `bottom-center`, above the registered sticky bars and the safe-area inset. This applies to every position, not only `auto`.
2. With sticky bottom bars registered and right-aligned actions in the shell header (search, bell, avatar): `top-center`.
3. With sticky bottom bars only: `top-right`.
4. Otherwise: `bottom-right`.

`resolvePosition()` and `toastPlacement()` in Kit/Feedback/toastPlacement.ts return the resolved position, and tests/js/toast.test.mjs checks each branch.

Products differ here. Sonner, Linear and Vercel use bottom-right; Stripe and Notion use top-right; mobile apps use bottom-center. The setting is per app, never per page.

### Sticky bars

Bars pinned to the viewport's foot register their height with `useStickyBars()`, so a toast never covers them. `FloatingToolbar` does this while it floats. A form's own Save bar can join with `useStickyBars().track(el)`, which measures the element while it is mounted, or with `register(px)`.

## Anchored toasts

`from('save')` names the trigger by its action-descriptor key. The server has no knowledge of the DOM. Rendered descriptor buttons (ActionBar, RowActions, the action menus) already carry the key as `data-action`. The Toaster uses the element the person last activated with that key, which picks out the right row's button in a table, or else the only element on the page with that key.

| Style | What happens | Use it for |
|---|---|---|
| `fly-out` | The toast animates from the trigger into the resolved position (FLIP, about 300ms). | When the movement itself should draw the eye |
| `anchored` | A short "✓ message" bubble is pinned to the trigger and dismisses itself. There is no shell toast. | Table row actions and inline toggles, where the row is the context |
| `pulse` (default for success) | A normal shell toast, plus a brief ring on the trigger | Saves: the toast reassures, the ring points back at the button that saved |
| `plain` (default for the other types) | A shell toast only | Background results and anything without a trigger |

**Fallback.** You get a plain toast at the shell position when there is no `from`, or when the trigger is missing, gone (a closed modal, a removed row, a new page) or offscreen.

**Reduced motion.** Under `prefers-reduced-motion`, nothing flies: a fly-out becomes a plain toast plus a pulse. The anchored bubble still appears, without animation.

## Forms and history

- **Form saves have a trigger.** `FormActions` renders its submit button with `data-action` set to the submit descriptor's `key`, or `save` when there is none, so `->from('save')` finds it without the app adding anything.
- **History is the app's.** The Toaster dispatches a `kit:toast` window event with the flashed toast as `detail` each time it shows one. An app that keeps a history (a notification bell, say) listens for it; the kit keeps none.
- **Kit components without a page visit** (the Workbench's refused reorder) send a toast with a `kit:toast:show` window event, whose `detail` is a toast payload. The Toaster renders it like a flash, deduplicated by its `id`. Pages never send it: a page's toast comes from its controller.

## After a deploy: a new version

The Toaster also installs, once, a small client setup for a page that outlived a deploy (Kit/Feedback/newVersion.ts). It listens to two documented hooks and to nothing else:

- **`vite:preloadError`**: a lazy chunk the deploy removed failed to load. The error is handled (`event.preventDefault()`), then the page reloads (`window.location.reload()`), unless a form on it holds unsaved changes. Then the notice shows instead. Unsaved is the leave guard's own state (formFrame.ts's `pageUnsaved()`): a mounted `FormActions` or Workbench `:form` with warn-on-leave on whose form is dirty, or with pending work such as an upload. A form with `warn-on-leave` false, or one without a form frame, doesn't count.
- **Inertia's `location` event with `versionChange`**: a request found the server on a new asset version. The notice shows; Inertia's own handling is unchanged. On a background (async) request, such as `router.reload()` or a poll, Inertia keeps the page. This event needs `@inertiajs/vue3` 3.6 or later. The kit's floor is `^3.0`, and below 3.6 Inertia has no `location` event and reloads the page itself.

The notice is one quiet toast, "A new version is available.", with a Reload action that reloads the page. It stays until dismissed and shows once per page load, whichever hook fires first. With a form still dirty, Reload asks first through the leave guard (the browser's own prompt).

The failed chunk's own request is cheap: a missing file under the build directory is a plain 404 before the session starts ([deploy.md](deploy.md#a-missing-build-file)).

## Testing

The kit registers two `TestResponse` macros once the app has booted. They have the same names and arguments as inertiaui/notifications' own, so an app's tests keep passing when it moves to that package ([below](#inertia-notifications)):

```php
$this->post('/cases/1', $data)->assertToast('Changes saved', 'success');
$this->post('/cases/1', $data)->assertToast(fn (array $toast): bool => $toast['data']['kit']['from'] === 'save');
$this->get('/dashboard')->assertNoToasts();
```

- **What counts:** the toasts a response delivers. That's a flash after a redirect (or an Inertia location or redirect response), in an Inertia page's flash data (JSON or the first HTML view), or in the package's `X-InertiaUI-Toasts` header. A plain response leaves a flashed toast in the session for a later page, so it delivers none.
- **One shape under either driver.** Each toast reads as the package's: `title`, `type`, `description`, `actions`, `duration`, and the kit's trigger and style under `data.kit` (`from`, `style`). The kit's `toast` key and the package's list both count, one toast per id.
- **The type** is `success`, `info`, `warning` or `error`, or the package's `ToastType` enum.
- This replaces reading `session(SessionKey::FLASH_DATA)['toast']` by hand.

## Inertia Notifications

`kit.toasts.driver` (`KIT_TOASTS_DRIVER`) picks who delivers and renders toasts: `kit` (the default, the Toaster above) or `inertiaui` ([inertiaui/notifications](https://inertiaui.com/inertia-notifications)). Under `inertiaui`, nothing at a call site changes. `Toast::success(...)`, `$this->toast(...)->from('save')`, `->style()`, `kit:toast:show` and the shell's `<Toaster />` stay as they are, and every rule (types, one non-destructive action, durations) is still checked in `Toast`. Only the delivery moves:

- **Server.** A kit toast flashes into the package's list (`flash.inertiaui.toasts`), so it shows beside toasts the package makes itself and passes its `Toast::fake()` / response assertions. Its `uuid` and `id` are the kit's id, so a re-flash replaces its own entry. Its duration is the kit's (null stays until dismissed). Its trigger and style travel as `data.kit`. An `anchored` toast stays on the kit's own `toast` key, because it is no corner toast.
- **Client.** The shell's `<Toaster />` mounts the package's Toaster at the kit's resolved position (`kit.toasts.position`, `auto` included), moved under the shell's pinned top and above the sticky bars and the safe area. It still meets each kit toast's trigger: pulse and fly-out find the rendered toast by `data-toast-id`, and anchored shows the kit's bubble. It also dispatches `kit:toast` for each kit toast, renders `kit:toast:show` through the package's `toast()`, and shows the new-version notice there.

### Moving an app

1. `composer require inertiaui/notifications` and `npm install @inertiaui/notifications-vue`. The package needs `inertiajs/inertia-laravel` 3.8+, `@inertiajs/vue3` 3.11+ and Tailwind 4.3+.
2. In `resources/css/app.css`: `@import '@inertiaui/notifications-vue/theme.css';`, then `@import '@inertiaui/notifications-vue/shadcn.css';` (the toasts take the app's shadcn tokens), and `@source '../../node_modules/@inertiaui/notifications-vue/dist';`.
3. In `resources/js/app.ts`, before `createInertiaApp()`: `initNotifications()`, then hand the Toaster over (the kit never imports the package itself, so an app on the `kit` driver builds without it):

   ```ts
   import { Toaster, initNotifications, toast } from '@inertiaui/notifications-vue';
   import { bridgeInertiaUIToasts } from '@kit/ui';

   initNotifications();
   bridgeInertiaUIToasts({ Toaster, toast });
   ```

4. Set `KIT_TOASTS_DRIVER=inertiaui`. Don't mount the package's `<Toaster />` yourself: the shell's is the one Toaster. A second gives two stacks, and every toast shows twice.
5. Tests that read `session(...)['toast']` move to `assertToast()` / `assertNoToasts()` ([Testing](#testing)) first, while the app is still on the `kit` driver. The same assertions then pass after the switch.

### What the shim can't keep

- **The look.** Toasts render with the package's markup and theme (through its shadcn token map), not vue-sonner's rich colours. That's a visible change.
- **The guardrails, outside kit calls.** The kit refuses destructive actions, unknown types and more than one action only in `Toast`. A toast made with the package's own `Toast` or the browser `toast()` (`ToastAction::make('Delete')->delete()`, say) isn't checked.
- **One app-wide position.** The package also accepts `->position()` and named Toasters per toast. Kit calls never set them.
- **Timing while away.** The package's own pause (hover, focus, hidden tab, blurred window, at least one more second on resume) replaces the kit's "start the timer when they're back".
- **`kit:toast` history** covers kit toasts only. A toast the package makes itself, or an inbox arrival, isn't dispatched.
- **One toast per request** is gone: under `inertiaui`, two toasts in a request both show, in order.

## Coverage

- `tests/ToastCheck.php` covers the PHP helper: the payload, the refusals, `from` and `style`, and the shared position and driver.
- `tests/ToastDeliveryCheck.php` covers delivery over real requests under both drivers, with the real package booted. That includes the package's shape, one entry per re-flash, anchored on the kit's key, kit and package toasts in one list, and `assertToast()` / `assertNoToasts()` passing and failing.
- `tests/js/toast.test.mjs` covers each `auto` branch, trigger resolution, the fallbacks, reduced motion, the shell's single mount, and reading a kit toast back out of the package's list.
- `tests/js/new-version.test.mjs` covers the new-version decision (each hook, clean and unsaved), the handled error, the one persistent notice and its Reload. The browser job (`leave.html`, on the real `@inertiajs/vue3`) dispatches `vite:preloadError` with a clean form (the page reloads) and a dirty one (the notice shows, and the edit is kept), and answers a background reload with a version change (the page is kept, and the notice shows).
- The kit has no browser harness, so the FLIP animation, the bubble's position and vue-sonner's rendering are not checked in a browser.
- The kit's FocusShell (`@kit/shell`, which the Workspace Layout renders for a focus shell) mounts the Toaster, with `:toaster="false"` to leave it to the host's own root shell. A host that renders its own focus shell mounts `<Toaster />` there.
