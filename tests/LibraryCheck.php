<?php

// The starter feature library under src/Features: dependency graph, autoloading, registry wiring and controller redirects; no database.
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\Features\Mcp\Operations\WorkspaceConnection;
use Tey\Kit\Features\Workspaces\Slugs;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** The starter's FeatureGraphTest::REQUIRES at teylabs/starter 9f8405b, plus Shell (core only), which SessionUi now requires; the allowed set is the transitive closure. */
const REQUIRES = [
    'Core' => [],
    'Workspaces' => ['Core'],
    'Access' => ['Workspaces'],
    'Settings' => ['Workspaces', 'Access'],
    'Mcp' => ['Workspaces', 'Access'],
    'Shell' => ['Core'],
    'SessionUi' => ['Workspaces', 'Access', 'Shell'],
    'Notes' => ['Workspaces', 'Access', 'SessionUi'],
];
const IDS = ['core' => 'Core', 'workspaces' => 'Workspaces', 'access' => 'Access', 'settings' => 'Settings', 'shell' => 'Shell', 'session-ui' => 'SessionUi', 'mcp' => 'Mcp', 'notes' => 'Notes'];
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
        // Tey\Kit\Mail is a feature-neutral library (the mail composer and its scanner), with no lifecycle or composition-root reach.
        if (! str_starts_with($name, 'Tey\\Kit\\') || str_starts_with($name, 'Tey\\Kit\\Mail\\')) {
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
        phpViolations('SessionUi/Operations/Bad.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Operations;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nuse Tey\\Kit\\Features\\Access\\Access;\nfinal class Bad { public function a() { return [\\Tey\\Kit\\Features\\FeaturesServiceProvider::class, \\Tey\\Kit\\Blueprint::class, \\App\\Models\\User::class, Models\\Note::class]; } }")
        === ['SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\Mcp\\Context', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Features\\FeaturesServiceProvider', 'SessionUi/Operations/Bad.php uses Tey\\Kit\\Blueprint', 'SessionUi/Operations/Bad.php uses App\\Models\\User'],
        'the graph check catches a feature, the composition root, a kit class and a host class',
    );
    libraryCheck(phpViolations('SessionUi/Integrations/Mcp/Good.php', "<?php\nnamespace Tey\\Kit\\Features\\SessionUi\\Integrations\\Mcp;\nuse Tey\\Kit\\Features\\Mcp\\Context;\nfinal class Good {}") === [], 'an integration may use the feature it integrates with');
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
    libraryCheck(FeaturesServiceProvider::selected(['mcp', 'access', 'workspaces', 'core']) === ['core', 'workspaces', 'access', 'mcp'], 'a selection is kept in canonical order');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'workspaces', 'mcp']), 'Feature mcp requires feature access', 'a missing requirement refuses');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'foundation']), 'Unknown feature: foundation', 'an unknown feature refuses');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'core']), 'once', 'a duplicate refuses');
    libraryCheck(FeaturesServiceProvider::selected(['core', 'shell']) === ['core', 'shell'], 'shell needs only core');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['shell']), 'Feature shell requires feature core', 'shell without core refuses');
    libraryCheck(FeaturesServiceProvider::selected(['core', 'workspaces', 'access', 'session-ui']) === ['core', 'workspaces', 'access', 'shell', 'session-ui'], 'a selection from before the split (session-ui without shell) carries shell');
    libraryRefuses(fn () => FeaturesServiceProvider::selected(['core', 'session-ui']), 'Feature session-ui requires feature workspaces', 'carrying shell loosens no other requirement');

    // Slugs: lowercase letters, digits and single hyphens between them; no reserved list, because the `@` marks the tenant.
    foreach (['acme', 'acme-co', 'a1', '42', 'login', 'workspaces', 'admin'] as $slug) {
        libraryCheck(Slugs::valid($slug), $slug.' is a valid slug');
    }
    foreach (['', 'Acme', '-acme', 'acme-', 'a--b', 'a_b', 'a b', 'a/b', '@acme', str_repeat('a', 64)] as $slug) {
        libraryCheck(! Slugs::valid($slug), '"'.$slug.'" is not a valid slug');
    }

    // The whole selection contributes exactly what the starter's FeatureProvider did.
    $all = registryFor(array_keys(IDS));
    libraryCheck(array_map(fn (array $route): array => [basename($route['file']), $route['group']], $all->routeFiles()) === [['workspaces.php', 'web'], ['connections.php', 'web'], ['mcp.php', null], ['settings.php', 'web'], ['sections.php', 'web'], ['notes.php', 'web']], 'route files load in the starter order, then notes');
    libraryCheck(array_map(fn (array $route): bool => is_file($route['file']), $all->routeFiles()) === array_fill(0, 6, true), 'every route file ships in the library');
    libraryCheck(array_map(fn (string $tool): string => class_basename($tool), $all->mcpTools()) === ['ReadSettings', 'UpdateSettings', 'ListNotes', 'ReadNote', 'WriteNote', 'DeleteNote', 'ReadDashboard'], 'MCP tools are the starter tools in order, then the notes tools, then the dashboard read tool the composition root adds');
    libraryCheck($all->navigationLinks() === [['ability' => 'dashboard.read', 'label' => 'Dashboard', 'suffix' => '', 'group' => 'workspace', 'order' => -10, 'active' => '', 'slot' => 'primary'], ['ability' => 'notes.read', 'label' => 'Notes', 'suffix' => 'notes', 'group' => 'workspace', 'order' => 10, 'active' => null, 'slot' => 'primary']], 'the nav is the Dashboard, then Notes: settings is a landing page, not a nav slot');
    libraryCheck(array_map(fn (array $section): string => $section['key'].'/'.$section['group'], $all->settingsSections('workspace')) === ['general/Workspace', 'people/Workspace'] && array_column($all->settingsSections('workspace'), 'icon') === ['sliders-horizontal', 'users'] && array_column($all->settingsSections('personal'), 'key') === ['profile', 'password', 'security', 'workspaces'], 'the settings sections register by scope, in order, in one Workspace group, with icons (the AI assistants page is hidden by default)');
    $sections = (new Registry)->settingsSection('workspace', 'b', 'B', 'b', null, 'G', 5)->settingsSection('workspace', 'a', 'A', 'a', null, 'G', 5)->settingsSection('workspace', 'c', 'C', 'c', 'x.read', 'H', 1)->settingsSection('personal', 'p', 'P', 'p');
    libraryCheck(array_column($sections->settingsSections('workspace'), 'key') === ['c', 'b', 'a'] && array_column($sections->settingsSections('personal'), 'key') === ['p'], 'sections sort by order, then registration, within their scope');
    libraryRefuses(fn () => (new Registry)->settingsSection('team', 'a', 'A', 'a'), 'Unknown settings scope: team', 'an unknown settings scope refuses');
    libraryRefuses(fn () => (new Registry)->settingsSection('workspace', 'Bad Key', 'A', 'a'), 'Invalid settings section key', 'a section key is a slug');
    libraryRefuses(fn () => (new Registry)->settingsSection('workspace', 'a', 'A', 'a')->settingsSection('workspace', 'a', 'A2', 'a'), 'Settings section registered twice', 'a section registers once per scope');
    $ordered = (new Registry)->navigation('b.read', 'B', 'b', order: 5)->navigation('a.read', 'A', 'a', slot: 'user-menu', group: 'account', active: 'a/*')->navigation('c.read', 'C', 'c', order: 5);
    libraryCheck(array_column($ordered->navigationLinks(), 'suffix') === ['a', 'b', 'c'] && $ordered->navigationLinks()[0]['active'] === 'a/*' && $ordered->navigationLinks()[0]['group'] === 'account', 'navigation sorts by order, then registration, and keeps its group and active pattern');
    $listed = (new Registry)->navigation('a.read', 'A', 'a', active: ['a/*', 'route:a.*'])->modulePage('a', 'a.read', 'P', 'a/p', active: ['a/p*', 'route:a.p.*']);
    libraryCheck($listed->navigationLinks()[0]['active'] === ['a/*', 'route:a.*'] && $listed->modulePages('a')[0]['active'] === ['a/p*', 'route:a.p.*'], 'navigation and module pages keep an active list');
    libraryRefuses(fn () => (new Registry)->navigation('a.read', 'A', 'a', active: []), 'non-empty list', 'an empty active list refuses');
    libraryRefuses(fn () => (new Registry)->modulePage('a', 'a.read', 'P', 'a/p', active: ['a/*', 'route:']), 'route:<name pattern>', 'a bare route: entry refuses');
    libraryRefuses(fn () => (new Registry)->navigation('a.read', 'A', 'a', slot: 'settings'), 'Unknown navigation slot: settings', 'an unknown navigation slot refuses');
    libraryCheck($all->tokenScopes() === ['mcp:use' => 'Act as you in the workspaces you approve, with what your role there allows', 'mcp:act' => 'Act as you in the workspaces you approve', 'settings.read' => 'Read workspace settings', 'settings.write' => 'Update workspace settings', 'dashboard.read' => 'Read the workspace dashboard', 'notes.read' => 'Read workspace notes', 'notes.write' => 'Create, change and delete your workspace notes'], 'token scopes are the starter scopes, then session-ui\'s dashboard.read, then notes\'');
    libraryCheck($all->workspaceCapabilities() === ['workspace.settings', 'workspace.dashboard', 'workspace.notes'], 'capabilities are the starter capabilities, then notes');
    libraryCheck($all->abilityMap() === ['settings.read' => 'workspace.settings', 'settings.write' => 'workspace.settings', 'dashboard.read' => 'workspace.dashboard', 'members.manage' => 'workspace.settings', 'notes.read' => 'workspace.notes', 'notes.write' => 'workspace.notes', 'notes.moderate' => 'workspace.notes'], 'each ability is gated by the capability Access used to hard-code');
    $statuses = ['workspace_not_found' => 404, 'workspace_mismatch' => 404, 'account_inactive' => 403, 'verification_required' => 403, 'admission_required' => 403, 'permission_denied' => 403, 'intent_conflict' => 409, 'unknown_arguments' => 422, 'capability_unavailable' => 404, 'entitlement_required' => 403, 'connection_denied' => 403, 'connection_intent_conflict' => 409, 'connection_revoked' => 409, 'connection_active' => 409, 'stale_settings' => 409];
    libraryCheck(array_filter($statuses, fn (int $status, string $code): bool => $all->status($code) !== $status, ARRAY_FILTER_USE_BOTH) === [] && $all->status('unregistered') === null, 'every starter error code keeps its status');
    libraryCheck(str_starts_with((string) $all->message('stale_settings'), 'These settings changed'), 'stale messages are kept');
    libraryCheck(count($all->provisioners()) === 1 && $all->errorPage() !== null, 'settings provisions and session-ui renders error pages');

    // Partial selections contribute only their own seams; optional integrations need both sides.
    $workspaces = registryFor(['core', 'workspaces']);
    libraryCheck($workspaces->mcpTools() === [] && $workspaces->navigationLinks() === [] && $workspaces->abilityMap() === [] && $workspaces->errorPage() === null && count($workspaces->routeFiles()) === 1, 'core and workspaces alone register one route file and no tools, abilities or pages');
    $settings = registryFor(['core', 'workspaces', 'access', 'settings']);
    libraryCheck($settings->mcpTools() === [] && $settings->capabilityFor('settings.write') === 'workspace.settings' && $settings->capabilityFor('connections.read') === null, 'settings without mcp registers no tools, and connection abilities stay unknown');
    $mcpOnly = registryFor(['core', 'workspaces', 'access', 'mcp']);
    libraryCheck($mcpOnly->mcpTools() === [], 'mcp without settings registers no tools');
    Container::getInstance()->instance(Registry::class, $mcpOnly);
    libraryCheck(WorkspaceConnection::grantableScopes() === [], 'a connection can be granted only adopted abilities (and acts-as-me only alongside them)');
    Container::getInstance()->instance(Registry::class, $all);
    libraryCheck(WorkspaceConnection::grantableScopes() === ['mcp:act', 'settings.read', 'settings.write', 'dashboard.read', 'notes.read', 'notes.write'] && WorkspaceConnection::baseAbility() === 'settings.read', 'with every feature the grantable scopes and base ability are the starter ones');

    // Route options come from kit.routes.<feature>.
    $configured = registryFor(['core', 'workspaces', 'access', 'settings', 'mcp'], ['routes' => ['mcp' => ['enabled' => false], 'settings' => ['prefix' => 'app', 'middleware' => ['verified'], 'name' => 'kit.']]]);
    $routes = array_map(fn (array $route): array => [basename($route['file']), $route['options']], $configured->routeFiles());
    libraryCheck($routes === [['workspaces.php', ['prefix' => '', 'middleware' => [], 'name' => '']], ['settings.php', ['prefix' => 'app', 'middleware' => ['verified'], 'name' => 'kit.']]], 'a disabled feature loads no routes, and prefix, middleware and name are passed through');
    libraryCheck(count($configured->settingsSections('workspace')) === 1 && $configured->capabilityFor('settings.read') === 'workspace.settings', 'disabling routes keeps the rest of the feature');

    // The registry fails closed.
    $empty = new Registry;
    libraryRefuses(fn () => $empty->guardConnection(new Principal(1, 2), (object) ['id' => 1], 'settings.read'), 'connection_denied', 'a delegated principal with no connection feature is refused');
    libraryCheck($empty->capabilityFor('settings.read') === null, 'an ability no feature declares has no capability');
    libraryRefuses(fn () => (new Registry)->abilities(['a.read' => 'x'])->abilities(['a.read' => 'y']), 'Conflicting capability', 'an ability cannot be gated by two capabilities');
    libraryRefuses(fn () => (new Registry)->connectionGuard(fn () => null)->connectionGuard(fn () => null), 'already registered', 'a second connection guard refuses');
    libraryRefuses(fn () => (new Registry)->errorPages(fn () => null)->errorPages(fn () => null), 'already registered', 'a second error page renderer refuses');

    // The views the mcp feature renders ship with the library.
    foreach (['authorize'] as $view) {
        libraryCheck(is_file(dirname(__DIR__).'/resources/views/mcp/'.$view.'.blade.php'), 'kit::mcp.'.$view.' ships');
    }
    // The brand: footer lines from config, and the placeholders still in place.
    $placeholders = Tey\Kit\Brand::placeholders();
    $defaults = (require dirname(__DIR__).'/config/kit.php')['brand'];
    Container::getInstance()->instance('config', new Repository(['app' => ['name' => 'Placeholder'], 'kit' => ['brand' => $defaults]]));
    libraryCheck(Tey\Kit\Brand::name() === 'Placeholder' && in_array('brand.accent', Tey\Kit\Brand::placeholders(), true) && in_array('brand.legal_name', Tey\Kit\Brand::placeholders(), true), 'the default brand is all placeholders');
    Container::getInstance()->instance('config', new Repository(['app' => ['name' => 'A Product'], 'kit' => ['brand' => [...$defaults, 'logomark' => '/mark.svg', 'accent' => '#0f766e', 'hello_email' => 'hello@a.test', 'support_email' => 'help@a.test', 'privacy_email' => 'privacy@a.test', 'mail_from_address' => 'updates@a.test', 'legal_name' => 'A Ltd', 'postal_address' => '1 Street', 'terms_url' => '/t', 'privacy_url' => '/p', 'credit_for' => 'A Client']]]));
    libraryCheck(Tey\Kit\Brand::placeholders() === [] && Tey\Kit\Brand::webFooter() === ['identity' => 'A Product', 'credit' => 'Built by Tey Labs, for A Client', 'links' => [['label' => 'Terms', 'url' => '/t'], ['label' => 'Privacy', 'url' => '/p'], ['label' => 'Privacy requests', 'url' => 'mailto:privacy@a.test']]], 'a configured brand has no placeholders, and the web footer is identity, credit and legal links');
    libraryCheck(! str_contains(json_encode(Tey\Kit\Brand::webFooter()), '©'), 'the footer has no ©');
    Container::getInstance()->instance('config', new Repository([]));
    foreach (['html/message', 'html/header', 'html/footer', 'html/button', 'text/message', 'text/footer'] as $view) {
        libraryCheck(is_file(dirname(__DIR__).'/resources/views/mail/'.$view.'.blade.php'), 'the branded mail view '.$view.' ships');
    }
    // Rule 21, cruddy controllers: only the seven resource actions (or one __invoke), and grouped controller routes, never flat tuples.
    foreach (glob(ROOT.'/*/Http/*Controller.php') as $file) {
        preg_match_all('/public function (\w+)\(/', (string) file_get_contents($file), $methods);
        libraryCheck(array_diff($methods[1], ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', '__invoke', '__construct', 'middleware']) === [], basename($file).' has only resource actions');
    }
    foreach (glob(ROOT.'/*/routes/*.php') as $file) {
        libraryCheck(! preg_match("/\\[\\s*[\\w\\\\]+::class\\s*,\\s*'\\w+'\\s*\\]/", (string) file_get_contents($file)), basename($file).' groups its controller routes (no flat tuples)');
    }
    foreach (['Access/Operations/ComplimentaryAccess', 'Mcp/Operations/WorkspaceConnection', 'Settings/Operations/WorkspaceProfile', 'Workspaces/Operations/AcceptWorkspaceInvitation', 'Workspaces/Operations/ProvisionWorkspace'] as $operation) {
        libraryCheck(str_contains((string) file_get_contents(ROOT.'/'.$operation.'.php'), 'use AsAction;'), $operation.' is a laravel-actions action');
    }
    // `artisan` runs with Collision's adapter bound as the exception handler, which has no `dontReportWhen`/`renderable`: booting must not call them.
    $adapterApp = new Illuminate\Foundation\Application(dirname(__DIR__));
    $adapterApp->instance(Illuminate\Contracts\Debug\ExceptionHandler::class, new class implements Illuminate\Contracts\Debug\ExceptionHandler
    {
        public function report(Throwable $e): void {}

        public function shouldReport(Throwable $e): bool
        {
            return false;
        }

        public function render($request, Throwable $e): Symfony\Component\HttpFoundation\Response
        {
            return new Symfony\Component\HttpFoundation\Response;
        }

        public function renderForConsole($output, Throwable $e): void {}
    });
    (new Tey\Kit\Features\Core\CoreProvider($adapterApp))->boot();
    libraryCheck(true, 'the core provider boots under a console exception handler that is not the framework\'s');
    // A bare class name in a TypeScript docblock resolves against global classes first, and laravel-actions aliases a global
    // `Action`, so generated types read `undefined` in a host that has it. The kit's docblocks name Action fully qualified.
    $bare = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        foreach (file($file->getPathname()) as $number => $line) {
            if (preg_match('/@(param|return|var)\b/', $line) && preg_match('/(?<![\\\\\w])Action\b/', $line)) {
                $bare[] = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1).':'.($number + 1);
            }
        }
    }
    libraryCheck($bare === [], 'docblocks name Action fully qualified, so generated types resolve beside a global Action alias'.($bare === [] ? '' : ': '.implode(', ', $bare)));
    require __DIR__.'/ControllerRedirectCheck.php';
    echo "Library and isolated controller/route checks only; no consumer app, database, middleware, browser or MCP transport.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
