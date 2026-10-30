<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tey\Kit\Features\Core\Data\PageWidth;

/** What each feature contributes to the shared seams: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, connection guards and error codes. */
final class Registry
{
    /** @var list<array{file: string, group: ?string, options: array{prefix: string, middleware: list<string>, name: string}}> */
    private array $routes = [];

    /** @var list<string> The adopted features, for a route file that links to another feature's page only when it is there. */
    private array $features = [];

    /** @var array<string, string> ability => capability */
    private array $abilities = [];

    /** @var ?callable(Principal, object, string): void */
    private $connectionGuard = null;

    /** @var ?callable(string, int, ?string, Request): mixed */
    private $errorPage = null;

    /** @var ?callable(Request): ?string */
    private $workspaceOpener = null;

    /** @var list<string> Route-name patterns of the pages that render in the focus shell for a signed-in viewer. */
    private array $focusRoutes = [];

    /** @var ?callable(): array{identity: string, credit: string, links: list<array{label: string, url: string}>} */
    private $webFooter = null;

    /** @var list<callable> */
    private array $requestHooks = [];

    /** @var list<callable> */
    private array $startHooks = [];

    private ?string $dashboard = null;

    /** @var list<class-string> */
    private array $tools = [];

    /** @var list<string> Domain error codes a host's MCP tools may refuse with, passed through to the assistant as they are. */
    private array $mcpRefusalCodes = [];

    /** @var list<array{ability: string, label: string, suffix: string, group: string, order: int, active: string|list<string>|null, slot: string}> */
    private array $navigation = [];

    /** @var list<array{module: string, ability: string, label: string, suffix: string, order: int, active: string|list<string>|null}> */
    private array $modulePages = [];

    /** @var list<array{scope: string, key: string, label: string, description: string, ability: ?string, group: string, order: int}> */
    private array $sections = [];

    /** @var array<string, string> */
    private array $scopes = [];

    /** @var list<string> */
    private array $capabilities = [];

    /** @var array<string, array{roles: list<string>, capability: ?string}> */
    private array $permissions = [];

    /** @var array<string, array{limits: list<string>, upgrade: ?array{label: string, suffix: string}}> capability => what its entitlement declares */
    private array $entitlements = [];

    /** @var array<string, string> ability => permission */
    private array $abilityPermissions = [];

    /** @var list<callable(object): void> */
    private array $provisioners = [];

    /** @var array<string, int> */
    private array $statuses = [];

    /** @var array<string, string> */
    private array $messages = [];

    /** @param list<string> $features the adopted features, in canonical order */
    public function adopting(array $features): self
    {
        $this->features = array_values($features);

        return $this;
    }

    public function adopts(string $id): bool
    {
        return in_array($id, $this->features, true);
    }

    /**
     * A library route file, loaded in registration order after the host's own routes. `web` puts it in the web
     * middleware group; a null group loads it bare. The options add a URI prefix, middleware and a route-name prefix,
     * and `enabled: false` leaves the file out.
     *
     * @param  array{enabled?: bool, prefix?: string, middleware?: list<string>, name?: string}  $options
     */
    public function routes(string $file, ?string $group = 'web', array $options = []): self
    {
        if (($options['enabled'] ?? true) === false) {
            return $this;
        }
        $this->routes[] = ['file' => $file, 'group' => $group, 'options' => ['prefix' => (string) ($options['prefix'] ?? ''), 'middleware' => array_values($options['middleware'] ?? []), 'name' => (string) ($options['name'] ?? '')]];

        return $this;
    }

    /** @param array<string, string> $abilities ability => the workspace capability that gates it */
    public function abilities(array $abilities): self
    {
        foreach ($abilities as $ability => $capability) {
            if (isset($this->abilities[$ability]) && $this->abilities[$ability] !== $capability) {
                throw new RuntimeException('Conflicting capability for ability: '.$ability);
            }
            $this->abilities[$ability] = $capability;
        }

        return $this;
    }

    /** Checks a delegated connection's grant for an ability; it throws to refuse. One guard only. */
    public function connectionGuard(callable $guard): self
    {
        if ($this->connectionGuard !== null) {
            throw new RuntimeException('A connection guard is already registered.');
        }
        $this->connectionGuard = $guard;

        return $this;
    }

    /** @param list<class-string> $tools */
    public function tools(array $tools): self
    {
        array_push($this->tools, ...$tools);

        return $this;
    }

    /** @var ?callable(class-string, list<string>, array<string, string>): void */
    private $capabilityToolCheck = null;

    /** Checks a tool passed to capabilityTools() against the declared capabilities; it throws to refuse. The MCP feature's, one only. */
    public function capabilityToolCheck(callable $check): self
    {
        if ($this->capabilityToolCheck !== null) {
            throw new RuntimeException('A capability tool check is already registered.');
        }
        $this->capabilityToolCheck = $check;

        return $this;
    }

    /**
     * MCP tools that name their workspace capability with `#[Capability]` (the MCP feature's attribute), listed and refused by that capability alone. Fails closed at
     * boot: with no MCP feature adopted, or for a tool the MCP feature's check refuses (no attribute, not gated, a capability not declared before it, or an `ABILITY` another capability gates).
     *
     * @param  list<class-string>  $tools
     */
    public function capabilityTools(array $tools): self
    {
        if ($this->capabilityToolCheck === null) {
            throw new RuntimeException('Capability tools need the mcp feature.');
        }
        foreach ($tools as $tool) {
            ($this->capabilityToolCheck)($tool, $this->capabilities, $this->abilities, array_keys($this->scopes));
        }

        return $this->tools($tools);
    }

    /** @var (callable(class-string): array{scope: string, explains: string}|null)|null reads a tool's explicit grant, if it declares one; the MCP feature's */
    private $explicitGrantReader = null;

    /** @param  callable(class-string): array{scope: string, explains: string}|null  $reader */
    public function explicitGrantsUsing(callable $reader): self
    {
        $this->explicitGrantReader = $reader;

        return $this;
    }

    /**
     * The scopes a connection holds only by explicit consent (an `#[Capability(explicit: true)]` tool's scope): scope => its label (the token scope's description), what
     * ticking it allows (`explains`) and the tool. A blanket acts-as-me grant never covers these. Empty without the mcp feature.
     *
     * @return array<string, array{label: string, explains: string, tool: class-string}>
     */
    public function explicitScopes(): array
    {
        $explicit = [];
        if ($this->explicitGrantReader === null) {
            return $explicit;
        }
        foreach ($this->tools as $class) {
            $grant = ($this->explicitGrantReader)($class);
            if ($grant === null) {
                continue;
            }
            $explicit[$grant['scope']] = ['label' => (string) ($this->scopes[$grant['scope']] ?? $grant['scope']), 'explains' => $grant['explains'], 'tool' => $class];
        }

        return $explicit;
    }

    /**
     * Domain codes (`stale_write`, `report_blocked`) a host's MCP tools refuse with by throwing `RuntimeException('<code>')`: the consent gate passes them to the assistant
     * as they are, alongside its own codes. Any other message still becomes `operation_failed`.
     *
     * @param  list<string>  $codes
     */
    public function mcpRefusalCodes(array $codes): self
    {
        foreach ($codes as $code) {
            if (! is_string($code) || preg_match('/^[a-z][a-z0-9_]*$/', $code) !== 1) {
                throw new RuntimeException('An MCP refusal code is a snake_case string: '.json_encode($code));
            }
        }
        $this->mcpRefusalCodes = array_values(array_unique([...$this->mcpRefusalCodes, ...$codes]));

        return $this;
    }

    /** @return list<string> the host's registered MCP refusal codes */
    public function registeredMcpRefusalCodes(): array
    {
        return $this->mcpRefusalCodes;
    }

    /**
     * A workspace navigation link, shown when the principal holds the ability. The `slot` places it in the shell: `primary`
     * (the header's nav) or `user-menu` (the account menu). Settings is not a slot: it is one nav link to a landing page
     * that lists the registered settings sections. `group` and `order` sort links
     * within a slot (lower first, then registration order). `active` marks the link current: a pattern on the path under the workspace
     * (`cases*`), or a list of them for a module that spans several prefixes; an entry `route:<name pattern>` (`route:trips.entries.*`)
     * matches the current route's name instead. It defaults to the suffix and everything below it.
     *
     * @param  string|list<string>|null  $active
     */
    public function navigation(string $ability, string $label, string $suffix, string $slot = 'primary', string $group = 'workspace', int $order = 0, string|array|null $active = null): self
    {
        self::validateActive($active);
        if (! in_array($slot, ['primary', 'user-menu'], true)) {
            throw new RuntimeException('Unknown navigation slot: '.$slot);
        }
        $this->navigation[] = ['ability' => $ability, 'label' => $label, 'suffix' => $suffix, 'group' => $group, 'order' => $order, 'active' => $active, 'slot' => $slot];

        return $this;
    }

    /** @param  string|list<string>|null  $active An empty list, a non-string entry or a bare `route:` refuses at registration, not as a link that never lights. */
    private static function validateActive(string|array|null $active): void
    {
        if (! is_array($active)) {
            return;
        }
        if ($active === [] || ! array_is_list($active)) {
            throw new RuntimeException('Navigation active must be a pattern or a non-empty list of patterns.');
        }
        foreach ($active as $entry) {
            if (! is_string($entry) || $entry === 'route:') {
                throw new RuntimeException('Navigation active entries are path patterns or route:<name pattern>.');
            }
        }
    }

    /**
     * A page of a module, for the shell's SubNav. The module is the primary navigation link whose suffix is `$module`; while it (or
     * one of its pages) is current, the shell lists its pages the principal may open, in `order` and then registration order. The
     * ability, suffix and `active` rules are navigation()'s. A module with no pages gets no SubNav; a small app registers none.
     *
     * @param  string|list<string>|null  $active
     */
    public function modulePage(string $module, string $ability, string $label, string $suffix, int $order = 0, string|array|null $active = null): self
    {
        self::validateActive($active);
        $this->modulePages[] = ['module' => $module, 'ability' => $ability, 'label' => $label, 'suffix' => $suffix, 'order' => $order, 'active' => $active];

        return $this;
    }

    /**
     * A section of the settings landing page. `workspace` sections are pages at `/@{workspace}/settings/{key}` and need the
     * ability to be listed; `personal` ones are at `/me/{key}` and belong to no workspace. The landing page lists them
     * grouped and in order (lower `order` first, then registration order). The feature registers the section's route itself,
     * with a breadcrumb under the landing page's. `icon` is a lucide icon name (kebab case, e.g. `sliders-horizontal`) shown on the landing
     * card and in the sidebar; one the kit does not know falls back to a generic icon. Group names never repeat a lone item's label.
     */
    public function settingsSection(string $scope, string $key, string $label, string $description, ?string $ability = null, string $group = 'General', int $order = 0, ?string $icon = null, bool $placeholder = false): self
    {
        if (! in_array($scope, ['workspace', 'personal'], true)) {
            throw new RuntimeException('Unknown settings scope: '.$scope);
        }
        if (! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $key)) {
            throw new RuntimeException('Invalid settings section key: '.$key);
        }
        foreach ($this->sections as $section) {
            if ($section['scope'] === $scope && $section['key'] === $key) {
                throw new RuntimeException('Settings section registered twice: '.$scope.'/'.$key);
            }
        }
        $this->sections[] = ['scope' => $scope, 'key' => $key, 'label' => $label, 'description' => $description, 'ability' => $ability, 'group' => $group, 'order' => $order, 'icon' => $icon, 'placeholder' => $placeholder];
        // A planned section is a placeholder page at the section's own address, in the settings layout (which supplies the width).
        if ($placeholder) {
            if ($scope !== 'workspace') {
                throw new RuntimeException('Only a workspace settings section can be a placeholder: '.$key);
            }
            $this->placeholder('workspaces.settings.'.$key, 'settings/'.$key, $ability, $label, $description, ['Settings', $label], settings: true);
        }

        return $this;
    }

    /** @param array<string, string> $scopes */
    public function scopes(array $scopes): self
    {
        $this->scopes = [...$this->scopes, ...$scopes];

        return $this;
    }

    /** @param list<string> $capabilities */
    public function capabilities(array $capabilities): self
    {
        array_push($this->capabilities, ...$capabilities);

        return $this;
    }

    /**
     * An entitlement: the capability is part of a plan, so a workspace has it only when its plan includes it (`Entitlements`). `limits` names the counts the
     * plan caps (`seats`). Not included is absent by default, like a capability that is off; `upgrade` opts into a 403 and an upgrade action at the workspace
     * path `suffix` instead. Declared once, for a declared capability.
     *
     * @param  list<string>  $limits
     * @param  ?array{label: string, suffix: string}  $upgrade
     */
    public function entitlement(string $capability, array $limits = [], ?array $upgrade = null): self
    {
        if (! in_array($capability, $this->capabilities, true)) {
            throw new RuntimeException('An entitlement needs a declared capability: '.$capability);
        }
        foreach ($limits as $limit) {
            if (! preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/', $limit)) {
                throw new RuntimeException('Invalid entitlement limit: '.$limit);
            }
        }
        $declared = ['limits' => array_values(array_unique($limits)), 'upgrade' => $upgrade === null ? null : ['label' => (string) $upgrade['label'], 'suffix' => (string) $upgrade['suffix']]];
        if (isset($this->entitlements[$capability]) && $this->entitlements[$capability] !== $declared) {
            throw new RuntimeException('Conflicting declaration for entitlement: '.$capability);
        }
        $this->entitlements[$capability] = $declared;

        return $this;
    }

    /** @return ?array{limits: list<string>, upgrade: ?array{label: string, suffix: string}} what the capability's entitlement declares, or null for a capability no plan gates */
    public function entitlementFor(string $capability): ?array
    {
        return $this->entitlements[$capability] ?? null;
    }

    /** @return array<string, array{limits: list<string>, upgrade: ?array{label: string, suffix: string}}> */
    public function entitlements(): array
    {
        return $this->entitlements;
    }

    /**
     * A permission a feature declares, with the Concierge roles it is granted to by default (`kit:permissions:sync`
     * creates it and grants those; custom grants are kept). `capability` is the Pennant gate that must also be active
     * in the workspace, and `abilities` the legacy abilities whose role check this permission replaces. Declared once.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $abilities
     */
    public function permission(string $name, array $roles, ?string $capability = null, array $abilities = []): self
    {
        if (! preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)+\z/', $name)) {
            throw new RuntimeException('Invalid permission name: '.$name);
        }
        $declared = ['roles' => array_values(array_unique($roles)), 'capability' => $capability];
        if (isset($this->permissions[$name]) && $this->permissions[$name] !== $declared) {
            throw new RuntimeException('Conflicting declaration for permission: '.$name);
        }
        $this->permissions[$name] = $declared;
        foreach ($abilities as $ability) {
            if (isset($this->abilityPermissions[$ability]) && $this->abilityPermissions[$ability] !== $name) {
                throw new RuntimeException('Conflicting permission for ability: '.$ability);
            }
            $this->abilityPermissions[$ability] = $name;
        }

        return $this;
    }

    /** @return array<string, array{roles: list<string>, capability: ?string}> */
    public function permissionCatalog(): array
    {
        return $this->permissions;
    }

    /** The permission that gates an ability, or null for an ability checked some other way. */
    public function permissionFor(string $ability): ?string
    {
        return $this->abilityPermissions[$ability] ?? null;
    }

    /** @var array<string, array{route: string, uri: string, ability: ?string, title: string, description: string, breadcrumbs: list<string>, width: ?PageWidth, milestone: ?string, links: list<array{label: string, route: string, ability: ?string}>, settings: bool}> */
    private array $placeholders = [];

    /**
     * A page that is not built yet, registered under the real page's route name, address, ability, trail and width, so the shell, the navigation and the document title are
     * what the real page will have. Making it real is a controller for the same route name and deleting this line. It is a GET-only page behind the same sign-in as any
     * workspace page, shows the honest "Not built yet" (what the page will do, an optional "Planned for {milestone}", up to three links) and answers 404, not 403, to
     * anyone without the ability.
     *
     * @param  ?string  $ability  the real page's ability; with none the page is open to any member
     * @param  list<string>  $breadcrumbs  the trail's titles, the last being this page's
     * @param  list<array{label: string, route: string, ability?: ?string}>  $links  at most three: a label and a workspace route name; left out for a person who may not open it
     */
    public function placeholder(string $route, string $uri, ?string $ability, string $title, string $description, array $breadcrumbs = [], PageWidth|string|null $width = null, ?string $milestone = null, array $links = [], bool $settings = false): self
    {
        if (isset($this->placeholders[$route])) {
            throw new RuntimeException('Placeholder registered twice: '.$route);
        }
        if (! preg_match('#\A[a-z0-9]+(?:[-/][a-z0-9]+)*\z#', $uri)) {
            throw new RuntimeException('Invalid placeholder address: '.$uri);
        }
        if (count($links) > 3) {
            throw new RuntimeException('A placeholder has at most three links: '.$route);
        }
        if ($milestone !== null && preg_match('/\d+\.\d+|\b20\d\d\b/', $milestone)) {
            throw new RuntimeException('A placeholder\'s milestone is words only, with no version or date: '.$route);
        }
        $this->placeholders[$route] = ['route' => $route, 'uri' => $uri, 'ability' => $ability, 'title' => $title, 'description' => $description, 'breadcrumbs' => $breadcrumbs === [] ? [$title] : array_values($breadcrumbs), 'width' => is_string($width) ? PageWidth::from($width) : $width, 'milestone' => $milestone, 'links' => array_map(fn (array $link): array => ['label' => (string) $link['label'], 'route' => (string) $link['route'], 'ability' => $link['ability'] ?? null], array_values($links)), 'settings' => $settings];

        return $this;
    }

    /** @return array<string, array{route: string, uri: string, ability: ?string, title: string, description: string, breadcrumbs: list<string>, width: ?PageWidth, milestone: ?string, links: list<array{label: string, route: string, ability: ?string}>, settings: bool}> */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /** @var array{get: callable, set: callable}|null */
    private ?array $workspaceTimezone = null;

    /** How the workspace's timezone is read and written (inside its tenant context), so the welcome flow can confirm it without depending on the settings feature. One only. */
    public function workspaceTimezoneUsing(callable $get, callable $set): self
    {
        $this->workspaceTimezone = ['get' => $get, 'set' => $set];

        return $this;
    }

    /** @return array{get: callable, set: callable}|null */
    public function workspaceTimezone(): ?array
    {
        return $this->workspaceTimezone;
    }

    /** A step run inside a newly provisioned workspace's tenant context, in registration order. */
    public function provisioning(callable $provision): self
    {
        $this->provisioners[] = $provision;

        return $this;
    }

    /**
     * @param  array<string, int>  $statuses  error code => HTTP status
     * @param  array<string, string>  $messages  error code => page message
     */
    public function errors(array $statuses, array $messages = []): self
    {
        foreach ($statuses as $code => $status) {
            if (isset($this->statuses[$code]) && $this->statuses[$code] !== $status) {
                throw new RuntimeException('Conflicting status for error code: '.$code);
            }
            $this->statuses[$code] = $status;
        }
        $this->messages = [...$this->messages, ...$messages];

        return $this;
    }

    /** Renders a registered error code as a page for a browser request. One renderer only. */
    public function errorPages(callable $render): self
    {
        if ($this->errorPage !== null) {
            throw new RuntimeException('An error page renderer is already registered.');
        }
        $this->errorPage = $render;

        return $this;
    }

    /** Routes (by name, `*` allowed) that render in the FocusShell for a signed-in viewer: the flows outside a workspace. A guest on them still gets the guest shell. */
    public function focusRoutes(string ...$patterns): self
    {
        $this->focusRoutes = array_values(array_unique([...$this->focusRoutes, ...$patterns]));

        return $this;
    }

    public function isFocusRoute(?string $name): bool
    {
        return $name !== null && Str::is($this->focusRoutes, $name);
    }

    /** Where the workspace list sends a request that has nothing to choose, or null to show the list. One opener only. */
    public function workspaceOpener(callable $opener): self
    {
        if ($this->workspaceOpener !== null) {
            throw new RuntimeException('A workspace opener is already registered.');
        }
        $this->workspaceOpener = $opener;

        return $this;
    }

    public function opensWorkspace(Request $request): ?string
    {
        return $this->workspaceOpener === null ? null : ($this->workspaceOpener)($request);
    }

    /**
     * A hook run first in each web request (StartKitRequest), before the session, the signed-in person or any of the host's middleware: beginning the
     * request's memos, so the host's own middleware (a tenant resolver) reads through them too. It must need nothing the rest of `web` sets up.
     */
    public function startRequestUsing(callable $hook): self
    {
        $this->startHooks[] = $hook;

        return $this;
    }

    public function startRequest(Request $request): void
    {
        foreach ($this->startHooks as $hook) {
            $hook($request);
        }
    }

    /** A hook run late in each web request (PrepareKitRequest), once the session has started (the brand's local-only `?brand=` switch, from the composition root). */
    public function beforeRequestUsing(callable $hook): self
    {
        $this->requestHooks[] = $hook;

        return $this;
    }

    public function prepareRequest(Request $request): void
    {
        foreach ($this->requestHooks as $hook) {
            $hook($request);
        }
    }

    /** The brand's web footer, from the composition root (`Tey\Kit\Brand`), so no feature depends on it. */
    public function webFooterUsing(callable $footer): self
    {
        $this->webFooter = $footer;

        return $this;
    }

    /** @return array{identity: string, credit: string, links: list<array{label: string, url: string}>} */
    public function webFooter(): array
    {
        return $this->webFooter !== null ? ($this->webFooter)() : ['identity' => (string) config('app.name'), 'credit' => '', 'links' => []];
    }

    /** The Inertia component an app renders for its dashboard in place of the kit's empty state. One only. */
    public function dashboardComponent(?string $component = null): self|string|null
    {
        if ($component === null) {
            return $this->dashboard;
        }
        if ($this->dashboard !== null) {
            throw new RuntimeException('A dashboard component is already registered.');
        }
        $this->dashboard = $component;

        return $this;
    }

    /** @var list<array{key: string, widget: class-string, order: int, ability: string}> */
    private array $widgets = [];

    /**
     * A dashboard widget: a session-ui `Dashboard\Widget` subclass, shown to a viewer who holds `ability` (and so its capability) in the workspace. Widgets
     * sort by `order` (lower first), then registration. The key is unique and dotted lower case (`kit.activity`, `queue.mine`). An app adds widgets here
     * and never forks the dashboard page.
     *
     * @param  class-string  $widget
     */
    public function widget(string $key, string $widget, int $order = 0, string $ability = 'dashboard.read'): self
    {
        if (! preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/', $key)) {
            throw new RuntimeException('Invalid widget key: '.$key);
        }
        foreach ($this->widgets as $entry) {
            if ($entry['key'] === $key) {
                throw new RuntimeException('Widget registered twice: '.$key);
            }
        }
        if (! is_subclass_of($widget, 'Tey\Kit\Features\SessionUi\Dashboard\Widget')) {
            throw new RuntimeException('A widget extends Tey\Kit\Features\SessionUi\Dashboard\Widget: '.$widget);
        }
        $this->widgets[] = ['key' => $key, 'widget' => $widget, 'order' => $order, 'ability' => $ability];

        return $this;
    }

    /** @var list<array{key: string, provider: class-string, ability: string, label: string, order: int}> */
    private array $searchProviders = [];

    /**
     * A global search provider: a session-ui `Search\SearchProvider` that answers the viewer's query in the current workspace, asked only for a viewer
     * who holds `ability` there (checked by `Access`, so its capability, entitlement and permission apply). `label` heads its group of results. Providers
     * sort by `order`, then registration. The key is unique and dotted lower case (`kit.people`, `app.orders`). The search page, the header palette and
     * the MCP `workspace_search` tool all read the same providers.
     *
     * @param  class-string  $provider
     */
    public function searchProvider(string $key, string $provider, string $ability, string $label, int $order = 0): self
    {
        if (! preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/', $key)) {
            throw new RuntimeException('Invalid search provider key: '.$key);
        }
        foreach ($this->searchProviders as $entry) {
            if ($entry['key'] === $key) {
                throw new RuntimeException('Search provider registered twice: '.$key);
            }
        }
        if (! is_subclass_of($provider, 'Tey\Kit\Features\SessionUi\Search\SearchProvider')) {
            throw new RuntimeException('A search provider implements Tey\Kit\Features\SessionUi\Search\SearchProvider: '.$provider);
        }
        $this->searchProviders[] = ['key' => $key, 'provider' => $provider, 'ability' => $ability, 'label' => $label, 'order' => $order];

        return $this;
    }

    /** @return list<array{key: string, provider: class-string, ability: string, label: string, order: int}> Sorted by order, then registration. */
    public function searchProviders(): array
    {
        $indexed = array_map(null, array_keys($this->searchProviders), $this->searchProviders);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /** @return list<array{key: string, widget: class-string, order: int, ability: string}> Sorted by order, then registration. */
    public function widgets(): array
    {
        $indexed = array_map(null, array_keys($this->widgets), $this->widgets);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /** @var array<string, array{model: class-string, view: callable, label: callable, url: ?callable}> */
    private array $noteSubjects = [];

    /** @var list<callable> */
    private array $noteComposers = [];

    /**
     * A record a note can be about (the notes feature): `alias` is what `kit_notes.subject_type` stores, never a class name. `view(Principal, Model): bool`
     * decides whether the viewer may see the record, and so its notes; `label(Model): string` and `url(Model): ?string` show it on the card. An
     * alias no app registered is refused, so a note on it is never shown.
     *
     * @param  class-string  $model
     */
    public function noteSubject(string $alias, string $model, callable $view, callable $label, ?callable $url = null): self
    {
        if (! preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/', $alias)) {
            throw new RuntimeException('Invalid note subject alias: '.$alias);
        }
        if (isset($this->noteSubjects[$alias])) {
            throw new RuntimeException('Note subject registered twice: '.$alias);
        }
        $this->noteSubjects[$alias] = ['model' => $model, 'view' => $view, 'label' => $label, 'url' => $url];

        return $this;
    }

    /** @return ?array{model: class-string, view: callable, label: callable, url: ?callable} */
    public function noteSubjectFor(string $alias): ?array
    {
        return $this->noteSubjects[$alias] ?? null;
    }

    /**
     * Adds to the notes composer for the request: `fn (Request): array` returns `options` (extra buttons, `list<array{key, label, icon, url}>`) and an
     * optional `subject_search` URL for a "link to record" picker. Hooks run in registration order; a later key replaces an earlier one.
     */
    public function noteComposer(callable $hook): self
    {
        $this->noteComposers[] = $hook;

        return $this;
    }

    /** @return list<callable> */
    public function noteComposers(): array
    {
        return $this->noteComposers;
    }

    public function errorPage(): ?callable
    {
        return $this->errorPage;
    }

    public function loadRoutes(): void
    {
        foreach ($this->routes as ['file' => $file, 'group' => $group, 'options' => $options]) {
            $middleware = $group === null ? $options['middleware'] : [$group, ...$options['middleware']];
            if ($middleware === [] && $options['prefix'] === '' && $options['name'] === '') {
                require $file;

                continue;
            }
            Route::middleware($middleware)->prefix($options['prefix'])->name($options['name'])->group($file);
        }
    }

    /** @return list<array{file: string, group: ?string, options: array{prefix: string, middleware: list<string>, name: string}}> */
    public function routeFiles(): array
    {
        return $this->routes;
    }

    /** The capability that gates an ability, or null for an ability no adopted feature declares. */
    public function capabilityFor(string $ability): ?string
    {
        return $this->abilities[$ability] ?? null;
    }

    /** @return array<string, string> */
    public function abilityMap(): array
    {
        return $this->abilities;
    }

    public function guardConnection(Principal $principal, object $workspace, string $ability): void
    {
        // Fail closed: a delegated principal with no adopted connection feature is refused.
        if ($this->connectionGuard === null) {
            throw new RuntimeException('connection_denied');
        }
        ($this->connectionGuard)($principal, $workspace, $ability);
    }

    /** @return list<class-string> */
    public function mcpTools(): array
    {
        // `kit.mcp.tools` turns a tool off by its MCP name (`['workspace_dashboard' => false]`), for an app that serves its own: it is neither listed nor callable.
        $off = array_keys(array_filter((array) config('kit.mcp.tools', []), fn (mixed $on): bool => $on === false));
        if ($off === []) {
            return $this->tools;
        }

        return array_values(array_filter($this->tools, fn (string $class): bool => ! in_array(app($class)->name(), $off, true)));
    }

    /** @return list<array{module: string, ability: string, label: string, suffix: string, order: int, active: string|list<string>|null}> One module's pages, sorted by order, then registration. */
    public function modulePages(string $module): array
    {
        $indexed = array_filter(array_map(null, array_keys($this->modulePages), $this->modulePages), fn (array $entry): bool => $entry[1]['module'] === $module);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /** @return list<array{ability: string, label: string, suffix: string, group: string, order: int, active: string|list<string>|null, slot: string}> Sorted by order, then registration. */
    public function navigationLinks(): array
    {
        $indexed = array_map(null, array_keys($this->navigation), $this->navigation);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /**
     * The scope's settings sections in landing order: by `order`, then registration.
     *
     * @return list<array{scope: string, key: string, label: string, description: string, ability: ?string, group: string, order: int, icon: ?string, placeholder: bool}>
     */
    public function settingsSections(string $scope): array
    {
        $indexed = array_filter(array_map(null, array_keys($this->sections), $this->sections), fn (array $entry): bool => $entry[1]['scope'] === $scope);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /** @return array<string, string> */
    public function tokenScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function workspaceCapabilities(): array
    {
        return $this->capabilities;
    }

    /** @return list<callable(object): void> */
    public function provisioners(): array
    {
        return $this->provisioners;
    }

    public function status(string $code): ?int
    {
        return $this->statuses[$code] ?? null;
    }

    public function message(string $code): ?string
    {
        return $this->messages[$code] ?? null;
    }
}
