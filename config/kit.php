<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features. Null reads them from the kit's installed record.
    'features' => null,

    // The font set <x-kit::fonts /> loads: instrument, instrument-inter or hanken.
    'fonts' => env('KIT_FONTS', 'instrument'),

    // The brand: one place for the platform's identity, with every value from the environment. The shell's mark is the
    // product's logomark beside its name (never a workspace's); `php artisan kit:brand` makes the favicons, touch icon, mail mark
    // and social image from `logomark` and `accent`; mail and the footers read the rest. Values left at these placeholders are
    // listed by `Tey\Kit\Brand::placeholders()`, which a production preflight can refuse.
    // The product's name is APP_NAME (`app.name`).
    'brand' => [
        'identity' => env('KIT_BRAND_IDENTITY'),
        'logomark' => env('KIT_BRAND_LOGOMARK', '/logomark.svg'),
        'wordmark' => env('KIT_BRAND_WORDMARK'),
        // The default is a neutral zinc with no hue, so an unbranded build is plainly unbranded; the brand's accent overrides it.
        'accent' => env('KIT_BRAND_ACCENT', '#52525b'),
        'hello_email' => env('KIT_BRAND_HELLO_EMAIL', 'hello@example.com'),
        'support_email' => env('KIT_BRAND_SUPPORT_EMAIL', 'support@example.com'),
        'mail_from_address' => env('KIT_BRAND_MAIL_FROM_ADDRESS', 'updates@example.com'),
        'legal_name' => env('KIT_BRAND_LEGAL_NAME', 'Example Company'),
        'postal_address' => env('KIT_BRAND_POSTAL_ADDRESS', '1 Example Street, Example City'),
        'credit_verb' => env('KIT_BRAND_CREDIT_VERB', 'Built'),
        'credit_org' => env('KIT_BRAND_CREDIT_ORG', 'Tey Labs'),
        'credit_for' => env('KIT_BRAND_CREDIT_FOR'),
        'terms_url' => env('KIT_BRAND_TERMS_URL', '/terms'),
        'privacy_url' => env('KIT_BRAND_PRIVACY_URL', '/privacy'),
        'manage_url' => env('KIT_BRAND_MANAGE_URL', '/me'),
        // A TrueType font for the social image's product name; null searches the usual system locations.
        'font' => env('KIT_BRAND_FONT'),
    ],

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix).
    'routes' => [],

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    'mcp' => ['connection_ability' => 'settings.read'],
];
