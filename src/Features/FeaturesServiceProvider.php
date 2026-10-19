<?php

namespace Tey\Kit\Features;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\McpProvider;
use Tey\Kit\Features\Notes\NotesProvider;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\Settings\SettingsProvider;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;

/**
 * The composition root: registers each adopted feature's provider, fills the Registry and loads each
 * adopted feature's migrations from `database/migrations/kit/<feature>`.
 *
 * The adopted features are `config('kit.features')`, or when that is null the `blueprint_features` of the
 * kit's installed record. Nothing is registered when there are none.
 */
final class FeaturesServiceProvider extends ServiceProvider
{
    /** Feature id => [provider, required features], in canonical order; the graph matches the starter's FeatureGraphTest. */
    public const FEATURES = [
        'core' => [CoreProvider::class, []],
        'workspaces' => [WorkspacesProvider::class, ['core']],
        'access' => [AccessProvider::class, ['workspaces']],
        'settings' => [SettingsProvider::class, ['workspaces', 'access']],
        'session-ui' => [SessionUiProvider::class, ['workspaces', 'access']],
        'mcp' => [McpProvider::class, ['workspaces', 'access']],
        'notes' => [NotesProvider::class, ['workspaces', 'access']],
    ];

    /** The order features contribute in, which orders their routes, tools and navigation; kept from the starter. */
    private const CONTRIBUTIONS = ['core', 'workspaces', 'access', 'mcp', 'settings', 'notes', 'session-ui'];

    /**
     * The adopted features in canonical order. Unknown ids, duplicates and missing requirements are refused.
     *
     * @param  list<string>|null  $ids
     * @return list<string>
     */
    public static function selected(?array $ids): array
    {
        $ids ??= [];
        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('Adopt each feature once.');
        }
        foreach ($ids as $id) {
            if (! isset(self::FEATURES[$id])) {
                throw new RuntimeException('Unknown feature: '.$id);
            }
            foreach (self::FEATURES[$id][1] as $required) {
                if (! in_array($required, $ids, true)) {
                    throw new RuntimeException('Feature '.$id.' requires feature '.$required);
                }
            }
        }

        return array_values(array_intersect(array_keys(self::FEATURES), $ids));
    }

    /**
     * Route options for one feature from `kit.routes.<id>`.
     *
     * @return array{enabled: bool, prefix: string, middleware: list<string>, name: string}
     */
    public static function routeOptions(string $id): array
    {
        $options = (array) config('kit.routes.'.$id, []);

        return ['enabled' => ($options['enabled'] ?? true) !== false, 'prefix' => (string) ($options['prefix'] ?? ''), 'middleware' => array_values((array) ($options['middleware'] ?? [])), 'name' => (string) ($options['name'] ?? '')];
    }

    /** @param list<string> $features adopted ids, already validated */
    public static function registry(array $features): Registry
    {
        $registry = new Registry;
        foreach (self::CONTRIBUTIONS as $id) {
            if (! in_array($id, $features, true)) {
                continue;
            }
            self::FEATURES[$id][0]::contribute($registry, $features, self::routeOptions($id));
        }

        return $registry;
    }

    /** @return list<string>|null The features the installed record lists; null when nothing is installed. */
    public static function installed(string $basePath): ?array
    {
        $path = $basePath.'/.mod-maintenance/installed.json';
        if (! is_file($path)) {
            return null;
        }
        $record = json_decode((string) file_get_contents($path), true);
        $features = is_array($record) ? ($record['artifact']['metadata']['blueprint_features'] ?? null) : null;
        if (! is_array($features) || array_filter($features, fn ($id): bool => ! is_string($id)) !== []) {
            throw new RuntimeException('The kit installed record does not list its features.');
        }

        return array_values($features);
    }

    /** @return list<string> */
    private function adopted(): array
    {
        $configured = config('kit.features');

        return self::selected(is_array($configured) ? $configured : self::installed($this->app->basePath()));
    }

    public function register(): void
    {
        $features = $this->adopted();
        if ($features === []) {
            return;
        }
        foreach ($features as $id) {
            $this->app->register(self::FEATURES[$id][0]);
        }
        $this->app->singleton(Registry::class, fn (): Registry => self::registry($features));
    }

    public function boot(): void
    {
        $features = $this->adopted();
        foreach ($features as $id) {
            $this->loadMigrationsFrom($this->app->databasePath('migrations/kit/'.$id));
        }
        if ($features === [] || $this->app->routesAreCached()) {
            return;
        }
        $this->app->booted(fn () => app(Registry::class)->loadRoutes());
    }
}
