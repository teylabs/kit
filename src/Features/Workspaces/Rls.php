<?php

namespace Tey\Kit\Features\Workspaces;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * PostgreSQL row-level security: a second tenant lock under the app's own workspace scoping. Opt-in (`kit.tenancy.rls.enabled`) and PostgreSQL only;
 * on any other driver every method here is a no-op and nothing is enforced by the database.
 *
 * A protected table carries the policy `workspace_id = the transaction's kit.workspace_id`. The id is set only with `set_config(..., true)` (SET LOCAL)
 * inside a transaction, never for the session, so it ends with the transaction and cannot reach the next request or job on a reused or pooled
 * connection. With no id set, a protected table shows no rows and refuses every insert.
 */
final class Rls
{
    /** The transaction setting the policies read. */
    public const SETTING = 'kit.workspace_id';

    /** The policy's name on every protected table. */
    public const POLICY = 'kit_workspace';

    /** Whether RLS is on and the runtime connection is PostgreSQL. */
    public static function enabled(): bool
    {
        return (bool) config('kit.tenancy.rls.enabled', false) && self::driver(self::connection()) === 'pgsql';
    }

    /** The runtime (app) connection: NOBYPASSRLS, not the tables' owner. */
    public static function connection(): string
    {
        return (string) (config('kit.tenancy.rls.connection') ?? config('database.default'));
    }

    /**
     * Runs `$fn` with the workspace id set for the transaction. Outside a transaction it opens one around `$fn` (a request-wide or job-wide
     * transaction); inside one it sets the id for the rest of `$fn` and puts the previous value back afterwards, also when `$fn` throws.
     * Off, or on another driver, it just runs `$fn`.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function within(int $workspaceId, Closure $fn): mixed
    {
        if (! self::enabled()) {
            return $fn();
        }
        $db = DB::connection(self::connection());
        if ($db->transactionLevel() === 0) {
            return $db->transaction(function () use ($db, $workspaceId, $fn): mixed {
                self::set($db, (string) $workspaceId);

                return $fn();
            });
        }
        $previous = self::read($db);
        self::set($db, (string) $workspaceId);
        try {
            return $fn();
        } finally {
            try {
                self::set($db, $previous ?? '');
            } catch (Throwable) {
                // An aborted transaction refuses every statement; it rolls back, and the setting with it.
            }
        }
    }

    /** The workspace id the current transaction carries, or null (none set, RLS off, or another driver). */
    public static function current(): ?int
    {
        if (! self::enabled()) {
            return null;
        }
        $value = self::read(DB::connection(self::connection()));

        return $value === null ? null : (int) $value;
    }

    /**
     * Migration helper, run after the table exists: enables and forces row-level security on `$table` and (re)creates the workspace policy. Idempotent.
     * A no-op while RLS is off or the migration connection is not PostgreSQL. Run migrations as the owner (the migrator/admin connection).
     */
    public static function protect(string $table, string $column = 'workspace_id'): void
    {
        $db = DB::connection();
        if (! config('kit.tenancy.rls.enabled', false) || $db->getDriverName() !== 'pgsql') {
            return;
        }
        $t = self::identifier($table);
        $c = self::identifier($column);
        $match = $c.' = nullif(current_setting(\''.self::SETTING.'\', true), \'\')::bigint';
        $db->statement('alter table '.$t.' enable row level security');
        $db->statement('alter table '.$t.' force row level security');
        $db->statement('drop policy if exists '.self::POLICY.' on '.$t);
        $db->statement('create policy '.self::POLICY.' on '.$t.' using ('.$match.') with check ('.$match.')');
    }

    /** The reverse of protect(), for a migration's down(). */
    public static function unprotect(string $table): void
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'pgsql') {
            return;
        }
        $t = self::identifier($table);
        $db->statement('drop policy if exists '.self::POLICY.' on '.$t);
        $db->statement('alter table '.$t.' no force row level security');
        $db->statement('alter table '.$t.' disable row level security');
    }

    /**
     * The guard: tables in the current schema with a workspace_id column that lack RLS, FORCE or the policy, less `kit.tenancy.rls.except`.
     *
     * @return list<string>
     */
    public static function unprotected(?string $connection = null, string $column = 'workspace_id'): array
    {
        $db = DB::connection($connection ?? self::connection());
        if ($db->getDriverName() !== 'pgsql') {
            return [];
        }
        $rows = $db->select(<<<'SQL'
            select c.relname as name from pg_class c join pg_namespace n on n.oid = c.relnamespace
            where c.relkind in ('r', 'p') and n.nspname = current_schema()
              and exists (select 1 from pg_attribute a where a.attrelid = c.oid and a.attname = ? and not a.attisdropped)
              and not (c.relrowsecurity and c.relforcerowsecurity and exists (select 1 from pg_policy p where p.polrelid = c.oid and p.polname = ?))
            order by c.relname
            SQL, [$column, self::POLICY]);
        $except = (array) config('kit.tenancy.rls.except', []);

        return array_values(array_filter(array_map(fn (object $row): string => (string) $row->name, $rows), fn (string $name): bool => ! in_array($name, $except, true)));
    }

    /**
     * Why RLS is not enforced as configured, for the preflight; empty when it is, or when it is off. Fails closed: a runtime role that is a superuser,
     * has BYPASSRLS or owns a table in the schema, an unprotected workspace table, or a runtime connection that cannot be reached is each a problem.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        if (! config('kit.tenancy.rls.enabled', false)) {
            return [];
        }
        $name = self::connection();
        if (self::driver($name) !== 'pgsql') {
            return [];
        }
        if ($name === config('kit.tenancy.rls.admin_connection')) {
            return ['the runtime connection '.$name.' is the admin connection: the app would run as the migrator'];
        }
        try {
            $db = DB::connection($name);
            $role = $db->selectOne('select current_user as name, rolsuper as super, rolbypassrls as bypass from pg_roles where rolname = current_user');
            $owned = $db->select('select tablename from pg_tables where schemaname = current_schema() and tableowner = current_user order by tablename');
            $unprotected = self::unprotected($name);
        } catch (Throwable $error) {
            return ['the runtime connection '.$name.' cannot be checked ('.get_class($error).')'];
        }
        $problems = [];
        if ($role->super) {
            $problems[] = 'the runtime role '.$role->name.' is a superuser, which bypasses RLS';
        }
        if ($role->bypass) {
            $problems[] = 'the runtime role '.$role->name.' has BYPASSRLS';
        }
        if ($owned !== []) {
            $problems[] = 'the runtime role '.$role->name.' owns '.implode(', ', array_map(fn (object $row): string => $row->tablename, $owned)).' (an owner can turn RLS off)';
        }
        if ($unprotected !== []) {
            $problems[] = 'workspace tables without the RLS policy: '.implode(', ', $unprotected).' (protect them with Rls::protect() in a migration, or list them in kit.tenancy.rls.except)';
        }

        return $problems;
    }

    /**
     * Creates the runtime role, or brings an existing one back to the runtime shape, on the current (admin) connection: LOGIN, NOSUPERUSER, NOBYPASSRLS,
     * NOINHERIT, no CREATEDB or CREATEROLE; DML on every table in the schema (no TRUNCATE, which skips RLS) and the sequences, now and, through
     * ALTER DEFAULT PRIVILEGES, for tables the current role creates later. A null password leaves an existing role's password alone.
     */
    public static function grantRuntimeRole(string $role, ?string $password, ?Connection $db = null): void
    {
        $db ??= DB::connection();
        if ($db->getDriverName() !== 'pgsql') {
            throw new RuntimeException('The runtime role is PostgreSQL only.');
        }
        $r = self::identifier($role);
        $exists = $db->selectOne('select 1 as found from pg_roles where rolname = ?', [$role]) !== null;
        if (! $exists && ($password === null || $password === '')) {
            throw new InvalidArgumentException('A new runtime role needs a password.');
        }
        $options = 'login nosuperuser nobypassrls noinherit nocreatedb nocreaterole';
        if ($password !== null && $password !== '') {
            $options .= ' password '.$db->getPdo()->quote($password);
        }
        $db->statement(($exists ? 'alter role ' : 'create role ').$r.' '.$options);
        $schema = self::identifier((string) $db->selectOne('select current_schema() as name')->name);
        $db->statement('grant usage on schema '.$schema.' to '.$r);
        $db->statement('grant select, insert, update, delete on all tables in schema '.$schema.' to '.$r);
        $db->statement('grant usage, select, update on all sequences in schema '.$schema.' to '.$r);
        $db->statement('alter default privileges in schema '.$schema.' grant select, insert, update, delete on tables to '.$r);
        $db->statement('alter default privileges in schema '.$schema.' grant usage, select, update on sequences to '.$r);
    }

    private static function set(Connection $db, string $value): void
    {
        // Always a bound parameter, and always `true` (SET LOCAL): never a session-level setting.
        $db->select('select set_config(?, ?, true)', [self::SETTING, $value]);
    }

    private static function read(Connection $db): ?string
    {
        $value = $db->selectOne('select nullif(current_setting(?, true), \'\') as id', [self::SETTING])->id;

        return $value === null ? null : (string) $value;
    }

    private static function driver(string $connection): ?string
    {
        $driver = config('database.connections.'.$connection.'.driver');

        return is_string($driver) ? $driver : null;
    }

    private static function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Not a plain identifier: '.$name);
        }

        return '"'.$name.'"';
    }
}
