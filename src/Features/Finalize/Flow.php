<?php

namespace Tey\Kit\Features\Finalize;

use Closure;
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
}
