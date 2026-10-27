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

`Tey\Kit\Features\Core\Toast` flashes Inertia's `toast`: `{type, message, description?, action?, from?, style, duration}`. The type is `success`, `info`, `warning` or `error`.

- **An action** is a single item: a label and an `href`, with an optional `get`, `post`, `put` or `patch` method. It is never destructive. A label starting with a verb such as delete, remove, discard or revoke is refused, as is a `delete` method. A destructive step needs a confirmation, not a toast. A toast with an action stays up for 8 seconds; one without stays up for 4.
- **Bad input is refused, not repaired.** An unknown type or style, a blank message or an action with extra keys throws `InvalidArgumentException`.

## When a controller toasts

- **Saves: yes.** A short confirmation such as "Changes saved".
- **Background and async results: yes.** For example, "Import finished" or "Export ready".
- **Errors: not as the only signal.** Anything the person must fix is a validation error or an inline response next to the field or row. An `error` toast can add to that, but it never replaces it.
- **Navigation: no.** The new page is the confirmation.

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
| `fly-out` (default for success) | The toast animates from the trigger into the resolved position (FLIP, about 300ms). | Page saves from a toolbar or a header button |
| `anchored` | A short "✓ message" bubble is pinned to the trigger and dismisses itself. There is no shell toast. | Table row actions and inline toggles, where the row is the context |
| `pulse` | A normal shell toast, plus a brief ring on the trigger | When the toast should point back at what changed without moving |
| `plain` (default for the other types) | A shell toast only | Background results and anything without a trigger |

**Fallback.** You get a plain toast at the shell position when there is no `from`, or when the trigger is missing, gone (a closed modal, a removed row, a new page) or offscreen.

**Reduced motion.** Under `prefers-reduced-motion`, nothing flies: a fly-out becomes a plain toast plus a pulse. The anchored bubble still appears, without animation.

## Coverage

- `tests/ToastCheck.php` covers the PHP helper: the payload, the refusals, `from` and `style`, and the shared position.
- `tests/js/toast.test.mjs` covers each `auto` branch, trigger resolution, the fallbacks, reduced motion and the shell's single mount.
- The kit has no browser harness, so the FLIP animation, the bubble's position and vue-sonner's rendering are not checked in a browser.
- There is no FocusShell layout component in the kit. A host that renders its own focus shell mounts `<Toaster />` there.
