<?php

namespace Tey\Kit\TypeScript;

use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;

/** Spatie's #[TypeScript] class transformer with literal docblock types (LiteralTypesTranspiler). Use it in place of AttributedClassTransformer. */
final class AttributedDataTransformer extends AttributedClassTransformer
{
    public function __construct()
    {
        parent::__construct(transpilePhpStanTypeToTypeScriptTypeAction: new LiteralTypesTranspiler);
    }
}
