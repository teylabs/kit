<?php

namespace Tey\Kit\Features\Notes\Contracts;

use Spatie\MediaLibrary\HasMedia;

/**
 * A workspace note: the model `kit.models.note` names, which uses Concerns\IsNote. The kit's own is Models\Note (`kit_notes`); an app that adopted
 * notes has its own (`notes`). Media rows name it by its alias, `kit-note`, whichever class it is.
 */
interface Note extends HasMedia
{
    public function breadcrumbTitle(): string;
}
