<?php

namespace Tey\Kit\Features;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use RuntimeException;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\McpProvider;
use Tey\Kit\Features\Notes\NotesProvider;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\Settings\SettingsProvider;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;
use Tey\Kit\Brand;
use Tey\Kit\BrandHead;
use Tey\Kit\Fonts;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;

/**
 * The composition root: registers each adopted feature's provider, fills the Registry and loads each
 * adopted feature's migrations from `database/migrations/kit/<feature>`.
 *
 * The adopted features are the app's installed `tey/kit:<feature>` lineages, or `config('kit.features')` when it is
 * set, as an explicit override. Lineages are read for `kit.app_id`, else the bound LifecycleHost's app id, else
 * `kit-lifecycle.app_id` (kit:install's config); with none, and no override, nothing is registered.
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
     * The override when set, else the features of the installed lineages.
     *
     * @return list<string>|null
     */
    public static function adopted(string $root, ?string $appId, ?array $override): ?array
    {
        if ($override !== null) {
            return $override;
        }
        if ($appId === null || $appId === '') {
            return null;
        }

        return array_keys(Lineages::adopted($root, $appId));
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

    /** @return list<string> */
    private function features(): array
    {
        $appId = config('kit.app_id');
        if ($appId === null && config('kit.features') === null) {
            // Package providers register before the app's own, so kit:install's host binding may not be bound yet;
            // its config/kit-lifecycle.php names the same app id.
            $appId = $this->app->bound(LifecycleHost::class) ? $this->app->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id');
        }

        return self::selected(self::adopted($this->app->basePath(), $appId, config('kit.features')));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/kit.php', 'kit');
        $features = $this->features();
        if ($features === []) {
            return;
        }
        foreach ($features as $id) {
            $this->app->register(self::FEATURES[$id][0]);
        }
        $this->app->singleton(Registry::class, fn (): Registry => self::registry($features)->webFooterUsing(Brand::webFooter(...)));
    }

    /** The brand reaches mail: the sender is always the product's name, replies go to the hello address, and the mail views are the kit's branded ones. */
    private function bootBrand(): void
    {
        config([
            'mail.from' => ['address' => Brand::get('mail_from_address') ?: config('mail.from.address'), 'name' => Brand::name()],
            'mail.reply_to' => ['address' => Brand::get('hello_email'), 'name' => Brand::name()],
            'mail.brand' => Brand::mail(),
            'mail.markdown.paths' => [dirname(__DIR__, 2).'/resources/views/mail', ...(array) config('mail.markdown.paths', [])],
        ]);
    }

    public function boot(): void
    {
        // Published files are the app's own (host-owned): no feature generates or owns them.
        $this->publishes([dirname(__DIR__, 2).'/config/kit.php' => $this->app->configPath('kit.php')], 'kit-config');
        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade): void {
            $blade->component('kit::fonts', Fonts::class);
            $blade->component('kit::brand', BrandHead::class);
        });
        $this->bootBrand();
        $features = $this->features();
        foreach ($features as $id) {
            $this->loadMigrationsFrom($this->app->databasePath('migrations/kit/'.$id));
        }
        if ($features === [] || $this->app->routesAreCached()) {
            return;
        }
        $this->app->booted(fn () => app(Registry::class)->loadRoutes());
    }
}
