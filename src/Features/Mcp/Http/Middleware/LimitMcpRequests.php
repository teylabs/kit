<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /mcp against an assistant's reconnect storm: a per-token rate (kit.mcp.limits.per_minute) and a cap on requests in flight per token
 * (per_token) and across the app (total). Over either, a 429 with Retry-After, before the request does any work. A cap of 0 turns that guard off.
 */
class LimitMcpRequests
{
    public function handle(Request $request, Closure $next): mixed
    {
        $limits = (array) config('kit.mcp.limits', []);
        $token = (string) ($request->user()?->currentAccessToken()?->oauth_access_token_id ?? $request->user()?->getAuthIdentifier() ?? $request->ip());
        $perMinute = (int) ($limits['per_minute'] ?? 120);
        if ($perMinute > 0) {
            $key = 'kit-mcp|'.$token;
            if (RateLimiter::tooManyAttempts($key, $perMinute)) {
                return $this->refuse(max(1, RateLimiter::availableIn($key)));
            }
            RateLimiter::hit($key, 60);
        }
        $held = [];
        try {
            foreach (['token:'.$token => (int) ($limits['per_token'] ?? 4), 'total' => (int) ($limits['total'] ?? 0)] as $scope => $cap) {
                if ($cap <= 0) {
                    continue;
                }
                $lock = $this->slot($scope, $cap, max(1, (int) ($limits['hold_seconds'] ?? 30)));
                if ($lock === null) {
                    return $this->refuse(max(1, (int) ($limits['retry_after'] ?? 2)));
                }
                $held[] = $lock;
            }

            return $next($request);
        } finally {
            foreach ($held as $lock) {
                $lock->release();
            }
        }
    }

    /** One free slot of `$cap` for this scope, or null when every slot is held. A slot expires after `$seconds` if a worker dies holding it. */
    private function slot(string $scope, int $cap, int $seconds): ?Lock
    {
        for ($i = 0; $i < $cap; $i++) {
            $lock = Cache::lock('kit-mcp-inflight|'.$scope.'|'.$i, $seconds);
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    private function refuse(int $seconds): Response
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'Too many requests; retry after '.$seconds.'s.']], 429, ['Retry-After' => (string) $seconds]);
    }
}
