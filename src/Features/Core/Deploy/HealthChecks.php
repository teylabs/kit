<?php

namespace Tey\Kit\Features\Core\Deploy;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * What `/up` checks, on Laravel's DiagnosingHealth: the cheap things that make a deploy unusable when wrong. The key, the tables the kit needs (and `sessions` with the database session
 * driver), and, in production, debug off and no unresolved brand placeholders. A failure throws, which makes `/up` a 500. It is monitoring: nothing here blocks a deploy (that is
 * `kit:preflight`). A pass is remembered for half a minute, so a probe every few seconds costs one set of checks.
 */
final class HealthChecks
{
    /** @param  Closure(): list<string>  $placeholders  the brand keys still at a placeholder (from the composition root, which knows the brand) */
    public function __construct(private readonly Closure $placeholders) {}

    public function handle(object $event): void
    {
        $key = 'kit.health.'.app()->environment().'.'.hash('crc32b', (string) config('app.key').config('session.driver').json_encode(($this->placeholders)()));
        try {
            if (Cache::get($key) === true) {
                return;
            }
        } catch (Throwable) {
            // no cache to remember with: check every time
        }
        $failures = $this->failures();
        if ($failures !== []) {
            throw new RuntimeException('Unhealthy: '.implode('; ', $failures));
        }
        try {
            Cache::put($key, true, 30);
        } catch (Throwable) {
        }
    }

    /** @return list<string> */
    public function failures(): array
    {
        $failures = [];
        if ((string) config('app.key') === '') {
            $failures[] = 'APP_KEY is not set';
        }
        $tables = ['users', 'workspaces', 'concierge_memberships', 'roles', 'kit_meta'];
        if (config('session.driver') === 'database') {
            $tables[] = (string) config('session.table', 'sessions');
        }
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = 'the table '.$table.' is missing';
            }
        }
        if (app()->isProduction()) {
            if (config('app.debug')) {
                $failures[] = 'APP_DEBUG is on in production';
            }
            if (($placeholders = ($this->placeholders)()) !== []) {
                $failures[] = 'brand placeholders are unresolved ('.implode(', ', $placeholders).')';
            }
        }

        return $failures;
    }
}
