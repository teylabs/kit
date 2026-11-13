<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tey\Concierge\Support\ConciergeModels;

/**
 * The domain models and tables, by key (since 0.19): `Kit::model('delivery')`, `Kit::table('note_intent')`. The kit ships each model's behaviour (a
 * trait and a contract); the app owns the table and the model once it adopts the feature (`kit:scaffold <feature> --adopt`) and names them in
 * `kit.models` (and `kit.tables`, for a table the kit reads without a model). Kit code reaches every one of them through here, never through the kit's
 * own classes, which stay the default for an app that has not adopted: they keep the kit's table names, so such an app runs as before.
 *
 * Each feature's provider registers its own defaults (Kit::defaults()), so no feature names another's classes. The composition root
 * (Tey\Kit\Features\DomainModels) checks what config names against each key's contract.
 */
final class Kit
{
    /** @var array<string, class-string<Model>> key => the kit's own model */
    private static array $models = [];

    /** @var array<string, string> key => the kit's own table, for a table read without a model */
    private static array $tables = [];

    /**
     * Adds the kit's own models and tables, the defaults when `kit.models` and `kit.tables` leave a key unset or null. Each feature's provider adds its own.
     *
     * @param  array<string, class-string<Model>>  $models
     * @param  array<string, string>  $tables
     */
    public static function defaults(array $models, array $tables): void
    {
        self::$models = $models + self::$models;
        self::$tables = $tables + self::$tables;
    }

    /**
     * The model class for a key: `kit.models.<key>`, else the kit's own. `user` and `workspace` are the kit's existing bindings (Users::model(), and
     * Concierge's `concierge.models.workspace`).
     *
     * @return class-string<Model>
     */
    public static function model(string $key): string
    {
        if ($key === 'user') {
            return Users::model();
        }
        if ($key === 'workspace') {
            return ConciergeModels::workspace();
        }
        $default = self::$models[$key] ?? throw new RuntimeException('Unknown kit model: '.$key.' (no registered feature declares it; is its feature in kit.features?)');
        $configured = config('kit.models.'.$key);

        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    /** The table for a key: its model's table, or `kit.tables.<key>` (else the kit's own) for a table the kit reads without a model. */
    public static function table(string $key): string
    {
        if ($key === 'user' || $key === 'workspace' || isset(self::$models[$key])) {
            return (new (self::model($key)))->getTable();
        }
        $default = self::$tables[$key] ?? throw new RuntimeException('Unknown kit table: '.$key.' (no registered feature declares it; is its feature in kit.features?)');
        $configured = config('kit.tables.'.$key);

        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    /**
     * Registers each key's stable morph alias (`note` is `kit-note`, `delivery_recipient` is `kit-delivery-recipient`) against whichever class config
     * names, so stored rows (media, audit, an app's own morphs) never orphan when an app adopts. Once the app names its own model, a row stored under the
     * kit's class name, before the alias existed, resolves to the app's model too.
     */
    public static function aliases(string ...$keys): void
    {
        $map = [];
        foreach ($keys as $key) {
            $default = self::$models[$key] ?? throw new RuntimeException('Unknown kit model: '.$key);
            $class = self::model($key);
            $map['kit-'.str_replace('_', '-', $key)] = $class;
            if (ltrim($class, '\\') !== $default) {
                $map[$default] = $class;
            }
        }
        Relation::morphMap($map);
    }

    /** Whether a feature is on: the Registry's adopted features once the composition root built one, else `kit.features`. */
    public static function enabled(string $feature): bool
    {
        if (app()->bound(Registry::class)) {
            return app(Registry::class)->adopts($feature);
        }

        return in_array($feature, (array) config('kit.features', []), true);
    }

    /**
     * Whether to delete from $table, which $feature owns: true when the feature is on and the table exists. A feature that is off has nothing to
     * delete, so false. A feature that is on and has no table throws: a deletion never reports success while that feature's rows stay behind under
     * another name or in a table that was never migrated (#967).
     */
    public static function present(string $feature, string $table): bool
    {
        if (! self::enabled($feature)) {
            return false;
        }
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('The '.$feature.' feature is on but its table '.$table.' is missing, so nothing was deleted. Run php artisan migrate, or name the table the app uses in config/kit.php.');
        }

        return true;
    }
}
