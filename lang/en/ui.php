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
    // AddressFieldset and AddressText. The region and postal-code labels follow the country.
    'address' => [
        'line1' => 'Street address',
        'line2' => 'Apartment, suite or unit',
        'city' => 'City',
        'country' => 'Country',
        'region' => 'Region',
        'region_ca' => 'Province or territory',
        'region_us' => 'State',
        'postal_code' => 'Postal code',
        'postal_code_ca' => 'Postal code',
        'postal_code_us' => 'ZIP code',
        'postal_hint_ca' => 'Canadian postal codes look like A1A 1A1.',
        'postal_hint_us' => 'ZIP codes look like 12345 or 12345-6789.',
        'region_placeholder' => 'Choose',
        'country_placeholder' => 'Choose a country',
        'map' => 'View on a map',
    ],
    // InputTags: why a tag was refused. `:tag` is the tag, `:count` the limit.
    'tags' => [
        'duplicate' => '“:tag” is already added.',
        'too_long' => 'Tags are at most :count characters.',
        'invalid' => '“:tag” is not a valid tag.',
        'too_many' => 'At most :count tags.',
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
