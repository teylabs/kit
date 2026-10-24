<?php

// Emits the TypeScript twin of the Tey\Kit\Explanation Data contracts by reflection; no application boot, no data access.
// Usage: php bin/generate-explanation-ts.php [output file]   (default: the core feature's generated stub)
//        php bin/generate-explanation-ts.php --check          (exit 1 when the stub is out of date)
declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = getenv('KIT_AUTOLOAD') ?: $root.'/vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('Tey\\Kit\\', $root.'/src', true);

const STUB = 'resources/features/core/components/Kit/Explanation/explanation.generated.ts.stub';

function explanationType(ReflectionParameter $parameter, string $doc): string
{
    $type = $parameter->getType();
    if (! $type instanceof ReflectionNamedType) {
        throw new RuntimeException('Unsupported type on $'.$parameter->getName());
    }
    $name = $type->getName();
    if ($name === 'array') {
        if (! preg_match('/@param\s+list<(\w+)>\s+\$'.$parameter->getName().'\b/', $doc, $match)) {
            throw new RuntimeException('Array $'.$parameter->getName().' needs a list<T> docblock.');
        }
        $ts = (['string' => 'string', 'int' => 'number'][$match[1]] ?? $match[1]).'[]';
    } else {
        $ts = match ($name) {
            'string' => 'string',
            'int', 'float' => 'number',
            'bool' => 'boolean',
            default => (new ReflectionClass($name))->getShortName(),
        };
    }

    return $type->allowsNull() && $name !== 'null' ? $ts.' | null' : $ts;
}

function explanationTypeScript(string $directory): string
{
    $names = array_map(fn (string $file) => basename($file, '.php'), glob($directory.'/*.php'));
    sort($names, SORT_STRING);
    $out = '';
    foreach ($names as $name) {
        $class = new ReflectionClass('Tey\\Kit\\Explanation\\'.$name);
        if ($class->isEnum()) {
            $out .= "export type {$name} = ".implode(' | ', array_map(fn ($case) => "'".$case->getBackingValue()."'", (new ReflectionEnum($class->getName()))->getCases())).";\n";
        } elseif ($class->isSubclassOf(Spatie\LaravelData\Data::class)) {
            $constructor = $class->getConstructor();
            $doc = (string) $constructor->getDocComment();
            $out .= "export type {$name} = {\n";
            foreach ($constructor->getParameters() as $parameter) {
                $out .= $parameter->getName().': '.explanationType($parameter, $doc).",\n";
            }
            $out .= "};\n";
        }
    }

    return $out;
}

$generated = explanationTypeScript($root.'/src/Explanation');
if (($argv[1] ?? null) === '--check') {
    if (file_get_contents($root.'/'.STUB) !== $generated) {
        fwrite(STDERR, STUB." is out of date: run php bin/generate-explanation-ts.php\n");
        exit(1);
    }
    echo "PASS explanation types are current\n";
    exit(0);
}
file_put_contents($argv[1] ?? $root.'/'.STUB, $generated);
