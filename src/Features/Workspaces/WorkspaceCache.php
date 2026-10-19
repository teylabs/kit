<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\Cache;
use Tey\Kit\Features\Workspaces\Models\Workspace;

final class WorkspaceCache
{
    public function put(string $key, mixed $value): void
    {
        Cache::put($this->key($key), $value, 60);
    }

    public function get(string $key): mixed
    {
        return Cache::get($this->key($key));
    }

    private function key(string $key): string
    {
        return 'workspace:'.Workspace::required()->getKey().':'.$key;
    }
}
