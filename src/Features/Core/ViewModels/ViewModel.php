<?php

namespace Tey\Kit\Features\Core\ViewModels;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Inertia\ResponseFactory;
use JsonSerializable;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * A page's props, as one object: every public, non-static method with no required parameters is a prop (a method named in `$hidden`, or any
 * protected or private one, is not). The constructor takes the minimum the page cannot derive. Each prop method declares its return type, and an array
 * return needs a docblock shape (`@return list<array{id: int}>`), because the generated TypeScript is read from them.
 *
 * Eager by design: `toArray()` runs every prop method on every render (Inertia calls it before it filters a partial reload), so `only` trims what is
 * sent, not what is computed. Laziness is the prop's job: a method whose prop is expensive, or only needed on a partial reload, returns
 * `Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()` or `Inertia::scroll()`, which Inertia evaluates only when asked. A child ViewModel, a Data value, an enum or an
 * Arrayable comes out as plain arrays and values; `null` stays `null`. There is no `make()` here (constructors differ per subclass): use `new`.
 */
abstract class ViewModel implements Arrayable, JsonSerializable
{
    /** Public methods that are not props. @var list<string> */
    protected array $hidden = [];

    /**
     * A page that is meant to render without the workspace shell's breadcrumb bar and module tabs (a public document): true silences the
     * development warning below. A workspace page extends PageViewModel instead.
     */
    protected bool $standalone = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $this->warnUnlessPage();
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

    /**
     * The breadcrumb bar and module tabs read the page's `layout()`, which only PageViewModel provides, so a page rendered from a plain ViewModel
     * silently gets neither. In local and testing, a ViewModel handed straight to `Inertia::render()` (its caller is Inertia's ResponseFactory, not a
     * parent ViewModel, a modal or a JSON response) logs a warning naming the class. Production stays silent and nothing here throws.
     */
    private function warnUnlessPage(): void
    {
        if ($this instanceof PageViewModel || $this->standalone) {
            return;
        }

        try {
            if (! function_exists('app') || ! app()->bound('env') || ! app()->bound('log') || ! app()->environment('local', 'testing')) {
                return;
            }
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
            if (($caller['function'] ?? null) === 'render' && is_a($caller['class'] ?? '', ResponseFactory::class, true)) {
                app('log')->warning(static::class.' renders an Inertia page but is not a PageViewModel, so the page gets no breadcrumb bar or module tabs; extend '.PageViewModel::class.'.');
            }
        } catch (Throwable) {
            // A warning is never worth a failed page.
        }
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
