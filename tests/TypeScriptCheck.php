<?php

// The Explanation TypeScript mapping: a page view model's Explanation props are typed as the kit's own `@kit/ui` types, not `undefined`.
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Tey\Kit\TypeScript\ExplanationTypesExtension;
use Tey\Kit\TypeScript\ViewModelTransformer;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
$loader->addPsr4('Tey\\Kit\\Tests\\Fixtures\\TypeScript\\', __DIR__.'/fixtures/typescript', true);

function typeScriptCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

/** Generates the types for the fixture view model, with or without the kit's extension. */
function generated(bool $withExtension): string
{
    $directory = sys_get_temp_dir().'/kit-typescript-'.bin2hex(random_bytes(5));
    mkdir($directory);
    try {
        $config = TypeScriptTransformerConfigFactory::create()
            ->transformer(ViewModelTransformer::class, AttributedClassTransformer::class)
            ->transformDirectories(__DIR__.'/fixtures/typescript')
            ->outputDirectory($directory)
            ->writer(new GlobalNamespaceWriter('types.d.ts'))
            ->withoutManifest();
        if ($withExtension) {
            $config->extension(new ExplanationTypesExtension);
        }
        TypeScriptTransformer::create($config)->execute();

        return file_get_contents($directory.'/types.d.ts');
    } finally {
        @unlink($directory.'/types.d.ts');
        @rmdir($directory);
    }
}

try {
    typeScriptCheck(str_contains(generated(false), 'readonly working: undefined[]'), 'without the extension a list of Explanation nodes is emitted as undefined[] (the gap)');

    $types = generated(true);
    typeScriptCheck(str_contains($types, "readonly working: import('@kit/ui').ExplanationNode[]"), 'a list<ExplanationNode> prop is typed as the kit ExplanationNode');
    typeScriptCheck(str_contains($types, "readonly document: import('@kit/ui').ExplanationDocument"), 'an ExplanationDocument prop is typed as the kit ExplanationDocument');
    typeScriptCheck(! str_contains($types, 'undefined'), 'no Explanation prop is emitted as undefined');

    // Every name mapped is a type the kit's generated file exports, and every type it exports is mapped.
    $stub = file_get_contents(__DIR__.'/../resources/features/core/components/Kit/Explanation/explanation.generated.ts.stub');
    preg_match_all('/^export type (\w+) =/m', $stub, $exported);
    $mapped = ExplanationTypesExtension::names();
    $exportedNames = $exported[1];
    sort($exportedNames, SORT_STRING);
    typeScriptCheck($mapped === $exportedNames, 'the mapped names are exactly the types explanation.generated.ts exports');
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage()."\n");
    exit(1);
}
echo "TypeScript mapping checks only; no host build or vue-tsc run.\n";
