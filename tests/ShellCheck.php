<?php

// The shell feature: its core-only boundary, the ShellData contract and its aliases, the SubNav's server selection, and the stub contracts of the bar slot, SubNav and GuestShell.
use Illuminate\Support\Str;
use Tey\Kit\Blueprint;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\Shell\Data\ShellData;
use Tey\Kit\Features\Shell\Data\ShellKind;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

const STUBS = __DIR__.'/../resources/features/shell/pages/Workspace';

function shellCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
function stub(string $path): string
{
    return file_get_contents(STUBS.'/'.$path.'.vue.stub');
}
/** A stub's code with its comments removed, for checks about what the code uses rather than what its comments say. */
function code(string $source): string
{
    return preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m', '#<!--.*?-->#s'], '', $source);
}

try {
    // The boundary: shell needs core only, and a core+shell host gets the shell and core's files and nothing of the session.
    $manifest = json_decode(file_get_contents(__DIR__.'/../resources/features/shell/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    shellCheck($manifest['requires']['features'] === ['core'] && array_keys($manifest['requires']['packages']) === ['inertiajs/inertia-laravel', 'spatie/laravel-data'], 'shell requires core and no session, workspace or access package');
    $core = Blueprint::features(['core'])->snapshot();
    $shell = Blueprint::features(['core', 'shell'])->snapshot();
    $added = array_keys(array_diff_key($shell['files'], $core['files']));
    shellCheck($added !== [] && array_filter($added, fn (string $path): bool => ! str_starts_with($path, 'resources/js/pages/Workspace/Layout.vue') && ! str_starts_with($path, 'resources/js/pages/Workspace/Partials/') && $path !== 'resources/js/types/kit-shell.d.ts') === [], 'core+shell adds only the shell\'s layout, partials and type aliases ('.count($added).' files)');
    $requirements = implode(' ', array_keys($shell['manifest']['requirements']));
    shellCheck(! preg_match('/concierge|pennant|fortify|multitenancy|permission/', $requirements), 'core+shell needs no Concierge, Pennant, Fortify, multitenancy or permission package');
    $session = json_decode(file_get_contents(__DIR__.'/../resources/features/session-ui/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    shellCheck(in_array('shell', $session['requires']['features'], true) && array_filter($session['owns'], fn (string $owned): bool => str_starts_with($owned, 'resources/js/pages/Workspace/Layout.vue') || str_starts_with($owned, 'resources/js/pages/Workspace/Partials')) === [], 'session-ui requires shell and owns none of its files');
    shellCheck(Blueprint::features(['core', 'workspaces', 'access', 'shell', 'session-ui'])->selection() === ['core', 'workspaces', 'access', 'shell', 'session-ui'], 'session-ui composes with shell');
    foreach ($shell['files'] as $path => $bytes) {
        if (str_starts_with($path, 'resources/js/pages/Workspace/')) {
            shellCheck(! str_contains($bytes, 'SessionUi') && ! preg_match('/[\'"]\/(login|logout|me)\b|route\(/', $bytes), $path.' names no session-ui type and no auth route');
        }
    }

    // ShellData: the shell's contract, the SubNav descriptor optional, the old names aliases.
    $data = new ShellData(['label' => 'Your account', 'url' => '/me', 'current' => false], ['name' => 'P', 'url' => '/', 'logomark' => null], null, null, [], null, ['home' => '/', 'switcher' => '/', 'sign_in' => '/login', 'sign_out' => null], ['identity' => 'P', 'credit' => '', 'links' => []]);
    $last = array_slice((new ReflectionMethod(ShellData::class, '__construct'))->getParameters(), -1)[0];
    shellCheck($data->subNavigation === null && $data->kind === ShellKind::App && $last->getName() === 'subNavigation' && $last->allowsNull() && $last->getDefaultValue() === null && $last->isPromoted(), 'ShellData ends with a promoted, nullable subNavigation that defaults to null, so existing callers are unchanged');
    shellCheck(\Tey\Kit\Features\SessionUi\Data\ShellData::class !== ShellData::class && (new ReflectionClass(\Tey\Kit\Features\SessionUi\Data\ShellData::class))->getName() === ShellData::class && \Tey\Kit\Features\SessionUi\Data\ShellKind::App === ShellKind::App, 'the session-ui names alias the shell\'s ShellData and ShellKind');
    $aliases = file_get_contents(__DIR__.'/../resources/features/shell/types/kit-shell.d.ts.stub');
    shellCheck(str_contains($aliases, 'declare namespace Tey.Kit.Features.SessionUi.Data') && str_contains($aliases, 'export type ShellData = Tey.Kit.Features.Shell.Data.ShellData;') && str_contains($aliases, 'export type ShellKind = Tey.Kit.Features.Shell.Data.ShellKind;') && ! preg_match('/^\s*(import|export)\s/m', str_replace(['    export type'], '', $aliases)), 'the type aliases are a global declaration a host\'s generated types merge with');

    // The SubNav's server selection: the active module, its allowed pages in order, one current or none.
    $registry = (new Registry)
        ->modulePage('orders', 'orders.read', 'Invoices', 'orders/invoices', order: 5)
        ->modulePage('orders', 'orders.read', 'Overview', 'orders', order: 0, active: 'orders')
        ->modulePage('orders', 'orders.write', 'Open orders', 'orders/open', order: 1)
        ->modulePage('orders', 'orders.read', 'Returns', 'orders/returns', order: 1)
        ->modulePage('customers', 'customers.read', 'All customers', 'customers');
    $primary = [['label' => 'Dashboard', 'suffix' => '', 'current' => false], ['label' => 'Orders', 'suffix' => 'orders', 'current' => true], ['label' => 'Customers', 'suffix' => 'customers', 'current' => false]];
    $may = fn (string $ability): bool => $ability !== 'orders.write';
    $url = fn (array $page): string => '/@acme/'.$page['suffix'];
    $at = fn (string $path): Closure => fn (array $page): bool => Str::is($page['active'] !== null ? [$page['active']] : [$page['suffix'], $page['suffix'].'/*'], $path);
    $nav = PageShell::moduleNavigation($registry, $primary, $may, $url, $at('orders/invoices'));
    shellCheck($nav !== null && $nav['label'] === 'Orders' && array_column($nav['items'], 'label') === ['Overview', 'Returns', 'Invoices'], 'the active module\'s pages, in order then registration, without the forbidden one or another module\'s');
    shellCheck(array_column($nav['items'], 'current') === [false, false, true] && $nav['items'][2]['url'] === '/@acme/orders/invoices', 'the page the path is on is the one current page, with its server URL');
    $nested = (new Registry)->modulePage('orders', 'orders.read', 'Overview', 'orders')->modulePage('orders', 'orders.read', 'Invoices', 'orders/invoices');
    shellCheck(array_column(PageShell::moduleNavigation($nested, $primary, $may, $url, $at('orders/invoices/7'))['items'], 'current') === [false, true], 'when several pages match the path, only the most specific is current');
    shellCheck(array_column(PageShell::moduleNavigation($registry, $primary, $may, $url, $at('orders-report'))['items'], 'current') === [false, false, false], 'a current module with no matching page marks none current, never the first');
    $offModule = [['label' => 'Orders', 'suffix' => 'orders', 'current' => false], ['label' => 'Customers', 'suffix' => 'customers', 'current' => false]];
    shellCheck(PageShell::moduleNavigation($registry, $offModule, $may, $url, $at('orders/returns'))['label'] === 'Orders', 'a current module page makes its module active even when the module link is not current');
    shellCheck(PageShell::moduleNavigation($registry, $offModule, $may, $url, $at('reports')) === null, 'no active module, no SubNav');
    shellCheck(PageShell::moduleNavigation($registry, $primary, fn (): bool => false, $url, $at('orders')) === null, 'a module whose pages are all forbidden has no SubNav');
    shellCheck(PageShell::moduleNavigation(new Registry, $primary, $may, $url, $at('orders')) === null, 'a small app with no module pages has no SubNav');

    // The bar slot: once, in the bar, never in the drawer; no Teleport.
    $layout = stub('Layout');
    shellCheck(substr_count($layout, '<slot name="bar" />') === 1 && ! str_contains(stub('Partials/ShellDrawer'), 'name="bar"') && ! str_contains($layout, 'Teleport'), 'the bar slot renders once, never in the drawer, with no Teleport');
    shellCheck(strpos($layout, '<slot name="bar" />') > strpos($layout, '<header') && strpos($layout, '<slot name="bar" />') < strpos($layout, '</header>') && ! preg_match('/lg:hidden[^>]*>\s*<slot name="bar"|hidden[^>]*lg:flex[^>]*>\s*<slot name="bar"/', $layout), 'the bar slot sits in the header at every width');
    shellCheck(substr_count($layout, '<slot name="actions" />') === 2 && substr_count($layout, '<slot name="extra" />') === 2, 'actions and extra keep their header and drawer places');

    // SubNav: ordinary links, the server's current state, no tab semantics; NavigationHeader is untouched beside it.
    $subnav = stub('Partials/SubNav');
    shellCheck(str_contains($subnav, '<nav v-if="props.items.length" :aria-label="props.label"') && str_contains($subnav, ':aria-current="item.current ? \'page\' : undefined"') && ! preg_match('/role="tab|aria-selected|aria-pressed|@keydown/', $subnav), 'SubNav is a labelled nav of links with aria-current, no tab roles or arrow keys');
    shellCheck(str_contains($subnav, 'border-b-2') && str_contains($subnav, 'min-h-11') && str_contains($subnav, 'overflow-x-auto') && str_contains($subnav, "prefers-reduced-motion: reduce"), 'SubNav marks the current page with a border, keeps 44px targets, scrolls sideways and honours reduced motion');
    shellCheck(str_contains($subnav, 'list.scrollTo(') && ! str_contains($subnav, 'scrollIntoView') && ! preg_match('/\.focus\(/', $subnav), 'revealing a link scrolls the strip only, never the page, and never moves focus');
    shellCheck(substr_count($layout, '<SubNav ') === 1 && str_contains($layout, 'props.shell.subNavigation?.items.length') && str_contains($layout, 'data-navigation-header'), 'the Layout renders SubNav from ShellData only, beside the NavigationHeader');

    // The replaceable parts: the Layout takes UserMenu and WorkspaceSwitcher only through `@kit/shell`, whose default barrel exports the kit's own.
    shellCheck(str_contains($layout, "import { UserMenu, WorkspaceSwitcher } from '@kit/shell';") && ! preg_match("#from '\./Partials/(UserMenu|WorkspaceSwitcher)(\.vue)?'#", $layout), 'the Layout imports UserMenu and WorkspaceSwitcher through @kit/shell, never by relative path');
    $barrel = file_get_contents(STUBS.'/Partials/index.ts.stub');
    shellCheck(str_contains($barrel, "export { default as UserMenu } from './UserMenu.vue';") && str_contains($barrel, "export { default as WorkspaceSwitcher } from './WorkspaceSwitcher.vue';") && isset(Blueprint::lineage('shell')['files']['resources/js/pages/Workspace/Partials/index.ts']), 'shell generates the @kit/shell barrel with the kit defaults');

    // The move: session-ui lists every path it gave shell, so an installed session-ui hands them over on update.
    $moved = array_column(Blueprint::lineage('session-ui')['feature']['moved'] ?? [], 'to', 'path');
    shellCheck($moved !== [] && array_unique($moved) === [array_key_first($moved) => 'shell'] && array_diff_key($moved, Blueprint::lineage('shell')['files']) === [] && array_intersect_key($moved, Blueprint::lineage('session-ui')['files']) === [], 'session-ui moves its old shell files to shell, which generates each of them ('.count($moved).')');

    // GuestShell: core-only, the lockup shared with the bar, a labelled way back, the appearance switch.
    $guest = stub('Partials/GuestShell');
    shellCheck(! str_contains(code($guest), 'ShellData') && ! preg_match('/\\buser\\b/', code($guest)) && str_contains($guest, "import Wordmark from './Wordmark.vue';") && str_contains($layout, "import Wordmark from './Partials/Wordmark.vue';"), 'GuestShell needs no ShellData or user and shares the bar\'s Wordmark');
    shellCheck(str_contains($guest, "title?: string; description?: string; width?: 'narrow' | 'wide'; backUrl?: string | null; backLabel?: string;") && str_contains($guest, '<slot />') && str_contains($guest, '<slot name="footer" />') && str_contains($guest, '<slot name="mark" />'), 'GuestShell takes title, description, width, backUrl and backLabel, with default, footer and mark slots');
    shellCheck(str_contains($guest, 'data-guest-back') && str_contains($guest, '{{ props.backLabel }}') && str_contains($guest, '<AppearanceToggle') && str_contains($guest, 'href="#main"') && ! preg_match('/tabindex="[1-9]/', $guest), 'GuestShell has a labelled way back, the appearance switch, a skip link and no positive tabindex');
    echo "Shell checks only: the core+shell host build, render and keyboard behaviour run in a throwaway headless harness (see the PR), not in CI; no consumer acceptance.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
