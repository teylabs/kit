<?php

// Market Grotesk: Bricolage Grotesque throughout, a rust accent on oat paper. The picker's olive header waits for a header-highlight token: the kit's ink header
// marks the active item with the accent, which is 1.8:1 on olive, so the header is the theme's own for now.
return [
    'label' => 'Market Grotesk',
    'status' => 'stable',
    'fonts' => ['heading' => 'Bricolage Grotesque', 'body' => 'Bricolage Grotesque', 'weights' => ['heading' => [700], 'body' => [400, 500, 600]], 'axes' => ['heading' => 'opsz 12..96']],
    'radius' => 10,
    'light' => [
        'accent' => '#b44f2a', 'accent_foreground' => '#ffffff', 'accent_strong' => '#9c3f1f', 'ink' => '#3f4a2a', 'header' => 'theme', 'marker' => 'strong', 'header_rule' => '#c2562f',
        'background' => '#f6f3ec', 'card' => '#fffdf8', 'border' => '#ddd6c6', 'link' => '#9c3f1f',
    ],
    'dark' => [
        'accent' => '#e7895f', 'accent_foreground' => '#15170f',
        'background' => '#13150f', 'card' => '#1c1f16', 'border' => '#343a28', 'muted' => '#23271b', 'link' => '#efa27f',
    ],
];
