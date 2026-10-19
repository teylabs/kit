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

    /** @var list<class-string> */
    private array $tools = [];

    /** @var list<array{ability: string, label: string, suffix: string}> */
    private array $navigation = [];

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

    /** A workspace navigation link, shown when the principal holds the ability. */
    public function navigation(string $ability, string $label, string $suffix): self
    {
        $this->navigation[] = ['ability' => $ability, 'label' => $label, 'suffix' => $suffix];

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

    /** @return list<array{ability: string, label: string, suffix: string}> */
    public function navigationLinks(): array
    {
        return $this->navigation;
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
