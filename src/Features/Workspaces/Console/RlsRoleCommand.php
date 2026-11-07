<?php

namespace Tey\Kit\Features\Workspaces\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tey\Kit\Features\Workspaces\Rls;
use Throwable;

/**
 * Creates (or reshapes) the PostgreSQL runtime role the app connects as with RLS on: NOBYPASSRLS, not the owner, DML only, with default privileges
 * for tables the migrator creates later. Runs on the admin (owner) connection. The password is read from an environment variable, never an argument.
 */
class RlsRoleCommand extends Command
{
    protected $signature = 'kit:rls:role {role : The runtime role name} {--password-env=DB_RUNTIME_PASSWORD : The environment variable holding its password} {--database= : The admin connection (default kit.tenancy.rls.admin_connection)}';

    protected $description = 'Create or update the PostgreSQL runtime role for row-level security (NOBYPASSRLS, DML grants, default privileges)';

    public function handle(): int
    {
        $connection = (string) ($this->option('database') ?? config('kit.tenancy.rls.admin_connection') ?? config('database.default'));
        $password = getenv((string) $this->option('password-env'));
        try {
            Rls::grantRuntimeRole((string) $this->argument('role'), $password === false ? null : $password, DB::connection($connection));
        } catch (Throwable $error) {
            $this->error('The runtime role was not set up: '.$error->getMessage());

            return self::FAILURE;
        }
        $this->info('Runtime role '.$this->argument('role').' is ready on '.$connection.': NOBYPASSRLS, DML grants, default privileges for new tables.');

        return self::SUCCESS;
    }
}
