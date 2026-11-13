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
use Tey\Kit\Catalog;
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
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Tey\Kit\Mail\FluentMailMessage;
use Tey\Kit\Mail\InlineBrandImages;
use Tey\Kit\PassportKeys;
use Tey\Kit\Search\SearchWorkspace;
use Tey\Mod\Maintenance\Checkout;
use Throwable;

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
    /** @var list<string>|null the adopted features, once read */
    private ?array $features = null;

    /** @var array<string, array{lineages: list<string>, carry: bool, tables: list<string>|null}|null> DiscoveryCache's entry per app id, once looked up */
    private array $cached = [];

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

    /** The `kit.<key>.adopt` flag => the host table kit:add records in the workspaces lineage's `adopt.tables` when the host owns it. */
    private const ADOPT_TABLES = ['workspaces' => 'workspaces', 'access' => 'permission'];

    /** The order features contribute in, which orders their routes, tools and navigation; kept from the starter. */
    private const CONTRIBUTIONS = ['core', 'workspaces', 'access', 'mcp', 'settings', 'shell', 'session-ui', 'workspace-ui', 'notes', 'finalize', 'deliver'];

    /**
     * The adopted features in canonical order. Unknown ids, duplicates and missing requirements are refused.
     *
     * One compatibility rule: session-ui owned the shell before shell was its own feature, so a selection made then (a session-ui
     * lineage, or a `kit.features` list) that names session-ui without shell carries shell with it. Shell's provider adds no routes; it shares the `shell` prop.
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
     * What `kit:cache` stores for one app id, read live: the adopted lineages, whether session-ui carries workspace-ui, and the host tables
     * the workspaces lineage adopted (null when its record cannot be read). A record that cannot be read throws.
     *
     * @return array{lineages: list<string>, carry: bool, tables: list<string>|null}
     */
    public static function discover(string $root, string $appId): array
    {
        return ['lineages' => array_keys(Lineages::adopted($root, $appId)), 'carry' => self::carriesWorkspaceUi($root, $appId), 'tables' => self::adoptedTables($root, $appId)];
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

    /**
     * `kit.workspaces.adopt` and `kit.access.adopt` left null (the default) read the workspaces lineage's installed record: kit:add records there
     * which host tables it adopted (`adopt.tables`: `workspaces`, `permission`). A value the app sets (KIT_WORKSPACES_ADOPT, KIT_ACCESS_ADOPT or its
     * own config/kit.php) wins. The kit's workspaces and permission migrations read them, so they never create a table the host owns.
     */
    private function adoptionFlags(): void
    {
        if (config('kit.workspaces.adopt') !== null && config('kit.access.adopt') !== null) {
            return;
        }
        $tables = [];
        try {
            $appId = config('kit.app_id') ?? ($this->app->bound(LifecycleHost::class) ? $this->app->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id'));
            if (is_string($appId) && $appId !== '') {
                // A cached null (the record's workspaces artifact could not be read) is kept: only a miss reads the record.
                $cached = $this->cached($appId);
                $tables = $cached !== null ? $cached['tables'] : self::adoptedTables($this->app->basePath(), $appId);
            }
        } catch (Throwable) {
            return;
        }
        if ($tables === null) {
            // No readable record: the flags stay null, and the migrations adopt only the tables the database already has.
            return;
        }
        foreach (self::adopts($tables) as $key) {
            if (config('kit.'.$key.'.adopt') === null) {
                config(['kit.'.$key.'.adopt' => true]);
            }
        }
    }

    /**
     * The adopt flags the recorded host tables turn on: `workspaces` (kit.workspaces.adopt) and `access` (kit.access.adopt).
     *
     * @param  list<string>|null  $tables
     * @return list<string>
     */
    public static function adopts(?array $tables): array
    {
        return array_keys(array_filter(self::ADOPT_TABLES, fn (string $table): bool => in_array($table, $tables ?? [], true)));
    }

    /**
     * The host tables kit:add recorded on the workspaces lineage, or null when the record cannot be read.
     *
     * @return list<string>|null
     */
    private static function adoptedTables(string $root, string $appId): ?array
    {
        try {
            $adopt = Checkout::artifact($root, $appId, Lineages::name('workspaces'))?->metadata['adopt'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_array($adopt) && is_array($adopt['tables'] ?? null) ? array_values(array_filter($adopt['tables'], 'is_string')) : [];
    }

    /**
     * The discovery cache's entry, looked up once per provider: the adoption flags and the features both need it, and each lookup re-takes
     * the fingerprint, which hashes the installed records.
     *
     * @return array{lineages: list<string>, carry: bool, tables: list<string>|null}|null
     */
    private function cached(string $appId): ?array
    {
        if (! array_key_exists($appId, $this->cached)) {
            $this->cached[$appId] = DiscoveryCache::entry($this->app, $appId);
        }

        return $this->cached[$appId];
    }

    /** @return list<string> */
    private function features(): array
    {
        // Read once per provider: register and boot both need them, and the lineages behind them do not change within a request.
        return $this->features ??= $this->resolveFeatures();
    }

    /** @return list<string> */
    private function resolveFeatures(): array
    {
        $appId = config('kit.app_id');
        $override = config('kit.features');
        if ($appId === null && ($override === null || self::mayCarry($override))) {
            // Package providers register before the app's own, so kit:install's host binding may not be bound yet;
            // its config/kit-lifecycle.php names the same app id.
            $appId = $this->app->bound(LifecycleHost::class) ? $this->app->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id');
        }

        $root = $this->app->basePath();
        $cached = is_string($appId) && $appId !== '' ? $this->cached($appId) : null;
        if ($cached === null) {
            return self::selected(self::carried($root, $appId, self::adopted($root, $appId, $override)));
        }

        // The same answer from the record as cached by kit:cache, unchanged since (DiscoveryCache checks).
        return self::boots($cached, $override);
    }

    /**
     * The features the app boots from one discover() entry (live, or as kit:cache stored it): `$override` when set, else the lineages, with
     * workspace-ui carried when the record carries it (see carried()), then selected(). kit:detach writes this list into config/kit.php.
     *
     * @param  array{lineages: list<string>, carry: bool, tables: list<string>|null}  $entry
     * @param  list<string>|null  $override
     * @return list<string>
     */
    public static function boots(array $entry, ?array $override = null): array
    {
        $ids = $override ?? $entry['lineages'];

        return self::selected($entry['carry'] && self::mayCarry($ids) ? [...$ids, 'workspace-ui'] : $ids);
    }

    /**
     * A second compatibility rule: session-ui owned the workspace pages before workspace-ui was its own feature. An app that installed it then
     * names session-ui without workspace-ui (in its lineages, or a `kit.features` list), and keeps its workspace pages on until kit:update has
     * moved them: workspace-ui is carried while session-ui's installed lineage still owns those pages or has released them to workspace-ui,
     * and once workspace-ui's own lineage is installed. A session-ui installed after the split, alone, is the sign-in only.
     *
     * @param  list<string>|null  $ids
     * @return list<string>|null
     */
    public static function carried(string $root, ?string $appId, ?array $ids): ?array
    {
        if ($ids === null || $appId === null || $appId === '' || ! self::mayCarry($ids)) {
            return $ids;
        }

        return self::carriesWorkspaceUi($root, $appId) ? [...$ids, 'workspace-ui'] : $ids;
    }

    /** Whether the installed lineages carry workspace-ui for a selection that names session-ui without it (see carried()). */
    private static function carriesWorkspaceUi(string $root, string $appId): bool
    {
        $installed = Checkout::installed($root, $appId);
        $session = $installed[Lineages::name('session-ui')] ?? null;
        $carry = isset($installed[Lineages::name('workspace-ui')]);
        if (! $carry && $session !== null) {
            $manifest = json_decode((string) file_get_contents(Catalog::package()->directory('session-ui').'/feature.json'), true, 64, JSON_THROW_ON_ERROR);
            // Only the workspace pages of the first split carry it. The invitation page and the workspace-timezone step moved to workspace-ui later (0.6.0):
            // a session-ui that still owns or released them alone is the sign-in, and its host adds workspace-ui itself when it uses invitations.
            $pages = array_column(array_filter($manifest['moved'] ?? [], fn (array $entry): bool => $entry['to'] === 'workspace-ui' && self::workspacePage($entry['path'])), 'path');
            $released = Checkout::artifact($root, $appId, Lineages::name('session-ui'))?->metadata['released']['files'] ?? [];
            $released = array_filter((array) $released, fn ($entry): bool => is_array($entry) && ($entry['to'] ?? null) === 'workspace-ui' && self::workspacePage((string) ($entry['path'] ?? '')));
            $carry = array_intersect($pages, array_keys($session['files'])) !== [] || $released !== [];
        }

        return $carry;
    }

    /** A page of the first workspace-ui split: under pages/Workspace or pages/Provision. */
    private static function workspacePage(string $path): bool
    {
        return str_starts_with($path, 'resources/js/pages/Workspace/') || str_starts_with($path, 'resources/js/pages/Provision/');
    }

    /** @param list<string> $ids */
    private static function mayCarry(array $ids): bool
    {
        return in_array('session-ui', $ids, true) && ! in_array('workspace-ui', $ids, true);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/kit.php', 'kit');
        // The brand resolves now, before any provider boots: config/brand.php, then the kit default (a KIT_BRAND_* variable still set is ignored and logged). An invalid colour throws
        // in local and testing, except under kit:brand:migrate (the way out of it) and kit:status (which reports it), and anywhere else is logged and takes the
        // default. The log lines wait for boot.
        $lenient = $this->app->runningInConsole() && BrandConfig::lenientCommand((array) ($_SERVER['argv'] ?? []));
        $brandLogs = BrandConfig::resolve($this->app->make('config'), (string) $this->app->environment(), ! $lenient);
        if ($brandLogs !== []) {
            $this->app->booted(fn () => BrandConfig::log($this->app->make('log'), $brandLogs));
        }
        // Every page route declares a breadcrumb, so gretel's route macro must exist whether or not the host's package discovery found it.
        $this->app->register(GretelServiceProvider::class);
        $features = $this->features();
        $this->adoptionFlags();
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
        // The installed record's answers (adopted lineages, adopted tables), read once at deploy instead of on every request.
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
