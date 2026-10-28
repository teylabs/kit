<?php

// A ViewModel's props come in a stable declaration order, the same on every PHP version: ancestors first, a class's traits in `use` order, then its
// own methods by line, with an override keeping its parent's slot. The generated TypeScript follows the same order. No database.
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Tests\Fixtures\PropOrder\ChildPage;
use Tey\Kit\TypeScript\ViewModelTransformer;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
$loader->addPsr4('Tey\\Kit\\Tests\\Fixtures\\PropOrder\\', __DIR__.'/fixtures/prop-order', true);

function orderCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

try {
    $expected = ['title', 'subtitle', 'badge', 'zebra', 'alpha'];
    orderCheck(ViewModel::propMethods(ChildPage::class) === $expected, 'props are ancestors first, then the trait in use order, then the class by line; overrides keep their slot: '.implode(', ', ViewModel::propMethods(ChildPage::class)));
    orderCheck(array_keys((new ChildPage)->toArray()) === $expected && (new ChildPage)->toArray()['title'] === 'Badged' && (new ChildPage)->toArray()['subtitle'] === 'Child subtitle', 'toArray() follows the same order with the overriding values');

    $directory = sys_get_temp_dir().'/kit-prop-order-'.bin2hex(random_bytes(5));
    mkdir($directory);
    try {
        TypeScriptTransformer::create(TypeScriptTransformerConfigFactory::create()
            ->transformer(ViewModelTransformer::class, AttributedClassTransformer::class)
            ->transformDirectories(__DIR__.'/fixtures/prop-order')
            ->outputDirectory($directory)
            ->writer(new GlobalNamespaceWriter('types.d.ts'))
            ->withoutManifest())->execute();
        $types = (string) file_get_contents($directory.'/types.d.ts');
    } finally {
        exec('rm -rf '.escapeshellarg($directory));
    }
    preg_match_all('/^(?:readonly )?(\w+)\??:/m', $types, $matches);
    orderCheck($matches[1] === $expected, 'the generated TypeScript lists the props in the same order: '.implode(', ', $matches[1]));
    // CI runs PHP 8.4 and 8.5: both must generate these exact bytes.
    orderCheck(hash('sha256', $types) === '8fe66bafd88483f4710601a4bf8455686b41fa3234eaa021e030edb8c68df037', 'the generated TypeScript is byte-identical on every PHP version');

    echo "OK view model prop order\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.$error->getMessage()."\n".$error->getTraceAsString()."\n");
    exit(1);
}
