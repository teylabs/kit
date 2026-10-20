<?php

namespace Tey\Kit\TypeScript;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\Transformers\ClassTransformer;
use Spatie\TypeScriptTransformer\TypeResolvers\Data\ParsedClass;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptObject;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnknown;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Transformed\Untransformable;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/**
 * Emits one TypeScript property per prop method of a ViewModel, typed from its return type (and its `@return` docblock for array shapes), so a page writes
 * `defineProps<EditClientViewModel>()` and restates nothing. Which methods are props is `ViewModel::propMethods()`, the rule `toArray()` uses at runtime, so the
 * generated type cannot describe a payload the server does not send. Register it before the attributed-class transformer, which would also match a ViewModel
 * and quietly emit `{}`. A development tool: the host registers it only where types are generated.
 */
class ViewModelTransformer extends ClassTransformer
{
    protected function shouldTransform(PhpClassNode $phpClassNode): bool
    {
        return ! $phpClassNode->isAbstract()
            && count($phpClassNode->getAttributes(TypeScript::class)) > 0
            && is_subclass_of($phpClassNode->getName(), ViewModel::class);
    }

    public function transform(PhpClassNode $phpClassNode, TransformationContext $context): Transformed|Untransformable
    {
        $transformed = parent::transform($phpClassNode, $context);

        if ($transformed instanceof Untransformable) {
            return $transformed;
        }

        $attribute = $phpClassNode->getAttributes(TypeScript::class)[0]->getRawArguments();

        if (($attribute['name'] ?? null) !== null) {
            $transformed->nameAs($attribute['name']);
        }

        return $transformed;
    }

    protected function getTypeScriptNode(PhpClassNode $phpClassNode, TransformationContext $context, ?ParsedClass $parsedClass = null): TypeScriptNode
    {
        $properties = [];

        foreach (ViewModel::propMethods($phpClassNode->getName()) as $name) {
            $method = $phpClassNode->getMethod($name);
            $annotation = $this->docTypeResolver->method($method)?->returnType;
            $type = match (true) {
                $annotation !== null => $this->transpilePhpStanTypeToTypeScriptTypeAction->execute($annotation, $method->getDeclaringClass(), []),
                $method->getReturnType() !== null => $this->transpilePhpTypeNodeToTypeScriptTypeAction->execute($method->getReturnType(), $method->getDeclaringClass()),
                default => new TypeScriptUnknown,
            };
            $properties[] = new TypeScriptProperty($name, $type, false, true);
        }

        return new TypeScriptObject($properties);
    }
}
