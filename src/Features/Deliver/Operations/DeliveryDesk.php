<?php

namespace Tey\Kit\Features\Deliver\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Deliver\Data\DeliverPreview;
use Tey\Kit\Features\Deliver\Data\DeliverState;
use Tey\Kit\Features\Deliver\Data\DeliveryRecipientView;
use Tey\Kit\Features\Deliver\Data\DeliveryView;
use Tey\Kit\Features\Deliver\Data\RecipientOption;
use Tey\Kit\Features\Deliver\Deliveries;
use Tey\Kit\Features\Deliver\Delivery;
use Tey\Kit\Features\Deliver\DeliveryAttachment;
use Tey\Kit\Features\Deliver\DeliveryMail;
use Tey\Kit\Features\Deliver\DeliveryNotification;
use Tey\Kit\Features\Deliver\Events\DeliveryBounced;
use Tey\Kit\Features\Deliver\Events\DeliveryCancelled;
use Tey\Kit\Features\Deliver\Events\DeliverySent;
use Tey\Kit\Features\Deliver\Models\DeliveryEvent;
use Tey\Kit\Features\Deliver\Models\DeliveryRecipient;
use Tey\Kit\Features\Deliver\Models\DeliveryRecord;
use Tey\Kit\Features\Deliver\SentMessages;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Models\Finalization;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The deliver operations, the same ones for the page and the MCP tools. Every call runs inside the current workspace, checks the workspace permission and
 * then the definition's own per-subject check, and fails closed. The preview and the send build the email the same way, from the app's Notification.
 *
 * At most once: a send carries a claim key (unique per workspace), and the same key again answers the delivery it already made. A send that may or may
 * not have left (the provider timed out) is `unknown` and is never sent again by the kit. Scheduled sends are rows: the scheduler's sweep claims a due row
 * atomically (scheduled → sending, only one worker wins), re-checks the gate, the version, the sender's access and the recipients, then sends or cancels.
 */
final class DeliveryDesk
{
    public const array CHANNELS = ['web', 'mcp'];

    /** A row still `sending` this long after its claim stopped mid-send: it becomes `unknown`, for a person to check with the provider. */
    public const int STUCK_MINUTES = 10;

    private const array ABILITIES = ['read' => 'deliver.read', 'send' => 'deliver.send', 'resend' => 'deliver.resend'];

    public function __construct(private readonly Deliveries $deliveries, private readonly Access $access, private readonly SentMessages $sent) {}

    public function state(Principal $principal, string $definition, string $key): DeliverState
    {
        $delivery = $this->deliveries->get($definition);
        $subject = $this->subject($delivery, $key);
        $this->authorize($principal, $delivery, $subject, 'read');

        return $this->present($principal, $delivery, $subject);
    }

    /**
     * The email as it would be sent with this draft: the same Notification, Cc, Bcc and attachment decision, with no customer link (its button goes
     * nowhere) and nothing written.
     *
     * @param  array<string, mixed>  $input
     */
    public function preview(Principal $principal, string $definition, string $key, array $input): DeliverPreview
    {
        $delivery = $this->deliveries->get($definition);
        $subject = $this->subject($delivery, $key);
        $this->authorize($principal, $delivery, $subject, 'read');
        $draft = $this->draft($delivery, $subject, $input, false);
        [$wrapper, $file, $outcome] = $this->build($delivery, $subject, $draft, null, false, null, preview: true);
        $mail = $wrapper->toMail(Notification::route('mail', $draft['to']));

        return new DeliverPreview((string) ($mail->subject ?? ''), (string) $mail->render(), $outcome, $file?->filename, $file === null ? null : strlen($file->contents));
    }

    /** The document as it is now, for the preview's PDF frame. Nothing is written. */
    public function attachment(Principal $principal, string $definition, string $key): DeliveryAttachment
    {
        $delivery = $this->deliveries->get($definition);
        $subject = $this->subject($delivery, $key);
        $this->authorize($principal, $delivery, $subject, 'read');

        return $delivery->attachmentFor($subject) ?? throw new RuntimeException('deliver_no_attachment');
    }

    /**
     * Sends now, or schedules for `send_at`. `test` sends to the signed-in sender only, marked as a test, with no customer link, and does not count as
     * delivered.
     *
     * @param  array<string, mixed>  $input
     */
    public function send(Principal $principal, string $definition, string $key, string $revision, array $input, string $claimKey, string $channel = 'web', bool $test = false, ?int $resentFrom = null, bool $revokePrevious = false): DeliverState
    {
        $this->channel($channel);
        $claimKey = trim($claimKey);
        if ($claimKey === '' || strlen($claimKey) > 120) {
            throw new RuntimeException('deliver_claim_key_required');
        }
        $delivery = $this->deliveries->get($definition);

        [$subject, $record, $sendNow] = DB::transaction(function () use ($principal, $delivery, $key, $revision, $input, $claimKey, $channel, $test, $resentFrom, $revokePrevious): array {
            $subject = $this->subject($delivery, $key, lock: true);
            $this->authorize($principal, $delivery, $subject, $resentFrom === null ? 'send' : 'resend');
            // The same claim key again is the same request: answer what it already did, never a second email.
            $existing = DeliveryRecord::query()->where('claim_key', $claimKey)->first();
            if ($existing !== null) {
                if ($existing->definition !== $delivery->name || $existing->subject_key !== $delivery->keyOf($subject)) {
                    throw new RuntimeException('deliver_claim_key_reused');
                }

                return [$subject, $existing, false];
            }
            $this->fresh($delivery, $subject, $revision);
            if (! $test && ($gate = $delivery->gateFor($subject)) !== true) {
                throw new DeliverRefused($gate);
            }
            $original = null;
            if ($resentFrom !== null) {
                $original = $this->record($delivery, $subject, $resentFrom);
                if ($original->test || ! in_array($original->state, ['sent', 'unknown', 'failed'], true)) {
                    throw new RuntimeException('deliver_not_resendable');
                }
            }
            $draft = $this->draft($delivery, $subject, $input, $test, $principal);
            // Built once before anything is written, so a Notification that cannot render refuses the send instead of recording one.
            $this->build($delivery, $subject, $draft, null, $test, null);
            $finalization = $delivery->finalizeFlowName() === null ? null : $this->finalizationOf($delivery, $subject);
            $record = DeliveryRecord::query()->create([
                'definition' => $delivery->name, 'subject_key' => $delivery->keyOf($subject), 'state' => $draft['sendAt'] === null ? 'sending' : 'scheduled', 'test' => $test,
                'claim_key' => $claimKey, 'created_by' => $principal->actorId, 'channel' => $channel, 'created_at' => now(), 'send_at' => $draft['sendAt'],
                'claimed_at' => $draft['sendAt'] === null ? now() : null, 'subject_line' => $draft['subject'], 'message' => $draft['message'],
                'version' => $delivery->versionOf($subject), 'finalization_id' => $finalization?->getKey(), 'resent_from_id' => $original?->getKey(),
            ]);
            foreach (['to', 'cc', 'bcc'] as $role) {
                foreach ($draft[$role] as $address) {
                    DeliveryRecipient::query()->create(['delivery_id' => $record->getKey(), 'role' => $role, 'address' => $address, 'name' => $draft['names'][$address] ?? null]);
                }
            }
            if ($original !== null && $revokePrevious && $original->token_hash !== null && $original->token_revoked_at === null) {
                $original->forceFill(['token_revoked_at' => now()])->save();
            }

            return [$subject, $record, $draft['sendAt'] === null];
        });
        if ($sendNow) {
            $this->deliver($delivery, $subject, $record);
        }

        return $this->present($principal, $delivery, $subject);
    }

    /** @param  array<string, mixed>  $input */
    public function resend(Principal $principal, string $definition, string $key, int $deliveryId, string $revision, array $input, string $claimKey, bool $revokePrevious = false, string $channel = 'web'): DeliverState
    {
        return $this->send($principal, $definition, $key, $revision, $input, $claimKey, $channel, false, $deliveryId, $revokePrevious);
    }

    public function cancel(Principal $principal, string $definition, string $key, int $deliveryId, string $revision, string $channel = 'web'): DeliverState
    {
        $this->channel($channel);
        $delivery = $this->deliveries->get($definition);
        [$subject, $record] = DB::transaction(function () use ($principal, $delivery, $key, $deliveryId, $revision): array {
            $subject = $this->subject($delivery, $key, lock: true);
            $this->authorize($principal, $delivery, $subject, 'send');
            $this->fresh($delivery, $subject, $revision);
            $record = $this->record($delivery, $subject, $deliveryId);
            // Only while it is still scheduled: a row the sweep already claimed is on its way.
            $updated = DeliveryRecord::query()->whereKey($record->getKey())->where('state', 'scheduled')
                ->update(['state' => 'cancelled', 'cancelled_by' => $principal->actorId, 'cancelled_at' => now(), 'cancel_reason' => 'Cancelled.']);
            if ($updated !== 1) {
                throw new RuntimeException('deliver_not_cancellable');
            }

            return [$subject, $record->refresh()];
        });
        event(new DeliveryCancelled($delivery->name, $record, 'Cancelled.', false));

        return $this->present($principal, $delivery, $subject);
    }

    /** Turns a delivery's customer link into a 404, for everyone on it. */
    public function revoke(Principal $principal, string $definition, string $key, int $deliveryId, string $revision, string $channel = 'web'): DeliverState
    {
        $this->channel($channel);
        $delivery = $this->deliveries->get($definition);
        $subject = DB::transaction(function () use ($principal, $delivery, $key, $deliveryId, $revision): Model {
            $subject = $this->subject($delivery, $key, lock: true);
            $this->authorize($principal, $delivery, $subject, 'resend');
            $this->fresh($delivery, $subject, $revision);
            $record = $this->record($delivery, $subject, $deliveryId);
            if ($record->token_hash === null || $record->token_revoked_at !== null) {
                throw new RuntimeException('deliver_link_inactive');
            }
            $record->forceFill(['token_revoked_at' => now()])->save();

            return $subject;
        });

        return $this->present($principal, $delivery, $subject);
    }

    /**
     * The scheduler's pass, every minute: rows stuck mid-send become `unknown`; due rows are claimed one at a time (only one worker wins each) and sent
     * or cancelled inside their own workspace.
     *
     * @return array{sent: int, cancelled: int, unknown: int}
     */
    public function sweep(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $unknown = DB::table('kit_deliveries')->where('state', 'sending')->where('claimed_at', '<', $now->subMinutes(self::STUCK_MINUTES))
            ->update(['state' => 'unknown', 'error' => 'Stopped mid-send. Check with the mail provider before sending again.']);
        $counts = ['sent' => 0, 'cancelled' => 0, 'unknown' => $unknown];
        $due = DB::table('kit_deliveries')->where('state', 'scheduled')->where('send_at', '<=', $now)->orderBy('id')->get(['id', 'workspace_id']);
        foreach ($due as $row) {
            if (DB::table('kit_deliveries')->where('id', $row->id)->where('state', 'scheduled')->update(['state' => 'sending', 'claimed_at' => $now]) !== 1) {
                continue;
            }
            $outcome = Workspaces::run((int) $row->workspace_id, function () use ($row): string {
                return $this->runScheduled((int) $row->id);
            });
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * A provider webhook's event for one address of one message: `delivered`, `bounced` or `complained`. Answers false for a message the kit did not
     * send, an address not on it, or an event already recorded (a webhook sent twice); a bounce dispatches DeliveryBounced.
     */
    public function recordEvent(string $messageId, string $address, string $event, string $providerEventId, ?CarbonImmutable $at = null): bool
    {
        if (! in_array($event, ['delivered', 'bounced', 'complained'], true)) {
            throw new \InvalidArgumentException('A delivery event is delivered, bounced or complained.');
        }
        $row = DB::table('kit_deliveries')->where('provider_message_id', $messageId)->first(['id', 'workspace_id', 'definition']);
        if ($row === null) {
            return false;
        }

        return Workspaces::run((int) $row->workspace_id, function () use ($row, $address, $event, $providerEventId, $at): bool {
            $recipient = DeliveryRecipient::query()->where('delivery_id', $row->id)->where('address', strtolower(trim($address)))->first();
            if ($recipient === null) {
                return false;
            }
            $at ??= CarbonImmutable::now();
            try {
                DeliveryEvent::query()->create(['recipient_id' => $recipient->getKey(), 'event' => $event, 'provider_event_id' => $providerEventId, 'occurred_at' => $at]);
            } catch (QueryException) {
                return false;
            }
            // A bounce or complaint is final for that address; a late "delivered" does not undo it.
            if ($event !== 'delivered' || ! in_array($recipient->status, ['bounced', 'complained'], true)) {
                $recipient->forceFill(['status' => $event, 'last_event_at' => $at])->save();
            }
            if ($event !== 'delivered') {
                event(new DeliveryBounced((string) $row->definition, DeliveryRecord::query()->findOrFail($row->id), $recipient));
            }

            return true;
        });
    }

    /** Cancels a subject's scheduled sends when its finalize flow is un-finalized. Delivered links stay as they are. */
    public function cancelForUnfinalized(string $flow, Model $subject): int
    {
        $cancelled = 0;
        foreach ($this->deliveries->all() as $delivery) {
            if ($delivery->finalizeFlowName() !== $flow) {
                continue;
            }
            foreach (DeliveryRecord::query()->where('definition', $delivery->name)->where('subject_key', $delivery->keyOf($subject))->where('state', 'scheduled')->get() as $record) {
                $cancelled += $this->cancelAutomatically($delivery, $record, 'Un-finalized before it was sent.') ? 1 : 0;
            }
        }

        return $cancelled;
    }

    /**
     * The customer's link: the delivery's subject as the definition shows it, for a valid, unrevoked, unexpired token. Anything else is a 404. The first
     * open is recorded; nothing tracks the email itself.
     */
    public function open(int $deliveryId, string $token): Response
    {
        $row = DB::table('kit_deliveries')->where('id', $deliveryId)->first(['workspace_id', 'token_hash', 'token_expires_at', 'token_revoked_at']);
        if ($row === null || $row->token_hash === null || $row->token_revoked_at !== null || $token === ''
            || ! hash_equals((string) $row->token_hash, hash('sha256', $token)) || CarbonImmutable::parse($row->token_expires_at)->isPast()) {
            abort(404);
        }

        return Workspaces::run((int) $row->workspace_id, function () use ($deliveryId): Response {
            $record = DeliveryRecord::query()->findOrFail($deliveryId);
            $delivery = $this->deliveries->get($record->definition);
            $subject = $delivery->find($record->subject_key) ?? abort(404);
            if ($record->first_opened_at === null) {
                DeliveryRecord::query()->whereKey($deliveryId)->whereNull('first_opened_at')->update(['first_opened_at' => now()]);
            }
            $response = $delivery->customerResponse($subject, $record);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        });
    }

    /** One scheduled row the sweep claimed: re-check everything, then send or cancel. */
    private function runScheduled(int $id): string
    {
        $record = DeliveryRecord::query()->findOrFail($id);
        try {
            $delivery = $this->deliveries->get($record->definition);
        } catch (RuntimeException) {
            DeliveryRecord::query()->whereKey($id)->update(['state' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Its delivery is no longer defined.']);

            return 'cancelled';
        }
        $subject = $delivery->find($record->subject_key);
        $reason = match (true) {
            $subject === null => 'It no longer exists.',
            ($gate = $delivery->gateFor($subject)) !== true => $gate,
            $delivery->versionOf($subject) !== $record->version => 'The document changed after it was scheduled.',
            ! $this->allowed(new Principal($record->created_by), $delivery, $subject, 'send') => 'The person who scheduled it can no longer send.',
            ($gone = $this->goneRecipient($delivery, $subject, $record)) !== null => $gone.' is no longer a contact.',
            default => null,
        };
        if ($reason !== null) {
            $this->cancelAutomatically($delivery, $record, $reason, from: 'sending');

            return 'cancelled';
        }
        $this->deliver($delivery, $subject, $record);

        return $record->refresh()->state === 'sent' ? 'sent' : 'unknown';
    }

    private function cancelAutomatically(Delivery $delivery, DeliveryRecord $record, string $reason, string $from = 'scheduled'): bool
    {
        if (DeliveryRecord::query()->whereKey($record->getKey())->where('state', $from)->update(['state' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]) !== 1) {
            return false;
        }
        event(new DeliveryCancelled($delivery->name, $record->refresh(), $reason, true));

        return true;
    }

    /** Sends a claimed row. A build failure is `failed` (nothing left); a transport failure is `unknown` (it may have left) and is never retried. */
    private function deliver(Delivery $delivery, Model $subject, DeliveryRecord $record): void
    {
        $roles = $record->recipients()->get()->groupBy('role')->map(fn ($rows): array => $rows->pluck('address')->all());
        $link = null;
        try {
            if (! $record->test) {
                $token = bin2hex(random_bytes(32));
                $expires = CarbonImmutable::now()->addDays($delivery->linkLifetimeDays());
                $record->forceFill(['token_hash' => hash('sha256', $token), 'token_expires_at' => $expires])->save();
                $link = URL::temporarySignedRoute('kit.deliver.view', $expires, ['delivery' => $record->getKey(), 'token' => $token]);
            }
            $draft = ['subject' => $record->subject_line, 'message' => $record->message, 'to' => $roles['to'] ?? [], 'cc' => $roles['cc'] ?? [], 'bcc' => $roles['bcc'] ?? []];
            [$wrapper, , $outcome] = $this->build($delivery, $subject, $draft, $link, (bool) $record->test, (int) $record->getKey());
            $record->forceFill(['attachment' => $outcome])->save();
        } catch (\Throwable $error) {
            $record->forceFill(['state' => 'failed', 'error' => 'The email could not be built: '.$error->getMessage()])->save();

            return;
        }
        try {
            Notification::route('mail', $draft['to'])->notifyNow($wrapper);
        } catch (\Throwable $error) {
            $record->forceFill(['state' => 'unknown', 'error' => 'The mail provider did not confirm it: '.$error->getMessage()])->save();

            return;
        }
        DB::transaction(function () use ($record): void {
            $record->forceFill(['state' => 'sent', 'sent_at' => now(), 'provider_message_id' => $this->sent->pull((int) $record->getKey())])->save();
            DeliveryRecipient::query()->where('delivery_id', $record->getKey())->where('status', 'pending')->update(['status' => 'sent']);
        });
        event(new DeliverySent($delivery->name, $record->refresh()));
    }

    /**
     * The email for a draft: the app's Notification wrapped with Cc, Bcc, the attachment (when it fits) and the test marker.
     *
     * @param  array{subject: string, message: string, to: list<string>, cc: list<string>, bcc: list<string>}  $draft
     * @return array{0: DeliveryNotification, 1: ?DeliveryAttachment, 2: string}
     */
    private function build(Delivery $delivery, Model $subject, array $draft, ?string $link, bool $test, ?int $id, bool $preview = false): array
    {
        $file = $delivery->attachmentFor($subject);
        $outcome = $file === null ? 'none' : ($delivery->attaches($file) ? 'attached' : 'linked');
        $mail = new DeliveryMail($draft['subject'], $draft['message'], $link, $outcome, $test, $preview);
        $wrapper = new DeliveryNotification($delivery->notificationFor($subject, $mail), $draft['cc'], $draft['bcc'], $outcome === 'attached' ? $file : null, $test, $id);

        return [$wrapper, $file, $outcome];
    }

    /**
     * A draft from the input, checked: To and Cc from the live recipient options (a contact who left is refused, not dropped), Bcc any valid address
     * (the internal copies), duplicates collapsed, the subject and message escaped by the Notification, a send time in the future.
     *
     * @param  array<string, mixed>  $input
     * @return array{to: list<string>, cc: list<string>, bcc: list<string>, names: array<string, ?string>, subject: string, message: string, sendAt: ?CarbonImmutable}
     */
    private function draft(Delivery $delivery, Model $subject, array $input, bool $test, ?Principal $principal = null): array
    {
        $defaults = $delivery->defaultsFor($subject);
        $subjectLine = trim((string) ($input['subject'] ?? $defaults['subject']));
        $message = (string) ($input['message'] ?? $defaults['message']);
        if ($subjectLine === '' || mb_strlen($subjectLine) > 255 || mb_strlen($message) > 10000) {
            throw new RuntimeException('deliver_subject_required');
        }
        $sendAt = null;
        if (! $test && ($input['send_at'] ?? null) !== null && $input['send_at'] !== '') {
            try {
                $sendAt = CarbonImmutable::parse((string) $input['send_at'])->utc();
            } catch (\Throwable) {
                throw new RuntimeException('deliver_send_at_invalid');
            }
            if ($sendAt->isPast() || $sendAt->gt(CarbonImmutable::now()->addYear())) {
                throw new RuntimeException('deliver_send_at_invalid');
            }
        }
        if ($test) {
            $model = Users::model();
            $email = strtolower((string) $model::query()->whereKey($principal?->actorId)->value('email'));

            return ['to' => [$email], 'cc' => [], 'bcc' => [], 'names' => [], 'subject' => $subjectLine, 'message' => $message, 'sendAt' => null];
        }
        $options = collect($delivery->recipientOptions($subject))->keyBy('address');
        $list = fn (string $role): array => array_values(array_unique(array_filter(array_map(fn ($address): string => strtolower(trim((string) $address)), (array) ($input[$role] ?? [])))));
        // Contacts picked by the person must still be live options (a contact who left is refused, never dropped); typed addresses (to_other, cc_other) are
        // any valid email, for a customer whose contact has none on file.
        foreach ([...$list('to'), ...$list('cc')] as $address) {
            if (! $options->has($address)) {
                throw new DeliverRefused($address.' is not one of this record\'s contacts.', 'deliver_recipient_unknown');
            }
        }
        $to = array_values(array_unique([...$list('to'), ...$list('to_other')]));
        $cc = array_values(array_diff(array_unique([...$list('cc'), ...$list('cc_other')]), $to));
        $bcc = array_values(array_diff($list('bcc'), $to, $cc));
        if ($to === []) {
            throw new RuntimeException('deliver_recipients_required');
        }
        foreach ([...$list('to_other'), ...$list('cc_other'), ...$bcc] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new DeliverRefused($address.' is not an email address.', 'deliver_address_invalid');
            }
        }

        return ['to' => $to, 'cc' => $cc, 'bcc' => $bcc, 'names' => $options->map(fn (array $option): ?string => $option['name'])->all(), 'subject' => $subjectLine, 'message' => $message, 'sendAt' => $sendAt];
    }

    private function goneRecipient(Delivery $delivery, Model $subject, DeliveryRecord $record): ?string
    {
        $options = collect($delivery->recipientOptions($subject))->pluck('address')->all();
        foreach ($record->recipients()->whereIn('role', ['to', 'cc'])->pluck('address') as $address) {
            if (! in_array($address, $options, true)) {
                return $address;
            }
        }

        return null;
    }

    private function present(Principal $principal, Delivery $delivery, Model $subject): DeliverState
    {
        $key = $delivery->keyOf($subject);
        $gate = $delivery->gateFor($subject);
        $revision = $this->revision($delivery, $subject);
        $canSend = $this->allowed($principal, $delivery, $subject, 'send');
        $canResend = $this->allowed($principal, $delivery, $subject, 'resend');
        $records = DeliveryRecord::query()->with('recipients')->where('definition', $delivery->name)->where('subject_key', $key)->orderByDesc('id')->limit(50)->get();
        $names = $this->names($records->pluck('created_by')->all());
        $current = $delivery->finalizeFlowName() === null ? null : $this->finalizationOf($delivery, $subject);
        $views = $records->map(function (DeliveryRecord $record) use ($delivery, $subject, $names, $current, $canSend, $canResend, $revision): DeliveryView {
            $actions = [];
            if ($record->state === 'scheduled' && $canSend) {
                $actions[] = new Action('Cancel', $this->url($delivery, $subject, 'deliveries/'.$record->getKey().'/cancel', $revision), 'post', key: 'cancel-'.$record->getKey());
            }
            if (! $record->test && in_array($record->state, ['sent', 'unknown', 'failed'], true) && $canResend) {
                $actions[] = new Action('Resend', $this->url($delivery, $subject, 'deliveries/'.$record->getKey().'/resend', $revision), 'post', key: 'resend-'.$record->getKey());
            }
            $linkActive = $record->token_hash !== null && $record->token_revoked_at === null && $record->token_expires_at?->isFuture() === true;
            if ($linkActive && $canResend) {
                $actions[] = new Action('Revoke link', $this->url($delivery, $subject, 'deliveries/'.$record->getKey().'/revoke', $revision), 'post', key: 'revoke-'.$record->getKey());
            }
            $note = match (true) {
                $record->state === 'cancelled' => $record->cancel_reason,
                in_array($record->state, ['failed', 'unknown'], true) => $record->error,
                $record->finalization_id === null && ! $record->test && $delivery->finalizeFlowName() !== null => 'Sent without finalizing.',
                default => null,
            };

            return new DeliveryView((int) $record->getKey(), (string) $record->state, (bool) $record->test, $names[$record->created_by] ?? 'Someone', (string) $record->channel,
                $record->created_at->toIso8601String(), $record->send_at?->toIso8601String(), $record->sent_at?->toIso8601String(), (string) $record->subject_line,
                (string) $record->attachment, $record->recipients->map(fn (DeliveryRecipient $r): DeliveryRecipientView => new DeliveryRecipientView((string) $r->role, (string) $r->address, $r->name, (string) $r->status))->all(),
                $record->finalization_id === null ? null : $current?->getKey() === $record->finalization_id, $linkActive, $record->first_opened_at?->toIso8601String(), $note,
                $record->resent_from_id, $actions);
        })->all();
        $defaults = $delivery->defaultsFor($subject);
        $send = null;
        if ($canSend) {
            $url = $this->url($delivery, $subject, 'send', $revision);
            $send = $gate === true ? new Action('Send', $url, 'post', key: 'send', priority: 'primary') : new Action('Send', $url, 'post', disabled: true, reason: $gate, key: 'send', priority: 'primary');
        }

        return new DeliverState($delivery->name, $key, $gate === true, $gate === true ? null : $gate, $defaults['subject'], $defaults['message'],
            array_map(fn (array $option): RecipientOption => new RecipientOption($option['address'], $option['name'], $option['role']), $delivery->recipientOptions($subject)),
            $delivery->internalCopiesFor($subject), $views, $revision,
            new Action('Preview', $this->url($delivery, $subject, 'preview'), 'post', key: 'preview'), $send,
            $canSend ? new Action('Send me a test', $this->url($delivery, $subject, 'test', $revision), 'post', key: 'test') : null,
            $delivery->attachmentFor($subject) === null ? null : $this->url($delivery, $subject, 'attachment'));
    }

    /** What a write must have seen: the gate, the version, the people it may go to, and the history's states. */
    private function revision(Delivery $delivery, Model $subject): string
    {
        $key = $delivery->keyOf($subject);
        $history = DeliveryRecord::query()->where('definition', $delivery->name)->where('subject_key', $key)->orderBy('id')->get(['id', 'state', 'token_revoked_at'])
            ->map(fn (DeliveryRecord $r): array => [$r->getKey(), $r->state, $r->token_revoked_at?->getTimestamp()])->all();
        $gate = $delivery->gateFor($subject);

        return hash('sha256', json_encode([$delivery->name, $key, $gate, $delivery->versionOf($subject), array_column($delivery->recipientOptions($subject), 'address'), $history], JSON_THROW_ON_ERROR));
    }

    private function fresh(Delivery $delivery, Model $subject, string $revision): void
    {
        if (! hash_equals($this->revision($delivery, $subject), $revision)) {
            throw new RuntimeException('deliver_stale');
        }
    }

    private function record(Delivery $delivery, Model $subject, int $id): DeliveryRecord
    {
        return DeliveryRecord::query()->where('definition', $delivery->name)->where('subject_key', $delivery->keyOf($subject))->whereKey($id)->first()
            ?? throw new RuntimeException('deliver_not_found');
    }

    private function finalizationOf(Delivery $delivery, Model $subject): ?Finalization
    {
        $flow = (string) $delivery->finalizeFlowName();

        return Finalization::query()->where('flow', $flow)->where('subject_key', app(Flows::class)->get($flow)->keyOf($subject))->whereNull('unfinalized_at')->orderByDesc('id')->first();
    }

    private function subject(Delivery $delivery, string $key, bool $lock = false): Model
    {
        Workspaces::required();

        return $delivery->find($key, $lock) ?? throw new RuntimeException('deliver_subject_not_found');
    }

    /** Workspace permission first, then the definition's per-subject check. A subject the viewer may not read is not found, so its existence does not leak. */
    private function authorize(Principal $principal, Delivery $delivery, Model $subject, string $ability): void
    {
        $this->access->authorize($principal, self::ABILITIES[$ability]);
        if (! $delivery->allows($principal, $subject, $ability)) {
            throw new RuntimeException($ability === 'read' || ! $delivery->allows($principal, $subject, 'read') ? 'deliver_subject_not_found' : 'permission_denied');
        }
        if ($ability !== 'read') {
            $this->authorize($principal, $delivery, $subject, 'read');
        }
    }

    private function allowed(Principal $principal, Delivery $delivery, Model $subject, string $ability): bool
    {
        try {
            $this->authorize($principal, $delivery, $subject, $ability);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function channel(string $channel): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException('A delivery channel is web or mcp.');
        }
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $model = Users::model();

        return $model::query()->whereIn((new $model)->getKeyName(), $ids)->pluck('name', (new $model)->getKeyName())->map(fn ($name): string => (string) $name)->all();
    }

    private function url(Delivery $delivery, Model $subject, string $path, ?string $revision = null): string
    {
        $url = WorkspaceUrl::to(Workspaces::required(), 'deliver/'.$delivery->name.'/'.rawurlencode($delivery->keyOf($subject)).'/'.$path, 'deliver');

        return $revision === null ? $url : $url.'?revision='.$revision;
    }
}
