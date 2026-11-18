<?php

namespace Tey\Kit\Features\Workspaces\Flags;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tey\Kit\Features\Core\Tables\TableQuery;

/**
 * Flags: facts the system (or a person) raises on a record, stored as spatie/laravel-tags tags of type `flag`. A new flag is applied, not declared: an
 * undefined flag works and shows neutral, labelled from its key. `define()` (once, in a service provider) only sets how one is shown: its label, its
 * tone, and whether people may dismiss it ("Not a duplicate"). Apply and remove flags where the state they describe changes, through HasFlags.
 */
final class Flags
{
    public const TYPE = 'flag';

    /** The tones in the order flags are shown: the most severe first. */
    public const SEVERITY = ['danger', 'warning', 'orange', 'pink', 'purple', 'info', 'teal', 'success', 'slate', 'neutral'];

    /** @var array<string, array{key: string, label: string, tone: string, dismissible: bool, order: int}> */
    private static array $defined = [];

    public static function define(string $key, ?string $label = null, string $tone = 'neutral', bool $dismissible = false): void
    {
        $key = self::key($key);
        self::$defined[$key] = ['key' => $key, 'label' => $label ?? Str::headline($key), 'tone' => $tone, 'dismissible' => $dismissible, 'order' => self::$defined[$key]['order'] ?? count(self::$defined)];
    }

    /** @return array{key: string, label: string, tone: string, dismissible: bool, order: int} */
    public static function definition(string $key): array
    {
        $key = self::key($key);

        return self::$defined[$key] ?? ['key' => $key, 'label' => Str::headline($key), 'tone' => 'neutral', 'dismissible' => false, 'order' => PHP_INT_MAX];
    }

    /** @return array<string, array{key: string, label: string, tone: string, dismissible: bool, order: int}> */
    public static function defined(): array
    {
        return self::$defined;
    }

    /**
     * A table's "Flags" filter (`filter[flags]=unpaid,stalled`): the rows raised with any of the ticked flags. FilterPopover shows it as a group of
     * checkboxes. `$keys` are the flags offered, in order; by default every defined flag.
     *
     * @template TTable of TableQuery
     *
     * @param  TTable  $table
     * @param  list<string>|null  $keys
     * @return TTable
     */
    public static function filter(TableQuery $table, ?array $keys = null, string $label = 'Flags', string $name = 'flags'): TableQuery
    {
        $options = [];
        foreach ($keys ?? array_keys(self::$defined) as $key) {
            $options[self::key($key)] = self::definition($key)['label'];
        }

        return $table->filter($name, fn (Builder $query, string $value) => $query->flagged(...explode(',', $value)), $label, $options, multiple: true);
    }

    /** A flag's key is its slug: `possible-duplicate`. */
    public static function key(string $key): string
    {
        return Str::slug($key);
    }

    /** For tests: forget every definition. */
    public static function flush(): void
    {
        self::$defined = [];
    }
}
