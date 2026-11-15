<?php

namespace Tey\Kit\Features\Settings;

use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsMapper;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Workspaces\Workspaces;

class WorkspaceSettings extends Settings
{
    private ?int $boundWorkspaceId = null;

    public string $display_name = 'New workspace';

    public string $timezone = 'UTC';

    public static function group(): string
    {
        return 'workspace.'.Workspaces::required()->getKey();
    }

    public static function cacheKey(): string
    {
        return static::group();
    }

    public function save(): self
    {
        if ($this->boundWorkspaceId !== Workspaces::required()->getKey()) {
            throw new \RuntimeException('workspace_mismatch');
        }
        // The mapper is shared by the package; initialise it for this current
        // workspace even when a different settings instance ran in between.
        app(SettingsMapper::class)->initialize(static::class);
        try {
            return parent::save();
        } finally {
            RequestMemo::instance()->flush();
        }
    }

    /**
     * The current workspace's settings. Never a scoped or singleton binding (a worker can visit many tenants): read once per request for each workspace
     * (RequestMemo, keyed by its group), and each caller gets its own copy, so a change one makes without saving reaches no other. Saving empties the memo.
     */
    public static function current(): self
    {
        $settings = clone RequestMemo::instance()->get('settings:'.static::group(), function (): self {
            $loaded = new self;
            $loaded->toArray();

            return $loaded;
        });
        $settings->boundWorkspaceId = Workspaces::required()->getKey();

        return $settings;
    }
}
