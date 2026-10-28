<?php

namespace Tey\Kit\Features\Core\ViewModels;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;
use ReflectionClass;
use ReflectionMethod;

/**
 * A page's props, as one object: every public, non-static method with no required parameters is a prop (a method named in `$hidden`, or any
 * protected or private one, is not). The constructor takes the minimum the page cannot derive. Each prop method declares its return type, and an array
 * return needs a docblock shape (`@return list<array{id: int}>`), because the generated TypeScript is read from them.
 *
 * `toArray()` runs every prop method on every render (Inertia calls it before it filters a partial reload), so a method buys one home and a type, not
 * laziness: return `Inertia::optional()` or `Inertia::defer()` from a method that is expensive. A child ViewModel, a Data value, an enum or an
 * Arrayable comes out as plain arrays and values; `null` stays `null`. There is no `make()` here (constructors differ per subclass): use `new`.
 */
abstract class ViewModel implements Arrayable, JsonSerializable
{
    /** Public methods that are not props. @var list<string> */
    protected array $hidden = [];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $props = [];

        foreach (static::propMethods(static::class, $this->hidden) as $method) {
            $props[$method] = self::normalize($this->{$method}());
        }

        return $props;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The one rule that says which methods are props, shared by `toArray()` and the TypeScript transformer so the generated type cannot drift from the payload.
     *
     * @param  class-string<self>  $class
     * @param  list<string>|null  $hidden  null reads the class's own default
     * @return list<string>
     */
    public static function propMethods(string $class, ?array $hidden = null): array
    {
        $reflection = new ReflectionClass($class);
        $hidden ??= (array) ($reflection->getDefaultProperties()['hidden'] ?? []);
        $methods = [];

        foreach (self::declarationOrder($reflection) as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->isConstructor() || str_starts_with($method->getName(), '__') || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            // The base's own methods (toArray, jsonSerialize) are not props.
            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            if (in_array($method->getName(), $hidden, true)) {
                continue;
            }

            if ($method->getReturnType() === null) {
                throw new LogicException($class.'::'.$method->getName().'() is a prop and declares no return type.');
            }

            $methods[] = $method->getName();
        }

        return $methods;
    }

    /**
     * The class's methods in a stable declaration order, the same on every PHP version (reflection's own order differs between them): ancestors
     * first, root to child; within a class, its traits' methods in `use` order, then its own by line. An override keeps the slot of the method it
     * overrides, so adding one never reorders the props.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return list<ReflectionMethod>
     */
    private static function declarationOrder(ReflectionClass $reflection): array
    {
        $chain = [];
        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            array_unshift($chain, $class);
        }
        $names = [];
        foreach ($chain as $class) {
            foreach (self::ownOrder($class) as $name) {
                $names[$name] ??= true;
            }
        }
        // Anything the walk missed (an aliased trait method) follows, by name.
        $rest = array_diff(array_map(fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()), array_keys($names));
        sort($rest);

        return array_map(fn (string $name): ReflectionMethod => $reflection->getMethod($name), [...array_keys($names), ...$rest]);
    }

    /**
     * The methods a class or trait itself contributes, in order: its traits' (in `use` order, each ordered the same way), then those written in
     * its own file, by line.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<string>
     */
    private static function ownOrder(ReflectionClass $class): array
    {
        $names = [];
        foreach ($class->getTraits() as $trait) {
            array_push($names, ...self::ownOrder($trait));
        }
        $own = array_filter($class->getMethods(), fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class->getName() && $method->getFileName() === $class->getFileName());
        usort($own, fn (ReflectionMethod $a, ReflectionMethod $b): int => $a->getStartLine() <=> $b->getStartLine());

        return array_values(array_unique([...$names, ...array_map(fn (ReflectionMethod $method): string => $method->getName(), $own)]));
    }

    protected static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof Arrayable => $value->toArray(),
            is_array($value) => array_map(static::normalize(...), $value),
            default => $value,
        };
    }
}
