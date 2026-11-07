<?php

// Soft Studio: Fraunces (soft, wonky) headings on Source Sans 3, terracotta buttons and links on warm paper, a white header with an orange rule.
return [
    'label' => 'Soft Studio',
    'status' => 'stable',
    'fonts' => ['heading' => 'Fraunces', 'body' => 'Source Sans 3', 'weights' => ['heading' => [600], 'body' => [400, 600]], 'axes' => ['heading' => 'SOFT 100, WONK 1']],
    'radius' => 14,
    'light' => [
        'accent' => '#a8432a', 'accent_foreground' => '#ffffff', 'accent_strong' => '#a8432a', 'header' => 'theme', 'header_rule' => '#d9773b',
        'background' => '#fbf7f1', 'card' => '#ffffff', 'border' => '#eadfd3', 'link' => '#a8432a',
    ],
    'dark' => [
        'accent' => '#e98a6c', 'accent_foreground' => '#1c1512',
        'background' => '#17120f', 'card' => '#211a16', 'border' => '#3a2f28', 'muted' => '#2a211c', 'link' => '#f0a184',
    ],
];
