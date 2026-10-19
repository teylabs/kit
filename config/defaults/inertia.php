<?php

// The kit's inertia defaults, laid over the package's own; a published config/inertia.php replaces them.

return ['ssr' => ['enabled' => false], 'testing' => ['ensure_pages_exist' => true, 'page_paths' => [resource_path('js/pages')], 'page_extensions' => ['vue']]];
