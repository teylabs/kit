<?php

// Password confirmation on the kit's Auth/ConfirmPassword page (Fortify's password.confirm), shared by test:onboarding (with workspace-ui) and test:auth-only
// (without): the SafeRedirect table, the page and its Cancel URL, a wrong and a right password, an off-site intended URL, and a route behind password.confirm.
// Returns a function taking the app, a user whose password is 'a long passphrase', and the check function.
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tey\Kit\Features\SessionUi\ConfirmPasswordCancel;
use Tey\Kit\Features\SessionUi\Contracts\ConfirmPasswordCancelUrl;
use Tey\Kit\Features\SessionUi\SafeRedirect;

return function ($app, object $user, callable $check, string $label): void {
    $origin = 'https://kit.test';
    foreach ([
        ['/me/security', '/me/security'], ['/@acme/settings?tab=people#x', '/@acme/settings?tab=people#x'], ['https://kit.test/me', 'https://kit.test/me'],
        ['HTTPS://KIT.TEST/me', 'HTTPS://KIT.TEST/me'], ['https://kit.test:443/me', 'https://kit.test:443/me'],
        [null, 'F'], ['', 'F'], ['https://evil.test/me', 'F'], ['http://kit.test/me', 'F'], ['https://kit.test:8443/me', 'F'], ['https://kit.test.evil.test/', 'F'],
        ['//evil.test', 'F'], ['///evil.test', 'F'], ['/\\evil.test', 'F'], ['\\\\evil.test', 'F'], ['https:\\\\evil.test', 'F'], ['/me\\..\\x', 'F'],
        ['javascript:alert(1)', 'F'], ['JavaScript:alert(1)', 'F'], ['data:text/html,x', 'F'], ['https://user:pass@kit.test/me', 'F'], ['https://user@kit.test/me', 'F'],
        [' /me', 'F'], ["/me\n", 'F'], ["/\t/evil.test", 'F'], ['me', 'F'], ['ftp://kit.test/me', 'F'], ['https:/evil.test', 'F'],
    ] as [$url, $expected]) {
        $check(SafeRedirect::sameOrigin($url, 'F', $origin) === $expected, $label.'SafeRedirect: '.json_encode($url).' gives '.json_encode($expected));
    }

    $session = $app['session']->driver();
    $session->flush();
    $hit = function (string $method, string $path, array $data = [], array $server = []) use ($app, $user, $session) {
        Auth::guard('web')->setUser($user);
        $request = Request::create('https://kit.test'.$path, $method, $data, server: $server);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        $app->instance('request', $request);
        try {
            return $app['router']->dispatch($request);
        } catch (Throwable $error) {
            return $app->make(ExceptionHandler::class)->render($request, $error);
        }
    };
    $inertia = ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'text/html, application/xhtml+xml', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
    $home = (string) config('kit.home', '/');
    $cancelFor = function (array $server, string $query = '') use ($hit, $inertia): array {
        $body = json_decode((string) $hit('GET', '/user/confirm-password'.$query, server: $inertia + $server)->getContent(), true) ?? [];

        return [$body['component'] ?? null, $body['props']['cancel'] ?? null, $body['props']['title'] ?? null];
    };

    [$component, $cancel, $title] = $cancelFor([]);
    $check($component === 'Auth/ConfirmPassword/Page' && $title === 'Confirm your password' && $cancel === $home, $label.'GET /user/confirm-password is the kit\'s Auth/ConfirmPassword page, Cancel to kit.home with no referer');
    $check($cancelFor(['HTTP_REFERER' => 'https://kit.test/me/security'])[1] === 'https://kit.test/me/security', $label.'a same-origin referer is kept as Cancel');
    $check($cancelFor([], '?cancel='.urlencode('/me/profile'))[1] === '/me/profile', $label.'a same-origin path in ?cancel= is kept as Cancel');
    foreach (['https://evil.test/x', '//evil.test', 'javascript:alert(1)', '/\\evil.test'] as $bad) {
        $check($cancelFor([], '?cancel='.urlencode($bad))[1] === $home && $cancelFor(['HTTP_REFERER' => $bad])[1] === $home, $label.'an off-site Cancel ('.$bad.') is replaced by kit.home, from ?cancel= or the referer');
    }
    $check($cancelFor(['HTTP_REFERER' => 'https://kit.test/user/confirm-password'])[1] === $home, $label.'the page itself as referer is not a Cancel target');

    // The default order (ConfirmPasswordCancel): ?cancel=, then the referer, then the intended URL one level up when a real non-guest GET route answers there,
    // then kit.home. A refused step (off the app) falls through to the next, never to its URL.
    $check($cancelFor(['HTTP_REFERER' => 'https://kit.test/me/security'], '?cancel='.urlencode('/me/profile'))[1] === '/me/profile', $label.'?cancel= wins over the referer');
    foreach (['https://evil.test/x', '//evil.test', '/\\evil.test', 'javascript:alert(1)', 'https://kit.test@evil.test/x', 'https://user@kit.test/x', '%2F%2Fevil.test', '%2F%5Cevil.test', 'http://kit.test/x', 'https://kit.test:8443/x', 'https://kit.test.evil.test/x'] as $bad) {
        $check($cancelFor(['HTTP_REFERER' => 'https://kit.test/me/security'], '?cancel='.urlencode($bad))[1] === 'https://kit.test/me/security', $label.'a refused ?cancel= ('.$bad.') falls through to the same-origin referer, never to itself');
    }
    $app['router']->middleware(['web', 'auth:web'])->get('/fixture-area', fn () => response('area'));
    $app['router']->middleware(['web', 'guest:web'])->get('/fixture-guest', fn () => response('guest'));
    $app['router']->getRoutes()->refreshNameLookups();
    try {
        foreach ([
            ['/fixture-area/deep', '/fixture-area', 'the intended URL one level up, a real non-guest route, when ?cancel= and the referer are refused'],
            ['https://kit.test/fixture-area/deep?x=1', '/fixture-area', 'an absolute same-origin intended URL, one level up'],
            ['/fixture-guest/deep', $home, 'a guest route one level up is not a Cancel target: kit.home'],
            ['/fixture-nowhere/deep', $home, 'no route one level up: kit.home'],
            ['https://evil.test/fixture-area/deep', $home, 'an off-site intended URL is refused: kit.home'],
            ['//evil.test/fixture-area/deep', $home, 'a protocol-relative intended URL is refused: kit.home'],
        ] as [$intended, $expected, $what]) {
            $session->put('url.intended', $intended);
            $check($cancelFor(['HTTP_REFERER' => 'https://evil.test/x'], '?cancel='.urlencode('//evil.test'))[1] === $expected, $label.$what.' ('.$intended.')');
        }
    } finally {
        $session->forget('url.intended');
    }

    // A host's own binding wins over the kit's order.
    $kit = $app->getBindings()[ConfirmPasswordCancelUrl::class] ?? null;
    $app->bind(ConfirmPasswordCancelUrl::class, fn () => new class implements ConfirmPasswordCancelUrl
    {
        public function resolve(Request $request): string
        {
            return '/host-cancel';
        }
    });
    try {
        $check($cancelFor(['HTTP_REFERER' => 'https://kit.test/me/security'], '?cancel='.urlencode('/me/profile'))[1] === '/host-cancel', $label.'a host-bound ConfirmPasswordCancelUrl decides Cancel, over ?cancel= and the referer');
    } finally {
        $app->bind(ConfirmPasswordCancelUrl::class, $kit['concrete'] ?? ConfirmPasswordCancel::class, $kit['shared'] ?? false);
    }
    $check($cancelFor([])[1] === $home, $label.'the kit\'s default is back once the host binding is gone');

    // A wrong password: a validation error, nothing confirmed.
    $session->forget('auth.password_confirmed_at');
    $response = $hit('POST', '/user/confirm-password', ['password' => 'not it'], ['HTTP_ACCEPT' => 'application/json']);
    $check($response->getStatusCode() === 422 && isset(json_decode((string) $response->getContent(), true)['errors']['password']) && ! $session->has('auth.password_confirmed_at'), $label.'a wrong password is a validation error and confirms nothing');

    // The right one: confirmed, and back to the (same-origin) intended URL.
    $session->put('url.intended', 'https://kit.test/me/security');
    $response = $hit('POST', '/user/confirm-password', ['password' => 'a long passphrase'], $inertia);
    $check($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test/me/security' && is_int($session->get('auth.password_confirmed_at')), $label.'the right password sets auth.password_confirmed_at and returns to the intended URL ('.$response->headers->get('Location').')');

    // An off-site intended URL is refused: kit.home instead.
    foreach (['https://evil.test/steal', '//evil.test', '/\\evil.test', 'javascript:alert(1)'] as $bad) {
        $session->forget('auth.password_confirmed_at');
        $session->put('url.intended', $bad);
        $response = $hit('POST', '/user/confirm-password', ['password' => 'a long passphrase'], $inertia);
        $check($response->getStatusCode() === 302 && $response->headers->get('Location') === url($home) && ! str_contains((string) $response->headers->get('Location'), 'evil'), $label.'an off-site intended URL ('.$bad.') redirects to kit.home ('.$response->headers->get('Location').')');
    }

    // A route behind password.confirm: to the page, then back after confirming.
    $app['router']->aliasMiddleware('password.confirm', RequirePassword::class);
    $app['router']->middleware(['web', 'auth:web', 'password.confirm'])->get('/fixture-sensitive', fn () => response('sensitive'));
    $app['router']->getRoutes()->refreshNameLookups();
    $session->forget('auth.password_confirmed_at');
    $session->forget('url.intended');
    $response = $hit('GET', '/fixture-sensitive');
    $check($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test/user/confirm-password', $label.'a route behind password.confirm redirects to the confirm page ('.$response->headers->get('Location').')');
    $response = $hit('POST', '/user/confirm-password', ['password' => 'a long passphrase'], $inertia);
    $check($response->getStatusCode() === 302 && $response->headers->get('Location') === 'https://kit.test/fixture-sensitive', $label.'confirming returns to it ('.$response->headers->get('Location').')');
    $response = $hit('GET', '/fixture-sensitive');
    $check($response->getStatusCode() === 200 && $response->getContent() === 'sensitive', $label.'and it then opens');
    Auth::guard('web')->logout();
};
