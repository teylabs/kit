<?php

namespace Tey\Kit\Features\Finalize\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Finalize\Data\FinalizationRecord;
use Tey\Kit\Features\Finalize\Data\GateConfirmation;
use Tey\Kit\Features\Finalize\Data\GateError;
use Tey\Kit\Features\Finalize\Data\GateProgress;
use Tey\Kit\Features\Finalize\Data\GateState;
use Tey\Kit\Features\Finalize\Data\GateStep;
use Tey\Kit\Features\Finalize\Events\Finalized;
use Tey\Kit\Features\Finalize\Events\Unfinalized;
use Tey\Kit\Features\Finalize\Flow;
use Tey\Kit\Features\Finalize\Flows;
use Tey\Kit\Features\Finalize\Models\Confirmation;
use Tey\Kit\Features\Finalize\Models\Finalization;
use Tey\Kit\Features\Finalize\Step;
use Tey\Kit\Features\Finalize\StepOutcome;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/**
 * The finalize gate's operations, the same ones for the page and the MCP tools. Every call runs inside the current workspace, checks the workspace
 * permission and then the flow's own per-subject check, and fails closed. Steps are recomputed from real state on every call; nothing the client sends
 * decides a state. Every write carries the gate's `revision` and is refused (`finalize_stale`, 409) when the gate changed since the caller saw it.
 */
final class FinalizeGate
{
    public const array CHANNELS = ['web', 'mcp'];

    private const array ABILITIES = ['read' => 'finalize.read', 'confirm' => 'finalize.confirm', 'certify' => 'finalize.certify', 'unfinalize' => 'finalize.unfinalize'];

    public function __construct(private readonly Flows $flows, private readonly Access $access) {}

    /** The gate as the viewer may see it. */
    public function state(Principal $principal, string $flow, string $key): GateState
    {
        $definition = $this->flows->get($flow);
        $subject = $this->subject($definition, $key);
        $this->authorize($principal, $definition, $subject, 'read');

        return $this->present($principal, $definition, $subject);
    }

    /** True while the subject is finalized in this flow. For the app's own policies over the write paths that change what was certified. */
    public function isFinalized(string $flow, Model $subject): bool
    {
        $definition = $this->flows->get($flow);

        return $this->current($definition, $definition->keyOf($subject)) !== null;
    }

    /**
     * The printed fields frozen when the subject was finalized, for the app to print instead of the live related record. Null while the subject is not
     * finalized, or when its flow declares no snapshot. Read inside the subject's workspace, like isFinalized.
     *
     * @return array<string, int|string|bool|null>|null
     */
    public function snapshot(string $flow, Model $subject): ?array
    {
        $definition = $this->flows->get($flow);
        $finalization = $this->current($definition, $definition->keyOf($subject));

        return $finalization?->snapshot === null ? null : (array) $finalization->snapshot;
    }

    /**
     * The snapshot keys whose live value differs from the one finalized (changed, added or gone), sorted. Empty while not finalized, without a snapshot,
     * or when nothing changed. For "details changed since finalizing": taking the new values means un-finalizing and certifying again.
     *
     * @return list<string>
     */
    public function drift(string $flow, Model $subject): array
    {
        $definition = $this->flows->get($flow);
        $finalization = $this->current($definition, $definition->keyOf($subject));
        if ($finalization?->snapshot_hashes === null) {
            return [];
        }
        $kept = (array) $finalization->snapshot_hashes;
        $live = self::hashes($definition->snapshotFor($subject) ?? []);
        $keys = array_values(array_unique([...array_keys($kept), ...array_keys($live)]));
        $changed = array_values(array_filter($keys, fn (string $key): bool => ! isset($kept[$key], $live[$key]) || ! hash_equals((string) $kept[$key], $live[$key])));
        sort($changed);

        return $changed;
    }

    /**
     * @param  array<string, int|string|bool|null>  $values
     * @return array<string, string>
     */
    private static function hashes(array $values): array
    {
        return array_map(fn (int|string|bool|null $value): string => hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), $values);
    }

    public function confirm(Principal $principal, string $flow, string $key, string $step, string $revision, string $channel = 'web'): GateState
    {
        $this->channel($channel);
        $definition = $this->flows->get($flow);

        return DB::transaction(function () use ($principal, $definition, $key, $step, $revision, $channel): GateState {
            $subject = $this->subject($definition, $key, lock: true);
            $this->authorize($principal, $definition, $subject, 'confirm');
            $this->notFinalized($definition, $subject);
            $computed = $this->compute($definition, $subject);
            $this->fresh($computed, $revision);
            $target = $this->step($definition, $step);
            if ($target->kind !== 'confirmation') {
                throw new RuntimeException('finalize_step_not_confirmable');
            }
            $row = $computed['rows'][$step];
            if ($row['outcome']->state !== 'done') {
                throw new RuntimeException('finalize_step_not_ready');
            }
            Confirmation::query()->create([
                'flow' => $definition->name, 'subject_key' => $definition->keyOf($subject), 'step' => $step, 'fingerprint' => (string) $row['fingerprint'],
                'confirmed_by' => $principal->actorId, 'channel' => $channel, 'confirmed_at' => now(),
            ]);

            return $this->present($principal, $definition, $subject);
        });
    }

    public function finalize(Principal $principal, string $flow, string $key, string $revision, bool $certified, string $channel = 'web'): GateState
    {
        $this->channel($channel);
        if (! $certified) {
            throw new RuntimeException('finalize_certify_required');
        }
        $definition = $this->flows->get($flow);

        $state = DB::transaction(function () use ($principal, $definition, $key, $revision, $channel): array {
            $subject = $this->subject($definition, $key, lock: true);
            $this->authorize($principal, $definition, $subject, 'certify');
            $this->notFinalized($definition, $subject);
            // Recomputed under the lock: what the person saw must still be what is certified, and every required step must still hold.
            $computed = $this->compute($definition, $subject);
            $this->fresh($computed, $revision);
            if (! $computed['complete']) {
                throw new RuntimeException('finalize_incomplete');
            }
            $statement = $definition->statementFor($subject);
            // The declared printed fields of related records, read under the same lock: kept as values (the one exception to hashes only) and per-key hashes.
            $printed = $definition->snapshotFor($subject);
            $finalization = Finalization::query()->create([
                'snapshot' => $printed, 'snapshot_hashes' => $printed === null ? null : self::hashes($printed),
                'flow' => $definition->name, 'subject_key' => $definition->keyOf($subject), 'finalized_by' => $principal->actorId, 'finalized_at' => now(),
                'channel' => $channel, 'statement_version' => $statement['version'], 'statement_hash' => hash('sha256', $statement['text']),
                'revision' => $computed['revision'], 'snapshot_hash' => $computed['snapshot'],
            ]);
            $this->bumpLock($definition, $subject);

            return [$subject, $finalization];
        });
        [$subject, $finalization] = $state;
        // After the commit, so a listener's mail or PDF never describes a finalization that rolled back.
        event(new Finalized($definition->name, $subject, $finalization));

        return $this->present($principal, $definition, $subject);
    }

    public function unfinalize(Principal $principal, string $flow, string $key, string $revision, string $reason, string $channel = 'web'): GateState
    {
        $this->channel($channel);
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('finalize_reason_required');
        }
        $definition = $this->flows->get($flow);

        [$subject, $finalization] = DB::transaction(function () use ($principal, $definition, $key, $revision, $reason, $channel): array {
            $subject = $this->subject($definition, $key, lock: true);
            $this->authorize($principal, $definition, $subject, 'unfinalize');
            $finalization = $this->current($definition, $definition->keyOf($subject)) ?? throw new RuntimeException('finalize_not_finalized');
            $this->fresh($this->compute($definition, $subject), $revision);
            if (($refusal = $definition->unfinalizeRefusal($principal, $subject, $finalization)) !== null) {
                throw new UnfinalizeRefused($refusal);
            }
            $finalization->forceFill(['unfinalized_by' => $principal->actorId, 'unfinalized_at' => now(), 'unfinalized_channel' => $channel, 'unfinalize_reason' => $reason])->save();
            $this->bumpLock($definition, $subject);

            return [$subject, $finalization];
        });
        event(new Unfinalized($definition->name, $subject, $finalization));

        return $this->present($principal, $definition, $subject);
    }

    /**
     * Every step's outcome from real state, and what follows: the revision, the snapshot hash and whether the gate is complete.
     *
     * @return array{rows: array<string, array{step: Step, outcome: StepOutcome, fingerprint: ?string, latest: ?Confirmation, state: string}>, complete: bool, revision: string, snapshot: string, finalization: ?Finalization}
     */
    private function compute(Flow $definition, Model $subject): array
    {
        $key = $definition->keyOf($subject);
        $latest = Confirmation::query()->where('flow', $definition->name)->where('subject_key', $key)->orderByDesc('id')->get()->unique('step')->keyBy('step');
        $rows = [];
        foreach ($definition->stepList() as $step) {
            $outcome = $step->evaluate($subject);
            $fingerprint = $step->fingerprint($subject);
            $confirmation = $latest->get($step->key);
            $state = match (true) {
                $outcome->state === 'skipped' && ! $step->required() => 'skipped',
                $outcome->state !== 'done' => 'pending',
                $step->kind === 'check' => 'done',
                $confirmation !== null && hash_equals((string) $confirmation->fingerprint, (string) $fingerprint) => 'done',
                default => 'pending',
            };
            $rows[$step->key] = ['step' => $step, 'outcome' => $outcome, 'fingerprint' => $fingerprint, 'latest' => $confirmation, 'state' => $state];
        }
        $complete = collect($rows)->every(fn (array $row): bool => ! $row['step']->required() || in_array($row['state'], ['done', 'skipped'], true));
        $finalization = $this->current($definition, $key);
        $snapshot = hash('sha256', json_encode(array_map(fn (array $row): array => [$row['state'], $row['fingerprint'], $row['latest']?->id], $rows), JSON_THROW_ON_ERROR));

        return ['rows' => $rows, 'complete' => $complete, 'revision' => hash('sha256', $definition->name.'|'.$key.'|'.$snapshot.'|'.($finalization?->id ?? '-')), 'snapshot' => $snapshot, 'finalization' => $finalization];
    }

    private function present(Principal $principal, Flow $definition, Model $subject): GateState
    {
        $computed = $this->compute($definition, $subject);
        $key = $definition->keyOf($subject);
        $finalized = $computed['finalization'] !== null;
        $canConfirm = ! $finalized && $this->allowed($principal, $definition, $subject, 'confirm');
        $names = $this->names(collect($computed['rows'])->pluck('latest')->filter()->pluck('confirmed_by')->push($computed['finalization']?->finalized_by)->filter()->all());
        $current = collect($computed['rows'])->first(fn (array $row): bool => $row['step']->required() && $row['state'] === 'pending');
        $steps = [];
        $done = 0;
        $requiredDone = 0;
        $required = 0;
        foreach ($computed['rows'] as $stepKey => $row) {
            /** @var Step $step */
            $step = $row['step'];
            $outcome = $row['outcome'];
            $latest = $row['latest'];
            $stale = $latest !== null && $row['state'] !== 'done' && $outcome->state === 'done';
            $errors = $outcome->errors;
            $reason = $outcome->reason;
            $actions = $outcome->actions;
            if ($stale) {
                $reason = 'Changed since '.($names[$latest->confirmed_by] ?? 'someone').' confirmed it on '.$latest->confirmed_at->toFormattedDateString().'.';
                $errors = [new GateError($reason), ...$errors];
            }
            if ($step->kind === 'confirmation' && $row['state'] === 'pending' && $outcome->state === 'done' && $canConfirm) {
                $actions = [...$actions, new Action($step->confirmText(), $this->url($subject, $definition, 'steps/'.$stepKey.'/confirm', $computed['revision']), 'post', key: 'confirm-'.$stepKey)];
            }
            $state = $row['state'] === 'pending' && $current !== null && $current['step']->key === $stepKey && ! $finalized ? 'active' : $row['state'];
            $steps[] = new GateStep($stepKey, $step->label, $step->kind, $state, $step->required(), $step->describe() ?? $outcome->detail, $reason, $errors, $finalized ? [] : $actions,
                $latest === null ? null : new GateConfirmation($names[$latest->confirmed_by] ?? 'Someone', $latest->confirmed_at->toIso8601String(), $stale),
                $row['state'] === 'skipped' ? [] : $step->warningsFor($subject));
            $done += in_array($row['state'], ['done', 'skipped'], true) ? 1 : 0;
            $required += $step->required() ? 1 : 0;
            $requiredDone += $step->required() && in_array($row['state'], ['done', 'skipped'], true) ? 1 : 0;
        }
        $statement = $definition->statementFor($subject);
        $steps[] = new GateStep('certify', $definition->certifyText(), 'certify', $finalized ? 'done' : ($computed['complete'] ? 'active' : 'pending'), true, null, null, [], [], null);
        $finalize = null;
        if (! $finalized && $this->allowed($principal, $definition, $subject, 'certify')) {
            $url = $this->url($subject, $definition, 'finalize', $computed['revision']);
            $finalize = $computed['complete']
                ? new Action($definition->certifyText(), $url, 'post', key: 'finalize', priority: 'primary')
                : new Action($definition->certifyText(), $url, 'post', disabled: true, reason: 'Every required step must be done first.', key: 'finalize', priority: 'primary');
        }
        $record = null;
        if ($finalized) {
            $f = $computed['finalization'];
            $unfinalize = $this->allowed($principal, $definition, $subject, 'unfinalize')
                ? new Action('Un-finalize', $this->url($subject, $definition, 'unfinalize', $computed['revision']), 'post', key: 'unfinalize')
                : null;
            $record = new FinalizationRecord($names[$f->finalized_by] ?? 'Someone', $f->finalized_at->toIso8601String(), (string) $f->channel, (string) $f->statement_version, $unfinalize);
        }

        return new GateState($definition->name, $key, $steps, new GateProgress(count($computed['rows']), $done, $required, $requiredDone, $computed['complete'], $current === null ? null : $current['step']->key, $computed['revision']), $statement['text'], $statement['version'], $finalize, $record);
    }

    private function subject(Flow $definition, string $key, bool $lock = false): Model
    {
        Workspaces::required();

        return $definition->find($key, $lock) ?? throw new RuntimeException('finalize_subject_not_found');
    }

    /** Workspace permission first, then the flow's per-subject check. A subject the viewer may not read is not found, so its existence does not leak. */
    private function authorize(Principal $principal, Flow $definition, Model $subject, string $ability): void
    {
        $this->access->authorize($principal, self::ABILITIES[$ability]);
        if (! $definition->allows($principal, $subject, $ability)) {
            throw new RuntimeException($ability === 'read' || ! $definition->allows($principal, $subject, 'read') ? 'finalize_subject_not_found' : 'permission_denied');
        }
        if ($ability !== 'read') {
            $this->authorize($principal, $definition, $subject, 'read');
        }
    }

    private function allowed(Principal $principal, Flow $definition, Model $subject, string $ability): bool
    {
        try {
            $this->authorize($principal, $definition, $subject, $ability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function current(Flow $definition, string $key): ?Finalization
    {
        return Finalization::query()->where('flow', $definition->name)->where('subject_key', $key)->whereNull('unfinalized_at')->orderByDesc('id')->first();
    }

    private function notFinalized(Flow $definition, Model $subject): void
    {
        if ($this->current($definition, $definition->keyOf($subject)) !== null) {
            throw new RuntimeException('finalize_finalized');
        }
    }

    /** @param  array{revision: string}  $computed */
    private function fresh(array $computed, string $revision): void
    {
        if (! hash_equals($computed['revision'], $revision)) {
            throw new RuntimeException('finalize_stale');
        }
    }

    private function step(Flow $definition, string $key): Step
    {
        foreach ($definition->stepList() as $step) {
            if ($step->key === $key) {
                return $step;
            }
        }
        throw new RuntimeException('finalize_step_unknown');
    }

    private function bumpLock(Flow $definition, Model $subject): void
    {
        $column = $definition->lockColumnName();
        if ($column === null) {
            return;
        }
        // Through the model's own query, so the app's scopes and composite key apply; the row is already locked by this transaction.
        $subject->forceFill([$column => ((int) $subject->getAttribute($column)) + 1])->saveQuietly();
    }

    private function channel(string $channel): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException('A finalize channel is web or mcp.');
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

    /** An action's URL, carrying the revision the viewer was shown: a plain descriptor POST then sends it back, and a changed gate refuses it. */
    private function url(Model $subject, Flow $definition, string $path, ?string $revision = null): string
    {
        $url = WorkspaceUrl::to(Workspaces::required(), 'finalize/'.$definition->name.'/'.rawurlencode($definition->keyOf($subject)).'/'.$path, 'finalize');

        return $revision === null ? $url : $url.'?revision='.$revision;
    }
}
