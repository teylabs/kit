<?php

namespace Tey\Kit\Features\SessionUi;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Tey\Concierge\Models\TenantInvitation;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

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

    /** @var (Closure(Model, mixed): ?string)|null */
    private static ?Closure $landing = null;

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

    /**
     * @param  (Closure(Model, mixed): ?string)|null  $callback  where an accepted tenant invitation lands: given the new workspace and the signed-in
     *                                                           user, a path on this site. Null, or anything but a same-site path, keeps the default.
     */
    public static function landUsing(?Closure $callback): void
    {
        self::$landing = $callback;
    }

    /** Where an accepted invitation for `$workspace` lands: the host's landUsing() path, else Landing::to() of the workspace's home. */
    public static function landing(Model $workspace, mixed $user, Request $request): string
    {
        $to = self::$landing === null ? null : (self::$landing)($workspace, $user);
        if (is_string($to) && self::sameSite($to)) {
            return $to;
        }

        return Landing::to(WorkspaceUrl::to($workspace, '', 'session-ui', $request));
    }

    /** A path on this site: "/x", or an absolute URL on the app's own host. Never "//host" or another host. */
    private static function sameSite(string $to): bool
    {
        if (str_starts_with($to, '/')) {
            return ! str_starts_with($to, '//') && ! str_starts_with($to, '/\\');
        }
        $host = parse_url($to, PHP_URL_HOST);
        $scheme = parse_url($to, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) === 0;
    }

    public static function url(string $token, string $suffix = ''): string
    {
        return url('/invite/'.$token.$suffix);
    }

    /** For tests. */
    public static function flush(): void
    {
        self::$name = null;
        self::$landing = null;
    }
}
