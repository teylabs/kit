<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Host tables the access and settings migrations adopt in place instead of creating: `entitlements` and `complimentary_grants` (access), and
 * spatie/laravel-settings' table (settings, `settings.repositories.database.table`, `settings` by default). A table the host has is adopted
 * only when it is compatible, and adopting it creates, adds and changes nothing; an incompatible one is refused with the columns it names.
 *
 * Compatible means: every column the kit reads or writes exists, in a compatible type family (integer, string, time, boolean, json); a column
 * the kit writes null into is nullable; `id` is an auto-incrementing key; the unique index the kit relies on exists (on the same columns, in any
 * order); and, on a table the kit inserts into, every other host column is nullable, has a default or is auto-incrementing, so the kit's inserts
 * succeed. Extra host columns and indexes are otherwise kept as they are. A schema the kit cannot read is a refusal, never a create.
 */
final class AdoptableTables
{
    /** Type families, by the lower-cased `type_name` Laravel's schema builder reports on SQLite, PostgreSQL and MySQL. */
    private const FAMILIES = [
        'integer' => ['integer', 'int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int2', 'int4', 'int8', 'serial', 'bigserial', 'smallserial'],
        'string' => ['varchar', 'char', 'bpchar', 'character varying', 'character', 'text', 'tinytext', 'mediumtext', 'longtext', 'string', 'nvarchar', 'citext'],
        'time' => ['timestamp', 'timestamptz', 'timestamp without time zone', 'timestamp with time zone', 'datetime', 'datetime2'],
        'boolean' => ['bool', 'boolean', 'tinyint', 'bit'],
        // SQLite has no json type: Laravel's json() is text there.
        'json' => ['json', 'jsonb', 'text', 'longtext'],
    ];

    /** What the kit needs of a table it adopts, per feature: column => [family, writes null], the unique index, and whether the kit inserts rows. */
    public static function specs(string $feature): array
    {
        return match ($feature) {
            'access' => [
                'entitlements' => ['columns' => ['id' => ['integer', false], 'workspace_id' => ['integer', false], 'kind' => ['string', false], 'revoked_at' => ['time', true]], 'unique' => null, 'inserts' => false],
                'complimentary_grants' => ['columns' => [
                    'id' => ['integer', false], 'workspace_id' => ['integer', false], 'granted_by' => ['integer', false], 'intent_key' => ['string', false], 'fingerprint' => ['string', false],
                    'revoked_by' => ['integer', true], 'revocation_reason' => ['string', true], 'reason' => ['string', false], 'expires_at' => ['time', false], 'revoked_at' => ['time', true],
                ], 'unique' => ['granted_by', 'intent_key'], 'inserts' => true],
            ],
            'settings' => [
                self::settingsTable() => ['columns' => [
                    'id' => ['integer', false], 'group' => ['string', false], 'name' => ['string', false], 'locked' => ['boolean', false], 'payload' => ['json', false],
                    'created_at' => ['time', true], 'updated_at' => ['time', true],
                ], 'unique' => ['group', 'name'], 'inserts' => true],
            ],
            default => throw new RuntimeException('No adoptable tables for '.$feature.'.'),
        };
    }

    public static function settingsTable(): string
    {
        $table = function_exists('config') ? config('settings.repositories.database.table') : null;

        return is_string($table) && $table !== '' ? $table : 'settings';
    }

    /**
     * The feature's tables as the host database has them: those it has and are compatible (adopt), and a conflict per table it has that is not.
     * A table it does not have is neither: the migration creates it. Throws when the database cannot say whether a table exists.
     *
     * @return array{adopt: list<string>, conflicts: array<string, list<string>>}
     */
    public static function inspect(string $feature): array
    {
        $outcome = ['adopt' => [], 'conflicts' => []];
        foreach (self::specs($feature) as $table => $spec) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $problems = self::problems($table, $spec);
            if ($problems === []) {
                $outcome['adopt'][] = $table;
            } else {
                $outcome['conflicts'][$table] = $problems;
            }
        }

        return $outcome;
    }

    /**
     * For a migration: the tables to adopt, or a refusal naming every incompatible table and its columns. Nothing is created or written here.
     *
     * @return list<string>
     */
    public static function adoptOrRefuse(string $feature): array
    {
        $outcome = self::inspect($feature);
        if ($outcome['conflicts'] !== []) {
            throw new RuntimeException('The kit cannot adopt the host\'s '.self::describe($outcome['conflicts']).'. Nothing was created or changed. Make the table compatible, or rename it, then migrate again.');
        }

        return $outcome['adopt'];
    }

    /** @param  array<string, list<string>>  $conflicts */
    public static function describe(array $conflicts): string
    {
        return implode('; ', array_map(fn (string $table, array $problems): string => $table.' table ('.implode(', ', $problems).')', array_keys($conflicts), $conflicts));
    }

    /** @return list<string> what keeps the kit from adopting $table: empty when it is compatible */
    private static function problems(string $table, array $spec): array
    {
        try {
            $columns = [];
            foreach (Schema::getColumns($table) as $column) {
                $columns[strtolower((string) $column['name'])] = $column;
            }
            $indexes = $spec['unique'] === null ? [] : Schema::getIndexes($table);
        } catch (Throwable $error) {
            return ['its schema could not be read: '.$error->getMessage()];
        }
        if ($columns === []) {
            return ['its schema could not be read: no columns reported'];
        }
        $missing = $incompatible = $notNull = $required = [];
        foreach ($spec['columns'] as $name => [$family, $writesNull]) {
            $column = $columns[$name] ?? null;
            if ($column === null) {
                $missing[] = $name;

                continue;
            }
            $type = strtolower(trim((string) ($column['type_name'] ?? '')));
            if (! in_array($type, self::FAMILIES[$family], true)) {
                $incompatible[] = $name.' is '.($type === '' ? 'of an unknown type' : $type).', not '.$family;
            } elseif ($name === 'id' && ! ($column['auto_increment'] ?? false)) {
                $incompatible[] = 'id is not auto-incrementing';
            } elseif ($writesNull && ! ($column['nullable'] ?? false)) {
                $notNull[] = $name;
            }
        }
        if ($spec['inserts']) {
            foreach ($columns as $name => $column) {
                if (! isset($spec['columns'][$name]) && ! ($column['nullable'] ?? false) && ($column['default'] ?? null) === null && ! ($column['auto_increment'] ?? false) && ($column['generation'] ?? null) === null) {
                    $required[] = $name;
                }
            }
        }
        $problems = [];
        if ($missing !== []) {
            $problems[] = 'missing columns '.implode(', ', $missing);
        }
        if ($incompatible !== []) {
            $problems[] = 'incompatible columns: '.implode('; ', $incompatible);
        }
        if ($notNull !== []) {
            $problems[] = 'not nullable, but the kit writes null: '.implode(', ', $notNull);
        }
        if ($required !== []) {
            $problems[] = 'host columns the kit\'s inserts would leave empty (not nullable, no default): '.implode(', ', $required);
        }
        if ($spec['unique'] !== null && ! self::hasUnique($indexes, $spec['unique'])) {
            $problems[] = 'no unique index on ('.implode(', ', $spec['unique']).')';
        }

        return $problems;
    }

    private static function hasUnique(array $indexes, array $columns): bool
    {
        $want = $columns;
        sort($want);
        foreach ($indexes as $index) {
            $have = array_map(fn (mixed $column): string => strtolower((string) $column), (array) ($index['columns'] ?? []));
            sort($have);
            if ((($index['unique'] ?? false) || ($index['primary'] ?? false)) && $have === $want) {
                return true;
            }
        }

        return false;
    }
}
