<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Runs a step whose failure the caller catches and carries on from (a dashboard widget that becomes its error card). Inside an open transaction (with
 * row-level security on, every workspace request and job is one) it runs in a savepoint: on PostgreSQL a failed statement aborts the whole transaction,
 * so without one every later query in the request fails with 25P02. The savepoint is rolled back and the exception rethrown for the caller to handle.
 * Outside a transaction it just runs the step.
 */
final class Savepoint
{
    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function run(Closure $fn): mixed
    {
        // The connection the workspace transaction is on: the RLS runtime connection, or the default.
        $db = DB::connection(config('kit.tenancy.rls.connection'));
        if ($db->transactionLevel() === 0) {
            return $fn();
        }

        return $db->transaction($fn);
    }
}
