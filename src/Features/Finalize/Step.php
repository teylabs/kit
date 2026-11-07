<?php

namespace Tey\Kit\Features\Finalize;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Features\Finalize\Data\GateError;

/**
 * One step of a finalize flow, registered by the feature that owns it.
 *
 * - `Step::check($key, $label, fn (Model $subject) => …)` holds when its check passes against real state, by whatever path made it pass. The check returns
 *   a StepOutcome, a bool, or a list of error messages.
 * - `Step::confirmation($key, $label, fingerprint: fn (Model $subject) => …)` holds while a person has confirmed it and what they confirmed is unchanged.
 *   The fingerprint returns the **normalised values** the person attests to (amounts in minor units, labels, printed text), never timestamps: a quiet
 *   recalculation that changes nothing they saw must not undo their confirmation. `ready:` is an optional check that must hold before it can be confirmed.
 *
 * `warnings(fn (Model $subject) => [...])` adds non-blocking notes (an optional photo missing): shown in the step on every read, never blocking Confirm
 *   or completion. It returns a list of messages or GateErrors.
 *
 * `optional()` lets the flow complete without it; `detail()` is the line under its label; `confirmLabel()` names its Confirm button.
 */
final class Step
{
    private bool $required = true;

    private ?string $detail = null;

    private string $confirmLabel = 'Confirm';

    private ?Closure $warnings = null;

    private function __construct(public readonly string $key, public readonly string $label, public readonly string $kind, private readonly ?Closure $check, private readonly ?Closure $fingerprint) {}

    public static function check(string $key, string $label, callable $check): self
    {
        return new self(self::key($key), $label, 'check', Closure::fromCallable($check), null);
    }

    public static function confirmation(string $key, string $label, callable $fingerprint, ?callable $ready = null): self
    {
        return new self(self::key($key), $label, 'confirmation', $ready === null ? null : Closure::fromCallable($ready), Closure::fromCallable($fingerprint));
    }

    public function warnings(callable $warnings): self
    {
        $this->warnings = Closure::fromCallable($warnings);

        return $this;
    }

    /** @return list<GateError> */
    public function warningsFor(Model $subject): array
    {
        if ($this->warnings === null) {
            return [];
        }
        $warnings = ($this->warnings)($subject);
        if (! is_array($warnings)) {
            throw new \InvalidArgumentException("Finalize step [{$this->key}]'s warnings return a list of messages or GateErrors.");
        }

        return array_values(array_map(fn ($warning): GateError => is_string($warning) ? new GateError($warning) : $warning, $warnings));
    }

    public function optional(): self
    {
        $this->required = false;

        return $this;
    }

    public function detail(string $detail): self
    {
        $this->detail = $detail;

        return $this;
    }

    public function confirmLabel(string $label): self
    {
        $this->confirmLabel = $label;

        return $this;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function describe(): ?string
    {
        return $this->detail;
    }

    public function confirmText(): string
    {
        return $this->confirmLabel;
    }

    /** The check's outcome; a confirmation with no `ready` check is always ready. */
    public function evaluate(Model $subject): StepOutcome
    {
        return $this->check === null ? StepOutcome::done() : StepOutcome::from(($this->check)($subject));
    }

    /** A confirmation step's current fingerprint, a sha256 of its normalised values; null for a check. */
    public function fingerprint(Model $subject): ?string
    {
        if ($this->fingerprint === null) {
            return null;
        }
        $value = ($this->fingerprint)($subject);

        return hash('sha256', is_string($value) ? $value : json_encode(self::sorted($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            if (is_float($value) || is_object($value)) {
                throw new \InvalidArgumentException('A finalize fingerprint holds normalised scalars (integers, strings, booleans), not floats or objects.');
            }

            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sorted(...), $value);
    }

    private static function key(string $key): string
    {
        if (preg_match('/\A[a-z0-9]+(?:[-_.][a-z0-9]+)*\z/', $key) !== 1 || strlen($key) > 64) {
            throw new \InvalidArgumentException('Invalid finalize step key: '.$key);
        }

        return $key;
    }
}
