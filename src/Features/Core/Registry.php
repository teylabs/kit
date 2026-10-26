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

    private ?string $dashboard = null;

    /** @var list<class-string> */
    private array $tools = [];

    /** @var list<array{ability: string, label: string, suffix: string, group: string, order: int, active: ?string, slot: string}> */
    private array $navigation = [];

    /** @var list<array{module: string, ability: string, label: string, suffix: string, order: int, active: ?string}> */
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

    /** @var ?callable(class-string, list<string>): void */
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
     * boot: with no MCP feature adopted, or for a tool the MCP feature's check refuses (no attribute, not gated, or a capability not declared before it).
     *
     * @param  list<class-string>  $tools
     */
    public function capabilityTools(array $tools): self
    {
        if ($this->capabilityToolCheck === null) {
            throw new RuntimeException('Capability tools need the mcp feature.');
        }
        foreach ($tools as $tool) {
            ($this->capabilityToolCheck)($tool, $this->capabilities);
        }

        return $this->tools($tools);
    }

    /**
     * A workspace navigation link, shown when the principal holds the ability. The `slot` places it in the shell: `primary`
     * (the header's nav) or `user-menu` (the account menu). Settings is not a slot: it is one nav link to a landing page
     * that lists the registered settings sections. `group` and `order` sort links
     * within a slot (lower first, then registration order). `active` is a pattern on the path under the workspace
     * (`cases*`) that marks the link current; it defaults to the suffix and everything below it.
     */
    public function navigation(string $ability, string $label, string $suffix, string $slot = 'primary', string $group = 'workspace', int $order = 0, ?string $active = null): self
    {
        if (! in_array($slot, ['primary', 'user-menu'], true)) {
            throw new RuntimeException('Unknown navigation slot: '.$slot);
        }
        $this->navigation[] = ['ability' => $ability, 'label' => $label, 'suffix' => $suffix, 'group' => $group, 'order' => $order, 'active' => $active, 'slot' => $slot];

        return $this;
    }

    /**
     * A page of a module, for the shell's SubNav. The module is the primary navigation link whose suffix is `$module`; while it (or
     * one of its pages) is current, the shell lists its pages the principal may open, in `order` and then registration order. The
     * ability, suffix and `active` rules are navigation()'s. A module with no pages gets no SubNav; a small app registers none.
     */
    public function modulePage(string $module, string $ability, string $label, string $suffix, int $order = 0, ?string $active = null): self
    {
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

    /** A hook run at the start of each web request, once the session has started (the brand's local-only `?brand=` switch, from the composition root). */
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
        return $this->tools;
    }

    /** @return list<array{module: string, ability: string, label: string, suffix: string, order: int, active: ?string}> One module's pages, sorted by order, then registration. */
    public function modulePages(string $module): array
    {
        $indexed = array_filter(array_map(null, array_keys($this->modulePages), $this->modulePages), fn (array $entry): bool => $entry[1]['module'] === $module);
        usort($indexed, fn (array $a, array $b) => [$a[1]['order'], $a[0]] <=> [$b[1]['order'], $b[0]]);

        return array_column($indexed, 1);
    }

    /** @return list<array{ability: string, label: string, suffix: string, group: string, order: int, active: ?string, slot: string}> Sorted by order, then registration. */
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
