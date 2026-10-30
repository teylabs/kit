<?php

// The kit's UI copy, loaded as `kit::ui` and shared with the page as `kitUi`. Override it for the app in lang/vendor/kit/{locale}/ui.php.
// `:count`, `:label`, `:query` and `:seconds` are replaced on the page.
return [
    'select' => [
        'empty' => 'No results',
        'empty_query' => 'No results for “:query”',
        'too_short' => 'Type at least :count characters',
        'loading' => 'Loading…',
        'count' => ':count selected',
        'chips' => 'Selected',
        'remove' => 'Remove :label',
        'clear' => 'Clear',
        'clear_all' => 'Clear all',
        'select_all' => 'Select all',
        'deselect_all' => 'Deselect all',
        'create' => 'Create “:query”',
        'none' => 'None',
        'live' => [
            'results' => ':count results',
            'result' => '1 result',
            'no_results' => 'No results',
            'added' => 'Added: :label',
            'removed' => 'Removed: :label',
            'cleared' => 'Cleared',
            'created' => 'Created: :label',
        ],
    ],
    'popover' => [
        'close' => 'Close',
    ],
    // A form refused by a rate limiter (429): `throttled_retry` when the response says how long (Retry-After, in seconds), else `throttled`.
    'form' => [
        'throttled' => 'Too many requests. Try again in a moment.',
        'throttled_retry' => 'Too many requests. Try again in :seconds seconds.',
    ],
    // The answer to a reset-link request, the same whether or not the address has an account.
    'auth' => [
        'reset_link_sent' => 'If an account exists for that email, we\'ve sent a password reset link.',
    ],
];
