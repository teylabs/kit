<?php

namespace Tey\Kit\Features\Core\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry of the kit's ActivityStream (`Kit/Feed/ActivityStream`, typed `ActivityEntry` in `@kit/ui`). A host's presenter maps its own records (audit events,
 * a feed service's nodes, a record's timeline) into this, so the stream never knows where they came from.
 *
 * Make one with `text()` (a plain headline, always safe) or `trustedHtml()` (markup the host's server has already escaped: the kit never escapes or sanitizes
 * it, and the stream renders it as HTML). `occurredAt` is ISO 8601; `timestamp` and `timestampTitle` are display strings formatted by the host (without them, the
 * stream formats `occurredAt` in the viewer's locale and zone). `effectiveOn` (ISO date) and `effectiveOnLabel` mark a change that takes effect on another day than
 * it was recorded: "effective <label> · recorded <timestamp>". A null headline is a row of its time and meta only. `group()` folds entries under one headline.
 */
#[TypeScript]
final class ActivityEntryData extends Data
{
    /** The kit tones. `intent` may also be a host's own word (an open vocabulary): the stream shows anything else as neutral. */
    public const TONES = ['success', 'warning', 'danger', 'info', 'neutral'];

    /**
     * @param  array{text: string}|array{html: string}|null  $headline  null: a row of its time and meta only
     * @param  list<array{label: string, value: string}>  $meta
     * @param  list<ActivityEntryData>  $children
     * @param  array{date: string, label: string}|null  $day
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?array $headline,
        public ?string $occurredAt = null,
        public ?string $timestamp = null,
        public ?string $timestampTitle = null,
        public ?string $intent = null,
        public array $meta = [],
        public ?int $count = null,
        public bool $truncated = false,
        public array $children = [],
        public ?array $day = null,
        public ?string $effectiveOn = null,
        public ?string $effectiveOnLabel = null,
    ) {
        $keys = $headline === null ? null : array_keys($headline);
        if (! in_array($kind, ['activity', 'group'], true)) {
            throw new InvalidArgumentException('An activity entry is an activity or a group.');
        }
        if ($headline !== null && (($keys !== ['text'] && $keys !== ['html']) || ! is_string($headline[$keys[0]]))) {
            throw new InvalidArgumentException('An activity headline is exactly one of text or html.');
        }
        foreach ($meta as $fact) {
            if (! is_array($fact) || array_keys($fact) !== ['label', 'value'] || ! is_string($fact['label']) || ! is_string($fact['value'])) {
                throw new InvalidArgumentException('Activity meta is a list of {label, value} strings.');
            }
        }
        if ($day !== null && (array_keys($day) !== ['date', 'label'] || ! is_string($day['date']) || ! is_string($day['label']))) {
            throw new InvalidArgumentException('An activity day is {date, label}.');
        }
    }

    /**
     * A plain-text headline.
     *
     * @param  list<array{label: string, value: string}>  $meta
     * @param  array{date: string, label: string}|null  $day
     */
    public static function text(string $id, string $text, ?string $occurredAt = null, ?string $intent = null, array $meta = [], ?array $day = null, ?string $timestamp = null, ?string $timestampTitle = null, ?string $effectiveOn = null, ?string $effectiveOnLabel = null): self
    {
        return new self($id, 'activity', ['text' => $text], $occurredAt, $timestamp, $timestampTitle, $intent, $meta, day: $day, effectiveOn: $effectiveOn, effectiveOnLabel: $effectiveOnLabel);
    }

    /**
     * A headline of markup the host's server has already escaped (every interpolated value through `e()`); the kit renders it as HTML, unchanged.
     *
     * @param  list<array{label: string, value: string}>  $meta
     * @param  array{date: string, label: string}|null  $day
     */
    public static function trustedHtml(string $id, string $html, ?string $occurredAt = null, ?string $intent = null, array $meta = [], ?array $day = null, ?string $timestamp = null, ?string $timestampTitle = null, ?string $effectiveOn = null, ?string $effectiveOnLabel = null): self
    {
        return new self($id, 'activity', ['html' => $html], $occurredAt, $timestamp, $timestampTitle, $intent, $meta, day: $day, effectiveOn: $effectiveOn, effectiveOnLabel: $effectiveOnLabel);
    }

    /**
     * Entries folded under one headline. `count` is the group's full size; pass fewer `children` and the stream says how many more were not shown.
     *
     * @param  array{text: string}|array{html: string}  $headline
     * @param  list<ActivityEntryData>  $children
     * @param  array{date: string, label: string}|null  $day
     */
    public static function group(string $id, array $headline, array $children, ?int $count = null, ?string $occurredAt = null, ?string $intent = null, ?array $day = null): self
    {
        $count ??= count($children);

        return new self($id, 'group', $headline, $occurredAt, null, null, $intent, [], $count, $count > count($children), $children, $day);
    }
}
