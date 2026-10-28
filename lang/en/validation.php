<?php

// The kit's validation messages, loaded as `kit::validation` and used by the kit's own forms in place of Laravel's. Override them for the app in
// lang/vendor/kit/{locale}/validation.php.
return [
    'password' => [
        'uncompromised' => 'This password has appeared in a data breach, so it isn\'t safe. Pick another.',
    ],
];
