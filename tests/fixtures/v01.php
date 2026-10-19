<?php

use Tey\Kit\Catalog;

// The v0.1 foundation/defaults catalog: a regression fixture, not part of the package catalog.
return Catalog::at(__DIR__.'/v01', [
    'foundation' => 'sha256:40bbc9be619c2600cc406211b0a2f5b7282d925cf9066af5de83cccfb86a3541',
    'defaults' => 'sha256:71662b1b830430010da82e36afa59523ee4d94c2924942d4a5283d79934ec7f6',
], 'sha256:2fd1f7e44e6309e4f74e60fc36e1135ddf43307ff328d941b4175382612dab8c');
