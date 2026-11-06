<?php

// Literal docblock types reach the generated TypeScript: a Data property's `@param 'a'|'b'`, a ViewModel's `@return 'a'|'b'` and an array shape's
// literal field come out as TypeScript literals, not `unknown` (LiteralTypesTranspiler, used by the kit's transformers). Fixtures only; src is untouched.
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Tey\Kit\TypeScript\AttributedDataTransformer;
use Tey\Kit\TypeScript\ViewModelTransformer;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);
$loader->addPsr4('Tey\\Kit\\Fixtures\\TypeScriptLiterals\\', __DIR__.'/fixtures/typescript-literals', true);

function literalCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$directory = sys_get_temp_dir().'/kit-ts-literals-'.bin2hex(random_bytes(5));
mkdir($directory);
try {
    TypeScriptTransformer::create(TypeScriptTransformerConfigFactory::create()
        ->transformer(ViewModelTransformer::class, AttributedDataTransformer::class, EnumTransformer::class)
        ->transformDirectories(__DIR__.'/fixtures/typescript-literals')
        ->outputDirectory($directory)
        ->writer(new GlobalNamespaceWriter('types.d.ts'))
        ->withoutManifest())->execute();
    $types = (string) file_get_contents($directory.'/types.d.ts');
    literalCheck(str_contains($types, "status: 'queued' | 'running' | 'done'"), "a Data property's @param string literals are a union of literals");
    literalCheck(str_contains($types, 'priority: 1 | 2 | 3 | null'), 'integer literals and null too');
    literalCheck(str_contains($types, "readonly status: 'queued' | 'running'"), "a ViewModel's @return literals are a union of literals");
    literalCheck(preg_match("/state: 'ok' \\| 'failed'/", $types) === 1, "an array shape's literal field too");
    literalCheck(! str_contains($types, 'unknown'), 'nothing is generated as unknown');
    echo "TypeScript literal checks on fixtures: the kit's transformers only.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
} finally {
    @unlink($directory.'/types.d.ts');
    @rmdir($directory);
}
