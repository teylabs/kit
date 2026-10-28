<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The host tables the kit's migrations adopted in place rather than created, recorded when the adoption happens in `kit_adoptions` (made by the
 * first adoption; a fresh kit install never has it). Each row names the table and the columns the kit added to it. A down() migration reads the
 * record: an adopted table is never dropped, and only the columns the record says the kit added are.
 */
final class Adoptions
{
    public const TABLE = 'kit_adoptions';

    /** Records that the kit adopted $table, adding $columns (merged with what an earlier kit migration added). */
    public static function record(string $table, array $columns = []): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $t): void {
                $t->string('table')->primary();
                $t->text('columns_added');
                $t->timestamp('adopted_at');
            });
        }
        $added = array_values(array_unique([...self::added($table), ...$columns]));
        DB::table(self::TABLE)->updateOrInsert(['table' => $table], ['columns_added' => json_encode($added, JSON_THROW_ON_ERROR), 'adopted_at' => DB::table(self::TABLE)->where('table', $table)->value('adopted_at') ?? now()]);
    }

    public static function adopted(string $table): bool
    {
        return Schema::hasTable(self::TABLE) && DB::table(self::TABLE)->where('table', $table)->exists();
    }

    /** @return list<string> the columns the kit added to an adopted table */
    public static function added(string $table): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }
        $row = DB::table(self::TABLE)->where('table', $table)->value('columns_added');

        return $row === null ? [] : json_decode((string) $row, true, flags: JSON_THROW_ON_ERROR);
    }

    /** Takes $columns off the record of $table, or the whole record when $columns is null; the record table goes once it is empty. */
    public static function forget(string $table, ?array $columns = null): void
    {
        if (! self::adopted($table)) {
            return;
        }
        if ($columns === null) {
            DB::table(self::TABLE)->where('table', $table)->delete();
        } else {
            DB::table(self::TABLE)->where('table', $table)->update(['columns_added' => json_encode(array_values(array_diff(self::added($table), $columns)), JSON_THROW_ON_ERROR)]);
        }
        if (! DB::table(self::TABLE)->exists()) {
            Schema::drop(self::TABLE);
        }
    }
}
