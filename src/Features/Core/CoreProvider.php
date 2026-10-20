<?php

namespace Tey\Kit\Features\Core;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

final class CoreProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->errors(['workspace_not_found' => 404, 'workspace_mismatch' => 404, 'account_inactive' => 403, 'verification_required' => 403, 'admission_required' => 403, 'permission_denied' => 403, 'intent_conflict' => 409, 'unknown_arguments' => 422, 'slug_invalid' => 422, 'slug_taken' => 409]);
    }

    /**
     * PHP refuses a request whose input is too deep or too large ("Input variable nesting level exceeded", `max_input_vars`) with a
     * warning that Laravel raises as an ErrorException. Seen in production as `parse_str(): Input variable nesting level exceeded 64`,
     * thrown from Symfony's `HeaderUtils::parseQuery()` when `Request::fullUrl()` reads a query string like `?a[k0]…[k69]=1`.
     */
    private static function isInputLimit(ErrorException $e): bool
    {
        return str_contains($e->getMessage(), 'Input variable nesting level exceeded') || str_contains($e->getMessage(), 'max_input_vars');
    }

    public function boot(): void
    {
        // A client's oversized query string is a bad request, not a server fault: answered 400 and never reported.
        $handler = $this->app->make(ExceptionHandler::class);
        $handler->dontReportWhen(fn (Throwable $e): bool => $e instanceof ErrorException && self::isInputLimit($e));
        $handler->renderable(function (ErrorException $e, Request $request) {
            if (! self::isInputLimit($e)) {
                return null;
            }

            return $request->expectsJson() ? response()->json(['error' => 'bad_request'], 400) : response('Bad Request', 400);
        });
        // A registered error code becomes its status: JSON for API requests, else the adopted error page.
        $this->app->make(ExceptionHandler::class)->renderable(function (RuntimeException $e, Request $request) {
            $registry = app(Registry::class);
            $status = $registry->status($e->getMessage());
            if ($status === null) {
                return null;
            }
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], $status);
            }
            $page = $registry->errorPage();

            return $page === null ? response($e->getMessage(), $status) : $page($e->getMessage(), $status, $registry->message($e->getMessage()), $request);
        });
    }
}
