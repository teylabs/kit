<?php

namespace Tey\Kit\Validation;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The server side of the kit's pickers: a value is the plain ISO string the picker submits, and nothing else.
 * A date (`YYYY-MM-DD`), month (`YYYY-MM`), year (`YYYY`) and time (`HH:MM`) are wall values: the rule checks them as written and never converts them between zones.
 * An instant is RFC 3339 with an offset (`2026-07-15T09:30:00-03:00`); one without an offset fails, so a timed value never takes the server's zone by accident.
 * Bounds are inclusive and in the same form as the value; an instant's bounds compare as instants.
 */
final class IsoDate implements ValidationRule
{
    private const PATTERNS = [
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
        'month' => '/^\d{4}-\d{2}$/',
        'year' => '/^\d{4}$/',
        'time' => '/^\d{2}:\d{2}$/',
        'instant' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/',
    ];

    private const NOUNS = ['date' => 'date', 'month' => 'month', 'year' => 'year', 'time' => 'time', 'instant' => 'date and time'];

    private function __construct(private readonly string $kind, private readonly ?string $min = null, private readonly ?string $max = null)
    {
        foreach ([$min, $max] as $bound) {
            if ($bound !== null && ! self::valid($kind, $bound)) {
                throw new \InvalidArgumentException("IsoDate {$kind} bound [{$bound}] is not a {$kind} in ISO form.");
            }
        }
    }

    public static function date(?string $min = null, ?string $max = null): self
    {
        return new self('date', $min, $max);
    }

    public static function month(?string $min = null, ?string $max = null): self
    {
        return new self('month', $min, $max);
    }

    public static function year(?string $min = null, ?string $max = null): self
    {
        return new self('year', $min, $max);
    }

    public static function time(?string $min = null, ?string $max = null): self
    {
        return new self('time', $min, $max);
    }

    public static function instant(?string $min = null, ?string $max = null): self
    {
        return new self('instant', $min, $max);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    /** Whether `$value` is a real value of `$kind` in ISO form (`2026-02-30` and `24:00` are not). */
    public static function valid(string $kind, mixed $value): bool
    {
        if (! is_string($value) || ! isset(self::PATTERNS[$kind]) || preg_match(self::PATTERNS[$kind], $value) !== 1) {
            return false;
        }

        return match ($kind) {
            'date' => checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)),
            'month' => (int) substr($value, 5, 2) >= 1 && (int) substr($value, 5, 2) <= 12,
            'year' => (int) $value >= 1,
            'time' => (int) substr($value, 0, 2) <= 23 && (int) substr($value, 3, 2) <= 59,
            'instant' => self::instantValid($value),
        };
    }

    /** Compares two valid values of `$kind`: wall values as strings (ISO sorts), instants by the moment they name. */
    public static function compare(string $kind, string $a, string $b): int
    {
        if ($kind === 'instant') {
            return self::moment($a) <=> self::moment($b);
        }

        return strcmp($a, $b) <=> 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $noun = self::NOUNS[$this->kind];
        if (! self::valid($this->kind, $value)) {
            $fail($this->kind === 'instant' ? "The :attribute must be a {$noun} with its offset." : "The :attribute must be a valid {$noun}.");

            return;
        }
        if ($this->min !== null && self::compare($this->kind, $value, $this->min) < 0) {
            $fail("The :attribute must be on or after {$this->min}.");
        }
        if ($this->max !== null && self::compare($this->kind, $value, $this->max) > 0) {
            $fail("The :attribute must be on or before {$this->max}.");
        }
    }

    private static function instantValid(string $value): bool
    {
        $date = substr($value, 0, 10);
        $hour = (int) substr($value, 11, 2);
        $minute = (int) substr($value, 14, 2);
        $offset = preg_match('/([+-])(\d{2}):(\d{2})$/', $value, $m) === 1 ? [(int) $m[2], (int) $m[3]] : [0, 0];

        return self::valid('date', $date) && $hour <= 23 && $minute <= 59 && $offset[0] <= 14 && $offset[1] <= 59;
    }

    private static function moment(string $value): int
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
    }
}
