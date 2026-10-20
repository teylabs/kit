<?php

namespace Tey\Kit\Features\Core;

/** A record's own crumb title: a model that is a page of its own (`/notes/7`) says what its crumb reads. */
interface BreadcrumbTitle
{
    public function breadcrumbTitle(): string;
}
