<?php

namespace Tey\Kit\Features\Core;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as FrameworkHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use RuntimeException;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
        // Only the framework's own handler takes these: under `artisan`, Collision wraps it in an adapter without them, and HTTP errors are not its job.
        $handler = $this->app->make(ExceptionHandler::class);
        if (! $handler instanceof FrameworkHandler) {
            return;
        }
        // A client's oversized query string is a bad request, not a server fault: answered 400 and never reported.
        $handler->dontReportWhen(fn (Throwable $e): bool => $e instanceof ErrorException && self::isInputLimit($e));
        $handler->renderable(function (ErrorException $e, Request $request) {
            if (! self::isInputLimit($e)) {
                return null;
            }

            return $request->expectsJson() ? response()->json(['error' => 'bad_request'], 400) : response('Bad Request', 400);
        });
        // A query the table cannot honour (a sort or filter the server does not allow, from a stale link or a hand-edited URL) never reaches the client as a raw 400: an Inertia
        // visit is sent back to the same page without the bad parameter, with a toast; a JSON request gets a 400 it can read; anything else falls through to the error page below.
        if (class_exists(InvalidQuery::class)) {
            $handler->renderable(function (InvalidQuery $e, Request $request) {
                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    return response()->json(['error' => 'invalid_query', 'message' => $e->getMessage()], 400);
                }
                if ($request->header('X-Inertia') && $request->isMethodSafe()) {
                    $query = $request->query();
                    unset($query[match (true) {
                        $e instanceof InvalidSortQuery => 'sort',
                        $e instanceof InvalidFilterQuery => 'filter',
                        default => 'include',
                    }]);
                    Inertia::flash('toast', ['type' => 'info', 'message' => 'That sort or filter is not available, so it was cleared.']);

                    return redirect($request->url().($query === [] ? '' : '?'.http_build_query($query)), 303);
                }

                return null;
            });
        }
        // A registered error code becomes its status: JSON for API requests, else the adopted error page.
        $handler->renderable(function (RuntimeException $e, Request $request) {
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
        // Any other HTTP error on a page request (a 400, a 404, a 405, an expired session) is the adopted error page, not the framework's bare default.
        $handler->renderable(function (HttpExceptionInterface $e, Request $request) {
            $registry = app(Registry::class);
            $page = $registry->errorPage();
            if ($page === null || ($request->expectsJson() && ! $request->header('X-Inertia')) || $request->is('api/*', 'mcp/*', 'oauth/*', '.well-known/*')) {
                return null;
            }
            $response = $page('http_'.$e->getStatusCode(), $e->getStatusCode(), null, $request);

            return method_exists($response, 'toResponse') ? tap($response->toResponse($request), fn ($rendered) => $rendered->setStatusCode($e->getStatusCode())) : $response;
        });
    }
}
