<?php

namespace Tey\Kit\Themes;

use RuntimeException;

/**
 * The theme preset inventory and the preset in force. The inventory is the kit's `resources/themes/*.php`, then each directory in
 * `kit.themes.paths` (an app's own presets; a later file of the same name replaces an earlier one). The preset in force is, in order: the dev
 * switch's session choice (`?theme=`, only in `kit.themes.switch_envs` and never in production), then `kit.themes.default` (`KIT_THEME`), then
 * none, which leaves the plain `kit.brand` values exactly as they were.
 */
final class Themes
{
    /** The session key the dev switch keeps its choice under. */
    public const SESSION_KEY = 'kit.theme';

    /** @var array<string, array<string, Preset>> directories => the inventory, per process */
    private static array $loaded = [];

    /** @return array<string, Preset> name => preset, drafts included */
    public static function all(): array
    {
        $directories = array_values(array_filter([dirname(__DIR__, 2).'/resources/themes', ...array_map('strval', (array) config('kit.themes.paths', []))], fn (string $path): bool => $path !== ''));
        $key = implode("\0", $directories);
        if (isset(self::$loaded[$key])) {
            return self::$loaded[$key];
        }
        $presets = [];
        foreach ($directories as $directory) {
            foreach (glob(rtrim($directory, '/').'/*.php') ?: [] as $file) {
                $definition = require $file;
                if (! is_array($definition)) {
                    throw new RuntimeException("Theme preset file {$file} must return an array.");
                }
                $name = basename($file, '.php');
                $presets[$name] = Preset::fromArray($name, $definition);
            }
        }
        ksort($presets);

        return self::$loaded[$key] = $presets;
    }

    public static function find(?string $name): ?Preset
    {
        return $name === null || $name === '' ? null : (self::all()[$name] ?? null);
    }

    /**
     * The presets tenants may choose from: `kit.themes.offered` (null for every stable preset), never a draft.
     *
     * @return array<string, Preset>
     */
    public static function offered(): array
    {
        $offered = config('kit.themes.offered');
        $stable = array_filter(self::all(), fn (Preset $preset): bool => ! $preset->isDraft());

        return is_array($offered) ? array_intersect_key($stable, array_flip(array_filter($offered, 'is_string'))) : $stable;
    }

    /** The app's default preset (`kit.themes.default`, `KIT_THEME`), or null when unset. An unknown name is refused rather than ignored. */
    public static function default(): ?Preset
    {
        $name = config('kit.themes.default');
        if ($name === null || $name === '') {
            return null;
        }
        $preset = self::find(is_string($name) ? $name : null);
        if ($preset === null) {
            throw new RuntimeException('Unknown theme preset: '.(is_string($name) ? $name : get_debug_type($name)).'. Set KIT_THEME (kit.themes.default) to one of: '.implode(', ', array_keys(self::all())).', or leave it empty.');
        }

        return $preset;
    }

    /** Whether the dev switch works here: the environment is in `kit.themes.switch_envs` (local and staging by default), and it is never production. */
    public static function switchEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }
        $environments = config('kit.themes.switch_envs', ['local', 'staging']);

        return is_array($environments) && $environments !== [] && app()->environment(array_values(array_filter($environments, 'is_string')));
    }

    /** The dev switch's choice for this session (drafts included), or null. */
    public static function switched(): ?Preset
    {
        if (! app()->bound('request') || ! request()->hasSession() || ! self::switchEnabled()) {
            return null;
        }
        $name = request()->session()->get(self::SESSION_KEY);

        return is_string($name) ? self::find($name) : null;
    }

    /** The preset in force: the dev switch, then the app default, then none. */
    public static function active(): ?Preset
    {
        return self::switched() ?? self::default();
    }

    /** Forgets the loaded inventory (tests, and a long-running worker after a deploy). */
    public static function flush(): void
    {
        self::$loaded = [];
    }
}
