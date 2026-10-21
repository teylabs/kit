<?php

namespace Tey\Kit\Features;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
use Tey\Kit\Features\Core\Deploy\HealthChecks;
use Tey\Kit\Features\Core\Deploy\PreflightCommand;
use Tey\Kit\Features\SessionUi\FirstRunDemoCommand;
use Glhd\Gretel\Support\GretelServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use RuntimeException;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Mcp\McpProvider;
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
    ];

    /** The order features contribute in, which orders their routes, tools and navigation; kept from the starter. */
    private const CONTRIBUTIONS = ['core', 'workspaces', 'access', 'mcp', 'settings', 'session-ui'];

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
        // Every page route declares a breadcrumb, so gretel's route macro must exist whether or not the host's package discovery found it.
        $this->app->register(GretelServiceProvider::class);
        $features = $this->features();
        if ($features === []) {
            return;
        }
        foreach ($features as $id) {
            $this->app->register(self::FEATURES[$id][0]);
        }
        // A page's layout defaults to a heading and a document title; the shell feature replaces it with navigation and breadcrumbs.
        $this->app->bindIf(\Tey\Kit\Features\Core\ViewModels\PageLayouts::class, \Tey\Kit\Features\Core\ViewModels\PlainPageLayouts::class);
        $this->app->singleton(Registry::class, fn (): Registry => self::registry($features)->webFooterUsing(Brand::webFooter(...))->beforeRequestUsing(\Tey\Kit\BrandPreview::apply(...))->beforeRequestUsing(static function (): void {
            app(Access\Permissions::class)->remember();
            app(Access\Capabilities::class)->remember();
        }));
    }

    /** The brand reaches mail: the sender is always the product's name, replies go to the hello address, and the mail views are the kit's branded ones. */
    private function bootBrand(): void
    {
        Brand::applyToMail();
        // The mark in the mail header is a local PNG sent inline (a CID attachment), never a remote or data image.
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $html = $event->message->getHtmlBody();
            $mark = config('mail.brand.mark');
            if (is_string($html) && is_string($mark) && is_file($mark) && str_contains($html, 'src="kit-mark:"')) {
                $event->message->embedFromPath($mark, 'kit-mark', 'image/png');
                $event->message->html(str_replace('src="kit-mark:"', 'src="cid:kit-mark"', $html));
            }
        });
        config(['mail.markdown.paths' => [dirname(__DIR__, 2).'/resources/views/mail', ...(array) config('mail.markdown.paths', [])]]);
    }

    public function boot(): void
    {
        // Passport's keys arrive in whatever form a platform's dashboard allows (real newlines, escaped newlines, base64): the PEM it needs is made here, before it is read.
        if ($this->app->make('config')->has('passport')) {
            \Tey\Kit\PassportKeys::apply($this->app->make('config'));
        }
        // How the keys arrived, for the preflight to report (the form only, never a key).
        $this->app->instance('kit.passport-key-forms', fn (): array => \Tey\Kit\PassportKeys::forms());
        // Every cache the kit's packages build runs with `php artisan optimize` (and clears with `optimize:clear`): gretel registers the breadcrumbs, laravel-data and laravel-settings
        // theirs; the discovered structures (laravel-data's, medialibrary's and the kit's own) are cached here.
        // The hands-free deploy: the role and permission syncs follow a migrate (see CatalogSync), and /up runs the cheap production checks (HealthChecks).
        Event::listen([MigrationsEnded::class, NoPendingMigrations::class], CatalogSync::class);
        Event::listen(DiagnosingHealth::class, fn (DiagnosingHealth $event) => (new HealthChecks(Brand::placeholders(...)))->handle($event));
        $this->commands([PreflightCommand::class, FirstRunDemoCommand::class]);
        $this->optimizes('structure-scouts:cache', 'structure-scouts:clear', 'kit-structure-scouts');
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
