<?php

namespace Tey\Kit\Themes;

use InvalidArgumentException;

/**
 * A theme preset: a named set of `kit.brand` colour tokens for light and dark mode, its fonts and its corner radius. `light` uses the
 * `kit.brand` keys as they are; `dark` uses the same unsuffixed names (`accent`, `background`, `link`…), which `values()` maps onto the
 * `_dark` keys. A `draft` preset is offered to the dev switch only, never to tenants.
 */
final readonly class Preset
{
    /** The colour and role keys a preset owns: every one is set by `values()`, null where the preset leaves it to the default. */
    public const KEYS = ['accent', 'accent_foreground', 'accent_strong', 'accent_dark', 'accent_dark_foreground', 'style', 'ink', 'button', 'button_dark', 'header', 'marker', 'header_rule', 'background', 'card', 'border', 'muted', 'background_dark', 'card_dark', 'border_dark', 'muted_dark', 'link', 'link_dark', 'foreground', 'muted_foreground', 'foreground_dark', 'muted_foreground_dark'];

    /** Dark key => the `kit.brand` key it sets. */
    private const DARK = ['accent' => 'accent_dark', 'accent_foreground' => 'accent_dark_foreground', 'button' => 'button_dark', 'background' => 'background_dark', 'card' => 'card_dark', 'border' => 'border_dark', 'muted' => 'muted_dark', 'link' => 'link_dark', 'foreground' => 'foreground_dark', 'muted_foreground' => 'muted_foreground_dark'];

    /**
     * @param  array{heading: string, body: string, display?: ?string, weights?: array<string, int|list<int>>, axes?: array<string, string>}  $fonts
     * @param  array<string, string>  $light
     * @param  array<string, string>  $dark
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $fonts,
        public array $light,
        public array $dark,
        public int $radius = 8,
        public string $status = 'stable',
    ) {
        if (preg_match('/\A[a-z0-9-]{1,32}\z/', $name) !== 1) {
            throw new InvalidArgumentException("A theme preset's name is 1 to 32 of a-z, 0-9 and -: {$name}.");
        }
        if (! in_array($status, ['stable', 'draft'], true)) {
            throw new InvalidArgumentException("Theme preset {$name}: status is stable or draft.");
        }
        if (! is_string($fonts['heading'] ?? null) || ! is_string($fonts['body'] ?? null)) {
            throw new InvalidArgumentException("Theme preset {$name}: fonts needs a heading and a body family.");
        }
        foreach (array_keys($light) as $key) {
            if (! in_array($key, self::KEYS, true) || str_ends_with($key, '_dark')) {
                throw new InvalidArgumentException("Theme preset {$name}: unknown light key {$key}.");
            }
        }
        foreach (array_keys($dark) as $key) {
            if (! isset(self::DARK[$key])) {
                throw new InvalidArgumentException("Theme preset {$name}: unknown dark key {$key} (one of ".implode(', ', array_keys(self::DARK)).').');
            }
        }
    }

    /** @param  array<string, mixed>  $definition  a `resources/themes/*.php` file's array */
    public static function fromArray(string $name, array $definition): self
    {
        return new self(
            name: $name,
            label: (string) ($definition['label'] ?? $name),
            fonts: (array) ($definition['fonts'] ?? []),
            light: (array) ($definition['light'] ?? []),
            dark: (array) ($definition['dark'] ?? []),
            radius: (int) ($definition['radius'] ?? 8),
            status: (string) ($definition['status'] ?? 'stable'),
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * The flat `kit.brand` colour keys, every one of `KEYS` present (null where unset), so a preset replaces the configured brand's colours
     * whole instead of mixing with them.
     *
     * @return array<string, ?string>
     */
    public function values(): array
    {
        $values = array_fill_keys(self::KEYS, null);
        foreach ($this->light as $key => $value) {
            $values[$key] = $value;
        }
        foreach ($this->dark as $key => $value) {
            $values[self::DARK[$key]] = $value;
        }

        return $values;
    }

    /**
     * The Bunny (fonts.bunny.net) families for the preset's faces: `slug:400,600|slug:…`.
     */
    public function bunnyFamilies(): string
    {
        $weights = (array) ($this->fonts['weights'] ?? []);
        $families = [];
        foreach (['heading', 'body', 'display'] as $role) {
            $family = $this->fonts[$role] ?? null;
            if (! is_string($family) || $family === '') {
                continue;
            }
            $slug = strtolower(str_replace(' ', '-', $family));
            $wanted = array_map('intval', (array) ($weights[$role] ?? [400]));
            $families[$slug] = array_values(array_unique(array_merge($families[$slug] ?? [], $wanted)));
        }

        return implode('|', array_map(function (string $slug, array $list): string {
            sort($list);

            return $slug.':'.implode(',', $list);
        }, array_keys($families), $families));
    }

    /** The heading weight (the first heading weight listed, 600 unless set). */
    public function headingWeight(): int
    {
        $weights = (array) (($this->fonts['weights'] ?? [])['heading'] ?? [600]);

        return (int) ($weights[0] ?? 600);
    }
}
