# Typed confirmation

Some actions are destructive enough that a click isn't enough, such as replacing every record from a source or deleting a workspace. For these, the kit asks the person to type a piece of text, usually the thing's name, before Confirm turns on. The server then checks the same text again.

## On the page: Dialog `confirm-text`

```vue
<Dialog v-model:open="asking" variant="danger" title="Delete Acme Ltd?" confirm-label="Delete" confirm-text="Acme Ltd" :action="{ url: route('workspaces.destroy', workspace), method: 'delete' }" />
```

- A labelled input asks "Type Acme Ltd to confirm" (`confirm-text-label` overrides the wording). Pasting is fine.
- Confirm stays disabled until the text matches. A polite status announces when it does, and a hint under the input says Confirm stays off until then.
- The match is exact and case-sensitive. `:case-sensitive="false"` relaxes the case. Surrounding whitespace is ignored, but inner spacing isn't.
- Each opening starts empty. `@confirm` receives the typed text, and an `action` visit sends it as `confirmation`.

## From the server: a descriptor's `confirm.typed`

```php
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\TypedConfirmation;

new Action('Replace from source', route('sources.replace', $source), 'post', key: 'replace', destructive: true, menu: true,
    confirm: TypedConfirmation::confirm('Replace from source?', 'This overwrites every record with the source.', 'Replace', $source->name));
```

RowActions, BulkBar and ActionBar render it for you: the confirmation Dialog with the input, then the visit with `confirmation`.

- **ActionBar** runs a descriptor itself only when the host doesn't listen to `@action`. A host that listens runs it as before, including its own confirmation, and passes `confirm.typed` to its Dialog's `confirm-text`.
- Inside SectionHeader or EmptyState, the descriptor is re-emitted to the host, so the host runs it.

## The server re-check

The disabled button is never the guard. The operation behind the action checks `confirmation` first:

```php
public function replace(Request $request, Source $source): RedirectResponse
{
    Gate::authorize('update', $source);
    TypedConfirmation::require($request, $source->name); // ValidationException on `confirmation` unless it matches

    // …replace…
}
```

- `require($request|array, $expected, $caseSensitive = true)` refuses a missing, non-string or mismatched `confirmation`, with the message "Type “…” to confirm.".
- `matches()` is the same rule, returning a boolean.

## Coverage

- `composer test:typed-confirmation` covers the server re-check: missing, mismatched, wrong case, non-string and accepted, and the descriptor round trip.
- `composer test:js` (`typed-confirmation.test.mjs`) covers the match and the payload, and checks the Dialog, RowActions, BulkBar and ActionBar wiring by source.
- The components vue-tsc job compiles a fixture.
- Not covered: no browser types into the dialog.
