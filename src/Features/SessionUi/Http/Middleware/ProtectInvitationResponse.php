<?php

namespace Tey\Kit\Features\SessionUi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tey\Kit\Features\SessionUi\Http\TenantInvitationResponses;

/**
 * The tenant invitation link's responses: the token is in the URL, so nothing is cached and no Referer carries it on. Outermost on the group, so it also sees
 * a throttle's refusal and answers it in words (JSON for an API client, the invitation page otherwise), with the throttle's Retry-After kept.
 */
class ProtectInvitationResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() === 429) {
            // An error page rendered from the throttle's exception may drop its headers; the exception still has them.
            $exception = $response->exception ?? null;
            $retry = $response->headers->get('Retry-After') ?? ($exception instanceof HttpExceptionInterface ? ($exception->getHeaders()['Retry-After'] ?? null) : null);
            $response = $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['error' => 'throttled', 'message' => TenantInvitationResponses::THROTTLED], 429)
                : TenantInvitationResponses::throttled($request);
            if ($retry !== null) {
                $response->headers->set('Retry-After', (string) $retry);
            }
        }
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
