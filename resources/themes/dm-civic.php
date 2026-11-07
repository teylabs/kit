<?php

// DM Civic: DM Sans throughout at 700 with tight tracking, blue actions and links on pale sage, and a lime, teal, yellow and red striped rule under
// the header. The picker's near-black nav waits for a header-highlight token (the blue accent is under 4.5:1 on it as the active marker), so the
// header is the theme's own for now; its plum accent has no role token yet.
return [
    'label' => 'DM Civic',
    'status' => 'draft',
    'fonts' => ['heading' => 'DM Sans', 'body' => 'DM Sans', 'weights' => ['heading' => [700], 'body' => [400, 500, 700]], 'tracking' => ['heading' => '-0.03em']],
    'radius' => 10,
    'light' => [
        'foreground' => '#1a181b', 'muted_foreground' => '#504d40',
        'accent' => '#146bad', 'accent_foreground' => '#ffffff', 'accent_strong' => '#146bad', 'header' => 'theme', 'header_rule' => ['#c2c62a', '#1fbbbf', '#f9c319', '#fd4630'],
        'background' => '#f3f5e7', 'card' => '#ffffff', 'border' => '#e4e3d6', 'link' => '#0a56a3',
    ],
    'dark' => [
        'foreground' => '#eef0e4', 'muted_foreground' => '#b5b7a8',
        'accent' => '#6fb0e8', 'accent_foreground' => '#0e1116',
        'background' => '#121410', 'card' => '#1b1d18', 'border' => '#33362c', 'muted' => '#24261f', 'link' => '#8cc0f0',
    ],
];
