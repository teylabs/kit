<?php

// Appraisal: Instrument Sans headings on Inter, ink buttons and header, a gold rule and highlight, a rust link.
return [
    'label' => 'Appraisal',
    'status' => 'stable',
    'fonts' => ['heading' => 'Instrument Sans', 'body' => 'Inter', 'weights' => ['heading' => [600], 'body' => [400, 500, 600]]],
    'radius' => 8,
    'light' => [
        'style' => 'mono', 'ink' => '#28231f', 'accent' => '#deb100', 'accent_strong' => '#806600', 'header_rule' => '#deb100',
        'background' => '#fafafa', 'card' => '#ffffff', 'border' => '#e4e4e7', 'link' => '#b65326',
    ],
    'dark' => [
        'accent' => '#deb100', 'accent_foreground' => '#1a1714', 'button' => 'accent',
        'background' => '#141210', 'card' => '#1e1b18', 'border' => '#36312c', 'muted' => '#25211d', 'link' => '#eb9a6b',
    ],
];
