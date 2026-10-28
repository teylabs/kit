<?php

namespace Tey\Kit\Media;

use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An `<img>`'s `src`, `srcset` and intrinsic size for an item with `w<width>` conversions (HasResponsiveImages). Only conversions already generated are
 * listed, so a queued one never yields a broken URL; with none yet, the original is the only candidate. URLs come from `$url` (the app's delivery
 * route, never a bucket key), else Media Library's own.
 */
final class Srcset
{
    /**
     * @param  (Closure(Media, string): string)|null  $url  receives the item and the conversion name ('' for the original)
     * @return array{src: string, srcset: string, width: ?int, height: ?int}
     */
    public static function for(Media $media, ?Closure $url = null): array
    {
        $url ??= fn (Media $item, string $conversion): string => $item->getUrl($conversion);
        $widths = [];
        foreach (array_keys(array_filter((array) $media->generated_conversions)) as $name) {
            if (preg_match('/^w(\d+)$/', (string) $name, $match) === 1) {
                $widths[(int) $match[1]] = $name;
            }
        }
        ksort($widths);
        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');
        $candidates = [];
        foreach ($widths as $w => $name) {
            if ($width === null || $w < $width) {
                $candidates[] = $url($media, $name).' '.$w.'w';
            }
        }
        $original = $url($media, '');
        if ($width !== null) {
            $candidates[] = $original.' '.$width.'w';
        }

        return ['src' => $original, 'srcset' => implode(', ', $candidates), 'width' => $width === null ? null : (int) $width, 'height' => $height === null ? null : (int) $height];
    }
}
