<?php

namespace Tey\Kit\Features;

use Glhd\Gretel\Support\GretelServiceProvider;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use RuntimeException;
use Tey\Kit\Brand;
use Tey\Kit\BrandConfig;
use Tey\Kit\BrandHead;
use Tey\Kit\BrandPreview;
use Tey\Kit\Dashboard\ReadDashboard;
use Tey\Kit\Features\Access\AccessProvider;
use Tey\Kit\Features\Core\CoreProvider;
use Tey\Kit\Features\Core\Deploy\CatalogSync;
use Tey\Kit\Features\Core\Deploy\HealthChecks;
use Tey\Kit\Features\Core\Deploy\PreflightCommand;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;
use Tey\Kit\Features\Core\ViewModels\PlainPageLayouts;
use Tey\Kit\Features\Core\Workbench\NullWorkbenchPreferences;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;
use Tey\Kit\Features\Deliver\DeliverProvider;
use Tey\Kit\Features\Finalize\FinalizeProvider;
use Tey\Kit\Features\Mcp\McpProvider;
use Tey\Kit\Features\Notes\NotesProvider;
use Tey\Kit\Features\SessionUi\FirstRunDemoCommand;
use Tey\Kit\Features\SessionUi\SessionUiProvider;
use Tey\Kit\Features\Settings\SettingsProvider;
use Tey\Kit\Features\Shell\ShellProvider;
use Tey\Kit\Features\Workspaces\WorkspacesProvider;
use Tey\Kit\Features\WorkspaceUi\WorkspaceUiProvider;
use Tey\Kit\Fonts;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\InlineBrandImages;
use Tey\Kit\PassportKeys;
use Tey\Kit\Search\SearchWorkspace;

/**
 * The composition root: registers each adopted feature's provider, fills the Registry and loads each adopted feature's migrations.
 *
 * The adopted features are `config('kit.features')` and nothing else; the request path never reads install history.
 */
final class FeaturesServiceProvider extends ServiceProvider
{
    /** @var list<string>|null the adopted features, once read */
    private ?array $features = null;

    /** Whether this process has logged UNSET. */
    private static bool $unsetLogged = false;

    public const UNSET = 'kit.features is not set, so no kit feature is registered. Since kit 0.18 the features come only from config/kit.php: list them there (kit:detach on 0.17.8 writes the list).';

    /** Feature id => [provider, required features], in canonical order; the graph matches the starter's FeatureGraphTest. */
    public const FEATURES = [
        'core' => [CoreProvider::class, []],
        'workspaces' => [WorkspacesProvider::class, ['core']],
        'access' => [AccessProvider::class, ['workspaces']],
        'settings' => [SettingsProvider::class, ['workspaces', 'access']],
        'shell' => [ShellProvider::class, ['core']],
        'session-ui' => [SessionUiProvider::class, ['core', 'shell']],
        'workspace-ui' => [WorkspaceUiProvider::class, ['workspaces', 'access', 'shell', 'session-ui']],
        'mcp' => [McpProvider::class, ['workspaces', 'access']],
        'notes' => [NotesProvider::class, ['workspaces', 'access', 'workspace-ui']],
        'finalize' => [FinalizeProvider::class, ['workspaces', 'access']],
        'deliver' => [DeliverProvider::class, ['workspaces', 'access', 'finalize']],
    ];

    /** The order features contribute in, which orders their routes, tools and navigation; kept from the starter. */
    private const CONTRIBUTIONS = ['core', 'workspaces', 'access', 'mcp', 'settings', 'shell', 'session-ui', 'workspace-ui', 'notes', 'finalize', 'deliver'];

    /**
     * The adopted features in canonical order. Unknown ids, duplicates and missing requirements are refused.
     *
     * One compatibility rule: session-ui owned the shell before shell was its own feature, so a `kit.features` list
     * that names session-ui without shell carries shell with it. Shell's provider adds no routes; it shares the `shell` prop.
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
        if (in_array('session-ui', $ids, true) && ! in_array('shell', $ids, true)) {
            $ids[] = 'shell';
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
        $registry = (new Registry)->adopting($features);
        $origins = [];
        foreach (self::CONTRIBUTIONS as $id) {
            if (! in_array($id, $features, true)) {
                continue;
            }
            $before = $registry->abilityMap();
            // workspace-ui's routes were session-ui's before the split: they keep its names and `kit.routes.session-ui` options.
            self::FEATURES[$id][0]::contribute($registry, $features, self::routeOptions($id === 'workspace-ui' ? 'session-ui' : $id));
            $origins += array_fill_keys(array_keys(array_diff_key($registry->abilityMap(), $before)), $id);
        }
        // Fail closed: an ability on a capability no adopted feature declares is never defined in Pennant, so it would be a 404 for everyone.
        $registry->assertCapabilitiesDeclared($origins);
        // The dashboard's and search's MCP read tools span two features, so the composition root registers it once both have contributed.
        if (in_array('mcp', $features, true) && in_array('workspace-ui', $features, true)) {
            $registry->capabilityTools([ReadDashboard::class, SearchWorkspace::class]);
        }

        return $registry;
    }

    /** @return list<string> */
    private function features(): array
    {
        // Read once per provider: register and boot both need them.
        return $this->features ??= $this->resolveFeatures();
    }

    /**
     * `kit.features`, the only source of which features are on. Unset or empty registers nothing and logs an error once per process:
     * since 0.18 nothing reads the installed record, so an app that never wrote the list (kit:detach on 0.17.8 does) fails closed, visibly.
     *
     * @return list<string>
     */
    private function resolveFeatures(): array
    {
        $ids = config('kit.features');
        if ($ids === null || $ids === []) {
            if (! self::$unsetLogged) {
                self::$unsetLogged = true;
                $this->app->booted(fn () => $this->app->make('log')->error(self::UNSET));
            }

            return [];
        }

        return self::selected(is_array($ids) ? array_values($ids) : [$ids]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/kit.php', 'kit');
        // The brand resolves now, before any provider boots: config/brand.php, then the kit default (a KIT_BRAND_* variable still set is ignored and logged). An invalid colour throws
        // in local and testing, except under kit:brand:migrate (the way out of it), and anywhere else is logged and takes the
        // default. The log lines wait for boot.
        $lenient = $this->app->runningInConsole() && BrandConfig::lenientCommand((array) ($_SERVER['argv'] ?? []));
        $brandLogs = BrandConfig::resolve($this->app->make('config'), (string) $this->app->environment(), ! $lenient);
        if ($brandLogs !== []) {
            $this->app->booted(fn () => BrandConfig::log($this->app->make('log'), $brandLogs));
        }
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
        $this->app->bindIf(PageLayouts::class, PlainPageLayouts::class);
        // A Workbench's layout is remembered in the browser only, unless session-ui binds its per-person store.
        $this->app->bindIf(WorkbenchPreferences::class, NullWorkbenchPreferences::class);
        // The memos begin first in `web` (core's StartKitRequest), before any host middleware; the brand switch needs the session, so it runs late (session-ui's PrepareKitRequest).
        $this->app->singleton(Registry::class, fn (): Registry => self::registry($features)->webFooterUsing(Brand::webFooter(...))->beforeRequestUsing(BrandPreview::apply(...))->startRequestUsing(static function () use ($features): void {
            // Without access (a core and shell host signing in through session-ui) there is nothing of it to forget, and its classes stay unloaded.
            if (in_array('access', $features, true)) {
                app(Access\Permissions::class)->remember();
                app(Access\Capabilities::class)->remember();
                app(Access\Entitlements::class)->remember();
            }
            app(RequestMemo::class)->remember();
        }));
        // One per request: what the shell, the layout and the page all look up is read once, and any Eloquent write empties it.
        $this->app->scoped(RequestMemo::class);
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], static function (): void {
            if (app()->resolved(RequestMemo::class)) {
                app(RequestMemo::class)->flush();
            }
        });
    }

    /** The brand reaches mail: the sender is always the product's name, replies go to the hello address, and the mail views are the kit's branded ones. */
    private function bootBrand(): void
    {
        Brand::applyToMail();
        // The brand's images in mail (the header's lockup, a host view's mark) are local PNGs sent inline (CID attachments), never remote or data images.
        Event::listen(MessageSending::class, [InlineBrandImages::class, 'handle']);
        // Appended: a host's published resources/views/vendor/mail keeps priority over the kit's branded views.
        FluentMailMessage::appendMailPath(dirname(__DIR__, 2).'/resources/views/mail');
    }

    public function boot(): void
    {
        // Passport's keys arrive in whatever form a platform's dashboard allows (real newlines, escaped newlines, base64): the PEM it needs is made here, before it is read.
        if ($this->app->make('config')->has('passport')) {
            PassportKeys::apply($this->app->make('config'));
        }
        // How the keys arrived, for the preflight to report (the form only, never a key).
        $this->app->instance('kit.passport-key-forms', fn (): array => PassportKeys::forms());
        // Every cache the kit's packages build runs with `php artisan optimize` (and clears with `optimize:clear`): gretel registers the breadcrumbs, laravel-data and laravel-settings
        // theirs; the discovered structures (laravel-data's, medialibrary's and the kit's own) are cached here.
        // The hands-free deploy: the role and permission syncs follow a migrate (see CatalogSync), and /up runs the cheap production checks (HealthChecks).
        // Each belongs to the features that own what it touches, so a host that adopted fewer features boots none of the rest.
        $features = $this->features();
        // Branded mail: explicit true or false wins. Unset, session-ui turns it on only once the brand is filled in (no placeholders left), so a host with its own
        // branding is never sent half-branded mail. /up fails on unresolved placeholders only when the host OPTED IN (brand_mail true): a default never takes /up red.
        $brandMail = config('kit.brand_mail');
        $branded = $brandMail === true || ($brandMail === null && in_array('session-ui', $features, true) && Brand::placeholders() === []);
        $placeholderCheck = $brandMail === true;
        // The workspace columns found are cached across requests (IsKitWorkspace); any migration, up or down, may change them, so it forgets them.
        // A braced closure, never an arrow function: forget() answers false when nothing was cached, and a listener that returns false stops
        // the event, so CatalogSync (below) would never run after a migrate on a cold cache.
        Event::listen(MigrationsEnded::class, function (): void {
            rescue(fn () => Cache::forget('kit-workspace-columns'), report: false);
        });
        if (in_array('access', $features, true)) {
            Event::listen([MigrationsEnded::class, NoPendingMigrations::class], CatalogSync::class);
        }
        if ($features !== []) {
            Event::listen(DiagnosingHealth::class, fn (DiagnosingHealth $event) => (new HealthChecks($placeholderCheck ? Brand::placeholders(...) : static fn (): array => [], $features))->handle($event));
        }
        $this->commands([PreflightCommand::class, FirstRunDemoCommand::class, CacheCommand::class, ClearCommand::class]);
        $this->optimizes('structure-scouts:cache', 'structure-scouts:clear', 'kit-structure-scouts');
        // Nothing to cache since 0.18 (the features are config); kept so a deploy's `optimize` and `optimize:clear` still find them.
        $this->optimizes('kit:cache', 'kit:clear', 'kit');
        // Published files are the app's own (host-owned): no feature generates or owns them.
        $this->publishes([dirname(__DIR__, 2).'/config/kit.php' => $this->app->configPath('kit.php')], 'kit-config');
        // The app's own brand file, every key commented out at the kit's default: uncomment a key to set it (it then wins over the kit default).
        $this->publishes([dirname(__DIR__, 2).'/resources/stubs/brand.php' => $this->app->configPath('brand.php')], 'kit-brand');
        // The self-hosted font files (kit.fonts_source = self): <x-kit::fonts /> points at public/vendor/kit/fonts.
        $this->publishes([dirname(__DIR__, 2).'/resources/fonts/files' => $this->app->publicPath(Fonts::PUBLISHED)], 'kit-fonts');
        // For core's deploy preflight: whether kit.fonts_source is self and the set's files are not published (pages would use the system fonts).
        $this->app->instance('kit.fonts-unpublished', fn (): bool => rescue(fn (): bool => Fonts::source() === 'self' && ! Fonts::published(Fonts::faces(Fonts::set(config('kit.fonts')))), false, false));
        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade): void {
            $blade->component('kit::fonts', Fonts::class);
            $blade->component('kit::brand', BrandHead::class);
        });
        if ($branded) {
            $this->bootBrand();
        }
        foreach ($features as $id) {
            $this->loadMigrationsFrom($this->app->databasePath('migrations/kit/'.$id));
        }
        if ($features === [] || $this->app->routesAreCached()) {
            return;
        }
        $this->app->booted(fn () => app(Registry::class)->loadRoutes());
    }
}
