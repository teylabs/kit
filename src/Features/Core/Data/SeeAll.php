<?php

namespace Tey\Kit\Features\Core\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The one "see all" link a list section or widget offers, decided on the server (docs/see-all.md): `SeeAll::to('All cases', route('cases.index'))`. The
 * client draws every one the same way, "All cases →", or "All cases (42) →" with a `count`; an app never writes the link itself. The label names the noun
 * ("All cases"), never "View all" or "See all". `placement` is where the card draws it: `footer` (the default, for lists: bottom-right, after the list's
 * end, outside its scroll) or `header` (`inHeader()`, for summaries, stats and charts).
 */
#[TypeScript]
final class SeeAll extends Data
{
    private const LEGACY = '/\A(?:view|see|show)\s+all\s+(\S.*)\z/i';

    public function __construct(
        public string $label,
        public string $url,
        public ?int $count = null,
        #[LiteralTypeScriptType("'footer' | 'header'")] public string $placement = 'footer',
    ) {
        // Fail closed: a link with no words or nowhere to go, a negative count or an unknown placement is a mistake, never drawn.
        if (trim($label) === '' || trim($url) === '' || ($count !== null && $count < 0) || ! in_array($placement, ['footer', 'header'], true)) {
            throw new InvalidArgumentException('see_all_invalid');
        }
    }

    public static function to(string $label, string $url, ?int $count = null): self
    {
        return new self($label, $url, $count);
    }

    /** The same link with a count, drawn after the label: "All cases (42) →". Null drops it. */
    public function withCount(?int $count): self
    {
        return new self($this->label, $this->url, $count, $this->placement);
    }

    /** The same link in the card's header, for a card that is not a list (a summary, a stat, a chart). */
    public function inHeader(): self
    {
        return new self($this->label, $this->url, $this->count, 'header');
    }

    /**
     * The deprecated way to offer one: a header action that is a plain link labelled "All cases", "View all cases", "See all cases" or "Show all cases" (a GET
     * with no confirmation, enabled, not in a menu). It maps onto the descriptor as "All cases" in the footer; anything else is null and stays an action.
     *
     * @param  \Tey\Kit\Features\Core\Data\Action|array<string, mixed>  $action
     */
    public static function fromAction(Action|array $action): ?self
    {
        $action = $action instanceof Action ? $action->toArray() : $action;
        $label = trim((string) ($action['label'] ?? ''));
        $url = (string) ($action['url'] ?? '');
        if (strtolower((string) ($action['method'] ?? 'get')) !== 'get' || ($action['disabled'] ?? false) || ($action['confirm'] ?? null) !== null || ($action['menu'] ?? false) || ($action['destructive'] ?? false) || $url === '') {
            return null;
        }
        if (preg_match(self::LEGACY, $label, $noun) === 1) {
            return new self('All '.$noun[1], $url);
        }

        return preg_match('/\AAll\s+\S/', $label) === 1 ? new self($label, $url) : null;
    }
}
