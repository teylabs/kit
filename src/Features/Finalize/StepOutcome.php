<?php

namespace Tey\Kit\Features\Finalize;

use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Finalize\Data\GateError;

/**
 * What a step's check found. `done()` holds; `todo()` does not yet, with the reasons and the fix as action descriptors; `blocked()` cannot start until
 * something else is done, with a reason the person can act on; `skipped()` is for an optional step that does not apply.
 */
final class StepOutcome
{
    /**
     * @param  list<GateError>  $errors
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     */
    private function __construct(public readonly string $state, public readonly array $errors = [], public readonly array $actions = [], public readonly ?string $reason = null, public readonly ?string $detail = null) {}

    public static function done(?string $detail = null): self
    {
        return new self('done', detail: $detail);
    }

    /**
     * @param  list<GateError|string>  $errors
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     */
    public static function todo(array $errors = [], array $actions = [], ?string $detail = null): self
    {
        return new self('todo', self::errors($errors), array_values($actions), detail: $detail);
    }

    /** @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions */
    public static function blocked(string $reason, array $actions = []): self
    {
        return new self('blocked', [new GateError($reason)], array_values($actions), $reason);
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', reason: $reason);
    }

    /** A check may answer with an outcome, or a bool for done or not, or a list of error messages (none means done). */
    public static function from(mixed $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            $value === true => self::done(),
            $value === false, $value === null => self::todo(),
            is_array($value) => $value === [] ? self::done() : self::todo($value),
            default => throw new \InvalidArgumentException('A finalize check returns a StepOutcome, a bool or a list of errors.'),
        };
    }

    /**
     * @param  list<GateError|string>  $errors
     * @return list<GateError>
     */
    private static function errors(array $errors): array
    {
        return array_values(array_map(fn (GateError|string $error): GateError => is_string($error) ? new GateError($error) : $error, $errors));
    }
}
