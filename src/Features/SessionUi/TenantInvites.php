<?php

namespace Tey\Kit\Features\SessionUi;

use Closure;
use Tey\Concierge\Models\TenantInvitation;

/**
 * The tenant invitation link (`/invite/{token}`, route group `tenant-invitations`): an invitation to own a new workspace, which Concierge's
 * provisionWorkspaceUsing() creates when it is accepted. The invitee sees the workspace's name but doesn't choose it: the host's provisioner names it,
 * and nameUsing() tells this page the name it will use (the product's name when it isn't set). The provisioner gets the invitation, so a host that
 * later lets the invitee choose can read the choice from it.
 */
final class TenantInvites
{
    /** @var (Closure(TenantInvitation): ?string)|null */
    private static ?Closure $name = null;

    /** @param (Closure(TenantInvitation): ?string)|null $callback the name the provisioner will give the invitation's workspace */
    public static function nameUsing(?Closure $callback): void
    {
        self::$name = $callback;
    }

    public static function name(TenantInvitation $invitation): string
    {
        $name = self::$name === null ? null : (self::$name)($invitation);

        return is_string($name) && trim($name) !== '' ? trim($name) : (string) config('app.name');
    }

    public static function url(string $token, string $suffix = ''): string
    {
        return url('/invite/'.$token.$suffix);
    }

    /** For tests. */
    public static function flush(): void
    {
        self::$name = null;
    }
}
