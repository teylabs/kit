<?php

namespace Tey\Kit\Features\Core\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Assert;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Tey\Kit\Features\Core\Concerns\Touches;
use Tey\Kit\Features\Core\Touch;

/** Test helpers for the touched_at pattern (docs/touched-at.md): one child's declaration, and a coverage list of the children that forget theirs. */
final class TouchesAssertions
{
    /** @param class-string<Model> $child */
    public static function assertTouchesParent(string $child, string $relation): void
    {
        $model = new $child;
        if (! in_array(Touches::class, class_uses_recursive($child), true)) {
            self::fail("{$child} does not use the Touches concern.");
        }
        if (! in_array($relation, $model->touchesParents(), true)) {
            self::fail("{$child} does not list '{$relation}' in \$touchesParents.");
        }
        if (! method_exists($model, $relation) || ! $model->{$relation}() instanceof Relation) {
            self::fail("{$child}::{$relation}() is not a relation.");
        }
        if (class_exists(Assert::class)) {
            Assert::assertTrue(true);
        }
    }

    /**
     * The BelongsTo and MorphTo relations (declared with a return type) of $models that lead to a parent with a touched_at column but are not listed in
     * the model's `$touchesParents`, as "Class::relation". A MorphTo's parent type is unknown, so every undeclared one is listed; name the ones that
     * should not touch in $ignore.
     *
     * @param  list<class-string<Model>>  $models
     * @param  list<string>  $ignore  "Class::relation" entries that deliberately do not touch
     * @return list<string>
     */
    public static function uncovered(array $models, array $ignore = []): array
    {
        $missing = [];
        foreach ($models as $class) {
            $model = new $class;
            $declared = method_exists($model, 'touchesParents') ? $model->touchesParents() : [];
            foreach ((new \ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || ! $type instanceof ReflectionNamedType) {
                    continue;
                }
                $name = $method->getName();
                if (in_array($name, $declared, true) || in_array("{$class}::{$name}", $ignore, true)) {
                    continue;
                }
                if (is_a($type->getName(), MorphTo::class, true)) {
                    $missing[] = "{$class}::{$name}";
                } elseif (is_a($type->getName(), BelongsTo::class, true) && Touch::hasColumn($model->{$name}()->getRelated())) {
                    $missing[] = "{$class}::{$name}";
                }
            }
        }

        return $missing;
    }

    /** @param list<class-string<Model>> $models @param list<string> $ignore */
    public static function assertCovered(array $models, array $ignore = []): void
    {
        $missing = self::uncovered($models, $ignore);
        if ($missing !== []) {
            self::fail("These relations lead to a touched parent but are not in \$touchesParents:\n  ".implode("\n  ", $missing));
        }
        if (class_exists(Assert::class)) {
            Assert::assertTrue(true);
        }
    }

    private static function fail(string $message): never
    {
        if (class_exists(Assert::class)) {
            Assert::fail($message);
        }
        throw new RuntimeException($message);
    }
}
