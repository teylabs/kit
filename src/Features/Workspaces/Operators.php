<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Support\InvitationRules;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\Core\Users;
use Throwable;

/**
 * The `operators` table is the one source of truth for `concierge.manage-tenants`: a row, on a verified, active account.
 * Concierge's own default (the `concierge.tenant_invitations.platform_owners` emails) no longer applies once workspaces
 * boots, so an adopting host seeds operators from those emails (`kit:operators:seed --from-platform-owners`) before deploy.
 */
final class Operators
{
    public const SEED = 'php artisan kit:operators:seed --from-platform-owners';

    /** Why the user cannot operate even with a row: `unverified`, `inactive`, or null when they can. The Gate and the seed share it. */
    public static function refusal(mixed $user): ?string
    {
        return match (true) {
            ! Users::is($user) || ! $user instanceof Member => 'not a user',
            ! $user->conciergeHasVerifiedEmail() => 'unverified',
            ! RequestMemo::account($user)->isActive() => 'inactive',
            default => null,
        };
    }

    /** The `concierge.manage-tenants` Gate. */
    public static function allows(mixed $user): bool
    {
        return self::refusal($user) === null && self::listed((int) $user->getKey());
    }

    public static function listed(int $id): bool
    {
        return DB::table('operators')->where('actor_id', $id)->exists();
    }

    /** @return list<string> the configured platform owners, normalised as Concierge does (trimmed, lower-cased), unique */
    public static function platformOwners(): array
    {
        $emails = array_map(fn ($email): string => InvitationRules::normalizeEmail((string) $email), (array) config('concierge.tenant_invitations.platform_owners', []));

        return array_values(array_unique(array_filter($emails, fn (string $email): bool => $email !== '')));
    }

    /**
     * What seeding from the platform owners would do; nothing is written. Users are matched on the `email` column, normalised,
     * and confirmed against their Concierge email. A missing operators table counts as empty.
     *
     * @return array{matched: array<string, int>, operators: array<string, int>, skipped: array<string, string>, unmatched: list<string>}
     */
    public static function plan(): array
    {
        $plan = ['matched' => [], 'operators' => [], 'skipped' => [], 'unmatched' => []];
        $owners = self::platformOwners();
        if ($owners === []) {
            return $plan;
        }
        $found = [];
        foreach (Users::model()::query()->whereIn(DB::raw('lower(trim(email))'), $owners)->orderBy((new (Users::model()))->getKeyName())->get() as $user) {
            $email = $user instanceof Member ? InvitationRules::normalizeEmail($user->conciergeEmail()) : InvitationRules::normalizeEmail((string) $user->getAttribute('email'));
            if (in_array($email, $owners, true) && ! isset($found[$email])) {
                $found[$email] = $user;
            }
        }
        $table = Schema::hasTable('operators');
        foreach ($owners as $email) {
            $user = $found[$email] ?? null;
            if (! $user instanceof Model) {
                $plan['unmatched'][] = $email;
            } elseif (($why = self::refusal($user)) !== null) {
                $plan['skipped'][$email] = $why;
            } elseif ($table && self::listed((int) $user->getKey())) {
                $plan['operators'][$email] = (int) $user->getKey();
            } else {
                $plan['matched'][$email] = (int) $user->getKey();
            }
        }

        return $plan;
    }

    /** @return array{matched: array<string, int>, operators: array<string, int>, skipped: array<string, string>, unmatched: list<string>} */
    public static function seed(bool $dryRun = false): array
    {
        $plan = self::plan();
        if (! $dryRun && $plan['matched'] !== []) {
            DB::table('operators')->insertOrIgnore(array_map(fn (int $id): array => ['actor_id' => $id], array_values($plan['matched'])));
        }

        return $plan;
    }

    /**
     * The attention line when platform owners are configured and operators is empty (or not created yet): the owners would lose
     * tenant admin. Null otherwise, and when the database cannot be read; never throws.
     */
    public static function warning(): ?string
    {
        try {
            if (self::platformOwners() === [] || (Schema::hasTable('operators') && DB::table('operators')->exists())) {
                return null;
            }
            $matched = array_keys(self::plan()['matched']);
        } catch (Throwable) {
            return null;
        }

        return 'concierge.tenant_invitations.platform_owners is set but operators is empty: with workspaces, only operators manage tenants, so the platform owners lose tenant admin. Seed operators before deploy: '.self::SEED.' (would seed: '.($matched === [] ? 'none; no verified, active user matches' : implode(', ', $matched)).').';
    }
}
