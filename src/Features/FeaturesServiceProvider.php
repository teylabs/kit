<?php

namespace Tey\Kit\Features;

use Glhd\Gretel\Support\GretelServiceProvider;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use RuntimeException;
use Tey\Kit\Brand;
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
    ];

    /** The order features contribute in, which orders their routes, tools and navigation; kept from the starter. */
    private const CONTRIBUTIONS = ['core', 'workspaces', 'access', 'mcp', 'settings', 'shell', 'session-ui', 'workspace-ui', 'notes'];

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
                $adopt = Checkout::artifact($this->app->basePath(), $appId, Lineages::name('workspaces'))?->metadata['adopt'] ?? null;
                $tables = is_array($adopt) && is_array($adopt['tables'] ?? null) ? $adopt['tables'] : [];
            }
        } catch (Throwable) {
            // No readable record: the flags stay null, and the migrations adopt only the tables the database already has.
            return;
        }
        foreach (['workspaces' => 'workspaces', 'access' => 'permission'] as $key => $table) {
            if (config('kit.'.$key.'.adopt') === null && in_array($table, $tables, true)) {
                config(['kit.'.$key.'.adopt' => true]);
            }
        }
    }

    /** @return list<string> */
    private function features(): array
    {
        $appId = config('kit.app_id');
        $override = config('kit.features');
        if ($appId === null && ($override === null || self::mayCarry($override))) {
            // Package providers register before the app's own, so kit:install's host binding may not be bound yet;
            // its config/kit-lifecycle.php names the same app id.
            $appId = $this->app->bound(LifecycleHost::class) ? $this->app->make(LifecycleHost::class)->appId() : config('kit-lifecycle.app_id');
        }

        return self::selected(self::carried($this->app->basePath(), $appId, self::adopted($this->app->basePath(), $appId, $override)));
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

        return $carry ? [...$ids, 'workspace-ui'] : $ids;
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
        if (in_array('access', $features, true)) {
            Event::listen([MigrationsEnded::class, NoPendingMigrations::class], CatalogSync::class);
        }
        if ($features !== []) {
            Event::listen(DiagnosingHealth::class, fn (DiagnosingHealth $event) => (new HealthChecks($placeholderCheck ? Brand::placeholders(...) : static fn (): array => [], $features))->handle($event));
        }
        $this->commands([PreflightCommand::class, FirstRunDemoCommand::class]);
        $this->optimizes('structure-scouts:cache', 'structure-scouts:clear', 'kit-structure-scouts');
        // Published files are the app's own (host-owned): no feature generates or owns them.
        $this->publishes([dirname(__DIR__, 2).'/config/kit.php' => $this->app->configPath('kit.php')], 'kit-config');
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
