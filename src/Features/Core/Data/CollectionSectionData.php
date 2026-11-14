<?php

namespace Tey\Kit\Features\Core\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Lists\ListEmptyData;
use Tey\Kit\Features\Core\Lists\ListErrorData;

/**
 * Everything a CollectionSection draws around its items (docs/collection-section.md), decided on the server: a section showing a collection (a list, a grid,
 * a feed or table rows). Its `title` (a link to `seeAll`'s URL when there is one), an optional `description`, header `actions`, `segments` (tabs such as
 * All / New / In progress) and a `sortBy` menu, the `state` (`ready`, `empty` with `empty`, or `error` with `error`), an optional `maxHeight` past which the items
 * scroll inside the section, and `seeAll`, the "All cases →" link in its footer. The items themselves are the component's default slot.
 * CollectionSection draws it bare (a grid or list of cards) and CollectionCard on a kit Card; both take this one descriptor, so a page swaps one for the other by
 * name. A dashboard widget's frame carries the same fields, so WidgetCard draws a widget through CollectionCard.
 */
#[TypeScript]
final class CollectionSectionData extends Data
{
    /**
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $actions
     * @param  list<CollectionSegment>  $segments
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        #[LiteralTypeScriptType("'ready' | 'empty' | 'error'")] public string $state = 'ready',
        public ?ListEmptyData $empty = null,
        public ?ListErrorData $error = null,
        public array $actions = [],
        public array $segments = [],
        public ?CollectionSortData $sortBy = null,
        public ?SeeAll $seeAll = null,
        public ?string $maxHeight = null,
    ) {
        // Fail closed: no title, an unknown state, more than one current segment, or a max height that is not a plain px, rem or em length is a mistake, never drawn.
        $current = count(array_filter($segments, fn (CollectionSegment $segment): bool => $segment->current));
        if (trim($title) === '' || ! in_array($state, ['ready', 'empty', 'error'], true) || $current > 1
            || ($maxHeight !== null && (strlen($maxHeight) > 32 || preg_match('/\A(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em)\z/', $maxHeight) !== 1))) {
            throw new InvalidArgumentException('collection_section_invalid');
        }
    }
}
