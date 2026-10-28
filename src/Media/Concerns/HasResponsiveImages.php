<?php

namespace Tey\Kit\Media\Concerns;

use Spatie\Image\Enums\Fit;

/**
 * Declares responsive WebP conversions for a collection: one per width, named `w<width>`, fit within that width with no upscaling, and always queued
 * (on `kit.media.queue` through Conversions), so publishing never waits for them. Call it from the model's `registerMediaConversions()`.
 */
trait HasResponsiveImages
{
    /** @param  list<int>  $widths */
    protected function responsiveWebp(string $collection, array $widths): void
    {
        foreach ($widths as $width) {
            $this->addMediaConversion('w'.$width)
                ->format('webp')
                ->fit(Fit::Max, $width, 10 * $width)
                ->performOnCollections($collection)
                ->queued();
        }
    }
}
