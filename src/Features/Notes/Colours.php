<?php

namespace Tey\Kit\Features\Notes;

/** The note colours, from `kit.notes.colours`: the first is the default. A key outside the list is refused as `colour` invalid. */
final class Colours
{
    public const DEFAULT = [['key' => 'yellow', 'label' => 'Yellow'], ['key' => 'pink', 'label' => 'Pink'], ['key' => 'blue', 'label' => 'Blue'], ['key' => 'green', 'label' => 'Green']];

    /** @return list<array{key: string, label: string}> */
    public static function options(): array
    {
        $colours = array_values(array_filter((array) config('kit.notes.colours', self::DEFAULT), fn ($colour): bool => is_array($colour) && is_string($colour['key'] ?? null) && is_string($colour['label'] ?? null)));

        return array_map(fn (array $colour): array => ['key' => $colour['key'], 'label' => $colour['label']], $colours === [] ? self::DEFAULT : $colours);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::options(), 'key');
    }

    public static function default(): string
    {
        return self::keys()[0];
    }
}
