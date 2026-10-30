<?php

// tey/kit's defaults. Publish them with `php artisan vendor:publish --tag=kit-config`;
// the published config/kit.php is the app's own file, and kit updates never touch it.
return [
    // The adopted features. Null reads them from the kit's installed record.
    'features' => null,

    // The ISO 4217 currency for kit money inputs and amounts (InputNumber kind="money", Amount) that don't pass one, such as 'CAD'. Null: no symbol, never a guess.
    'currency' => env('KIT_CURRENCY'),

    // The font set <x-kit::fonts /> loads: instrument, instrument-inter, inter-serif or hanken.
    'fonts' => env('KIT_FONTS', 'instrument'),

    // Whether the brand reaches mail (sender name, reply-to, branded views). True: always, and /up fails in production while brand placeholders are unresolved.
    // False: never. Null (default): on with session-ui only once the brand is filled in (no placeholders), and /up never fails on placeholders.
    'brand_mail' => null,

    // The brand: one place for the platform's identity, with every value from the environment. The shell's mark is the
    // product's logomark beside its name (never a workspace's); `php artisan kit:brand` makes the favicons, touch icon, mail mark
    // and social image from `logomark` and `accent`; mail and the footers read the rest. Values left at these placeholders are
    // listed by `Tey\Kit\Brand::placeholders()`, which a production preflight can refuse.
    // The product's name is APP_NAME (`app.name`).
    // Facts about the host the kit cannot read for itself. `vite_global_types`: 'manual' records that a person verified @vitejs/plugin-vue's script.globalTypeFiles lists
    // vendor/tey/kit/types/kit.d.ts in a Vite config the kit cannot read statically (a plugin factory, mergeConfig); kit:status then reports it "verified manually".
    'host' => ['vite_global_types' => env('KIT_HOST_VITE_GLOBAL_TYPES')],

    'brand' => [
        'identity' => env('KIT_BRAND_IDENTITY'),
        'logomark' => env('KIT_BRAND_LOGOMARK', '/logomark.svg'),
        // The logomark for dark mode (a path in `public/`, shown instead of `logomark` under `.dark`), and the lockup: with `lockup` on, the header and guest pages show the mark
        // with the `wordmark` image (and its `wordmark_dark`) or, with none, the product's name, instead of the mark alone. All null or off by default.
        'logomark_dark' => env('KIT_BRAND_LOGOMARK_DARK'),
        'wordmark' => env('KIT_BRAND_WORDMARK'),
        'wordmark_dark' => env('KIT_BRAND_WORDMARK_DARK'),
        'lockup' => filter_var(env('KIT_BRAND_LOCKUP', false), FILTER_VALIDATE_BOOL),
        // The mail header's image: a PNG of the full lockup (the mark with the wordmark) for a light background, as a path in `public/` or an absolute file path. It is embedded
        // in every kit mail (an inline CID attachment, never a remote image), centred above the message at 220px wide, so give it 2x that or more. Null, or a missing or
        // non-PNG file, shows the product's name instead. SVG is not used: mail clients do not show it reliably.
        'mail_lockup' => env('KIT_BRAND_MAIL_LOCKUP'),
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
        // The roles `style` picks, each settable alone (null follows `style`): `button` (`accent` or `ink`: the primary button in light mode), `button_dark` (`accent` or `theme`: the
        // accent or the theme's own in dark mode), `header` (`theme` or `ink`: ink forces the dark header), `marker` (`strong` or `accent`: the colour of the active nav marker). So ink
        // buttons in light mode and accent buttons in dark mode, on the theme's own header, is `button=ink`, `button_dark=accent`, `header=theme`.
        'button' => env('KIT_BRAND_BUTTON'),
        'button_dark' => env('KIT_BRAND_BUTTON_DARK'),
        'header' => env('KIT_BRAND_HEADER'),
        'marker' => env('KIT_BRAND_MARKER'),
        // A thin line under the header: `accent` (the accent, and `accent_dark` in dark mode) or a #rrggbb. Null for none.
        'header_rule' => env('KIT_BRAND_HEADER_RULE'),
        // Surfaces, each a #rrggbb, null leaves the theme's neutral (page `background`, `card`, `border`, `muted`; the `_dark` twin for dark mode). `link` and `link_dark` set `--brand-link`,
        // for links (`text-(--brand-link)`); null leaves links as plain text. `contrastProblems()` reads the accent and the links against these.
        'background' => env('KIT_BRAND_BACKGROUND'),
        'card' => env('KIT_BRAND_CARD'),
        'border' => env('KIT_BRAND_BORDER'),
        'muted' => env('KIT_BRAND_MUTED'),
        'background_dark' => env('KIT_BRAND_BACKGROUND_DARK'),
        'card_dark' => env('KIT_BRAND_CARD_DARK'),
        'border_dark' => env('KIT_BRAND_BORDER_DARK'),
        'muted_dark' => env('KIT_BRAND_MUTED_DARK'),
        'link' => env('KIT_BRAND_LINK'),
        'link_dark' => env('KIT_BRAND_LINK_DARK'),
        // Named variants to compare side by side: a JSON object of name => the keys above (any colour or role key above). Only in a local environment, `?brand=<name>` previews one for the session (`?brand=` clears it);
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

    // Where the shell's Toaster sits: auto, top-center (just under the shell's header), top-right, bottom-right or bottom-center. Below `sm` it is always
    // bottom-center, above any sticky bar and the safe-area inset. `auto` is then top-center with a sticky bottom bar and header actions, top-right with
    // a sticky bar only, else bottom-right. Controllers decide whether to toast (Tey\Kit\Features\Core\Toast); pages never do.
    'toasts' => [
        'position' => env('KIT_TOASTS_POSITION', 'auto'),
        // Milliseconds per type; null stays until dismissed. A toast with an action stays at least 8000 (docs/toasts.md).
        'durations' => ['success' => 4000, 'info' => 4000, 'warning' => null, 'error' => null],
    ],

    // The kit's dashboard widgets to keep: you, activity and people (docs/dashboard.md). An app registers its own with Registry::widget().
    'dashboard' => ['widgets' => ['you', 'activity', 'people']],

    // Global search (docs/search.md), on once `workspace.search` is a capability that is on. `per_minute` is each person's searches a minute, across the
    // page, the header palette and the MCP tool; `people` keeps the kit's own provider (the workspace's members). An app adds providers with Registry::searchProvider().
    'search' => ['per_minute' => 30, 'people' => true],

    // Concierge grants (roles) whose holders the people lists leave out, such as a support role: People, the People widget and people search (docs/dashboard.md).
    'people' => ['hide_grants' => []],

    // The notes feature (docs/notes.md): the colours (the first is the default), the composer's placeholder, and what may be attached. Attachments
    // go on a private disk and are served only to someone who may read the note.
    'notes' => [
        'colours' => [['key' => 'yellow', 'label' => 'Yellow'], ['key' => 'pink', 'label' => 'Pink'], ['key' => 'blue', 'label' => 'Blue'], ['key' => 'green', 'label' => 'Green']],
        'placeholder' => 'Write a note…',
        'media' => ['disk' => env('KIT_NOTES_DISK', 'local'), 'accept' => ['image/png', 'image/jpeg', 'application/pdf'], 'max_files' => 5, 'max_kb' => 10240],
    ],

    // The host's user model. Null uses the `users` auth provider's model.
    'models' => ['user' => null],

    // Workspace capabilities that are switched on.
    'capabilities' => ['workspace.dashboard' => true, 'workspace.settings' => true],

    // What a workspace's plan includes when it has no entitlement row, per capability a feature declared an entitlement for:
    // ['workspace.reports' => ['included' => true, 'limits' => ['seats' => 5]]]. A declared entitlement left out here is not included.
    'entitlements' => [],

    // Per-feature route options: enabled, prefix, middleware, name (a route-name prefix). session-ui also takes route-group switches, each on by default:
    // 'session-ui' => ['invitations' => false] leaves out the kit's invitation link (transitional, for a host with its own flow), and likewise `welcome`,
    // `passkey-offer` and `demo-login`. An unknown key under session-ui stops the boot.
    'routes' => [],

    // The kit's home (workspace-ui): "/" by default. An app with its own public landing page at "/" moves the kit's home elsewhere ("/app"); its breadcrumbs root there.
    // Without workspace-ui (session-ui alone, the sign-in) the kit serves nothing here: it is the app's own page, where sign-in lands (fortify.home).
    'home' => env('KIT_HOME', '/'),

    // The home route's name: "home" by default. An app that already names its own landing page `home` sets another ("app.home"); the kit's breadcrumbs follow it.
    'home_name' => env('KIT_HOME_NAME', 'home'),

    // A route name taking `workspace`, where an accepted invitation lands. Null uses the first navigation link.
    'invitation_redirect' => null,

    // How a person gets a workspace: `invite_only` (default) means only an invitation does, so a person with none sees the no-workspace page without a create action.
    // `adopt`: the host owns its workspaces table, so the kit's migration never creates one, on a fresh database too (it only adds the kit's missing columns).
    // Null reads it from the workspaces lineage's installed record, which kit:add writes when it plans the adoption.
    // move_ability: what MoveToWorkspace requires in BOTH the source and the target workspace (an Access ability).
    'workspaces' => ['provisioning' => env('KIT_WORKSPACES_PROVISIONING', 'invite_only'), 'adopt' => env('KIT_WORKSPACES_ADOPT'), 'move_ability' => 'members.manage'],

    // Workspace and account deletion: off by default (the routes 404, the settings sections hidden). Turning one on ALSO requires binding its purge handler for the
    // app's own data, Tey\Kit\Features\Workspaces\Contracts\PurgesWorkspaceData or Tey\Kit\Features\SessionUi\Contracts\PurgesAccountData (one that does nothing,
    // explicitly, if the app has none): it runs first, in the deletion's transaction. On without a handler, the deletion refuses and kit:status flags it.
    'lifecycle' => [
        'workspace_delete' => filter_var(env('KIT_WORKSPACE_DELETE', false), FILTER_VALIDATE_BOOL),
        'account_delete' => filter_var(env('KIT_ACCOUNT_DELETE', false), FILTER_VALIDATE_BOOL),
        // Both deletions ask for the person's current password. On unless set to false explicitly: any other value keeps it on.
        'confirm_password' => filter_var(env('KIT_DELETE_CONFIRM_PASSWORD', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
    ],

    // `adopt`: the host owns spatie/laravel-permission's tables, so the kit's permission migration never creates them. Null reads the installed record, as above.
    'access' => ['adopt' => env('KIT_ACCESS_ADOPT')],

    // The hands-free deploy: the role and permission syncs run when migrations end. They skip under unit tests unless a test turns this on.
    'deploy' => ['sync_in_tests' => false],

    // MCP: the ability every connection must hold, the AI assistants settings page (hidden until the connection rework), the address the "add an assistant" cards use (default: this app's
    // /mcp) and which cards it offers, in order (claude-ai, chatgpt, claude-code, cursor, vscode), and how long, in seconds, a call's result is replayed for a retry with its intent_key.
    'mcp' => [
        'connection_ability' => 'settings.read',
        // Seeing and revoking other members' connections (the workspace.connections.manage permission: owners and admins); a person may always revoke their own.
        'manage_ability' => 'connections.manage',
        'ui' => filter_var(env('KIT_MCP_UI', false), FILTER_VALIDATE_BOOL),
        // Who runs the OAuth consent: 'kit' (the kit's Authorize page and connection row) or 'host' (an app with its own consent page and grants; Passport's approve as shipped).
        'consent' => env('KIT_MCP_CONSENT', 'kit'),
        'server_url' => env('KIT_MCP_SERVER_URL'),
        'assistants' => ['claude-ai', 'chatgpt', 'claude-code', 'cursor', 'vscode'],
        'intent_ttl' => 86400,
        // Turn off one of the kit's MCP tools by name, for an app that serves its own (an app-wide dashboard or search): `['workspace_dashboard' => false, 'workspace_search' => false]`.
        'tools' => [],
        // The workspace MCP server's name and instructions as assistants see them; null keeps the kit's ("Workspace operations").
        // `page_size`: how many tools, resources or prompts one list answer carries (some assistants never follow nextCursor); clamped to laravel/mcp's maximum (50).
        'server' => ['name' => env('KIT_MCP_SERVER_NAME'), 'instructions' => null, 'page_size' => 50],
        // Dynamic client registration (RFC 7591): assistants such as Claude register themselves as public OAuth clients at POST /oauth/register. Off by default (the route
        // does not exist). Registration is rate limited per IP; a policy class (Tey\Kit\Features\Mcp\Registration\RegistrationPolicy) may refuse a registration.
        'registration' => filter_var(env('KIT_MCP_REGISTRATION', false), FILTER_VALIDATE_BOOL),
        'registration_per_minute' => 10,
        'registration_policy' => null,
        // Private-use redirect schemes a native client may register (`cursor`, `vscode`), exact match; https, and http to a loopback host, are always allowed, and
        // javascript, data, blob and file never are. Empty: only https and loopback http.
        'redirect_schemes' => [],
        // The Blade view for the OAuth 400 page (a request that cannot go back to its app); null keeps the kit's. A closure through OAuthErrorPage::using() wins over both.
        'oauth_error_view' => null,
    ],
];
