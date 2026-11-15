<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Models\Account;
use Tey\Concierge\Models\Membership;
use WeakReference;

/**
 * Lookups the shell, the page's layout and the page all repeat (the signed-in person's membership, account, workspaces, the workspace by id, user_state,
 * the workspace's settings, avatars, ownership), answered once per web request. Each answer is Laravel's `once()`, keyed on a scope object this
 * instance owns: one per request (the instance is scoped), replaced by `flush()`, and gone with it, so nothing survives into the next request, an Octane
 * request or a queued job, and `Once::flush()` empties it too. Off until the request begins (`remember()`), and only for that request object, so
 * commands, jobs and tests outside a request always read afresh. It empties on any write statement or locking read, an Eloquent write, a rollback,
 * on SQLite (which compiles no lock) the outermost transaction beginning, and a job starting or ending: a read after a lock is never one from before it.
 */
final class RequestMemo
{
    /** The request's answers: `once()` keys on the object it is called from, so a new scope starts empty. */
    private ?object $scope = null;

    /** @var ?WeakReference<object> the request that began it: a later request object is never taken for it, even one PHP gives the same id */
    private ?WeakReference $request = null;

    public function remember(): void
    {
        $this->scope = self::scope();
        $this->request = WeakReference::create(request());
    }

    public function flush(): void
    {
        $this->scope = $this->scope === null ? null : self::scope();
    }

    /**
     * Whether a statement writes (an insert, upsert, update, delete, truncate or merge, also behind a CTE) or locks rows (`for update`, `for share`,
     * `lock in share mode`): what is read after it must not come from a read before it, so the memo empties. It errs toward a match, which costs a read.
     */
    public static function writesOrLocks(string $sql): bool
    {
        return preg_match('/^\s*(?:insert|replace|update|delete|truncate|merge)\b/i', $sql) === 1 || preg_match('/^\s*with\b.*\b(?:insert|update|delete|merge)\b/is', $sql) === 1
            || preg_match('/\bfor\s+(?:no\s+key\s+)?(?:update|share|key\s+share)\b|\block\s+in\s+share\s+mode\b/i', $sql) === 1;
    }

    private static function scope(): object
    {
        return new class
        {
            /** @var array<string, Closure> */
            private array $resolving = [];

            public function get(string $key, Closure $resolve): mixed
            {
                // once() hashes the closure's call site and its `use` values: the key alone, so each key is answered once on this scope.
                $this->resolving[$key] = $resolve;
                try {
                    return once(function () use ($key): mixed {
                        return ($this->resolving[$key])();
                    });
                } finally {
                    unset($this->resolving[$key]);
                }
            }
        };
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function get(string $key, Closure $resolve): mixed
    {
        if ($this->scope === null || $this->request?->get() !== request()) {
            return $resolve();
        }

        return $this->scope->get($key, $resolve);
    }

    public static function instance(): self
    {
        return app(self::class);
    }

    /** The person by id; the signed-in one is the session's own model. */
    public static function user(int $id): ?Model
    {
        $signedIn = auth()->guard('web')->user();
        if ($signedIn instanceof Model && Users::is($signedIn) && (int) $signedIn->getKey() === $id) {
            return $signedIn;
        }

        return self::instance()->get('user:'.$id, fn (): ?Model => Users::find($id));
    }

    public static function membership(Member $user, Workspace $workspace): ?Membership
    {
        return self::instance()->get('membership:'.$user->getKey().':'.$workspace->getKey(), fn (): ?Membership => app(Concierge::class)->membership($user, $workspace));
    }

    public static function account(Member $user): Account
    {
        return self::instance()->get('account:'.$user->getKey(), fn (): Account => app(Concierge::class)->account($user));
    }

    /** Whether the membership owns its workspace (Concierge's `isOwner`). An ownership transfer is a write, which empties the memo. */
    public static function owns(Membership $membership): bool
    {
        return self::instance()->get('owns:'.$membership->workspace_id.':'.$membership->getKey(), fn (): bool => app(Concierge::class)->isOwner($membership));
    }
}
