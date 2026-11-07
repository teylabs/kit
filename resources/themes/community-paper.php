<?php

// Community Paper: Young Serif headings on Inter, a deep green accent and link on newsprint, tight corners. The picker's near-black header waits for a
// header-highlight token (the green accent is 2.3:1 on it as the active marker), so the header is the theme's own for now.
return [
    'label' => 'Community Paper',
    'status' => 'stable',
    'fonts' => ['heading' => 'Young Serif', 'body' => 'Inter', 'weights' => ['heading' => [400], 'body' => [400, 500, 600]]],
    'radius' => 4,
    'light' => [
        'accent' => '#2f5d50', 'accent_foreground' => '#ffffff', 'accent_strong' => '#2f5d50', 'ink' => '#1d1b18', 'header' => 'theme', 'header_rule' => '#2f5d50',
        'background' => '#f7f3ea', 'card' => '#fffdf7', 'border' => '#e2dbcc', 'link' => '#2f5d50',
    ],
    'dark' => [
        'accent' => '#7fbfab', 'accent_foreground' => '#10140f',
        'background' => '#12130f', 'card' => '#1b1c17', 'border' => '#33342b', 'muted' => '#23241e', 'link' => '#8fcbb8',
    ],
];
