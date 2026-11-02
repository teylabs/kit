<?php

namespace Tey\Kit\Features\Mcp\Registration;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The record of which OAuth clients came from dynamic client registration (`kit_mcp_registrations`). Only these are listed, revoked and pruned by `kit:mcp:clients`,
 * and the consent page names them as unverified apps. A client registered before the kit kept this record is adopted by hand, never guessed at.
 */
final class Registrations
{
    public const string TABLE = 'kit_mcp_registrations';

    public static function record(string $clientId, string $applicationType, ?string $ip, bool $adopted = false): void
    {
        DB::table(self::TABLE)->insert(['oauth_client_id' => $clientId, 'application_type' => $applicationType, 'ip' => $ip, 'registered_at' => now(), 'adopted_at' => $adopted ? now() : null]);
    }

    public static function registered(string $clientId): bool
    {
        return self::available() && DB::table(self::TABLE)->where('oauth_client_id', $clientId)->exists();
    }

    public static function query(): Builder
    {
        return DB::table(self::TABLE);
    }

    /** Whether the table exists: a host that has not run the mcp migration yet sees no registered clients, and registration refuses. */
    public static function available(): bool
    {
        return DB::getSchemaBuilder()->hasTable(self::TABLE);
    }
}
