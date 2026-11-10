# Deliver

`deliver` emails a subject (a report, an invoice) to its customer, with a signed customer link. A definition is registered in a provider with `app(Deliveries::class)->register(Delivery::define('report.deliver')->…)`; the full list of its options is in the docblock of `Tey\Kit\Features\Deliver\Delivery`. This page covers the customer link's refusals, the preview's document and copying every send.

## The customer link

The email's link is `GET d/{delivery}/{token}` (`kit.deliver.view`): a temporary signed URL, with a per-delivery token stored only as a hash. It lasts `linkDays()` (30 by default). Every answer carries `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow` and `Referrer-Policy: no-referrer`.

| The link | The answer |
| --- | --- |
| Correctly signed, the right token, not expired, not revoked | `customerPage()`, or by default the attachment as it is now |
| A bad or missing signature, a tampered `expires`, an unknown delivery, a malformed or wrong token | The same bare 404, with no reason |
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

`$why` is `'expired'` or `'revoked'`. The status is the page's own (return a 410 to keep the contract; a redirect is allowed). It runs inside the delivery's workspace. Keep what it shows to what anyone holding the old link may see: the link is still in the customer's mailbox.

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

## Copying every send

`internalCopies()` only pre-fills the panel's Bcc, which the sender can clear. To copy every real send to fixed addresses (an archive mailbox, say), give the definition a `CopiesSends`:

```php
use Tey\Kit\Features\Deliver\CopiesSends;

final class ArchiveCopies implements CopiesSends
{
    /** @return list<string> */
    public function copyTo(mixed $subject): array
    {
        return [config('reports.archive_address')];
    }
}

Delivery::define('report.deliver')->copySends(new ArchiveCopies);
```

- The addresses are Bcc'd on every real send: a send now, a resend, and a scheduled send (asked for when it is scheduled, sent when the sweep runs it). Never on a test send or a preview.
- Each is recorded as a recipient with the role `copy`, so the history shows it and its delivery status.
- Addresses are lower-cased and trimmed, and any already among the To, Cc or Bcc entered, or repeated in the list, is dropped (case-insensitively).
- It fails closed without blocking the send: an empty or invalid address is skipped, and a `copyTo()` that throws copies no one.

A definition without `copySends()` sends exactly as before.

## Finalizing through your own model

`finalizeFlow()` records the kit finalization each send went out under (`finalization_id`) and reads `kit_finalizations` to tell whether it is still final. An app that finalizes through its own model (a `finalized_at` column, say) has no `kit_finalizations` row, so give the definition a `ResolvesFinalization` (or a closure of the same shape) that names what is final now, and gate it with `gate()`:

```php
use Tey\Kit\Features\Deliver\ResolvesFinalization;

final class AssignmentFinalization implements ResolvesFinalization
{
    public function finalizationRef(Model $subject): ?string
    {
        return $subject->finalized_at === null ? null : $subject->getKey().'@'.$subject->finalized_at->toIso8601String();
    }
}

Delivery::define('assignment.deliver')
    ->gate(fn (Assignment $a): true|string => $a->finalized_at !== null ? true : 'Finalize it first.')
    ->finalizationRef(new AssignmentFinalization);
```

A send, a resend and a scheduled send store the ref as `finalization_ref` on the delivery row. The history shows a delivery as "no longer final" once the current ref differs from the stored one (re-finalizing changes `finalized_at`, so it changes the ref), and says "Sent without finalizing." only when a row has neither a `finalization_id` nor a `finalization_ref`. A definition without `finalizationRef()` reads `kit_finalizations` exactly as before.

`finalization_ref` is the column to backfill for rows sent before the definition had a ref, which otherwise read "Sent without finalizing.". For example, in a migration of your own:

```php
DB::table('kit_deliveries')
    ->join('assignments', 'assignments.id', '=', 'kit_deliveries.subject_key')
    ->where('kit_deliveries.definition', 'assignment.deliver')
    ->where('kit_deliveries.test', false)
    ->whereNull('kit_deliveries.finalization_id')
    ->whereNull('kit_deliveries.finalization_ref')
    ->whereNotNull('assignments.finalized_at')
    // Select the delivery's id explicitly: both tables have an `id`.
    ->select(['kit_deliveries.id', 'kit_deliveries.subject_key', 'assignments.finalized_at'])
    ->orderBy('kit_deliveries.id')
    ->each(fn ($row) => DB::table('kit_deliveries')->where('id', $row->id)->update([
        'finalization_ref' => $row->subject_key.'@'.CarbonImmutable::parse($row->finalized_at)->toIso8601String(),
    ]));
```

Build the ref exactly as `finalizationRef()` does, or a backfilled row reads as no longer final.
