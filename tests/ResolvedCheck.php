<?php

// Resolved::configure() keeps a setting whichever way the service and the configuring provider are ordered: a plain Laravel container with a synthetic
// service, no Concierge, host or database. Bound and resolved before configure(), resolved only after, and re-bound at a later boot (as Concierge 0.11.1 does).
use Illuminate\Foundation\Application;
use Tey\Kit\Features\Core\Resolved;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function resolvedCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

final class ResolvedService
{
    public ?string $url = null;
}

$configure = fn (ResolvedService $service) => $service->url = 'configured';

// Resolved before configure() (the service's provider booted first).
$app = new Application(sys_get_temp_dir());
$app->singleton(ResolvedService::class);
$early = $app->make(ResolvedService::class);
Resolved::configure($app, ResolvedService::class, $configure);
resolvedCheck($early->url === 'configured' && $app->make(ResolvedService::class) === $early, 'a service resolved before configure() is configured at once');

// Bound and resolved only after configure() (the configuring provider booted first).
$app = new Application(sys_get_temp_dir());
Resolved::configure($app, ResolvedService::class, $configure);
$app->singleton(ResolvedService::class);
resolvedCheck($app->make(ResolvedService::class)->url === 'configured', 'a service bound and resolved after configure() is configured on resolution');

// Resolved early, then re-bound by a provider that binds at boot: the new instance is configured as well.
$app = new Application(sys_get_temp_dir());
$app->make(ResolvedService::class);
Resolved::configure($app, ResolvedService::class, $configure);
$app->singleton(ResolvedService::class);
$rebound = $app->make(ResolvedService::class);
resolvedCheck($rebound->url === 'configured', 'a service re-bound after configure() is configured again when next resolved');

// A setting made later by the app (its own provider boots last) wins over the kit's.
$app->make(ResolvedService::class)->url = 'host';
resolvedCheck($app->make(ResolvedService::class)->url === 'host', 'an app\'s later setting is kept');
