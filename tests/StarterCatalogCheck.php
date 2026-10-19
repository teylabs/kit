<?php

// The starter catalog in resources/features: manifests, pins, the requires graph, stubs, ownership, kit:list and migration loading.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Blueprint;
use Tey\Kit\Catalog;
use Tey\Kit\Features\FeaturesServiceProvider;
use Tey\Kit\KitServiceProvider;
use Tey\Mod\ModServiceProvider;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

/** Kept in step with Catalog::FEATURES; a changed manifest or stub must be repinned in both places on purpose. */
const PINS = [
    'core' => 'sha256:99c9e51b709a11508bfe639006883a6b6acf9bcecd10f647c3ec355a99c3e44a',
    'workspaces' => 'sha256:f34dd80040550d45b27b534652c5171f57e63dbb547bca0d93c012d71954fb23',
    'access' => 'sha256:f157033cd158eb0ee0ab945aa1f8b18041b845afbec55f520efb8a96e931b698',
    'settings' => 'sha256:3ff709b98359375794499e9eba9a7a73d8b8089281215d4bebd2ad71a9d93b1f',
    'session-ui' => 'sha256:c72675da6eb21eb4eca0cb9a251931e356d53d348627d96a0d914e6b330f9f89',
    'mcp' => 'sha256:40640b990cb23857b1076916c310615d03faf09e927e9a34c17fc431b5f6f7e2',
    'notes' => 'sha256:804cb514b4a4911892b5c6b8cbc4580b57fe7b734451e53d21d88ba05c9783b3',
];
const BASE = 'sha256:32b571f7cdd2b12d698d04a76a847a5561d96a60f051129dce52832112995fa7';
/** Generated files live only here: published config, per-feature migrations and Vue starting points. Routes ship in the library. */
const GENERATED = ['config/', 'database/migrations/kit/', 'resources/js/starter/'];

function starterCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}
/** @return list<string> */
function closureOf(string $id, array $seen = []): array
{
    foreach (FeaturesServiceProvider::FEATURES[$id][1] as $required) {
        if (! in_array($required, $seen, true)) {
            $seen[] = $required;
            $seen = closureOf($required, $seen);
        }
    }

    return $seen;
}
function within(string $path, string $prefix): bool
{
    return $path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/');
}

try {
    // Validation and pins.
    $catalog = Catalog::package();
    starterCheck($catalog->pins === PINS && $catalog->basePin === BASE, 'the package pins are the reviewed starter pins');
    $root = dirname(__DIR__).'/resources/features';
    starterCheck(Catalog::at($root, PINS, BASE)->featureIds() === array_keys(FeaturesServiceProvider::FEATURES), 'the catalog lists the starter features in canonical order');
    $all = Blueprint::all();
    starterCheck($all->selection() === array_keys(PINS) && $all->manifest['id'] === 'tey/kit', 'the whole catalog validates and every pin and the base pin verify');
    foreach (array_keys(PINS) as $id) {
        $selection = [$id, ...closureOf($id)];
        starterCheck(Blueprint::features($selection)->selection() === array_values(array_intersect(array_keys(PINS), $selection)), $id.' validates with exactly its requirement closure');
    }
    foreach (array_keys(PINS) as $id) {
        $wrong = PINS;
        $wrong[$id] = 'sha256:'.str_repeat('0', 64);
        try {
            Blueprint::features([$id, ...closureOf($id)], Catalog::at($root, $wrong, BASE));
            throw new RuntimeException('A wrong pin was accepted: '.$id);
        } catch (RuntimeException $error) {
            starterCheck(str_contains($error->getMessage(), 'identity mismatch'), 'a wrong '.$id.' pin refuses');
        }
    }

    // The requires closure is the library graph, which LibraryCheck ties to the starter's arch test.
    foreach (Blueprint::entries() as $entry) {
        $declared = $entry['requires'];
        sort($declared);
        $graph = FeaturesServiceProvider::FEATURES[$entry['id']][1];
        sort($graph);
        starterCheck($declared === $graph, $entry['id'].' requires what the library graph requires');
    }

    // Ownership.
    $manifests = [];
    foreach (array_keys(PINS) as $id) {
        $manifests[$id] = json_decode(file_get_contents($root.'/'.$id.'/feature.json'), true, flags: JSON_THROW_ON_ERROR);
    }
    $hostOwned = array_merge(...array_column($manifests, 'host_owned'));
    $overlaps = [];
    foreach ($manifests as $id => $manifest) {
        foreach ($manifest['owns'] as $owned) {
            foreach ($hostOwned as $host) {
                if (within($owned, $host) || within($host, $owned)) {
                    $overlaps[] = $id.' owns '.$owned.' against host-owned '.$host;
                }
            }
        }
    }
    starterCheck($overlaps === [], 'no owned path overlaps a host-owned path in any feature');
    starterCheck(in_array('app/Models/User.php', $manifests['core']['host_owned'], true) && in_array('bootstrap', $manifests['core']['host_owned'], true) && in_array('resources/js/app.ts', $manifests['session-ui']['host_owned'], true), 'core declares the common host-owned set and session-ui the app entry');
    $files = $all->manifest['generated'];
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => ! array_filter(GENERATED, fn (string $prefix): bool => str_starts_with($path, $prefix))) === [], 'every generated file is config, a kit migration or a Vue starting point');
    starterCheck(array_filter(array_keys($files), fn (string $path): bool => str_starts_with($path, 'routes/') || str_starts_with($path, 'app/')) === [], 'no route file or app class is generated');

    // Stubs.
    $snapshot = $all->snapshot()['files'];
    starterCheck(count($snapshot) === count($files), 'the whole catalog generates '.count($files).' files');
    $temp = sys_get_temp_dir().'/kit-starter-catalog-'.bin2hex(random_bytes(6));
    foreach ($snapshot as $path => $bytes) {
        @mkdir(dirname($temp.'/'.$path), 0700, true);
        file_put_contents($temp.'/'.$path, $bytes);
    }
    foreach ($snapshot as $path => $bytes) {
        if (str_ends_with($path, '.php')) {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp.'/'.$path).' 2>&1', $output, $status);
            starterCheck($status === 0, $path.' compiles');
            starterCheck(! str_contains($bytes, 'App\\Starter\\') && ! str_contains($bytes, 'LegacyDomainMigration'), $path.' names no starter-app class');
        }
    }
    foreach ($snapshot as $path => $bytes) {
        if (! str_ends_with($path, '.vue')) {
            continue;
        }
        starterCheck(substr_count($bytes, '<template>') === 1 && substr_count($bytes, '</template>') >= 1 && substr_count($bytes, '<script setup lang="ts">') === 1 && substr_count($bytes, '</script>') === 1, $path.' has one template and one script setup block');
        preg_match_all("/(?:import|from)\\s*['\"](\\.{1,2}\\/[^'\"]+)['\"]/", $bytes, $imports);
        foreach ($imports[1] as $import) {
            $target = realpath(dirname($temp.'/'.$path).'/'.$import);
            starterCheck($target !== false && str_starts_with($target, realpath($temp).'/resources/js/starter/'), $path.' import '.$import.' resolves inside the generated pages');
        }
    }
    echo "SKIP Vue compile: the kit has no vue-tsc or @vue/compiler-sfc; blocks and relative imports are checked instead.\n";

    // kit:list prints the starter catalog.
    foreach (['bootstrap/cache', 'storage/framework', 'storage/logs'] as $dir) {
        mkdir($temp.'/'.$dir, 0700, true);
    }
    file_put_contents($temp.'/composer.json', json_encode(['name' => 'example/consumer', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $app = Application::configure($temp)->withExceptions(fn ($e) => null)->withProviders([ModServiceProvider::class, KitServiceProvider::class])->create();
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    starterCheck($kernel->call('kit:list', ['--json' => true]) === 0 && json_decode(trim($kernel->output()), true, flags: JSON_THROW_ON_ERROR) === Blueprint::entries(), 'kit:list --json prints the starter catalog');
    $kernel->call('kit:list');
    $lines = explode("\n", trim($kernel->output()));
    starterCheck($lines[0] === 'core' && $lines[1] === '  requires: -' && in_array('notes', $lines, true) && in_array('  requires: workspaces, access', $lines, true) && ! in_array('foundation', $lines, true), 'kit:list prints each starter feature with what it requires, and no v0.1 fixture');

    // Adopted features' migrations load from database/migrations/kit/<feature> with no app configuration.
    @mkdir($temp.'/database/migrations', 0700, true);
    file_put_contents($temp.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration { public function up(): void { Schema::create('users', function (Blueprint \$t): void { \$t->id(); \$t->string('name'); \$t->string('email'); }); } };\n");
    $selected = ['core', 'workspaces', 'access', 'settings', 'mcp', 'notes'];
    $migrating = Application::configure($temp)->withExceptions(fn ($e) => null)->create();
    $migrating->booting(function () use ($migrating, $selected): void {
        $migrating['config']->set('database.default', 'testing');
        $migrating['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $migrating['config']->set('kit.features', $selected);
    });
    $kernel = $migrating->make(Kernel::class);
    $kernel->bootstrap();
    // Only the migration loading is under test, so the provider boots without registering the features' providers.
    (new FeaturesServiceProvider($migrating))->boot();
    $paths = $migrating['migrator']->paths();
    starterCheck(array_map(fn (string $path): string => substr($path, strlen($temp) + 1), $paths) === array_map(fn (string $id): string => 'database/migrations/kit/'.$id, $selected), 'each adopted feature registers its kit migration directory, in canonical order');
    starterCheck($kernel->call('migrate', ['--force' => true]) === 0, 'migrate runs the host and kit migrations on synthetic SQLite memory');
    $tables = ['users', 'workspaces', 'operators', 'audit_events', 'entitlements', 'complimentary_grants', 'settings', 'settings_intents', 'connections', 'notes', 'note_intents', 'roles', 'permissions'];
    starterCheck(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)) === [], 'every kit table exists after migrate');
    $ran = $migrating['db']->table('migrations')->pluck('migration')->all();
    starterCheck(in_array('0001_01_01_000000_create_starter_identity_tables', $ran, true) && in_array('create_starter_notes_tables', $ran, true), 'kit migrations keep the starter migration ids');
    // With kit.features null, the adopted features come from the installed record; a malformed record refuses.
    $record = sys_get_temp_dir().'/kit-installed-'.bin2hex(random_bytes(6));
    mkdir($record.'/.mod-maintenance', 0700, true);
    starterCheck(FeaturesServiceProvider::installed($record) === null, 'no installed record adopts nothing');
    file_put_contents($record.'/.mod-maintenance/installed.json', json_encode(['artifact' => ['metadata' => ['blueprint_features' => ['core', 'workspaces']]]]));
    starterCheck(FeaturesServiceProvider::installed($record) === ['core', 'workspaces'], 'the installed record lists the adopted features');
    file_put_contents($record.'/.mod-maintenance/installed.json', json_encode(['artifact' => []]));
    try {
        FeaturesServiceProvider::installed($record);
        throw new RuntimeException('A record without features was accepted');
    } catch (RuntimeException $error) {
        starterCheck(str_contains($error->getMessage(), 'does not list its features'), 'a record without its features refuses');
    }
    echo "Catalog checks only; nothing is applied to an app (kit #2/#4), and no package provider is booted.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
