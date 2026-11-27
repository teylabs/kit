<?php

namespace Tey\Kit\Features\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Http\Middleware\EnsureAccountActive as ConciergeEnsureAccountActive;
use Tey\Kit\Features\Core\RequestMemo;

/**
 * Concierge's `concierge.active`, reading the account through RequestMemo, so the request's later reads of it (Operators, the shell) reuse this one rather
 * than querying account_statuses again. Refuses exactly as Concierge's does.
 */
final class EnsureAccountActive extends ConciergeEnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof Member || RequestMemo::account($user)->isActive()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
