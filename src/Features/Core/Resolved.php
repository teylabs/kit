<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Illuminate\Contracts\Foundation\Application;

/**
 * Configures a service whenever it is resolved, so a provider's boot() works whichever provider boots first: a package that binds itself at boot
 * (Concierge from 0.11.1) would replace an instance resolved before then, and the setting would be lost. Already resolved, it is configured now too.
 */
final class Resolved
{
    /**
     * @param  class-string  $abstract
     * @param  Closure(object): mixed  $configure
     */
    public static function configure(Application $app, string $abstract, Closure $configure): void
    {
        if ($app->resolved($abstract)) {
            $configure($app->make($abstract));
        }
        $app->afterResolving($abstract, $configure);
    }
}
