<?php

namespace Tey\Kit\Features\Settings;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Models\WorkspaceSecret;
use Tey\Kit\Features\Workspaces\AdoptableTables;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The values of the settings registered with Registry::setting(). Each resolves in one order: its environment variable when that is set, then the
 * stored value, then its default. An `app` setting is stored in the settings table (group `app`, encrypted when the setting is); a `workspace` one in
 * `workspace_secrets` for the current workspace, always encrypted. A workspace setting read outside a workspace has no stored value.
 */
final class SettingValues
{
    public const GROUP = 'app';

    public function get(string $key): mixed
    {
        return $this->resolve($key)['value'];
    }

    /** Where the value comes from: `env`, `database` or `default`. */
    public function source(string $key): string
    {
        return $this->resolve($key)['source'];
    }

    /** @return array{value: mixed, source: 'env'|'database'|'default'} */
    public function resolve(string $key): array
    {
        $setting = $this->definition($key);
        if ($setting['env'] !== null && ($raw = Env::get($setting['env'])) !== null) {
            return ['value' => self::cast($setting['type'], $raw), 'source' => 'env'];
        }
        $stored = $this->stored($setting);
        if ($stored !== null) {
            return ['value' => $stored[0], 'source' => 'database'];
        }

        return ['value' => $setting['default'], 'source' => 'default'];
    }

    /** Stores a value; it still yields to the environment variable while that is set. A workspace setting needs a current workspace. */
    public function put(string $key, mixed $value): void
    {
        $setting = $this->definition($key);
        $json = json_encode($value, JSON_THROW_ON_ERROR);
        if ($setting['scope'] === 'workspace') {
            WorkspaceSecret::query()->updateOrCreate(['workspace_id' => (int) Workspaces::required()->getKey(), 'key' => $key], ['value' => $json]);

            return;
        }
        $payload = $setting['encrypted'] ? json_encode(Crypt::encryptString($json)) : $json;
        DB::table(AdoptableTables::settingsTable())->upsert([['group' => self::GROUP, 'name' => $key, 'locked' => false, 'payload' => $payload, 'created_at' => now(), 'updated_at' => now()]], ['group', 'name'], ['payload', 'updated_at']);
    }

    public function forget(string $key): void
    {
        $setting = $this->definition($key);
        if ($setting['scope'] === 'workspace') {
            WorkspaceSecret::query()->where('workspace_id', (int) Workspaces::required()->getKey())->where('key', $key)->delete();

            return;
        }
        DB::table(AdoptableTables::settingsTable())->where('group', self::GROUP)->where('name', $key)->delete();
    }

    /** @return array{key: string, scope: string, type: string, default: mixed, encrypted: bool, env: ?string, check: ?callable} */
    private function definition(string $key): array
    {
        return app(Registry::class)->settings()[$key] ?? throw new RuntimeException('Unknown setting: '.$key);
    }

    /** @return ?array{0: mixed} the stored value, boxed so a stored null is told apart from none */
    private function stored(array $setting): ?array
    {
        if ($setting['scope'] === 'workspace') {
            $workspace = Workspaces::current();
            $json = $workspace === null ? null : WorkspaceSecret::query()->where('workspace_id', (int) $workspace->getKey())->where('key', $setting['key'])->first()?->value;
        } else {
            $payload = DB::table(AdoptableTables::settingsTable())->where('group', self::GROUP)->where('name', $setting['key'])->value('payload');
            $json = $payload === null ? null : ($setting['encrypted'] ? Crypt::decryptString((string) json_decode((string) $payload, true)) : (string) $payload);
        }

        return $json === null ? null : [json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
    }

    private static function cast(string $type, mixed $raw): mixed
    {
        return match ($type) {
            'bool' => is_bool($raw) ? $raw : filter_var($raw, FILTER_VALIDATE_BOOL),
            'int' => (int) $raw,
            'json' => is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : $raw,
            default => (string) $raw,
        };
    }
}
