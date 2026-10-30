<?php

namespace Tey\Kit\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The server side of InputDateRange and InputTimeRange: an array `{start, end}` of two values of one IsoDate kind, the start not after the end.
 * Both ends blank is an empty range and passes unless the attribute is also `required`; one end blank fails. Errors land on the range's own attribute.
 */
final class IsoRange implements ValidationRule
{
    private function __construct(private readonly IsoDate $each) {}

    public static function of(IsoDate $each): self
    {
        return new self($each);
    }

    public static function dates(?string $min = null, ?string $max = null): self
    {
        return new self(IsoDate::date($min, $max));
    }

    public static function times(?string $min = null, ?string $max = null): self
    {
        return new self(IsoDate::time($min, $max));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || array_diff(array_keys($value), ['start', 'end']) !== []) {
            $fail('The :attribute must have a start and an end.');

            return;
        }
        $start = $value['start'] ?? null;
        $end = $value['end'] ?? null;
        if (($start === null || $start === '') && ($end === null || $end === '')) {
            return;
        }
        if ($start === null || $start === '' || $end === null || $end === '') {
            $fail('The :attribute needs both a start and an end.');

            return;
        }
        $failed = false;
        foreach (['start' => $start, 'end' => $end] as $part => $one) {
            foreach ($this->each->failures($one) as $message) {
                $failed = true;
                $fail(str_replace(':attribute', ":attribute {$part}", $message));
            }
        }
        if (! $failed && IsoDate::compare($this->each->kind(), $start, $end) > 0) {
            $fail('The :attribute end must not be before its start.');
        }
    }
}
