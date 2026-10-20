<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Support\Facades\Route;
use RuntimeException;

/** What each feature contributes to the shared seams: routes, MCP tools, navigation, token scopes, abilities, capabilities, provisioning steps, connection guards and error codes. */
final class Registry
{
    /** @var list<array{file: string, group: ?string, options: array{prefix: string, middleware: list<string>, name: string}}> */
    private array $routes = [];

    /** @var array<string, string> ability => capability */
    private array $abilities = [];

    /** @var ?callable(Principal, object, string): void */
    private $connectionGuard = null;

    /** @var ?callable(string, int, ?string, \Illuminate\Http\Request): mixed */
    private $errorPage = null;

    /** @var ?callable(\Illuminate\Http\Request): ?string */
    private $workspaceOpener = null;

    /** @var ?callable(): array{identity: string, credit: string, links: list<array{label: string, url: string}>} */
    private $webFooter = null;

    /** @var list<class-string> */
    private array $tools = [];

    /** @var list<array{ability: string, label: string, suffix: string, group: string, order: int, active: ?string, slot: string}> */
    private array $navigation = [];

    /** @var list<array{scope: string, key: string, label: string, description: string, ability: ?string, group: string, order: int}> */
    private array $sections = [];

    /** @var array<string, string> */
    private array $scopes = [];

    /** @var list<string> */
    private array $capabilities = [];

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

    /**
     * A workspace navigation link, shown when the principal holds the ability. The `slot` places it in the shell: `primary`
     * (the header's nav) or `user-menu` (the account menu). Settings is not a slot: it is one nav link to a landing page
     * that lists the registered settings sections. `group` and `order` sort links
     * within a slot (lower first, then registration order). `active` is a pattern on the path under the workspace
     * (`notes*`) that marks the link current; it defaults to the suffix and everything below it.
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
     * A section of the settings landing page. `workspace` sections are pages at `/@{workspace}/settings/{key}` and need the
     * ability to be listed; `personal` ones are at `/me/{key}` and belong to no workspace. The landing page lists them
     * grouped and in order (lower `order` first, then registration order). The feature registers the section's route itself,
     * with a breadcrumb under the landing page's.
     */
    public function settingsSection(string $scope, string $key, string $label, string $description, ?string $ability = null, string $group = 'General', int $order = 0): self
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
        $this->sections[] = ['scope' => $scope, 'key' => $key, 'label' => $label, 'description' => $description, 'ability' => $ability, 'group' => $group, 'order' => $order];

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

    /** Where the workspace list sends a request that has nothing to choose, or null to show the list. One opener only. */
    public function workspaceOpener(callable $opener): self
    {
        if ($this->workspaceOpener !== null) {
            throw new RuntimeException('A workspace opener is already registered.');
        }
        $this->workspaceOpener = $opener;

        return $this;
    }

    public function opensWorkspace(\Illuminate\Http\Request $request): ?string
    {
        return $this->workspaceOpener === null ? null : ($this->workspaceOpener)($request);
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
     * @return list<array{scope: string, key: string, label: string, description: string, ability: ?string, group: string, order: int}>
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
