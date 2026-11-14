<?php

namespace Tey\Kit\Features\Core;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Tey\Concierge\Concierge;
use Tey\Concierge\Contracts\Member;
use Tey\Concierge\Contracts\Workspace;
use Tey\Concierge\Models\Account;
use Tey\Concierge\Models\Membership;

/**
 * Lookups the shell, the page's layout and the page all repeat (the signed-in person's membership, account, workspaces, permission set, capability overrides), answered once per
 * web request. Off until the request begins (`remember()`), so commands, jobs and tests outside a request always read afresh; any Eloquent write empties it.
 */
final class RequestMemo
{
    /** @var ?array<string, mixed> */
    private ?array $memo = null;

    private ?int $request = null;

    public function remember(): void
    {
        $this->memo = [];
        $this->request = spl_object_id(request());
    }

    public function flush(): void
    {
        $this->memo = $this->memo === null ? null : [];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function get(string $key, Closure $resolve): mixed
    {
        if ($this->memo === null || $this->request !== spl_object_id(request())) {
            return $resolve();
        }
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolve();
        }

        return $this->memo[$key];
    }

    /**
     * Answers several keys in one read, for later get()s of them: `$read` receives the keys not answered yet and returns key => value for them. A key already
     * answered keeps its value, and nothing is read when every key is answered or while the memo is off (outside a request every lookup reads afresh).
     *
     * @param  list<string>  $keys
     * @param  Closure(list<string>): array<string, mixed>  $read
     */
    public function prime(array $keys, Closure $read): void
    {
        if ($this->memo === null || $this->request !== spl_object_id(request())) {
            return;
        }
        $missing = array_values(array_filter($keys, fn (string $key): bool => ! array_key_exists($key, $this->memo)));
        if ($missing !== []) {
            $this->memo += $read($missing);
        }
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
}
