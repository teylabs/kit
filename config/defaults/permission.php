<?php

// The kit's keys over spatie/laravel-permission's own defaults: workspace-scoped teams and an array cache.
return [
    'teams' => true,
    'column_names' => ['team_foreign_key' => 'workspace_id'],
    'cache' => ['store' => 'array'],
];
