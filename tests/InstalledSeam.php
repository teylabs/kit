<?php

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;

// Older release blueprints and the v0.1 fixture catalog require Concierge 0.7.x. The adapter checks the actually installed
// packages, so checks of those eras present Concierge at 0.7.6 until they restore the real inventory.
// Composer reads vendor inventories before a reloaded one, so the loader registry is emptied while the seam is active.
function installedSeamLoaders(): ReflectionProperty
{
    return new ReflectionProperty(ClassLoader::class, 'registeredLoaders');
}
function simulateInstalled(string $package, string $version): void
{
    $GLOBALS['installedSeamReal'] ??= [InstalledVersions::getAllRawData()[0], ClassLoader::getRegisteredLoaders()];
    $data = $GLOBALS['installedSeamReal'][0];
    $data['versions'][$package]['version'] = $version.'.0';
    $data['versions'][$package]['pretty_version'] = $version;
    installedSeamLoaders()->setValue(null, []);
    InstalledVersions::reload($data);
}
function restoreInstalled(): void
{
    installedSeamLoaders()->setValue(null, $GLOBALS['installedSeamReal'][1]);
    InstalledVersions::reload($GLOBALS['installedSeamReal'][0]);
}
/** As if the package were not installed: Composer's inventory no longer lists it (its classes stay autoloadable, so a check proves it never loads them). */
function simulateMissing(string $package): void
{
    $GLOBALS['installedSeamReal'] ??= [InstalledVersions::getAllRawData()[0], ClassLoader::getRegisteredLoaders()];
    $data = $GLOBALS['installedSeamReal'][0];
    unset($data['versions'][$package]);
    installedSeamLoaders()->setValue(null, []);
    InstalledVersions::reload($data);
}
