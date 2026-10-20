<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * The one place a workspace page's URL is built. In a session, a user with a single workspace gets the short form
 * (`/cases`) and a user with several the full form (`/@<slug>/cases`); adding a second workspace flips the links with
 * no other change. Anything shared outside a session (mail, exports) uses `shareable`, which is always the full,
 * absolute form. Both forms always resolve.
 */
final class WorkspaceUrl
{
    /** @var ?callable(int): string a slug lookup in place of the database, for checks that boot no database */
    private static $lookup = null;

    /** @param ?callable(int): string $lookup */
    public static function lookupUsing(?callable $lookup): void
    {
        self::$lookup = $lookup;
    }

    /**
     * A path in a workspace for the current user's session: short with one workspace, else full. `$feature` is the
     * kit feature that serves the page, so its configured route prefix (`kit.routes.<feature>.prefix`) is kept.
     */
    public static function to(int|Workspace $workspace, string $path = '', ?string $feature = null, ?Request $request = null): string
    {
        $path = ltrim($path, '/');
        if ($path !== '' && self::short($request ?? request())) {
            return self::join(self::prefix($feature), $path);
        }

        return self::full($workspace, $path, $feature);
    }

    /** The full form, as a path: `/@<slug>/<path>`. */
    public static function full(int|Workspace $workspace, string $path = '', ?string $feature = null): string
    {
        return self::join(self::prefix($feature), '@'.self::slug($workspace), $path);
    }

    /** A full-form URL as the session should link to it: its path, without the `@slug` segment for a user with one workspace. */
    public static function localise(string $url, ?Request $request = null): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        if (self::short($request ?? request())) {
            $path = preg_replace('#/@'.Slugs::PATTERN.'(?=/|$)#', '', $path, 1);
            $path = $path === '' ? '/' : $path;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** The full form with the app's host, for mail, exports and anything shared outside a session. */
    public static function shareable(int|Workspace $workspace, string $path = '', ?string $feature = null): string
    {
        return url(self::full($workspace, $path, $feature));
    }

    /** The workspace a request is about: the route's, else the session's current one, else the user's only one. Null when there is none. */
    public static function currentId(?Request $request = null): ?int
    {
        $request ??= request();
        $routed = $request->route()?->parameter('workspace');
        if (is_numeric($routed)) {
            return (int) $routed;
        }
        $user = $request->user('web');
        if ($user === null) {
            return null;
        }
        $remembered = $request->hasSession() ? $request->session()->get('kit.workspace') : null;
        if ($remembered !== null && Workspace::query()->whereKey($remembered)->exists() && app(Concierge::class)->membership($user, Workspace::query()->find($remembered))?->isActive()) {
            return (int) $remembered;
        }
        $memberships = self::memberships($user);

        return count($memberships) === 1 ? (int) $memberships[0]->getKey() : null;
    }

    /** The home crumb's name: the product's when the user has one workspace, else the current workspace's. */
    public static function homeName(?Request $request = null): string
    {
        $request ??= request();
        $user = $request->user('web');
        if ($user === null || count(self::memberships($user)) <= 1) {
            return (string) config('app.name');
        }
        $id = self::currentId($request);

        return $id === null ? (string) config('app.name') : (string) Workspace::query()->whereKey($id)->value('name');
    }

    /** Whether the signed-in user has exactly one workspace; decided once per request. */
    public static function short(Request $request): bool
    {
        if (! $request->attributes->has('kit.short_urls')) {
            $user = $request->user('web');
            $request->attributes->set('kit.short_urls', $user !== null && count(self::memberships($user)) === 1);
        }

        return $request->attributes->get('kit.short_urls');
    }

    /** @return list<Workspace> the workspaces a user has an active membership in */
    public static function memberships(Authenticatable $user): array
    {
        return Workspace::query()->orderBy('id')->get()->filter(fn (Workspace $workspace) => app(Concierge::class)->membership($user, $workspace)?->isActive())->values()->all();
    }

    /** @internal The slug a workspace's URLs carry. */
    public static function slug(int|Workspace $workspace): string
    {
        if ($workspace instanceof Workspace) {
            return $workspace->slug;
        }

        return self::$lookup !== null ? (self::$lookup)($workspace) : (string) Workspace::query()->whereKey($workspace)->value('slug');
    }

    private static function prefix(?string $feature): string
    {
        return $feature === null ? '' : trim((string) config('kit.routes.'.$feature.'.prefix', ''), '/');
    }

    private static function join(string ...$parts): string
    {
        return '/'.implode('/', array_filter(array_map(fn (string $part): string => trim($part, '/'), $parts), fn (string $part): bool => $part !== ''));
    }
}
