# Row-level security (PostgreSQL)

The kit's tenant model is a shared schema: every workspace table carries `workspace_id`, and the app scopes each query to the current workspace. Row-level security (RLS) adds a second lock under that scoping, in the database itself. If a query forgets its `where workspace_id = ?`, PostgreSQL still returns only the current workspace's rows and refuses writes into any other workspace.

It is opt-in (`kit.tenancy.rls.enabled`, off by default) and PostgreSQL only. **On any other driver it is a no-op:** nothing is set, nothing is checked, and the database enforces nothing. SQLite has no RLS.

## How it works

- **The policy.** `Rls::protect('notes')` enables and forces RLS on the table and creates the policy `kit_workspace`:
  `USING / WITH CHECK (workspace_id = nullif(current_setting('kit.workspace_id', true), '')::bigint)`.
  With no workspace set, the table shows no rows and refuses every insert.
- **Per transaction, never per session.** The workspace id is set only with `set_config('kit.workspace_id', ?, true)` (that is, `SET LOCAL`), always as a bound parameter, inside a transaction. It ends with the transaction, on commit or rollback. A session-level `SET` would carry over to the next request or job on a reused connection (PHP-FPM, Octane, a queue worker) or, behind a transaction pooler, to another client entirely. The kit never issues one.
- **Requests.** `Workspaces::run()` runs its closure inside `Rls::within($workspaceId, ...)`. `ResolveWorkspace` already wraps the rest of a workspace request in `Workspaces::run()`, so with RLS on, each workspace request runs in **one request-wide transaction** that carries the workspace id. Membership admission runs before it, outside the workspace. A nested `Workspaces::run()` (or `Rls::within()`) inside an open transaction sets the id for its closure only and puts the previous value back afterwards, even when the closure throws.
- **Jobs.** With RLS on, the queue runs jobs through `RlsQueuedHandler`. Each job runs in one transaction carrying the workspace spatie restored from its payload. A job with no current workspace **fails closed** (`rls_workspace_required`) before its middleware or `handle()` runs. A tenant-aware job queued with no workspace already fails in spatie (`CurrentTenantCouldNotBeDeterminedInTenantAwareJob`). A job declared not tenant-aware (spatie's `NotTenantAware`, or `multitenancy.not_tenant_aware_jobs`) runs with no workspace id, so protected tables show it nothing and refuse its inserts. Use the admin connection explicitly for central work across workspaces.
- **Two connections.** The app's runtime connection logs in as a role with `NOBYPASSRLS` that owns no table. The admin connection logs in as the migrator, which owns the tables and (on most hosts) has `BYPASSRLS`. Migrations, seeders and operator tasks use the admin connection. The runtime role never gets a path to bypass, which is why the kit uses two connections rather than `SET ROLE`.

## Setup

1. **Connections** (`config/database.php`): keep the default `pgsql` connection as the runtime one, and add an admin connection with the migrator's credentials.

   ```php
   'pgsql' => [/* ... */ 'username' => env('DB_RUNTIME_USERNAME'), 'password' => env('DB_RUNTIME_PASSWORD')],
   'pgsql_admin' => [/* same host and database */ 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD')],
   ```

2. **The runtime role**, created once on the admin connection. Either use the command (the password is read from an environment variable, never an argument):

   ```sh
   DB_RUNTIME_PASSWORD=... php artisan kit:rls:role app_runtime --database=pgsql_admin
   ```

   or call the same helper from a migration that runs on the admin connection:

   ```php
   public function up(): void
   {
       \Tey\Kit\Features\Workspaces\Rls::grantRuntimeRole(env('DB_RUNTIME_USERNAME'), env('DB_RUNTIME_PASSWORD'));
   }
   ```

   The role is `LOGIN NOSUPERUSER NOBYPASSRLS NOINHERIT NOCREATEDB NOCREATEROLE`, with `SELECT, INSERT, UPDATE, DELETE` on every table in the schema and `USAGE, SELECT, UPDATE` on the sequences. `ALTER DEFAULT PRIVILEGES` grants the same on tables the migrator creates later. It gets no `TRUNCATE`, because `TRUNCATE` skips RLS. Running it again brings an existing role back to that shape.

3. **Turn it on**: `KIT_RLS=true`. The config keys are `kit.tenancy.rls.enabled`, `connection` (the runtime connection, null for the default), `admin_connection` (default `pgsql_admin`) and `except`.

4. **Protect the workspace tables** in migrations, run as the admin (`php artisan migrate --database=pgsql_admin`):

   ```php
   Schema::create('notes', function (Blueprint $table) { /* ... */ $table->foreignId('workspace_id')->index(); });
   Rls::protect('notes');
   // down(): Rls::unprotect('notes');
   ```

   `protect()` is idempotent, and it does nothing while RLS is off or the connection is not PostgreSQL. A table that existed before you turned RLS on needs a new migration that calls `protect()`. Lead any composite index with `workspace_id`.

5. **Preflight.** `php artisan kit:preflight` fails when RLS is on (PostgreSQL) and:
   - the runtime role is a superuser, has `BYPASSRLS`, or owns any table in the schema (an owner can turn RLS off);
   - the runtime connection is the admin connection;
   - a table with a `workspace_id` column lacks RLS, `FORCE` or the policy, and isn't listed in `kit.tenancy.rls.except`;
   - the runtime connection cannot be reached.

   `Rls::unprotected()` returns the same list of unprotected tables, for an architecture test.

## Shared tables (`except`)

Some tables have a `workspace_id` column but are read before a workspace is chosen, or hold rows for no workspace. They stay unprotected and are listed in `kit.tenancy.rls.except`:

- Concierge's membership tables, which admission reads before the workspace transaction starts;
- the permission tables (`roles`, `model_has_roles`, `model_has_permissions`), whose team column is `workspace_id` and which hold global roles.

The kit's other tables with a `workspace_id` column (`audit_events`, `kit_notes`, `entitlements`, settings, MCP and delivery tables, and so on) have **not been verified under RLS**. Some are read outside a workspace, or keep rows whose `workspace_id` is null. Protect each one in your own migration once your app's paths are checked, or list it in `except`. The preflight lists every one you haven't decided on.

## Pitfalls

- **Silent zero-row writes.** An `UPDATE` or `DELETE` of another workspace's row affects 0 rows; it doesn't raise an error. Your 404s still come from app scoping. Only inserts and moves (`UPDATE ... SET workspace_id`) raise an error.
- **The outermost transaction.** A workspace request or job holds one transaction for its whole length. `DB::transaction()` inside it becomes a savepoint, which is fine. Code that calls `DB::commit()` early ends the transaction and loses the setting, so later queries see nothing. Streamed responses and slow external calls keep the transaction open, so move them to a job, or out of the workspace closure.
- **Errors rendered as responses.** The router turns a controller exception into a response before the transaction ends, so the transaction still commits. That matches running without RLS, where nothing was transactional. Use `DB::transaction()` in the action for all-or-nothing writes.
- **Seeders and tinker.** On the runtime connection, a seeder inserts nothing and fails the policy's check. Seed as the admin, or inside `Workspaces::run()`. Tinker on the default connection sees no workspace rows (failing closed). Operators use the admin connection explicitly, and that access should be audited.
- **Poolers (PgBouncer in transaction mode).** `SET LOCAL` inside a transaction is safe. Never use session-level `SET`, `SET ROLE` or `search_path` changes, and avoid SQL-level `PREPARE` (protocol-level prepared statements and PDO's emulated prepares are fine).
- **Advisory locks and LISTEN.** Session-scoped advisory locks (`pg_advisory_lock`, the database cache store's locks, `withoutOverlapping` and `onOneServer` on a database cache) and `LISTEN/NOTIFY` don't work through a transaction pooler: the next transaction may land on a different server connection. Use `pg_advisory_xact_lock` (released at commit) or Redis locks, and run `LISTEN` over a direct (non-pooled) connection.
- **Long-lived workers.** A queue worker or Octane process keeps one connection for many jobs or requests. That is safe only because every setting is `SET LOCAL`.
- **A custom queue handler.** RLS for jobs binds `Illuminate\Queue\CallQueuedHandler` to `RlsQueuedHandler`. An app that binds its own handler must extend `RlsQueuedHandler` instead.

## Laravel Cloud

Laravel Cloud's Serverless Postgres is Neon. From the documentation (not verified live):

- The injected `DB_USERNAME` is Neon's default role. It owns the tables (migrations run as it) and is a member of `neon_superuser`, which has `CREATEROLE` and, on projects created since August 2023, `BYPASSRLS`. Treat it as the **admin/migrator** connection and never as the runtime one.
- Cloud injects only that one set of credentials. Create the runtime role with `kit:rls:role` (or the migration above) on the injected user, then add `DB_RUNTIME_USERNAME` and `DB_RUNTIME_PASSWORD` as environment variables by hand, and point the default connection at them.
- The default `DB_HOST` is the direct endpoint. The `-pooler` hostname is PgBouncer in transaction mode, which you opt into by changing the host. Both work with the kit's `SET LOCAL` pattern. See the advisory lock and LISTEN caveats above before you switch.
- Before you rely on it, run on a non-production database: `select rolname, rolsuper, rolbypassrls, rolcreaterole from pg_roles where rolname = current_user;`, a trial `kit:rls:role`, and `kit:preflight` with the runtime credentials.
- If `CREATE ROLE` is refused, RLS cannot be enforced there: the only role has `BYPASSRLS`, and `FORCE` doesn't override that. `kit:preflight` fails in that setup, so leave RLS off and rely on app scoping.

## Coverage

`composer test:rls` (PostgreSQL only; CI runs it in the PostgreSQL job against a service container) covers:

- the runtime role command and its attributes, default privileges, and the runtime role being refused `DISABLE ROW LEVEL SECURITY` and `TRUNCATE`;
- the policy helper and its idempotence;
- refusals with app scoping removed: select, update and delete across workspaces (0 rows), insert into and move to another workspace (errors);
- a missing setting, `SET LOCAL` leaving nothing behind, and nested `within()`;
- a workspace request through `ResolveWorkspace`;
- queued jobs on the database queue: one in its workspace, a tenant-aware job and a plain job without one, and a `NotTenantAware` job;
- the guard and `kit:preflight`'s RLS problems (superuser, `BYPASSRLS`, owner, unprotected table, admin as runtime);
- the no-op on SQLite.

**Not covered:** a real pooler (PgBouncer), Octane, Horizon or a long-running worker process, Laravel Cloud or Neon, Inertia pages under RLS, MySQL, and the kit's own feature tables protected under RLS.
