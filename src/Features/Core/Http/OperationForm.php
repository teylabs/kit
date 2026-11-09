<?php

namespace Tey\Kit\Features\Core\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;

/**
 * The web side of an operation a form posts to. `input()` keeps only the fields the form declares on a page visit, so a stray browser field is dropped rather than
 * reaching the operation (which refuses unknown arguments, as it must for MCP and API callers). `run()` turns a field-level operation error into a
 * validation error on that field, so a page visit shows it inline; every other refusal (permission_denied, workspace_not_found, a 409 conflict)
 * keeps its registered status. A JSON request is left exactly as before: its whole input reaches the operation, and every operation error keeps its code.
 */
final class OperationForm
{
    /** Field-level operation errors and the field each belongs to. */
    public const FIELDS = ['slug_invalid' => 'slug', 'slug_taken' => 'slug'];

    private const MESSAGES = ['slug_invalid' => 'Use lowercase letters, numbers and hyphens.', 'slug_taken' => 'This address is already taken.'];

    /**
     * @param  list<string>  $fields  every field the form sends
     * @return array<string, mixed>
     */
    public static function input(Request $request, array $fields): array
    {
        // A JSON caller is an API client, not a form: it keeps the operation's strictness, so an argument it does not name is still `unknown_arguments`.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $request->except(['_token', '_method']);
        }

        // Presence only: the operation owns the rules, and runs them after its own checks, as it does for every caller.
        return Validator::make($request->all(), array_fill_keys($fields, ['sometimes']))->validated();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @param  array<string, string>  $fields  further field-level codes for this form, code => field
     * @return T
     */
    public static function run(Request $request, Closure $call, array $fields = []): mixed
    {
        try {
            return $call();
        } catch (RuntimeException $error) {
            $field = ($fields + self::FIELDS)[$error->getMessage()] ?? null;
            if ($field === null || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                throw $error;
            }
            $code = $error->getMessage();

            throw ValidationException::withMessages([$field => app(Registry::class)->message($code) ?? self::MESSAGES[$code] ?? $code]);
        }
    }
}
