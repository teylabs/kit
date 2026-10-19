<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class CoreProvider extends ServiceProvider implements Contributes
{
    public static function contribute(Registry $registry, array $features, array $routes): void
    {
        $registry->errors(['workspace_not_found' => 404, 'workspace_mismatch' => 404, 'account_inactive' => 403, 'verification_required' => 403, 'admission_required' => 403, 'permission_denied' => 403, 'intent_conflict' => 409, 'unknown_arguments' => 422]);
    }

    public function boot(): void
    {
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
