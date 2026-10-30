<?php

namespace Tey\Kit\Features\SessionUi\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse as FortifyFailed;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse as FortifySuccessful;

/**
 * The answer to a reset-link request, sent or not: back with the same `status` flash, or 200 `{"message"}` for JSON. Fortify's own failure says
 * the address has no account (a 422 or an `email` error), which lets anyone test addresses. Validation (422) and throttling (429) happen before this.
 */
final class NeutralResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /** Only Fortify's default responses are replaced; a host's own binding is kept. */
    public static function replaces(object $response): bool
    {
        return $response::class === FortifyFailed::class || $response::class === FortifySuccessful::class;
    }

    public static function message(): string
    {
        return (string) trans('kit::ui.auth.reset_link_sent');
    }

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::message()], 200)
            : back()->with('status', self::message());
    }
}
