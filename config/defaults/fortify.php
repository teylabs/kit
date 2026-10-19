<?php

// The kit's fortify defaults, laid over the package's own; a published config/fortify.php replaces them.

return ['guard' => 'web', 'middleware' => ['web'], 'username' => 'email', 'email' => 'email', 'home' => '/workspaces', 'views' => true, 'features' => []];
