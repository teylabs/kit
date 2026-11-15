<?php

namespace Tey\Kit\Http;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request under the Vite build directory (`build/` by default, or what the app set with `Vite::useBuildDirectory()`) that names no file in `public/`
 * answers a plain 404 here, before routing: no session, no XSRF cookie, no HTML error page. A web server normally serves that directory itself, so a
 * request that reaches PHP is a file that is gone, such as a chunk an older page asks for after a deploy. Global middleware, prepended by KitServiceProvider.
 */
final class MissingBuildAsset
{
    public function handle(Request $request, Closure $next): mixed
    {
        $directory = trim((fn (): string => (string) $this->buildDirectory)->call(app(Vite::class)), '/');
        $path = $request->decodedPath();
        if ($directory === '' || ! str_starts_with($path, $directory.'/') || is_file(public_path($path))) {
            return $next($request);
        }

        return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
