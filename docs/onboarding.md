# Onboarding

An invited person goes from the invitation email to the workspace on kit screens, with one email in all:

1. **Invitation email.** Concierge issues the invitation; session-ui sends the kit's branded email (the inviter's name and message) in place of Concierge's plain one.
2. **Invitation page** (`/invitations/{token}`). It explains the invitation and offers the next step: accept, sign in, or create the account.
3. **Create the account.** Name and password, with the password rules shown before typing. The invited address is verified by the invitation itself, so no verification email follows.
4. **Welcome steps**, then the workspace, where an app with passkeys offers "Add a passkey for next time".

## The invited address is verified

The token in an invitation reaches only the invited address, so using it proves that address:

- A new account made from an invitation is created verified.
- A signed-in account that isn't verified yet, whose email is the invited one, is verified when it opens or accepts a member invitation, or accepts a workspace (tenant) invitation.
- An account with a different address is never verified by someone else's invitation.

The kit fires no `Registered` event for an invited account, and even if the app's own listener runs, Laravel's `SendEmailVerificationNotification` skips a verified user.

## Password policy

The kit's default follows NIST 800-63B: **at least 8 characters, and not found in a known data breach**, with no composition rules (no required mixed case, numbers or symbols).

- session-ui sets it as `Password::defaults()` (`Tey\Kit\Features\SessionUi\PasswordPolicy`). An app that calls `Password::defaults()` in its own provider replaces it, because the app's providers boot after the kit's.
- Every kit password field validates with `Password::defaults()`: invitation sign-up and change password.
- The breach check uses Laravel's `uncompromised()` (Have I Been Pwned's range API, k-anonymous). Its failure reads "This password has appeared in a data breach, so it isn't safe. Pick another.", from `kit::validation.password.uncompromised`. Override it in `lang/vendor/kit/{locale}/validation.php`.
- Existing users aren't asked to change their password. The rule applies the next time a password is set.

### The rules under the field

`<PasswordInput rules />` shows the rules under a new-password field before anything is typed (`PasswordRules`): "At least 8 characters", ticked as soon as it is met, and "Not found in a known data breach (checked when you submit)". The numbers come from the rule in force (`kitPassword`, shared on every page from `Password::defaults()`), so an app's own minimum shows correctly. The list is joined to the input's `aria-describedby`.

## Passkey offer

After an invited sign-up, an app with passkeys shows "Add a passkey for next time" at the top of the first shell page (`PasskeyOffer`, in the shell layout).

- **Add a passkey** runs Fortify's own registration: the options from `/user/passkeys/options`, the browser's `navigator.credentials.create()`, then the credential to `/user/passkeys`. It uses the browser's WebAuthn JSON helpers (`PublicKeyCredential.parseCreationOptionsFromJSON`, `credential.toJSON()`), so no client library is needed. A browser without them isn't offered a passkey.
- The person has just chosen their password, so adding a passkey straight away doesn't ask for it again.
- **Not now** (or the close button) dismisses the offer for good (`DELETE /me/passkey-offer`). A failed or cancelled attempt says so and leaves the offer open.
- The offer is kept per person until it's used or dismissed, and it disappears once they have a passkey.

It's an optional integration (`kit:status` lists it under session-ui). To turn it on:

1. Add `Features::passkeys()` to `features` in `config/fortify.php`.
2. Run `laravel/passkeys`' migration (the `passkeys` table).
3. Make `App\Models\User` implement `Laravel\Fortify\Contracts\PasskeyUser` and use `Laravel\Fortify\PasskeyAuthenticatable`.

Without these, nothing is offered.

## Checks

`composer test:onboarding` covers the policy, the breach message, one email for an invited sign-up, every invite path verifying the address, change password, and the offer's presence and dismissal. `composer test:onboarding-js` covers the rules list, the fields that show it, and the passkey ceremony. Neither runs a browser.
