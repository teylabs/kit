<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features. Null reads them from the kit's installed record.
    'features' => null,

    // The font set <x-kit::fonts /> loads: instrument, instrument-inter or hanken.
    'fonts' => env('KIT_FONTS', 'instrument'),

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.settings' => true, 'workspace.notes' => true],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix).
    'routes' => [],

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    'mcp' => ['connection_ability' => 'settings.read'],
];
