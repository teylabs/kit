<?php

namespace Tey\Kit\Features\Finalize;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Core\Principal;

/**
 * A finalize flow: the steps a subject must pass before someone certifies it, and how the kit finds and guards that subject. An app registers one in a
 * provider with `app(Flows::class)->register(Flow::define(…)->…)`.
 *
 * - `subject($alias, resolve: fn (string $key, bool $lock): ?Model, key: fn (Model): string)`: how a subject is found from its key inside the current
 *   workspace (and locked for update when `$lock`), and how its key is written. A composite key (a tenant and a local number) is the app's own string.
 * - `steps([...])`: Step::check and Step::confirmation, in order. The certify step is added last by the kit.
 * - `statement(fn (Model): array{version: string, text: string})`: what the certifier agrees to; its version and a hash of its text are recorded.
 * - `can(fn (Principal, Model, string $ability): bool)`: a per-subject check after the workspace permission, for read, confirm, certify and unfinalize.
 *   Without one, the workspace permission alone decides.
 * - `lockColumn('lock_version')`: finalizing and un-finalizing bump it, so a stale write from an open tab is refused by the app's own check.
 * - `unfinalizeCheck(fn (Principal, Model, $finalization): ?string)`: an optional extra rule for un-finalizing (a second person, say); a string refuses
 *   with that reason. Off unless set.
 * - `url(fn (Model): string)`: where a browser returns after an action.
 * - `snapshot(fn (Model): array<string, int|string|bool|null>)`: the printed fields that come from a record the subject does not own (a customer's name
 *   and address on an invoice), as normalised scalars. Finalizing keeps a frozen copy of exactly these values on the finalization, with a hash per key,
 *   so the finalized subject prints what was certified even after that record is edited; `FinalizeGate::drift()` names the keys that changed since.
 *   Only the declared fields are kept: everything else about a finalization is hashes. Off unless set.
 * - `extras(rules: [...], schema: fn (JsonSchema): array, apply: fn (Model, Finalization, array $extras, Principal): void)`: further fields the certify
 *   request may carry (an optional "what changed" note, say). Only the declared names are read: a page visit drops any other field, a JSON or MCP
 *   caller is refused `unknown_arguments`, and the rules run after the gate's own checks. `schema` describes them to an assistant (the MCP finalize
 *   tool's `extra` argument); `apply` runs inside the finalize transaction, after the finalization is written, so a failure rolls it back. The validated
 *   extras also ride on `Finalized::$extras`. The kit stores none of them. Off unless set: a flow without extras reads nothing beyond `certified`.
 */
final class Flow
{
    private ?string $alias = null;

    private ?Closure $resolve = null;

    private ?Closure $key = null;

    /** @var list<Step> */
    private array $steps = [];

    private ?Closure $statement = null;

    private ?Closure $can = null;

    private ?string $lockColumn = null;

    private ?Closure $unfinalizeCheck = null;

    private ?Closure $url = null;

    private string $certifyLabel = 'Certify and finalize';

    private ?Closure $snapshot = null;

    /** @var array<string, mixed>|null */
    private ?array $extraRules = null;

    private ?Closure $extraSchema = null;

    private ?Closure $extraApply = null;

    /** Names the certify request already carries, or a form or the framework does: an extra never takes one. */
    public const array RESERVED_EXTRAS = ['certified', 'revision', 'flow', 'subject', 'intent_key', 'confirm_token', 'extra', '_token', '_method'];

    /** The most fields a snapshot keeps: it is the printed details of a related record, not a copy of the case. */
    public const int SNAPSHOT_MAX_KEYS = 50;

    private function __construct(public readonly string $name) {}

    public static function define(string $name): self
    {
        if (preg_match('/\A[a-z0-9]+(?:[-_.][a-z0-9]+)*\z/', $name) !== 1 || strlen($name) > 64) {
            throw new \InvalidArgumentException('Invalid finalize flow name: '.$name);
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

    /** @param  list<Step>  $steps */
    public function steps(array $steps): self
    {
        $keys = array_map(fn (Step $step): string => $step->key, $steps);
        if (count($keys) !== count(array_unique($keys)) || in_array('certify', $keys, true)) {
            throw new \InvalidArgumentException("A finalize flow's step keys are unique, and `certify` is the kit's.");
        }
        $this->steps = array_values($steps);

        return $this;
    }

    public function statement(callable $statement): self
    {
        $this->statement = Closure::fromCallable($statement);

        return $this;
    }

    public function can(callable $can): self
    {
        $this->can = Closure::fromCallable($can);

        return $this;
    }

    public function lockColumn(string $column): self
    {
        $this->lockColumn = $column;

        return $this;
    }

    public function unfinalizeCheck(callable $check): self
    {
        $this->unfinalizeCheck = Closure::fromCallable($check);

        return $this;
    }

    public function url(callable $url): self
    {
        $this->url = Closure::fromCallable($url);

        return $this;
    }

    public function snapshot(callable $snapshot): self
    {
        $this->snapshot = Closure::fromCallable($snapshot);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $rules  each extra field's validation rules, by field name
     * @param  callable(JsonSchema): array<string, mixed>  $schema  each field's JSON schema type, the same names
     * @param  (callable(Model, Models\Finalization, array<string, mixed>, Principal): void)|null  $apply
     */
    public function extras(array $rules, callable $schema, ?callable $apply = null): self
    {
        if ($rules === []) {
            throw new \InvalidArgumentException("Finalize flow [{$this->name}]'s extras name at least one field.");
        }
        foreach (array_keys($rules) as $field) {
            if (! is_string($field) || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $field) !== 1 || in_array($field, self::RESERVED_EXTRAS, true)) {
                throw new \InvalidArgumentException("Finalize flow [{$this->name}]'s extras are top-level snake_case field names, none of ".implode(', ', self::RESERVED_EXTRAS).'.');
            }
        }
        $this->extraRules = $rules;
        $this->extraSchema = Closure::fromCallable($schema);
        $this->extraApply = $apply === null ? null : Closure::fromCallable($apply);

        return $this;
    }

    public function certifyLabel(string $label): self
    {
        $this->certifyLabel = $label;

        return $this;
    }

    /** @internal Checked once, when the flow is registered. */
    public function assertComplete(): void
    {
        if ($this->alias === null || $this->statement === null || $this->steps === []) {
            throw new \InvalidArgumentException("Finalize flow [{$this->name}] needs subject(), statement() and at least one step.");
        }
    }

    public function alias(): string
    {
        return (string) $this->alias;
    }

    /** @return list<Step> */
    public function stepList(): array
    {
        return $this->steps;
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

    /** @return array{version: string, text: string} */
    public function statementFor(Model $subject): array
    {
        $statement = ($this->statement)($subject);
        if (! is_array($statement) || ! is_string($statement['version'] ?? null) || ! is_string($statement['text'] ?? null) || $statement['text'] === '') {
            throw new \RuntimeException("Finalize flow [{$this->name}]'s statement returns ['version' => string, 'text' => string].");
        }

        return ['version' => $statement['version'], 'text' => $statement['text']];
    }

    public function allows(Principal $principal, Model $subject, string $ability): bool
    {
        return $this->can === null || ($this->can)($principal, $subject, $ability) === true;
    }

    public function lockColumnName(): ?string
    {
        return $this->lockColumn;
    }

    public function unfinalizeRefusal(Principal $principal, Model $subject, mixed $finalization): ?string
    {
        $reason = $this->unfinalizeCheck === null ? null : ($this->unfinalizeCheck)($principal, $subject, $finalization);

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    public function urlFor(Model $subject): ?string
    {
        return $this->url === null ? null : ($this->url)($subject);
    }

    public function certifyText(): string
    {
        return $this->certifyLabel;
    }

    public function hasExtras(): bool
    {
        return $this->extraRules !== null;
    }

    /** @return list<string> the declared extra fields; empty without extras */
    public function extraFields(): array
    {
        return array_keys($this->extraRules ?? []);
    }

    /** @return array<string, mixed> */
    public function extraRules(): array
    {
        return $this->extraRules ?? [];
    }

    /**
     * The declared extras' JSON schema types, checked against the declared names. Empty without extras.
     *
     * @return array<string, mixed>
     */
    public function extraSchema(JsonSchema $schema): array
    {
        if ($this->extraSchema === null) {
            return [];
        }
        $types = ($this->extraSchema)($schema);
        if (! is_array($types) || array_diff(array_keys($types), $this->extraFields()) !== []) {
            throw new \RuntimeException("Finalize flow [{$this->name}]'s extras schema describes only its declared fields.");
        }

        return $types;
    }

    /** @param  array<string, mixed>  $extras  validated */
    public function applyExtras(Model $subject, Models\Finalization $finalization, array $extras, Principal $principal): void
    {
        if ($this->extraApply !== null) {
            ($this->extraApply)($subject, $finalization, $extras, $principal);
        }
    }

    public function hasSnapshot(): bool
    {
        return $this->snapshot !== null;
    }

    /**
     * The declared printed fields' current values, checked: string keys, at most SNAPSHOT_MAX_KEYS of them, each value an integer, string, boolean or null
     * (amounts in minor units, never floats). Null when the flow declares no snapshot.
     *
     * @return array<string, int|string|bool|null>|null
     */
    public function snapshotFor(Model $subject): ?array
    {
        if ($this->snapshot === null) {
            return null;
        }
        $values = ($this->snapshot)($subject);
        if (! is_array($values) || count($values) > self::SNAPSHOT_MAX_KEYS) {
            throw new \RuntimeException("Finalize flow [{$this->name}]'s snapshot returns at most ".self::SNAPSHOT_MAX_KEYS.' printed fields as an array.');
        }
        foreach ($values as $key => $value) {
            if (! is_string($key) || preg_match('/\A[A-Za-z][A-Za-z0-9_.]{0,63}\z/', $key) !== 1 || ! (is_int($value) || is_string($value) || is_bool($value) || $value === null)) {
                throw new \RuntimeException("Finalize flow [{$this->name}]'s snapshot holds named, normalised scalars (integers, strings, booleans, null), not floats, lists or objects.");
            }
        }
        ksort($values);

        return $values;
    }
}
