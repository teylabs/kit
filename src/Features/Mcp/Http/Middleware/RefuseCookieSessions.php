<?php

namespace Tey\Kit\Features\Mcp\Http\Middleware;

use Closure;
use Tey\Kit\Features\Mcp\AssistantLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport keeps the pending authorization request in the session during consent. A cookie session outgrows the browser's 4 KB cookie limit there and is dropped without
 * a word, so consent would 403 or re-render with no error. On `session.driver` cookie the consent routes refuse with a clear page and a log line instead.
 */
class RefuseCookieSessions
{
    /** The refusal, naming the assistant with `kit.mcp.assistant_label`. */
    public static function message(): string
    {
        return 'Connecting '.AssistantLabel::article().' '.AssistantLabel::singular().' needs a server-side session. Set SESSION_DRIVER=database (or redis): a cookie session is too small for the consent request.';
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (config('session.driver') !== 'cookie') {
            return $next($request);
        }
        Log::error('MCP consent refused: SESSION_DRIVER is cookie, which cannot hold the OAuth authorization request. Set SESSION_DRIVER=database (or redis).', ['route' => $request->route()?->getName()]);

        return new Response(self::message(), 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
