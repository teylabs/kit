<?php

namespace Tey\Kit\Features\SessionUi\Welcome;

use Closure;
use Illuminate\Support\Facades\DB;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Models\Workspace;
use Tey\Kit\Features\Workspaces\TenantBoundary;

/**
 * The first-run steps a new person walks through after joining. Step keys are strings everywhere; each step says whether it applies to this person in this workspace
 * (the workspace timezone only for an owner of a workspace whose timezone is unconfirmed), whether it is done, and how an answer is saved (`save`, as it changes) and
 * confirmed (`confirm`, on Continue). A step is handled when it is done or was skipped, so the flow never blocks and a skipped step comes back on the dashboard's
 * checklist. Apps add steps with `Onboarding::step()`; the kit registers the two timezones and the photo.
 */
final class Onboarding
{
    /** @var array<string, array{id: string, label: string, hint: string, order: int, optional: bool, done: Closure, applies: Closure, save: ?Closure, confirm: ?Closure, value: ?Closure}> */
    private static array $steps = [];

    /** The person and workspace the steps are asked about: set by the request that walks them. */
    private static ?int $user = null;

    private static ?int $workspace = null;

    public static function context(int $userId, ?int $workspaceId = null): void
    {
        self::$user = $userId;
        self::$workspace = $workspaceId;
    }

    public static function workspaceId(): ?int
    {
        return self::$workspace;
    }

    /**
     * @param  Closure(int, ?int): bool  $done  whether the person has completed the step (user id, workspace id)
     * @param  ?Closure(int, ?int): bool  $applies  whether it is asked of this person here (default: always)
     * @param  ?Closure(int, ?int, string): void  $save  stores an answer as it changes (user id, workspace id, value)
     * @param  ?Closure(int, ?int): void  $confirm  marks it confirmed (Continue)
     * @param  ?Closure(int, ?int): ?string  $value  the current answer, to show
     */
    public static function step(string $id, string $label, int $order, Closure $done, string $hint = '', bool $optional = true, ?Closure $applies = null, ?Closure $save = null, ?Closure $confirm = null, ?Closure $value = null): void
    {
        self::$steps[$id] = ['id' => $id, 'label' => $label, 'hint' => $hint, 'order' => $order, 'optional' => $optional, 'done' => $done, 'applies' => $applies ?? fn (): bool => true, 'save' => $save, 'confirm' => $confirm, 'value' => $value];
    }

    public static function defaults(): void
    {
        if (self::$steps !== []) {
            return;
        }
        self::step('timezone', 'Your timezone', 10, fn (int $user): bool => UserState::has($user, 'timezone.confirmed'), 'Confirm the timezone your dates and times show in', optional: false,
            save: fn (int $user, ?int $workspace, string $value) => UserState::set($user, 'timezone', $value),
            confirm: fn (int $user) => UserState::set($user, 'timezone.confirmed'),
            value: fn (int $user): ?string => UserState::get($user, 'timezone'));
        self::step('workspace-timezone', 'Workspace timezone', 15, fn (int $user, ?int $workspace): bool => self::workspaceTimezoneConfirmed($workspace), 'Confirm the timezone this workspace works in', optional: true,
            applies: fn (int $user, ?int $workspace): bool => self::ownsWorkspace($user, $workspace) && ! self::workspaceTimezoneConfirmed($workspace),
            save: function (int $user, ?int $workspace, string $value): void {
                $registry = app(Registry::class)->workspaceTimezone();
                if ($registry !== null && $workspace !== null) {
                    app(TenantBoundary::class)->run($workspace, fn () => ($registry['set'])($user, $value));
                }
            },
            confirm: fn (int $user, ?int $workspace) => $workspace === null ? null : UserState::set($user, 'workspace.timezone.confirmed', '1', $workspace),
            value: function (int $user, ?int $workspace): ?string {
                $registry = app(Registry::class)->workspaceTimezone();
                $current = $registry !== null && $workspace !== null ? app(TenantBoundary::class)->run($workspace, fn () => ($registry['get'])()) : null;

                // A workspace nobody has set yet starts from its owner's own zone, not UTC.
                return $current === null || $current === 'UTC' ? (UserState::get($user, 'timezone') ?? $current) : $current;
            });
        self::step('photo', 'Your photo', 20, fn (int $user): bool => (Avatars::urls($user)['url'] ?? null) !== null, 'Add a photo so teammates know who you are');
    }

    /** Whether any owner has confirmed this workspace's timezone: it is the workspace's, so once is enough. */
    private static function workspaceTimezoneConfirmed(?int $workspace): bool
    {
        return $workspace !== null && DB::table('kit_user_state')->where('workspace_id', $workspace)->where('key', 'workspace.timezone.confirmed')->exists();
    }

    private static function ownsWorkspace(int $user, ?int $workspace): bool
    {
        if ($workspace === null || app(Registry::class)->workspaceTimezone() === null || ! class_exists(Concierge::class)) {
            return false;
        }
        $person = RequestMemo::user($user);
        $space = MemberWorkspaces::workspace($workspace, $person);
        $membership = $person === null || $space === null ? null : RequestMemo::membership($person, $space);

        return $membership !== null && app(Concierge::class)->isOwner($membership);
    }

    /** @return list<array{id: string, label: string, order: int, optional: bool}> every step that applies to the person here, in order */
    public static function steps(): array
    {
        self::defaults();
        $user = self::$user ?? 0;
        $steps = array_values(array_filter(self::$steps, fn (array $step): bool => ($step['applies'])($user, self::$workspace)));
        usort($steps, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label'], 'order' => $step['order'], 'optional' => $step['optional']], $steps);
    }

    public static function find(string $id): ?array
    {
        return collect(self::steps())->firstWhere('id', $id);
    }

    /** The current answer of a step, or null. */
    public static function value(int $userId, string $id): ?string
    {
        return isset(self::$steps[$id]['value']) ? (self::$steps[$id]['value'])($userId, self::$workspace) : null;
    }

    /** Saves an answer as it changes. */
    public static function save(int $userId, string $id, string $value): void
    {
        if (isset(self::$steps[$id]['save'])) {
            (self::$steps[$id]['save'])($userId, self::$workspace, $value);
        }
    }

    /** Marks a step confirmed (Continue). */
    public static function confirm(int $userId, string $id): void
    {
        if (isset(self::$steps[$id]['confirm'])) {
            (self::$steps[$id]['confirm'])($userId, self::$workspace);
        }
    }

    /** Whether a step needs doing: neither completed nor skipped. */
    public static function pending(int $userId, string $id): bool
    {
        return in_array($id, array_column(self::steps(), 'id'), true) && self::state($userId, $id) === 'pending';
    }

    /** The next step still to do, or null when the flow is over. */
    public static function next(int $userId): ?array
    {
        foreach (self::steps() as $step) {
            if (self::pending($userId, $step['id'])) {
                return $step;
            }
        }

        return null;
    }

    /** Steps that were skipped and are still not done. @return list<array{id: string, label: string}> */
    public static function skipped(int $userId): array
    {
        return array_values(array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label']], array_filter(self::steps(), fn (array $step): bool => self::state($userId, $step['id']) === 'skipped')));
    }

    public static function skip(int $userId, string $id): void
    {
        UserState::set($userId, 'onboarding.skipped.'.$id);
    }

    public static function finished(int $userId): bool
    {
        return UserState::has($userId, 'onboarding.done');
    }

    /** @return 'done'|'skipped'|'pending' where one step stands for the person, from server state */
    public static function state(int $userId, string $id): string
    {
        self::defaults();

        return (self::$steps[$id]['done'])($userId, self::$workspace) ? 'done' : (UserState::has($userId, 'onboarding.skipped.'.$id) ? 'skipped' : 'pending');
    }

    /** Every step with where it stands: the welcome card's checklist. @return list<array{id: string, label: string, hint: string, state: string}> */
    public static function items(int $userId): array
    {
        return array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label'], 'hint' => self::$steps[$step['id']]['hint'], 'state' => self::state($userId, $step['id'])], self::steps());
    }
}
