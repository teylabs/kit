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

Your own security mail can leave out the link too: call `security()` on the `FluentMailMessage`. A host's published `message` and `footer` views get the same switch as the `manage` prop. The manage link on other mail is also built on `app.url`.

## Keeping the rule

`MailConformance::offenders()` returns the offending files (empty means conforming). In a host, one architecture test enforces it:

```php
use Tey\Kit\Mail\MailConformance;

test('app mail goes through notifications and the fluent composer', function (): void {
    expect(MailConformance::offenders(app_path()))->toBe([]);
});
```

Use `notificationsOnly()` or `fluentComposer()` to enforce one half.
