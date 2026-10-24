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

Text is escaped; `markdown()` is the raw escape hatch for author-written strings only. The composer registers its own view namespace and component path, so no provider is needed. It uses the kit's branded layout when `mail.brand` is applied, Laravel's otherwise.

## Keeping the rule

`MailConformance::offenders()` returns the offending files (empty means conforming). In a host, one architecture test enforces it:

```php
use Tey\Kit\Mail\MailConformance;

test('app mail goes through notifications and the fluent composer', function (): void {
    expect(MailConformance::offenders(app_path()))->toBe([]);
});
```

Use `notificationsOnly()` or `fluentComposer()` to enforce one half.
