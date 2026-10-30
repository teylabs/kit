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

The branded views read `mail.brand`: `identity`, `sender`, `credit`, `legal_name`, `address`, `contact`, `manage_url`, `mark` and the colour tokens `accent`, `accent_foreground`, `accent_strong` and `ink` (from `kit.brand`). A host's own published mail views can read further keys it merges into `mail.brand` after the kit has applied it (for example in a service provider's `boot`).

## Keeping the rule

`MailConformance::offenders()` returns the offending files (empty means conforming). In a host, one architecture test enforces it:

```php
use Tey\Kit\Mail\MailConformance;

test('app mail goes through notifications and the fluent composer', function (): void {
    expect(MailConformance::offenders(app_path()))->toBe([]);
});
```

Use `notificationsOnly()` or `fluentComposer()` to enforce one half.
