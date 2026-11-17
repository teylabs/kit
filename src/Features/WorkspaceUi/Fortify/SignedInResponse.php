<?php

namespace Tey\Kit\Features\WorkspaceUi\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;
use Tey\Kit\Features\WorkspaceUi\HomeDestination;

/**
 * Sign-in (password, and the two-factor challenge after it) straight to the person's home (HomeDestination), in one redirect rather than through
 * `kit.home` and the workspace list. The intended URL still wins. Only when Fortify's home is the kit's own (`kit.home` or the list): an app that
 * sends sign-in elsewhere keeps its page. JSON answers as Fortify's own.
 */
final class SignedInResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function __construct(private readonly bool $twoFactor = false) {}

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return $this->twoFactor ? new JsonResponse('', 204) : response()->json(['two_factor' => false]);
        }
        $home = Fortify::redirects('login');
        if (in_array(rtrim((string) $home, '/') ?: '/', [rtrim((string) config('kit.home', '/'), '/') ?: '/', WorkspaceUrl::chooser()], true)) {
            $home = HomeDestination::resolve($request) ?? WorkspaceUrl::chooser();
        }

        return redirect()->intended($home);
    }
}
