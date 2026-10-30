<?php

// The navigator item's PHP and TypeScript shapes cannot drift: the PHP WorkbenchItem serializes exactly the TS WorkbenchItem's fields (workbench.ts.stub), the
// generated types/kit.d.ts types `actions` as the kit's Action list, the WorkbenchReadTool gives the assistant exactly the documented item keys (row-menu
// `actions` are UI affordances and left out: no MCP tool exposes actions), and an item with Move up / Duplicate / Delete reaches the Inertia props with every
// ActionDescriptor key. An item's `thumbnail` ({ src, alt }, WorkbenchThumbnail) is typed the same on both sides, reaches the Inertia props, and is left out of
// the MCP read tool as well (a picture of the row, not content). Synthetic items, no database, no network.
use Illuminate\Config\Repository;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\NavigationHeader;
use Tey\Kit\Features\Core\Data\PageLayout;
use Tey\Kit\Features\Core\Data\WorkbenchItem;
use Tey\Kit\Features\Core\Data\WorkbenchLayout;
use Tey\Kit\Features\Core\Data\WorkbenchThumbnail;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\ViewModels\PageLayouts;
use Tey\Kit\Features\Core\ViewModels\WorkbenchViewModel;
use Tey\Kit\Features\Mcp\Capability;
use Tey\Kit\Features\Mcp\WorkbenchReadTool;

$loader = require $argv[1];
$root = dirname(__DIR__);
$loader->addPsr4('Tey\\Kit\\', $root.'/src', true);

function check(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** @return list<string> the field names of a one-line TS object type `export type Name = { a: …; b?: … };` */
function tsFields(string $source, string $name): array
{
    if (! preg_match('/export type '.$name.' = \{(.*?)\};/s', $source, $match)) {
        throw new RuntimeException("no `export type {$name}` found");
    }
    // Drop nested object types ({ title; … }) so only the top-level keys remain.
    $body = $match[1];
    while (preg_match('/\{[^{}]*\}/', $body)) {
        $body = (string) preg_replace('/\{[^{}]*\}/', 'X', $body);
    }
    preg_match_all('/(?:^|[;,])\s*(\w+)\??\s*:/', $body, $fields);

    return $fields[1];
}

/** @return array<string, string> the generated kit.d.ts type's top-level field => type */
function generatedFields(string $source, string $name): array
{
    if (! preg_match('/^export type '.$name.' = \{\n(.*?)^\};/ms', $source, $match)) {
        throw new RuntimeException("types/kit.d.ts has no generated {$name}");
    }
    $fields = [];
    $depth = 0;
    foreach (explode("\n", rtrim($match[1])) as $line) {
        if ($depth === 0 && preg_match('/^(\w+)\??: (.*?),?$/', $line, $field)) {
            $fields[$field[1]] = $field[2];
        }
        $depth += substr_count($line, '{') - substr_count($line, '}');
    }

    return $fields;
}

/** A deck editor whose slides carry row menus, as a host decides them. */
final class SlidesWorkbench extends WorkbenchViewModel
{
    public function title(): string
    {
        return 'Deck';
    }

    public function workbenchKey(): string
    {
        return 'deck-editor';
    }

    public function items(): array
    {
        return [slide()];
    }

    /** The remembered layout is WorkbenchCheck's: none here. */
    public function workbenchLayout(): WorkbenchLayout
    {
        return new WorkbenchLayout(null, null);
    }
}

function slide(): WorkbenchItem
{
    return new WorkbenchItem('s1', 'Title', 'title', 'Opening slide', [
        new Action('Move up', '/slides/1/move', 'post', disabled: true, reason: 'Already first', key: 'move-up'),
        new Action('Duplicate', '/slides/1/duplicate', 'post', key: 'duplicate'),
        new Action('Delete', '/slides/1', 'delete', confirm: ['title' => 'Delete this slide?', 'description' => 'It is removed from the deck.', 'label' => 'Delete'], key: 'delete', destructive: true),
    ], new WorkbenchThumbnail('/decks/7/slides/1.png', ''));
}

#[Capability('workspace.decks')]
final class ReadSlides extends WorkbenchReadTool
{
    public const ABILITY = 'decks.read';

    protected string $description = 'Read a deck.';

    protected function read(Principal $principal, int $workspace, int $subject): array
    {
        return ['items' => [slide(), ['id' => 's2', 'label' => 'Revenue', 'actions' => [['label' => 'Delete', 'url' => '/slides/2', 'method' => 'delete']], 'thumbnail' => ['src' => '/decks/7/slides/2.png', 'alt' => 'Bar chart']]]];
    }

    public function run(): array
    {
        return $this->call(new Principal(1), 1, ['id' => 7]);
    }
}

try {
    $base = sys_get_temp_dir().'/kit-wb-parity-'.bin2hex(random_bytes(4));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));
    mkdir($base.'/bootstrap/cache', 0777, true);
    $app = new Application($base);
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Kit', 'url' => 'https://kit.test', 'env' => 'testing', 'debug' => false],
        'kit' => ['features' => ['core']],
    ]));
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    foreach ([EventServiceProvider::class, LaravelDataServiceProvider::class] as $provider) {
        $app->register($provider);
    }
    $app->instance('request', Request::create('https://kit.test'));
    // The page shell is not under test: a bare layout.
    $app->instance(PageLayouts::class, new class implements PageLayouts
    {
        public function layout(string $title, ?string $section, ?NavigationHeader $navigationHeader = null): PageLayout
        {
            return new PageLayout([], $title, $title, []);
        }
    });
    $app->boot();

    $tsItem = tsFields((string) file_get_contents($root.'/resources/features/core/components/Kit/Workbench/workbench.ts.stub'), 'WorkbenchItem');
    $tsThumbnail = tsFields((string) file_get_contents($root.'/resources/features/core/components/Kit/Workbench/workbench.ts.stub'), 'WorkbenchThumbnail');
    sort($tsThumbnail);
    $descriptor = tsFields((string) file_get_contents($root.'/resources/features/core/components/Kit/Action/actionPlacement.ts.stub'), 'ActionDescriptor');
    $declared = (string) file_get_contents($root.'/types/kit.d.ts');
    sort($descriptor);

    // 1. PHP serializes exactly the TS item's fields.
    $phpKeys = array_keys(slide()->toArray());
    $sortedPhp = $phpKeys;
    $sortedTs = $tsItem;
    sort($sortedPhp);
    sort($sortedTs);
    check($sortedPhp === $sortedTs && in_array('actions', $phpKeys, true), 'the PHP WorkbenchItem serializes exactly the TS WorkbenchItem fields: '.implode(', ', $tsItem));
    check(array_keys((new WorkbenchItem('s1', 'Title'))->toArray()) === $phpKeys && (new WorkbenchItem('s1', 'Title'))->toArray()['actions'] === [], 'an item built without actions still sends every field, with an empty actions list (no menu)');
    check(in_array('thumbnail', $phpKeys, true) && (new WorkbenchItem('s1', 'Title'))->toArray()['thumbnail'] === null, 'an item built without a thumbnail sends thumbnail: null');
    $phpThumbnail = array_keys((new WorkbenchThumbnail('/t.png', ''))->toArray());
    sort($phpThumbnail);
    check($tsThumbnail === ['alt', 'src'] && $phpThumbnail === $tsThumbnail, 'the PHP WorkbenchThumbnail serializes exactly the TS WorkbenchThumbnail fields: alt, src');

    // 2. The generated type carries actions as the kit's Action, whose fields are ActionDescriptor's.
    $generated = generatedFields($declared, 'WorkbenchItem');
    $generatedKeys = array_keys($generated);
    sort($generatedKeys);
    check($generatedKeys === $sortedTs, 'types/kit.d.ts WorkbenchItem has the same fields as the TS WorkbenchItem');
    check(($generated['actions'] ?? null) === 'Tey.Kit.Features.Core.Data.Action[]', 'types/kit.d.ts types WorkbenchItem.actions as Tey.Kit.Features.Core.Data.Action[]');
    check(($generated['thumbnail'] ?? null) === 'Tey.Kit.Features.Core.Data.WorkbenchThumbnail | null', 'types/kit.d.ts types WorkbenchItem.thumbnail as Tey.Kit.Features.Core.Data.WorkbenchThumbnail | null');
    check(generatedFields($declared, 'WorkbenchThumbnail') === ['src' => 'string', 'alt' => 'string'], 'the generated WorkbenchThumbnail is { src: string, alt: string }: alt is required (empty for a decorative image)');
    $action = array_keys(generatedFields($declared, 'Action'));
    sort($action);
    check($action === $descriptor, 'the generated Action has exactly the ActionDescriptor fields, so an Action[] is an ActionDescriptor[]');

    // 3. The MCP read tool: the documented item keys only; row-menu actions are left out.
    $read = (new ReadSlides)->run();
    check(array_map('array_keys', $read['items']) === [['id', 'label', 'type', 'description'], ['id', 'label', 'type', 'description']], 'the MCP read tool gives each item exactly id, label, type and description: actions and thumbnails are UI affordances and left out');
    check($read['items'][0] === ['id' => 's1', 'label' => 'Title', 'type' => 'title', 'description' => 'Opening slide'], 'and keeps the item content unchanged');

    // 4. Round trip to the Inertia prop: the JSON the page receives.
    $props = json_decode((string) json_encode((new SlidesWorkbench)->toArray()), true, 512, JSON_THROW_ON_ERROR);
    $item = $props['items'][0];
    $itemKeys = array_keys($item);
    sort($itemKeys);
    check($itemKeys === $sortedTs, 'the Inertia prop item has the TS WorkbenchItem fields');
    check(array_column($item['actions'], 'key') === ['move-up', 'duplicate', 'delete'] && array_column($item['actions'], 'label') === ['Move up', 'Duplicate', 'Delete'], 'the row menu reaches the page in the host\'s order: Move up, Duplicate, Delete');
    foreach ($item['actions'] as $sent) {
        $keys = array_keys($sent);
        sort($keys);
        check($keys === $descriptor, 'action '.$sent['key'].' has exactly the ActionDescriptor keys');
    }
    check($item['thumbnail'] === ['src' => '/decks/7/slides/1.png', 'alt' => ''], 'the thumbnail reaches the page as { src, alt }, an empty alt kept as an empty string (not dropped or nulled)');
    $bare = json_decode((string) json_encode((new WorkbenchItem('s9', 'Plain'))->toArray()), true, 512, JSON_THROW_ON_ERROR);
    check(array_key_exists('thumbnail', $bare) && $bare['thumbnail'] === null, 'an item without a thumbnail reaches the page with thumbnail: null');
    [$up, , $delete] = $item['actions'];
    check($up['disabled'] === true && $up['reason'] === 'Already first' && $up['method'] === 'post', 'Move up arrives disabled with its reason');
    check($delete['method'] === 'delete' && $delete['destructive'] === true && $delete['confirm'] === ['title' => 'Delete this slide?', 'description' => 'It is removed from the deck.', 'label' => 'Delete'], 'Delete arrives destructive with its confirm');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
