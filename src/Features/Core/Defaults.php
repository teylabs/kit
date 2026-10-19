<?php

namespace Tey\Kit\Features\Core;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;

/**
 * The kit's config defaults for the packages its features use, from the library's config/defaults.
 *
 * Each is laid over the package's own defaults, and over any values already merged. When the app has
 * its own config/<key>.php, or config is cached, the app's file is the whole truth and nothing is applied.
 */
final class Defaults
{
    public static function apply(Application $app, string $key, ?string $package = null): void
    {
        if ($app->configurationIsCached() || is_file($app->configPath($key.'.php'))) {
            return;
        }
        $config = $app->make('config');
        $base = $package === null ? [] : require InstalledVersions::getInstallPath($package).'/config/'.$key.'.php';
        $config->set($key, array_replace_recursive($base, (array) $config->get($key, []), require self::path($key)));
    }

    public static function path(string $key): string
    {
        return dirname(__DIR__, 3).'/config/defaults/'.$key.'.php';
    }
}
