<?php

namespace Tey\Kit\Features;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Core\Kit;

/**
 * The domain models and tables each feature ships the behaviour of and the app owns once it adopts the feature (since 0.19): the composition
 * root's view of them. It checks what `kit.models` names against each key's contract (boot), says whether a feature is adopted (the migrator leaves
 * an adopted feature's create migrations to the app), and gives `kit:scaffold <feature> --adopt` the tables to move and the model to write.
 */
final class DomainModels
{
    /**
     * Feature => key => [contract (null: a table read without a model), trait, the kit's table, the app's default table, the app's default class name].
     * Parents first: the adopt migration renames and creates them in this order and drops them in reverse.
     */
    public const FEATURES = [
        'deliver' => [
            'delivery' => [Deliver\Contracts\Delivery::class, Deliver\Concerns\IsDelivery::class, 'kit_deliveries', 'deliveries', 'Delivery'],
            'delivery_recipient' => [Deliver\Contracts\DeliveryRecipient::class, Deliver\Concerns\IsDeliveryRecipient::class, 'kit_delivery_recipients', 'delivery_recipients', 'DeliveryRecipient'],
            'delivery_event' => [Deliver\Contracts\DeliveryEvent::class, Deliver\Concerns\IsDeliveryEvent::class, 'kit_delivery_events', 'delivery_events', 'DeliveryEvent'],
        ],
        'finalize' => [
            'finalization' => [Finalize\Contracts\Finalization::class, Finalize\Concerns\IsFinalization::class, 'kit_finalizations', 'finalizations', 'Finalization'],
            'finalization_confirmation' => [Finalize\Contracts\Confirmation::class, Finalize\Concerns\IsConfirmation::class, 'kit_finalize_confirmations', 'finalization_confirmations', 'FinalizationConfirmation'],
        ],
        'notes' => [
            'note' => [Notes\Contracts\Note::class, Notes\Concerns\IsNote::class, 'kit_notes', 'notes', 'Note'],
            'note_intent' => [null, null, 'kit_note_intents', 'note_intents', null],
        ],
        'access' => [
            'entitlement' => [null, null, 'entitlements', 'entitlements', null],
            'complimentary_grant' => [null, null, 'complimentary_grants', 'complimentary_grants', null],
        ],
        'session-ui' => [
            'security_event' => [SessionUi\Contracts\SecurityEvent::class, SessionUi\Concerns\IsSecurityEvent::class, 'kit_security_events', 'security_events', 'SecurityEvent'],
        ],
    ];

    /** The columns the kit reads and writes in each table, by key: what `kit:scaffold <feature> --check` looks for in the app's table. */
    public const COLUMNS = [
        'delivery' => ['id', 'workspace_id', 'definition', 'subject_key', 'state', 'test', 'claim_key', 'created_by', 'channel', 'created_at', 'send_at', 'claimed_at', 'sent_at', 'subject_line', 'message', 'version', 'finalization_id', 'attachment', 'provider_message_id', 'error', 'resent_from_id', 'cancelled_by', 'cancelled_at', 'cancel_reason', 'token_hash', 'token_expires_at', 'token_revoked_at', 'first_opened_at', 'finalization_ref'],
        'delivery_recipient' => ['id', 'workspace_id', 'delivery_id', 'role', 'address', 'name', 'status', 'last_event_at'],
        'delivery_event' => ['id', 'workspace_id', 'recipient_id', 'event', 'provider_event_id', 'occurred_at'],
        'finalization' => ['id', 'workspace_id', 'flow', 'subject_key', 'finalized_by', 'finalized_at', 'channel', 'statement_version', 'statement_hash', 'revision', 'snapshot_hash', 'unfinalized_by', 'unfinalized_at', 'unfinalized_channel', 'unfinalize_reason', 'snapshot', 'snapshot_hashes'],
        'finalization_confirmation' => ['id', 'workspace_id', 'flow', 'subject_key', 'step', 'fingerprint', 'confirmed_by', 'channel', 'confirmed_at'],
        'note' => ['id', 'workspace_id', 'author_id', 'subject_type', 'subject_id', 'body', 'colour', 'pinned', 'version', 'imported_from', 'created_at', 'updated_at', 'deleted_at'],
        'note_intent' => ['id', 'workspace_id', 'actor_id', 'intent_key', 'fingerprint', 'result', 'created_at'],
        'entitlement' => ['id', 'workspace_id', 'kind', 'revoked_at'],
        'complimentary_grant' => ['id', 'workspace_id', 'granted_by', 'intent_key', 'fingerprint', 'revoked_by', 'revocation_reason', 'reason', 'expires_at', 'revoked_at'],
        'security_event' => ['id', 'user_id', 'type', 'ip', 'agent', 'created_at'],
    ];

    /** Keys with a contract outside the adoptable features: their tables keep their own adoption (Concierge's binding, `kit.media.uploads_table`). */
    public const OTHER = [
        'media_upload' => \Tey\Kit\Media\Contracts\MediaUpload::class,
    ];

    /** The kit's own classes, which an app that has not adopted keeps (a key set to one of these is not adopted). */
    private const KIT_CLASSES = [
        Deliver\Models\DeliveryRecord::class, Deliver\Models\DeliveryRecipient::class, Deliver\Models\DeliveryEvent::class,
        Finalize\Models\Finalization::class, Finalize\Models\Confirmation::class, Notes\Models\Note::class, SessionUi\Models\SecurityEvent::class,
        \Tey\Kit\Media\Models\MediaUpload::class,
    ];

    /**
     * The keys of $feature the app names its own model or table for: a model other than the kit's, or a table set in `kit.tables`.
     *
     * @return list<string>
     */
    public static function adoptedKeys(string $feature): array
    {
        $keys = [];
        foreach (self::FEATURES[$feature] ?? [] as $key => [$contract]) {
            $configured = config(($contract === null ? 'kit.tables.' : 'kit.models.').$key);
            if (is_string($configured) && $configured !== '' && ($contract === null || ! in_array(ltrim($configured, '\\'), self::KIT_CLASSES, true))) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** Whether the app has adopted a feature's domain tables: all of them. A part is not adopted (problems() reports it), so the kit's migrations still run. */
    public static function adopted(string $feature): bool
    {
        $keys = self::adoptedKeys($feature);

        return $keys !== [] && count($keys) === count(self::FEATURES[$feature] ?? []);
    }

    /**
     * Why the configured domain models cannot serve, or [] when they can: each configured class exists, extends Eloquent's Model and implements its
     * key's contract; `kit.models.workspace`, when set, is Concierge's workspace model; and a feature is adopted whole or not at all. The composition
     * root stops the boot on any of them in local and testing.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        $problems = [];
        $workspace = config('kit.models.workspace');
        if ($workspace !== null && (! is_string($workspace) || ltrim($workspace, '\\') !== ltrim((string) config('concierge.models.workspace'), '\\'))) {
            $problems[] = 'kit.models.workspace ('.(is_string($workspace) ? $workspace : get_debug_type($workspace)).') must be null or name concierge.models.workspace: the workspace model is bound there and in multitenancy.tenant_model.';
        }
        $contracts = self::OTHER;
        foreach (self::FEATURES as $keys) {
            foreach ($keys as $key => [$contract]) {
                if ($contract !== null) {
                    $contracts[$key] = $contract;
                }
            }
        }
        foreach ($contracts as $key => $contract) {
            $class = config('kit.models.'.$key);
            if ($class === null) {
                continue;
            }
            if (! is_string($class) || ! class_exists($class)) {
                $problems[] = 'kit.models.'.$key.' ('.(is_string($class) ? $class : get_debug_type($class)).') is not a class.';
            } elseif (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, $contract)) {
                $problems[] = 'kit.models.'.$key.' ('.$class.') must extend '.Model::class.' and implement '.$contract.' (use its trait, '.self::trait($key).').';
            }
        }
        foreach (self::FEATURES as $feature => $keys) {
            $adopted = self::adoptedKeys($feature);
            if ($adopted !== [] && count($adopted) !== count($keys)) {
                $problems[] = 'The '.$feature.' feature is partly adopted ('.implode(', ', $adopted).'): name the app\'s model or table for every one of '.implode(', ', array_keys($keys)).' (kit:scaffold '.$feature.' --adopt writes them all).';
            }
        }

        return $problems;
    }

    /** The table a key reads now: Kit::table() once the feature's provider registered its defaults, else the kit's own name. */
    public static function current(string $feature, string $key): string
    {
        return rescue(fn (): string => Kit::table($key), self::FEATURES[$feature][$key][2], false);
    }

    private static function trait(string $key): string
    {
        foreach (self::FEATURES as $keys) {
            if (isset($keys[$key])) {
                return (string) $keys[$key][1];
            }
        }

        return \Tey\Kit\Media\Concerns\IsMediaUpload::class;
    }
}
