<?php

// Sunny DM Sans: Sunny Commons' palette with DM Sans throughout (800 headings, tight tracking): ink buttons with sunflower text, an orange rule and a
// burnt-orange link on cream. The picker's sunshine nav waits for header tokens.
return [
    'label' => 'Sunny DM Sans',
    'status' => 'draft',
    'fonts' => ['heading' => 'DM Sans', 'body' => 'DM Sans', 'weights' => ['heading' => [800], 'body' => [400, 500, 800]], 'tracking' => ['heading' => '-0.03em']],
    'radius' => 18,
    'light' => [
        'foreground' => '#2a2118', 'muted_foreground' => '#6a5a47',
        'accent' => '#2a2118', 'accent_foreground' => '#ffcf4a', 'accent_strong' => '#2a2118', 'header' => 'theme', 'header_rule' => '#ef6c3a',
        'background' => '#fffaf0', 'card' => '#ffffff', 'border' => '#f1e2c6', 'link' => '#b5471b',
    ],
    'dark' => [
        'foreground' => '#fbf3e6', 'muted_foreground' => '#c6b7a2',
        'accent' => '#ffcf4a', 'accent_foreground' => '#1f1810',
        'background' => '#16120c', 'card' => '#211b13', 'border' => '#3b3122', 'muted' => '#2a2218', 'link' => '#f59a6c',
    ],
];
