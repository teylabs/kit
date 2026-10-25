<?php

// The kit's UI copy, loaded as `kit::ui` and shared with the page as `kitUi`. Override it for the app in lang/vendor/kit/{locale}/ui.php.
// `:count`, `:label` and `:query` are replaced on the page.
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
];
