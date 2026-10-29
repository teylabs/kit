<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The server's re-check of a typed confirmation. A destructive action whose descriptor asks for typed text (`confirm.typed`) receives what the person typed as
 * `confirmation`; its operation calls `require()` before doing anything, because the client's disabled Confirm button is never the guard. Surrounding whitespace is
 * ignored, as in the dialog; case matters unless `$caseSensitive` is false. A mismatch, or no `confirmation` at all, is a validation error on `confirmation`.
 */
final class TypedConfirmation
{
    /** @param  Request|array<string, mixed>  $input */
    public static function require(Request|array $input, string $expected, bool $caseSensitive = true): void
    {
        $given = $input instanceof Request ? $input->input('confirmation') : ($input['confirmation'] ?? null);
        if (! self::matches(is_string($given) ? $given : null, $expected, $caseSensitive)) {
            throw ValidationException::withMessages(['confirmation' => 'Type “'.$expected.'” to confirm.']);
        }
    }

    public static function matches(?string $given, string $expected, bool $caseSensitive = true): bool
    {
        if ($given === null || $expected === '') {
            return false;
        }
        $given = trim($given);

        return $caseSensitive ? $given === $expected : mb_strtolower($given) === mb_strtolower($expected);
    }

    /**
     * A descriptor's `confirm` that asks for typed text: what Action's `confirm` takes.
     *
     * @return array{title: string, description: string, label: string, typed: string}
     */
    public static function confirm(string $title, string $description, string $label, string $typed): array
    {
        return ['title' => $title, 'description' => $description, 'label' => $label, 'typed' => $typed];
    }
}
