<?php

namespace Tey\Kit\Features\Core\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tey\Concierge\Support\Roles;
use Tey\Kit\Features\Core\Registry;

/**
 * The hands-free deploy: when migrations end (or there was nothing to migrate), the roles and then the permissions are synced, but only when the catalog they come from changed.
 * `php artisan migrate --force` is therefore the whole database step of a deploy. The catalog's fingerprint (the configured roles and every registered permission with its default
 * roles) is kept in `kit_meta`; the same fingerprint is a no-op, a fresh database has none and always syncs. Only a migrate "up" syncs (a rollback does not), unit tests skip it
 * unless a test opts in (`kit.deploy.sync_in_tests`), and a failing sync throws, so the migrate fails loudly and the deploy stops.
 */
final class CatalogSync
{
    public const KEY = 'catalog_fingerprint';

    public static function fingerprint(): string
    {
        return hash('sha256', (string) json_encode([config('concierge.roles'), app(Registry::class)->permissionCatalog()], JSON_THROW_ON_ERROR | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /**
     * The declared permissions the database does not hold: a deploy that synced an older catalog, a restored database, or a row deleted by hand. Empty when
     * the permission tables are not there yet (nothing to compare), so it never fails a fresh install before migrate.
     *
     * @return list<string>
     */
    public static function unsynced(): array
    {
        $declared = array_keys(app(Registry::class)->permissionCatalog());
        if ($declared === [] || ! class_exists(PermissionRegistrar::class) || ! class_exists(Roles::class) || ! app()->bound(PermissionRegistrar::class)) {
            return [];
        }
        $model = app(PermissionRegistrar::class)->getPermissionClass();
        if (! Schema::hasTable((new $model)->getTable())) {
            return [];
        }
        $present = $model::query()->whereIn('name', $declared)->where('guard_name', Roles::guard())->pluck('name')->all();

        return array_values(array_diff($declared, $present));
    }

    /** @param  object  $event  Illuminate's MigrationsEnded or NoPendingMigrations */
    public function handle(object $event): void
    {
        if (($event->method ?? null) !== 'up') {
            return;
        }
        if (app()->runningUnitTests() && ! config('kit.deploy.sync_in_tests', false)) {
            return;
        }
        if (! Schema::hasTable('kit_meta')) {
            return;
        }
        $current = self::fingerprint();
        // The same catalog is a no-op only while the database still holds every declared permission: a missing one syncs again, so a deploy heals it.
        if (DB::table('kit_meta')->where('key', self::KEY)->value('value') === $current && self::unsynced() === []) {
            return;
        }
        // Run the commands directly: Artisan::call() would replace the output the migrate command that triggered this has (a caller reads `Artisan::output()` after it).
        $commands = Artisan::all();
        foreach (['concierge:sync-roles', 'kit:permissions:sync'] as $name) {
            // An app without Concierge or the access feature has nothing of that kind to sync.
            if (! isset($commands[$name])) {
                continue;
            }
            $output = new BufferedOutput;
            $input = new ArrayInput(['--no-interaction' => true]);
            $input->setInteractive(false);
            if ($commands[$name]->run($input, $output) !== 0) {
                throw new RuntimeException('The deploy sync "'.$name.'" failed; migrations are done but the roles and permissions are not synced. '.trim($output->fetch()));
            }
        }
        DB::table('kit_meta')->updateOrInsert(['key' => self::KEY], ['value' => $current, 'updated_at' => now()]);
    }
}
