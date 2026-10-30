<?php

namespace Tey\Kit\Features\Core;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Exceptions\Handler as FrameworkHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tey\Kit\Features\Core\Http\Middleware\StartKitRequest;
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
        // The request's start is core's, so a host without session-ui has it too: StartKitRequest runs first in `web` (first in the group and in the
        // priority list, so no host middleware sorts ahead of it), beginning the request's memos and putting back the kit's Inertia props a flush removed,
        // before the host's middleware, route-model binding or an error page reads them. It joins through the HTTP kernel, which owns the groups, and
        // only once: the kernel skips a class already there, and session-ui no longer adds it.
        // An app without a `web` group (a bare app before its HTTP setup, a console-only install) has nothing to join, and the kernel throws on an unknown group.
        if ($this->app->bound(Kernel::class)) {
            $kernel = $this->app->make(Kernel::class);
            if (method_exists($kernel, 'getMiddlewareGroups') && array_key_exists('web', $kernel->getMiddlewareGroups())) {
                $kernel->prependMiddlewareToGroup('web', StartKitRequest::class);
                $kernel->prependToMiddlewarePriority(StartKitRequest::class);
            }
        }
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
                    Toast::info('That sort or filter is not available, so it was cleared.');

                    return redirect($request->url().($query === [] ? '' : '?'.http_build_query($query)), 303);
                }

                return null;
            });
        }
        // A registered error code below 500 is an expected refusal (a missing record, a denied action), not a fault: answered with its status and never reported.
        // An unregistered message, or a code registered as 5xx, still reports.
        $handler->dontReportWhen(function (Throwable $e): bool {
            if (! $e instanceof RuntimeException) {
                return false;
            }
            $status = app(Registry::class)->status($e->getMessage());

            return $status !== null && $status < 500;
        });
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
