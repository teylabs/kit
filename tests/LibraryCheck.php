<?php

// The starter feature library under src/Features: its dependency graph, autoloading and registry wiring; no app boot or database.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** The starter's FeatureGraphTest::REQUIRES at teylabs/starter 9f8405b; the allowed set is the transitive closure. */
const REQUIRES = [
    'Core' => [],
    'Workspaces' => ['Core'],
    'Access' => ['Workspaces'],
    'Settings' => ['Workspaces', 'Access'],
    'Mcp' => ['Workspaces', 'Access'],
    'Notes' => ['Workspaces', 'Access'],
    'SessionUi' => ['Workspaces', 'Access'],
];
const IDS = ['core' => 'Core', 'workspaces' => 'Workspaces', 'access' => 'Access', 'settings' => 'Settings', 'session-ui' => 'SessionUi', 'mcp' => 'Mcp', 'notes' => 'Notes'];
const ROOT = __DIR__.'/../src/Features';

function libraryCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function libraryRefuses(Closure $operation, string $contains, string $message): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        libraryCheck(str_contains($error->getMessage(), $contains), $message.' ('.$error->getMessage().')');

        return;
    }
    throw new RuntimeException('Expected refusal: '.$message);
}
/** @return list<string> */
function closure(string $feature, array $seen = []): array
{
    foreach (REQUIRES[$feature] as $required) {
        if (! in_array($required, $seen, true)) {
            $seen[] = $required;
            $seen = closure($required, $seen);
        }
    }

    return $seen;
}
/** @return list<string> Features a file may use: its own and its closure, plus <Other> and its closure under Integrations/<Other>. */
function allowed(string $relative): array
{
    $parts = explode('/', $relative);
    $allowed = [$parts[0], ...closure($parts[0])];
    if (($parts[1] ?? null) === 'Integrations' && isset($parts[2], REQUIRES[$parts[2]])) {
        array_push($allowed, $parts[2], ...closure($parts[2]));
    }

    return $allowed;
}
/** @return list<string> */
function phpViolations(string $relative, string $source): array
{
    $allowed = allowed($relative);
    $namespace = '';
    $inUse = false;
    $names = [];
    $tokens = PhpToken::tokenize($source);
    foreach ($tokens as $index => $token) {
        if ($token->is(T_NAMESPACE)) {
            $namespace = trim($tokens[$index + 2]->text ?? '');
        } elseif ($token->is(T_USE)) {
            $inUse = true;
        } elseif ($token->text === ';') {
            $inUse = false;
        } elseif ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && ($tokens[$index - 2]->id ?? null) !== T_NAMESPACE) {
            $name = ltrim($token->text, '\\');
            if (! $inUse && $token->is(T_NAME_QUALIFIED) && ! str_starts_with($name, 'Tey\\Kit\\') && ! str_starts_with($name, 'App\\')) {
                $name = $namespace.'\\'.$name;
            }
            $names[] = $name;
        }
    }
    $violations = [];
    foreach (array_unique($names) as $name) {
        // A feature never reaches into the host app, the composition root or the kit's lifecycle classes.
        if (str_starts_with($name, 'App\\')) {
            $violations[] = $relative.' uses '.$name;

            continue;
        }
        if (! str_starts_with($name, 'Tey\\Kit\\')) {
            continue;
        }
        $segments = explode('\\', $name);
        if (! ($segments[2] === 'Features' && isset($segments[4], REQUIRES[$segments[3]]) && in_array($segments[3], $allowed, true))) {
            $violations[] = $relative.' uses '.$name;
        }
    }

    return $violations;
}
/** @return list<string> Paths relative to src/Features, one directory deep or more. */
function featureFiles(): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen(ROOT) + 1);
        if ($file->getExtension() === 'php' && str_contains($relative, '/')) {
            $files[] = $relative;
        }
    }
    sort($files);

    return $files;
}
function registryFor(array $features, array $config = []): Registry
{
    Container::getInstance()->instance('config', new Repository(['kit' => $config]));

    return FeaturesServiceProvider::registry(FeaturesServiceProvider::selected($features));
}

try {
    // The graph.
    foreach (array_keys(REQUIRES) as $feature) {
        libraryCheck(! in_array($feature, closure($feature), true), $feature.' does not require itself');
    }
    $directories = array_map('basename', array_values(array_filter(glob(ROOT.'/*'), 'is_dir')));
    sort($directories);
    $expected = array_keys(REQUIRES);
    sort($expected);
    libraryCheck($directories === $expected, 'src/Features has one directory per starter feature');
    $declared = [];
    foreach (FeaturesServiceProvider::FEATURES as $id => [, $requires]) {
        $declared[IDS[$id]] = array_map(fn (string $required): string => IDS[$required], $requires);
    }
    $starter = REQUIRES;
    ksort($declared);
    ksort($starter);
    libraryCheck(array_keys(FeaturesServiceProvider::FEATURES) === array_keys(IDS) && $declared === $starter, 'the provider graph is the starter arch graph, in canonical order');

    // Every feature file uses only the features it requires.
    $files = featureFiles();
    $violations = [];
    foreach ($files as $relative) {
        array_push($violations, ...phpViolations($relative, file_get_contents(ROOT.'/'.$relative)));
    }
    libraryCheck(count($files) > 50, 'the graph check scans '.count($files).' feature files');
    libraryCheck($violations === [], 'feature files use only required features'.($violations === [] ? '' : ': '.implode('; ', $violations)));
    libraryCheck(
        phpViolations('Notes/Operations/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\Notes\\Operations;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nuse Tey\\Kit\\Features\\Access\\Access;\nfinal class Bad { public function a() { return [\\Tey\\Kit\\Features\\FeaturesServiceProvider::class, \\Tey\\Kit\\Blueprint::class, \\App\\Models\\User::class, Models\\Note::class]; } }")
        === ['Notes/Operations/Bad.php uses Tey\\Kit\\Features\\Mcp\\Context', 'Notes/Operations/Bad.php uses Tey\\Kit\\Features\\FeaturesServiceProvider', 'Notes/Operations/Bad.php uses Tey\\Kit\\Blueprint', 'Notes/Operations/Bad.php uses App\\Models\\User'],
        'the graph check catches a feature, the composition root, a kit class and a host class',
    );
    libraryCheck(phpViolations('Notes/Integrations/Mcp/Good.php', "<?php\nnamespace Tey\\Kit\\Features\\Notes\\Integrations\\Mcp;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nfinal class Good {}") === [], 'an integration may use the feature it integrates with');
    libraryCheck(phpViolations('Core/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\Core;\nuse Tey\\Kit\\Features\\Workspaces\\Models\\Workspace;\nfinal class Bad {}") === ['Core/Bad.php uses Tey\\Kit\\Features\\Workspaces\\Models\\Workspace'], 'core may not use workspaces');

    // Every class autoloads under the dev dependencies.
    $classes = 0;
    foreach ($files as $relative) {
        if (str_contains($relative, '/routes/')) {
            continue;
        }
        $class = 'Tey\\Kit\\Features\\'.str_replace('/', '\\', substr($relative, 0, -4));
        libraryCheck(class_exists($class) || interface_exists($class) || trait_exists($class), $class.' autoloads');
        $classes++;
    }
    libraryCheck(class_exists(FeaturesServiceProvider::class), 'the composition root autoloads ('.$classes.' feature classes)');

    // Selections are validated before anything registers.
    libraryCheck(FeaturesServiceProvider::selected(null) === [] && FeaturesServiceProvider::selected([]) === [], 'no adopted features registers nothing');
    libraryCheck(FeaturesServiceProvider::selected(['notes', 'access', 'workspaces', 'core']) === ['core', 'workspaces', 'access', 'notes'], 'a selection is kept in canonical order');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'workspaces', 'notes']), 'Feature notes requires feature access', 'a missing requirement refuses');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'foundation']), 'Unknown feature: foundation', 'an unknown feature refuses');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'core']), 'once', 'a duplicate refuses');

    // The whole selection contributes exactly what the starter's FeatureProvider did.
    $all = registryFor(array_keys(IDS));
    libraryCheck(array_map(fn (array $route): array => [basename($route['file']), $route['group']], $all->routeFiles()) === [['workspaces.php', 'web'], ['connections.php', 'web'], ['mcp.php', null], ['settings.php', 'web'], ['notes.php', 'web']], 'route files load in the starter order');
    libraryCheck(array_map(fn (array $route): bool => is_file($route['file']), $all->routeFiles()) === array_fill(0, 5, true), 'every route file ships in the library');
    libraryCheck(array_map(fn (string $tool): string => class_basename($tool), $all->mcpTools()) === ['ReadSettings', 'UpdateSettings', 'ListNotes', 'ReadNote', 'CreateNote', 'UpdateNote', 'DeleteNote'], 'MCP tools are the starter tools in order');
    libraryCheck($all->navigationLinks() === [['ability' => 'settings.read', 'label' => 'Settings', 'suffix' => 'settings'], ['ability' => 'notes.read', 'label' => 'Notes', 'suffix' => 'notes']], 'navigation is settings then notes');
    libraryCheck($all->tokenScopes() === ['mcp:use' => 'Use workspace MCP', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings', 'notes.read' => 'Read workspace notes', 'notes.write' => 'Create, update and delete workspace notes'], 'token scopes are the starter scopes');
    libraryCheck($all->workspaceCapabilities() === ['workspace.settings', 'workspace.notes'], 'capabilities are the starter capabilities');
    libraryCheck($all->abilityMap() === ['settings.read' => 'workspace.settings', 'settings.write' => 'workspace.settings', 'notes.read' => 'workspace.notes', 'notes.write' => 'workspace.notes'], 'each ability is gated by the capability Access used to hard-code');
    $statuses = ['workspace_not_found' => 404, 'workspace_mismatch' => 404, 'account_inactive' => 403, 'verification_required' => 403, 'admission_required' => 403, 'permission_denied' => 403, 'intent_conflict' => 409, 'unknown_arguments' => 422, 'capability_unavailable' => 403, 'entitlement_required' => 403, 'connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409, 'stale_settings' => 409, 'note_not_found' => 404, 'stale_note' => 409, 'note_intent_conflict' => 409];
    libraryCheck(array_filter($statuses, fn (int $status, string $code): bool => $all->status($code) !== $status, ARRAY_FILTER_USE_BOTH) === [] && $all->status('unregistered') === null, 'every starter error code keeps its status');
    libraryCheck(str_starts_with((string) $all->message('stale_settings'), 'These settings changed') && str_starts_with((string) $all->message('stale_note'), 'This note changed'), 'stale messages are kept');
    libraryCheck(count($all->provisioners()) === 1 && $all->errorPage() !== null, 'settings provisions and session-ui renders error pages');

    // Partial selections contribute only their own seams; optional integrations need both sides.
    $workspaces = registryFor(['core', 'workspaces']);
    libraryCheck($workspaces->mcpTools() === [] && $workspaces->navigationLinks() === [] && $workspaces->abilityMap() === [] && $workspaces->errorPage() === null && count($workspaces->routeFiles()) === 1, 'core and workspaces alone register one route file and no tools, abilities or pages');
    $settings = registryFor(['core', 'workspaces', 'access', 'settings']);
    libraryCheck($settings->mcpTools() === [] && $settings->capabilityFor('settings.write') === 'workspace.settings' && $settings->capabilityFor('notes.read') === null, 'settings without mcp registers no tools, and notes abilities stay unknown');
    $notesMcp = registryFor(['core', 'workspaces', 'access', 'mcp', 'notes']);
    libraryCheck(array_map('class_basename', $notesMcp->mcpTools()) === ['ListNotes', 'ReadNote', 'CreateNote', 'UpdateNote', 'DeleteNote'], 'notes with mcp registers only the notes tools');
    Container::getInstance()->instance(Registry::class, $notesMcp);
    libraryCheck(WorkspaceConnection::grantableScopes() === ['notes.read', 'notes.write'], 'a connection can be granted only adopted abilities');
    Container::getInstance()->instance(Registry::class, $all);
    libraryCheck(WorkspaceConnection::grantableScopes() === ['settings.read', 'settings.write', 'notes.read', 'notes.write'] && WorkspaceConnection::baseAbility() === 'settings.read', 'with every feature the grantable scopes and base ability are the starter ones');

    // Route options come from kit.routes.<feature>.
    $configured = registryFor(['core', 'workspaces', 'access', 'settings', 'notes'], ['routes' => ['notes' => ['enabled' => false], 'settings' => ['prefix' => 'app', 'middleware' => ['verified'], 'name' => 'kit.']]]);
    $routes = array_map(fn (array $route): array => [basename($route['file']), $route['options']], $configured->routeFiles());
    libraryCheck($routes === [['workspaces.php', ['prefix' => '', 'middleware' => [], 'name' => '']], ['settings.php', ['prefix' => 'app', 'middleware' => ['verified'], 'name' => 'kit.']]], 'a disabled feature loads no routes, and prefix, middleware and name are passed through');
    libraryCheck(count($configured->navigationLinks()) === 2 && $configured->capabilityFor('notes.read') === 'workspace.notes', 'disabling routes keeps the rest of the feature');

    // The registry fails closed.
    $empty = new Registry;
    libraryRefuses(fn () => $empty->guardConnection(new Principal(1, 2), (object) ['id' => 1], 'settings.read'), 'connection_denied', 'a delegated principal with no connection feature is refused');
    libraryCheck($empty->capabilityFor('settings.read') === null, 'an ability no feature declares has no capability');
    libraryRefuses(fn () => (new Registry)->abilities(['a.read' => 'x'])->abilities(['a.read' => 'y']), 'Conflicting capability', 'an ability cannot be gated by two capabilities');
    libraryRefuses(fn () => (new Registry)->connectionGuard(fn () => null)->connectionGuard(fn () => null), 'already registered', 'a second connection guard refuses');
    libraryRefuses(fn () => (new Registry)->errorPages(fn () => null)->errorPages(fn () => null), 'already registered', 'a second error page renderer refuses');

    // The views the mcp feature renders ship with the library.
    foreach (['connections', 'authorize'] as $view) {
        libraryCheck(is_file(dirname(__DIR__).'/resources/views/mcp/'.$view.'.blade.php'), 'kit::mcp.'.$view.' ships');
    }
    echo "Library checks only; no app boot, database, HTTP or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
