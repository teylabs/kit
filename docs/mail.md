# Mail

All mail is a Notification composed with `Tey\Kit\Mail\FluentMailMessage`. No Mailables, no `Mail::` facade calls (ad-hoc recipients use `Notification::route('mail', …)`), no mail templates.

```php
public function toMail(object $notifiable): FluentMailMessage
{
    return (new FluentMailMessage)
        ->subject('Your report is ready')
        ->preheader('Open it before Friday')
        ->headline('Report ready')
        ->facts(['Client' => $this->client, 'Due' => $this->due])
        ->button('Open the report', $this->url);
}
```

Text is escaped exactly once: user text such as "Ada's talk" or `<b>` shows as typed, in the HTML part and the text part, and never as an entity like `&#039;`. `markdown()` is the raw escape hatch for author-written strings only. The composer registers its own view namespace and component path, so no provider is needed. It uses the kit's branded layout when `mail.brand` is applied, Laravel's otherwise. Its paths are appended after the host's `mail.markdown.paths`, also on a renderer that was resolved first (a queue worker, an earlier mail), so a host's published `resources/views/vendor/mail` always wins.

The branded views read `mail.brand`: `identity`, `sender`, `credit`, `legal_name`, `address`, `contact`, `manage_url`, `mark`, `lockup` and the colour tokens `accent`, `accent_foreground`, `accent_strong` and `ink` (from `kit.brand`). A host's own published mail views can read further keys it merges into `mail.brand` after the kit has applied it (for example in a service provider's `boot`).

## The header: the brand lockup

The branded layout is Laravel's base markdown layout with the brand's lockup (the mark with the wordmark) centred above the message, linked to `app.url`. The button and links keep the brand accent. Set the image with `KIT_BRAND_MAIL_LOCKUP` (`kit.brand.mail_lockup`):

```dotenv
KIT_BRAND_MAIL_LOCKUP=/mail-lockup.png
```

- **A local PNG.** Give a path in `public/` (`/mail-lockup.png`) or an absolute file path. It's embedded in every message as an inline (CID) attachment, never linked as a remote image, so it shows without "load images" and works offline. SVG isn't used: mail clients don't show it reliably.
- **Size.** It's shown 220px wide, with the height kept to the image's aspect ratio (a 1152x208 PNG shows at 220x40). Use 2x that width or more so it's sharp on a high-density screen. Draw it for a light background.
- **Alt text** is the product's name (`APP_NAME`).
- **With no lockup** (unset, a missing file, or a file that isn't a PNG), the header is the product's name, centred, as in Laravel's default. It's never a broken image. `kit:status` reports a configured path that isn't a PNG as attention, not blocking.

`kit.brand.lockup` (`KIT_BRAND_LOCKUP`) is a separate switch: it shows the lockup in the shell's header and guest pages, not in mail.

## Account mail

With session-ui, Fortify's password reset and email address check are the kit's (`Tey\Kit\Features\SessionUi\Notifications\AccountMail`), not Laravel's stock "Hello!" mail. They use the kit's copy and sentence-case buttons ("Reset password", "Verify email address"). They're security mail, which a person can't opt out of, so they have no "Manage notifications" link. The reset link is built on `app.url`, so it's https on an https app even when the request or worker sees http. A host's own `ResetPassword::toMailUsing()`, `VerifyEmail::toMailUsing()` or `ResetPassword::createUrlUsing()` still wins. The email check's link is Laravel's signed URL, unchanged, because rewriting it would break its signature.

Your own security mail can leave out the link too: call `security()` on the `FluentMailMessage`. Mail to someone with no account here (an on-demand recipient such as `Notification::route('mail', …)`, a client sent a report) calls `external()`, the same switch: the link would point at an account page they can't open. A host's published `message` and `footer` views get the same switch as the `manage` prop. The manage link on other mail is also built on `app.url`.

## Security mail

With session-ui, the account owner is mailed when something security-relevant changes on their account. The mail comes from `SecurityLog`, the listener that already writes the account's security history:

- the password changed (from the account page or a reset);
- the email address changed, sent to the **old** address and naming the new one, so a takeover that moves the address doesn't go unnoticed;
- two-factor authentication confirmed or turned off;
- recovery codes regenerated;
- a passkey added or removed.

Each one is `Tey\Kit\Features\SessionUi\Notifications\SecurityNotice`. It's security mail: it has no manage link and can't be turned off per person. It says what changed, when, and from which IP address and browser, and offers **Reset password** for "this wasn't me". An app turns all of it off with `kit.security_mail` (`KIT_SECURITY_MAIL=false`). A mail that can't be sent is reported (the class names only) and never stops the change it describes.

**A first password is set, not changed.** An account's first password is logged as "Password set" and mails nobody, so a brand-new owner or an invitee choosing a password doesn't hear "your password was changed". The kit counts it as a first password in two cases:

- **No password yet:** the account had none (null or empty).
- **Marked:** it was marked with `SecurityLog::awaitFirstPassword($user)`. Use the mark when your app provisions an account with a random password and sends a reset link (`Tey\Kit\Features\SessionUi\Account\SecurityLog`). The first set clears the mark, and every later change is mailed as usual.

**Not covered:** sign-in alerts from a new device. They need device tracking, which the kit doesn't have yet; a sign-in is still written to the security history.

## Workspace mail identity

With the settings feature, each workspace has **Settings → Email sending**. Every `FluentMailMessage` built inside a workspace takes its sender from there. One resolver does this when the message is built, so no notification sets it itself. Mail built outside a workspace (account mail, password resets) keeps the platform's `mail.from`.

- **Sender name.** The workspace's name unless the workspace sets one.
- **From.** The platform address (`mail.from.address`), or an address on the workspace's own domain:
  - Saving a domain creates `send.<domain>` at the mail provider, and the page shows its DNS records to copy, with a status: pending, verified or failed.
  - **Check now** asks the provider to verify the domain.
  - Until the domain is verified, mail keeps the platform address with the workspace's sender name, so a pending or failed domain never stops mail.
  - Switching back to the platform address keeps the domain, so switching again doesn't create it twice.
- **Reply-To.**
  - `member`, the default: the member the mail is sent on behalf of, set with `->sentBy($user)` on the message. Mail with no sender falls back to the platform's `mail.reply_to`.
  - `fixed`: always the platform's `mail.reply_to`.
  - `custom`: an address the workspace sets.

An explicit `->from()` or `->replyTo()` on a message after it's built still wins.

**Provider.** The kit binds `Tey\Kit\Features\Settings\Mail\SendingDomains` to `ResendSendingDomains`. It needs a Resend key that can manage domains, in `services.resend.domains_key` (falling back to `services.resend.key`). Without a key, adding or checking a domain fails closed with a message on the domain field, and mail still sends from the platform address. Bind your own `SendingDomains` for another provider; tests bind a fake (`tests/MailIdentityCheck.php`), so no check calls a provider.

**Install.**
1. Run the `create_workspace_mail_identities_table` migration from the settings feature.
2. Publish the `Workspace/Settings/Email/Page` page.

Before the migration runs, mail resolves the defaults rather than failing.

**MCP.** `workspace_mail_identity_read` (the `settings.read` scope) returns the same fields as the page: sender name, from mode and address, the domain's status and DNS records, and the reply-to.

**Not covered.**
- No tool changes these settings over MCP.
- The kit doesn't poll the provider; the status changes when someone presses Check now.
- The provider's own domain-verification webhook isn't wired.

## Keeping the rule

`MailConformance::offenders()` returns the offending files (empty means conforming). In a host, one architecture test enforces it:

```php
use Tey\Kit\Mail\MailConformance;

test('app mail goes through notifications and the fluent composer', function (): void {
    expect(MailConformance::offenders(app_path()))->toBe([]);
});
```

Use `notificationsOnly()` or `fluentComposer()` to enforce one half.
