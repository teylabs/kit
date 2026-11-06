<?php

namespace Tey\Kit\TypeScript;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Spatie\TypeScriptTransformer\Actions\TranspilePhpStanTypeToTypeScriptNodeAction;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptLiteral;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;

/**
 * Spatie's docblock transpiler, plus literal types: `'queued'|'running'`, `1|2` and `1.5` in a `@param`, `@var` or `@return` (anywhere, an array shape's
 * field included) come out as TypeScript literals instead of `unknown`. Everything else is the parent's.
 */
final class LiteralTypesTranspiler extends TranspilePhpStanTypeToTypeScriptNodeAction
{
    protected function resolve(TypeNode $type, ?PhpClassNode $phpClassNode): TypeScriptNode
    {
        if ($type instanceof ConstTypeNode) {
            $expr = $type->constExpr;
            $literal = match (true) {
                $expr instanceof ConstExprStringNode => new TypeScriptLiteral($expr->value),
                $expr instanceof ConstExprIntegerNode => new TypeScriptLiteral((int) $expr->value),
                $expr instanceof ConstExprFloatNode => new TypeScriptLiteral((float) $expr->value),
                default => null,
            };
            if ($literal !== null) {
                return $literal;
            }
        }

        return parent::resolve($type, $phpClassNode);
    }
}
