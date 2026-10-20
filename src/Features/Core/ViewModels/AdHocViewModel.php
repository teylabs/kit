<?php

namespace Tey\Kit\Features\Core\ViewModels;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Inertia\Inertia;
use InvalidArgumentException;
use Tey\Kit\Features\Core\Data\PageLayout;

/**
 * A small page's props without a class of its own: `AdHocViewModel::make()->title('Reports')->with('stats', fn () => ...)`. Values are closures or plain
 * values; `optional()` and `defer()` give Inertia's own prop types. Keys are unique, and `layout` and `shell` are reserved. The props are untyped to the
 * generator (it cannot see ad-hoc keys); the page types them with the generated `AdHocPage<P>` helper, and the layout stays typed.
 * Graduate to a ViewModel class when the page has more than three props, any action or permission-dependent prop, more than one render site, a nested
 * ViewModel, or a test of its contract: the page-contract guard counts the render sites and fails one that breaks this rule.
 */
final class AdHocViewModel implements Arrayable
{
    private const array RESERVED = ['layout', 'shell'];

    /** @var array<string, mixed> */
    private array $props = [];

    private ?string $title = null;

    private PageLayout|Closure|null $layout = null;

    private function __construct() {}

    public static function make(): self
    {
        return new self;
    }

    /** Sets the layout's title and document title. */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /** A full layout (or a closure that makes one), for a page with section navigation. */
    public function layout(PageLayout|Closure $layout): self
    {
        $this->layout = $layout;

        return $this;
    }

    /** @param  string|array<string, mixed>  $key */
    public function with(string|array $key, mixed $value = null): self
    {
        foreach (is_array($key) ? $key : [$key => $value] as $name => $item) {
            $this->put($name, $item);
        }

        return $this;
    }

    public function optional(string $key, Closure $value): self
    {
        return $this->put($key, Inertia::optional($value));
    }

    public function defer(string $key, Closure $value, ?string $group = null): self
    {
        return $this->put($key, $group === null ? Inertia::defer($value) : Inertia::defer($value, $group));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $layout = $this->layout ?? ($this->title !== null ? fn () => app(PageLayouts::class)->layout((string) $this->title, null) : null);

        return $this->props + ['layout' => $layout instanceof Closure ? $layout() : ($layout ?? app(PageLayouts::class)->layout('', null))];
    }

    /** The number of props the page has (the guard counts it against the graduation rule). */
    public function propCount(): int
    {
        return count($this->props);
    }

    private function put(string $key, mixed $value): self
    {
        if (in_array($key, self::RESERVED, true)) {
            throw new InvalidArgumentException("The ad-hoc prop [{$key}] is reserved: the layout and the shell are not page props.");
        }

        if (array_key_exists($key, $this->props)) {
            throw new InvalidArgumentException("The ad-hoc prop [{$key}] is set twice.");
        }

        $this->props[$key] = $value;

        return $this;
    }
}
