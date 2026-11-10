# Deliver

`deliver` emails a subject (a report, an invoice) to its customer, with a signed customer link. A definition is registered in a provider with `app(Deliveries::class)->register(Delivery::define('report.deliver')->…)`; the full list of its options is in the docblock of `Tey\Kit\Features\Deliver\Delivery`. This page covers the customer link's refusals and the preview's document.

## The customer link

The email's link is `GET d/{delivery}/{token}` (`kit.deliver.view`): a temporary signed URL, with a per-delivery token stored only as a hash. It lasts `linkDays()` (30 by default). Every answer carries `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow` and `Referrer-Policy: no-referrer`.

| The link | The answer |
| --- | --- |
| Correctly signed, the right token, not expired, not revoked | `customerPage()`, or by default the attachment as it is now |
| A bad or missing signature, a tampered `expires`, an unknown delivery, a wrong token | The same bare 404, with no reason |
| Correctly signed with the right token, but expired | 410 |
| Correctly signed with the right token, but revoked | 410 |

The 404s cannot be told apart: each does the same lookup and the same constant-time hash comparison before refusing, and each throws the same empty `NotFoundHttpException`, which the app's own 404 page draws.

The signature is checked apart from its expiry (`URL::hasCorrectSignature()`), so a link whose signed `expires` has passed, or whose token is past its `token_expires_at`, is told from a tampered one. Revoked means `token_revoked_at` is set (Revoke link, or a resend that revokes the original). Revoked wins over expired.

### The expired page

By default an expired or revoked link is a plain 410 page that names neither the subject nor the recipient:

- expired: "This link has expired. Ask the sender for a new one."
- revoked: "This link is no longer available. Ask the sender for a new one."

A definition can answer with its own page instead:

```php
Delivery::define('report.deliver')
    // …
    ->expiredPage(fn (Report $report, DeliveryRecord $delivery, string $why): Response => response()->view('reports.link-gone', ['why' => $why], 410));
```

`$why` is `'expired'` or `'revoked'`. It runs inside the delivery's workspace. Keep what it shows to what anyone holding the old link may see: the link is still in the customer's mailbox.

### Signed-in members go to the subject

When someone signed in opens an expired or revoked link, and they may read the subject (the workspace's `deliver.read` permission, then the definition's `can()`), they are redirected to the subject's `url()` instead of the 410. Anything else (a guest, someone outside the workspace, a definition without `url()`, a check that throws) gets the 410. A link that still works shows the customer page to everyone.

## The preview's document

The panel's Preview builds the email exactly as it would be sent, and names the document that goes with it (attached, or linked because it is too large). By default that document is `attachment()`. A definition whose final PDF is expensive, or only exists once the subject is final, can show a draft instead:

```php
Delivery::define('report.deliver')
    ->attachment(fn (Report $report): ?DeliveryAttachment => $report->finalPdf())
    ->previewAttachment(fn (Report $report): ?DeliveryAttachment => $report->draftPdf());
```

`previewAttachment()` is called only for a preview (the panel's Preview, its document link, and the MCP preview tool). A send, a test send and a scheduled send always use `attachment()`. Without `previewAttachment()`, Preview is unchanged.

Loading the panel calls neither closure: the panel's state offers the document link whenever the definition has an `attachment()` or `previewAttachment()`, and the link is shown only once a preview names a document.
