<?php

namespace Tey\Kit\Features\Deliver;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Deliver\Models\DeliveryRecord;
use Tey\Kit\Features\Finalize\Operations\FinalizeGate;

/**
 * A delivery definition: how a subject (a report, an invoice) is emailed to its customer. An app registers one in a provider with
 * `app(Deliveries::class)->register(Delivery::define(…)->…)`.
 *
 * - `subject($alias, resolve: fn (string $key, bool $lock): ?Model, key: fn (Model): string)`: as a finalize flow finds its subject.
 * - `notification(fn (Model $subject, DeliveryMail $mail): Notification)`: the app's Notification, built on FluentMailMessage. The preview and the send
 *   both call its `toMail()`, so what is previewed is what is sent; `$mail` carries the subject line, message, customer link (null in a preview) and
 *   whether the document is attached or linked. The kit adds Cc, Bcc, the attachment and the test marker.
 * - `attachment(fn (Model): ?DeliveryAttachment)`: the document (a PDF), or null. Larger than `maxAttachmentBytes()` it is linked, not attached.
 * - `recipients(fn (Model): list<array{address: string, name?: ?string, role?: 'to'|'cc'}>)`: the people a send may choose from, from LIVE records (a
 *   deliverable address), never from a finalize snapshot. `internalCopies(fn (Model): list<string>)` are the Bcc defaults (a workspace setting).
 * - `defaults(fn (Model): array{subject: string, message: string})`: the editable defaults (a workspace setting).
 * - `finalizeFlow('report.finalize')` gates sending on that flow and records the finalization sent; `gate(fn (Model): true|string)` replaces it (true
 *   sends, a string refuses with that reason). One of them is required; a gate that throws refuses.
 * - `finalizationRef(ResolvesFinalization|fn (Model): ?string)`: for an app that finalizes through its own model. Each send records the ref as
 *   `finalization_ref`, and the history compares it with the current one instead of reading `kit_finalizations`.
 * - `version(fn (Model): ?string)`: what the current document is; a scheduled send whose version changed before it runs is cancelled.
 * - `can(fn (Principal, Model, string $ability): bool)`, `url(fn (Model): string)`: as finalize's.
 * - `linkDays(30)`: how long a customer link lasts. `customerPage(fn (Model, DeliveryRecord): Response)`: what the link shows; the default downloads
 *   the attachment as it is now.
 * - `expiredPage(fn (Model, DeliveryRecord, string $why): Response)`: what a correctly signed link with the right token shows once it has expired or
 *   been revoked (`$why` is 'expired' or 'revoked'). The default is a plain 410 that names neither the subject nor the recipient.
 * - `previewAttachment(fn (Model): ?DeliveryAttachment)`: the document the panel's Preview shows instead of `attachment()` (a draft rendering, say).
 *   Called only for a preview, never on page load and never for a send.
 */
final class Delivery
{
    private ?string $alias = null;

    private ?Closure $resolve = null;

    private ?Closure $key = null;

    private ?Closure $notification = null;

    private ?Closure $attachment = null;

    private ?Closure $previewAttachment = null;

    private ?Closure $recipients = null;

    private ?Closure $internalCopies = null;

    private ?Closure $defaults = null;

    private ?string $finalizeFlow = null;

    private ?Closure $gate = null;

    private ?Closure $finalizationRef = null;

    private ?Closure $version = null;

    private ?Closure $can = null;

    private ?Closure $url = null;

    private ?Closure $customerPage = null;

    private ?Closure $expiredPage = null;

    private int $linkDays = 30;

    private int $maxAttachmentBytes = 10 * 1024 * 1024;

    private function __construct(public readonly string $name) {}

    public static function define(string $name): self
    {
        if (preg_match('/\A[a-z0-9]+(?:[-_.][a-z0-9]+)*\z/', $name) !== 1 || strlen($name) > 64) {
            throw new \InvalidArgumentException('Invalid delivery name: '.$name);
        }

        return new self($name);
    }

    public function subject(string $alias, callable $resolve, ?callable $key = null): self
    {
        $this->alias = $alias;
        $this->resolve = Closure::fromCallable($resolve);
        $this->key = $key === null ? fn (Model $model): string => (string) $model->getKey() : Closure::fromCallable($key);

        return $this;
    }

    public function notification(callable $notification): self
    {
        $this->notification = Closure::fromCallable($notification);

        return $this;
    }

    public function attachment(callable $attachment): self
    {
        $this->attachment = Closure::fromCallable($attachment);

        return $this;
    }

    public function previewAttachment(callable $attachment): self
    {
        $this->previewAttachment = Closure::fromCallable($attachment);

        return $this;
    }

    public function maxAttachmentBytes(int $bytes): self
    {
        $this->maxAttachmentBytes = max(0, $bytes);

        return $this;
    }

    public function recipients(callable $recipients): self
    {
        $this->recipients = Closure::fromCallable($recipients);

        return $this;
    }

    public function internalCopies(callable $copies): self
    {
        $this->internalCopies = Closure::fromCallable($copies);

        return $this;
    }

    public function defaults(callable $defaults): self
    {
        $this->defaults = Closure::fromCallable($defaults);

        return $this;
    }

    public function finalizeFlow(string $flow): self
    {
        $this->finalizeFlow = $flow;

        return $this;
    }

    public function finalizationRef(ResolvesFinalization|callable $ref): self
    {
        $this->finalizationRef = $ref instanceof ResolvesFinalization ? $ref->finalizationRef(...) : Closure::fromCallable($ref);

        return $this;
    }

    public function gate(callable $gate): self
    {
        $this->gate = Closure::fromCallable($gate);

        return $this;
    }

    public function version(callable $version): self
    {
        $this->version = Closure::fromCallable($version);

        return $this;
    }

    public function can(callable $can): self
    {
        $this->can = Closure::fromCallable($can);

        return $this;
    }

    public function url(callable $url): self
    {
        $this->url = Closure::fromCallable($url);

        return $this;
    }

    public function linkDays(int $days): self
    {
        $this->linkDays = max(1, $days);

        return $this;
    }

    public function customerPage(callable $page): self
    {
        $this->customerPage = Closure::fromCallable($page);

        return $this;
    }

    public function expiredPage(callable $page): self
    {
        $this->expiredPage = Closure::fromCallable($page);

        return $this;
    }

    /** @internal Checked once, when the definition is registered. */
    public function assertComplete(): void
    {
        if ($this->alias === null || $this->notification === null || $this->recipients === null || ($this->finalizeFlow === null && $this->gate === null)) {
            throw new \InvalidArgumentException("Delivery [{$this->name}] needs subject(), notification(), recipients(), and finalizeFlow() or gate().");
        }
    }

    public function find(string $key, bool $lock = false): ?Model
    {
        $model = ($this->resolve)($key, $lock);

        return $model instanceof Model ? $model : null;
    }

    public function keyOf(Model $subject): string
    {
        return ($this->key)($subject);
    }

    public function notificationFor(Model $subject, DeliveryMail $mail): Notification
    {
        $notification = ($this->notification)($subject, $mail);
        if (! $notification instanceof Notification || ! method_exists($notification, 'toMail')) {
            throw new \RuntimeException("Delivery [{$this->name}]'s notification returns a Notification with toMail().");
        }

        return $notification;
    }

    public function attachmentFor(Model $subject): ?DeliveryAttachment
    {
        $file = $this->attachment === null ? null : ($this->attachment)($subject);
        if ($file !== null && ! $file instanceof DeliveryAttachment) {
            throw new \RuntimeException("Delivery [{$this->name}]'s attachment returns a DeliveryAttachment or null.");
        }

        return $file;
    }

    /** The document a preview shows: `previewAttachment()` when the definition has one, else `attachment()`. */
    public function previewAttachmentFor(Model $subject): ?DeliveryAttachment
    {
        if ($this->previewAttachment === null) {
            return $this->attachmentFor($subject);
        }
        $file = ($this->previewAttachment)($subject);
        if ($file !== null && ! $file instanceof DeliveryAttachment) {
            throw new \RuntimeException("Delivery [{$this->name}]'s previewAttachment returns a DeliveryAttachment or null.");
        }

        return $file;
    }

    /** Whether the definition can have a document at all, without building it (page load must not call either closure). */
    public function mayAttach(): bool
    {
        return $this->attachment !== null || $this->previewAttachment !== null;
    }

    public function attaches(DeliveryAttachment $file): bool
    {
        return strlen($file->contents) <= $this->maxAttachmentBytes;
    }

    /** @return list<array{address: string, name: ?string, role: string}> */
    public function recipientOptions(Model $subject): array
    {
        $options = [];
        foreach ((array) ($this->recipients)($subject) as $option) {
            $address = is_array($option) ? strtolower(trim((string) ($option['address'] ?? ''))) : '';
            if ($address === '' || isset($options[$address])) {
                continue;
            }
            $options[$address] = ['address' => $address, 'name' => isset($option['name']) ? (string) $option['name'] : null, 'role' => ($option['role'] ?? 'to') === 'cc' ? 'cc' : 'to'];
        }

        return array_values($options);
    }

    /** @return list<string> */
    public function internalCopiesFor(Model $subject): array
    {
        $copies = $this->internalCopies === null ? [] : (array) ($this->internalCopies)($subject);

        return array_values(array_unique(array_filter(array_map(fn ($address): string => strtolower(trim((string) $address)), $copies))));
    }

    /** @return array{subject: string, message: string} */
    public function defaultsFor(Model $subject): array
    {
        $defaults = $this->defaults === null ? [] : (array) ($this->defaults)($subject);

        return ['subject' => (string) ($defaults['subject'] ?? ''), 'message' => (string) ($defaults['message'] ?? '')];
    }

    /** True when the subject may be sent now, else the reason it may not. Fails closed: a gate that throws refuses. */
    public function gateFor(Model $subject): true|string
    {
        try {
            if ($this->gate !== null) {
                $result = ($this->gate)($subject);

                return $result === true ? true : (is_string($result) && $result !== '' ? $result : 'This cannot be sent yet.');
            }

            return app(FinalizeGate::class)->isFinalized((string) $this->finalizeFlow, $subject) ? true : 'Finalize it first.';
        } catch (\Throwable) {
            return 'This cannot be sent right now.';
        }
    }

    public function resolvesFinalization(): bool
    {
        return $this->finalizationRef !== null;
    }

    /** The app's ref for what is final now, or null when it is not final or the delivery has no finalizationRef(). */
    public function finalizationRefOf(Model $subject): ?string
    {
        $ref = $this->finalizationRef === null ? null : ($this->finalizationRef)($subject);

        return $ref === null || $ref === '' ? null : (string) $ref;
    }

    public function finalizeFlowName(): ?string
    {
        return $this->finalizeFlow;
    }

    public function versionOf(Model $subject): ?string
    {
        $version = $this->version === null ? null : ($this->version)($subject);

        return $version === null ? null : (string) $version;
    }

    public function allows(Principal $principal, Model $subject, string $ability): bool
    {
        return $this->can === null || ($this->can)($principal, $subject, $ability) === true;
    }

    public function urlFor(Model $subject): ?string
    {
        return $this->url === null ? null : ($this->url)($subject);
    }

    public function linkLifetimeDays(): int
    {
        return $this->linkDays;
    }

    public function customerResponse(Model $subject, DeliveryRecord $delivery): Response
    {
        if ($this->customerPage !== null) {
            return ($this->customerPage)($subject, $delivery);
        }
        $file = $this->attachmentFor($subject) ?? abort(404);

        return response($file->contents, 200, ['Content-Type' => $file->mime, 'Content-Disposition' => 'inline; filename="'.addcslashes($file->filename, '"\\').'"']);
    }

    /** @param 'expired'|'revoked' $why */
    public function expiredResponse(Model $subject, DeliveryRecord $delivery, string $why): Response
    {
        if ($this->expiredPage !== null) {
            $response = ($this->expiredPage)($subject, $delivery, $why);
            if (! $response instanceof Response) {
                throw new \RuntimeException("Delivery [{$this->name}]'s expiredPage returns a Response.");
            }

            return $response;
        }

        return self::goneResponse($why);
    }

    /** The default page for an expired or revoked link: a 410 that names neither the subject nor the recipient. */
    public static function goneResponse(string $why): Response
    {
        $text = $why === 'revoked' ? 'This link is no longer available. Ask the sender for a new one.' : 'This link has expired. Ask the sender for a new one.';

        return response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Link unavailable</title></head><body><p>'.$text.'</p></body></html>', 410, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
