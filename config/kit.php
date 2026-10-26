<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features. Null reads them from the kit's installed record.
    'features' => null,

    // The font set <x-kit::fonts /> loads: instrument, instrument-inter or hanken.
    'fonts' => env('KIT_FONTS', 'instrument'),

    // Whether the brand reaches mail (sender name, reply-to, branded views). Null follows the session-ui feature; true or false overrides it.
    'brand_mail' => null,

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
        // The accent's companions, each a #rrggbb: the text on the accent, the accent as links and focus on white (it must pass 4.5:1 there),
        // the accent as fills in dark mode and the text on it. `style`: `fill` (the accent fills primary buttons) or `mono` (the header and primary
        // buttons are `ink`, and the accent is only a highlight: the active marker and focus).
        'accent_foreground' => env('KIT_BRAND_ACCENT_FOREGROUND', '#ffffff'),
        'accent_strong' => env('KIT_BRAND_ACCENT_STRONG'),
        'accent_dark' => env('KIT_BRAND_ACCENT_DARK'),
        'accent_dark_foreground' => env('KIT_BRAND_ACCENT_DARK_FOREGROUND', '#09090b'),
        'style' => env('KIT_BRAND_STYLE', 'fill'),
        'ink' => env('KIT_BRAND_INK', '#09090b'),
        // Named variants to compare side by side: a JSON object of name => the keys above (accent, accent_foreground, accent_strong, accent_dark,
        // accent_dark_foreground, style, ink). Only in a local environment, `?brand=<name>` previews one for the session (`?brand=` clears it);
        // elsewhere the switch does nothing and these are never read. A brand's own values belong in its own environment, not in the template.
        'presets' => json_decode((string) env('KIT_BRAND_PRESETS', '{}'), true) ?: [],
        'hello_email' => env('KIT_BRAND_HELLO_EMAIL', 'hello@example.com'),
        'support_email' => env('KIT_BRAND_SUPPORT_EMAIL', 'support@example.com'),
        // The four address roles: updates@ is the From of system mail, support@ its Reply-To and the help link, hello@ general contact, privacy@ data requests (omitted in a private app).
        'privacy_email' => env('KIT_BRAND_PRIVACY_EMAIL', 'privacy@example.com'),
        'mail_from_address' => env('KIT_BRAND_MAIL_FROM_ADDRESS', 'updates@example.com'),
        'legal_name' => env('KIT_BRAND_LEGAL_NAME', 'Example Company'),
        'postal_address' => env('KIT_BRAND_POSTAL_ADDRESS', '1 Example Street, Example City'),
        'credit_verb' => env('KIT_BRAND_CREDIT_VERB', 'Built'),
        'credit_org' => env('KIT_BRAND_CREDIT_ORG', 'Tey Labs'),
        'credit_for' => env('KIT_BRAND_CREDIT_FOR'),
        // A private app (an internal tool, a client's own workspace) has no public legal entity to name: with this on, the legal name, postal address, Terms and
        // Privacy are left out of both footers and `Brand::placeholders()` does not ask for them. Anything left empty is omitted either way.
        'private' => filter_var(env('KIT_BRAND_PRIVATE', false), FILTER_VALIDATE_BOOL),
        'terms_url' => env('KIT_BRAND_TERMS_URL', '/terms'),
        'privacy_url' => env('KIT_BRAND_PRIVACY_URL', '/privacy'),
        'manage_url' => env('KIT_BRAND_MANAGE_URL', '/me'),
        // A TrueType font for the social image's product name; null searches the usual system locations.
        'font' => env('KIT_BRAND_FONT'),
    ],

    // Outside production, a quiet sign of where you are: `[Label] ` before the document title and a 2px edge across the top of the shell, per environment name (`label`, up to
    // 20 letters, digits, spaces or hyphens; `colour`, a #rrggbb that is 3:1 or better on the light and the dark shell). Never shown in production, whatever is set here; an entry that
    // fails either rule is not shown. `[]` turns it off everywhere. No product name belongs in a label.
    'environment_indicator' => ['staging' => ['label' => 'Staging', 'colour' => '#b45309']],

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true],

    // What a workspace's plan includes when it has no entitlement row, per capability a feature declared an entitlement for:
    // ['workspace.reports' => ['included' => true, 'limits' => ['seats' => 5]]]. A declared entitlement left out here is not included.
    'entitlements' => [],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix).
    'routes' => [],

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    // How a person gets a workspace: `invite_only` (default) means only an invitation does, so a person with none sees the no-workspace page without a create action.
    'workspaces' => ['provisioning' => env('KIT_WORKSPACES_PROVISIONING', 'invite_only')],

    // The hands-free deploy: the role and permission syncs run when migrations end. They skip under unit tests unless a test turns this on.
    'deploy' => ['sync_in_tests' => false],

    // MCP: the ability every connection must hold, the AI assistants settings page (hidden until the connection rework), the address the "add an assistant" cards use (default: this app's
    // /mcp) and which cards it offers, in order (claude-ai, chatgpt, claude-code, cursor, vscode).
    'mcp' => [
        'connection_ability' => 'settings.read',
        'ui' => filter_var(env('KIT_MCP_UI', false), FILTER_VALIDATE_BOOL),
        'server_url' => env('KIT_MCP_SERVER_URL'),
        'assistants' => ['claude-ai', 'chatgpt', 'claude-code', 'cursor', 'vscode'],
    ],
];
