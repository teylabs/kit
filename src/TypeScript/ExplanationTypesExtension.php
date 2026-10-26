<?php

namespace Tey\Kit\TypeScript;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Support\Extensions\TypeScriptTransformerExtension;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;

/**
 * Maps every Explanation data class and enum to the type `@kit/ui` already exports (`explanation.generated.ts`), so a page view model's `list<ExplanationNode>` or
 * `ExplanationDocument` prop is typed as the kit's own type instead of `undefined`. Register it where types are generated: `$config->extension(new ExplanationTypesExtension)`.
 * The kit's types are generated from the same PHP classes, so the two cannot drift. A development tool: the host registers it only where types are generated.
 */
final class ExplanationTypesExtension implements TypeScriptTransformerExtension
{
    public function enrich(TypeScriptTransformerConfigFactory $factory): void
    {
        foreach (self::names() as $name) {
            $factory->replaceType('Tey\\Kit\\Explanation\\'.$name, new TypeScriptRaw("import('@kit/ui').{$name}"));
        }
    }

    /** @return list<string> The short names the kit's TypeScript twin declares: the Data classes and the enums of `src/Explanation`. */
    public static function names(): array
    {
        $names = [];
        foreach (glob(__DIR__.'/../Explanation/*.php') as $file) {
            $name = basename($file, '.php');
            $class = new \ReflectionClass('Tey\\Kit\\Explanation\\'.$name);
            if ($class->isEnum() || $class->isSubclassOf(Data::class)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }
}
