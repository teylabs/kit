<?php

namespace Tey\Kit\Features\Core;

/** A feature's provider: what the feature contributes to the shared seams once it is adopted. */
interface Contributes
{
    /**
     * @param  list<string>  $features  every adopted feature id
     * @param  array{enabled: bool, prefix: string, middleware: list<string>, name: string}  $routes  this feature's route options
     */
    public static function contribute(Registry $registry, array $features, array $routes): void;
}
