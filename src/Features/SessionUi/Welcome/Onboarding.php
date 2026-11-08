<?php

namespace Tey\Kit\Features\SessionUi\Welcome;

use Closure;
use Illuminate\Support\Facades\DB;
use Tey\Concierge\Concierge;
use Tey\Kit\Features\Core\Data\ChecklistAction;
use Tey\Kit\Features\Core\Data\ChecklistItem;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Core\RequestMemo;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\SessionUiRoutes;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\Workspaces\MemberWorkspaces;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * The first-run steps a new person walks through after joining. Step keys are strings everywhere; each step says whether it applies to this person in this workspace
 * (the workspace timezone only for an owner of a workspace whose timezone is unconfirmed), whether it is done, and how an answer is saved (`save`, as it changes) and
 * confirmed (`confirm`, on Continue). A step is handled when it is done or was skipped, so the flow never blocks and a skipped step comes back on the dashboard's
 * checklist. Apps add steps with `Onboarding::step()`; the kit registers the person's timezone and the photo, and with workspace-ui the workspace's timezone.
 *
 * Each step has a `scope`: `person` (about the signed-in person, such as their timezone or photo; the dashboard's You card lists those not yet done) or
 * `workspace` (about the workspace, such as its timezone; the dashboard's "Set up this workspace" card lists those, to people who manage its settings).
 */
final class Onboarding
{
    public const array SCOPES = ['person', 'workspace'];

    /** @var array<string, array{id: string, label: string, hint: string, order: int, optional: bool, scope: 'person'|'workspace', done: Closure, applies: Closure, save: ?Closure, confirm: ?Closure, value: ?Closure}> */
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
     * @param  'person'|'workspace'  $scope  whom the step is about: the person (the default) or the workspace
     */
    public static function step(string $id, string $label, int $order, Closure $done, string $hint = '', bool $optional = true, ?Closure $applies = null, ?Closure $save = null, ?Closure $confirm = null, ?Closure $value = null, string $scope = 'person'): void
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException("An onboarding step's scope is person or workspace, not {$scope}.");
        }
        self::$steps[$id] = ['id' => $id, 'label' => $label, 'hint' => $hint, 'order' => $order, 'optional' => $optional, 'scope' => $scope, 'done' => $done, 'applies' => $applies ?? fn (): bool => true, 'save' => $save, 'confirm' => $confirm, 'value' => $value];
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
        // The workspace timezone's page (Welcome/WorkspaceTimezone) is workspace-ui's: without it the step is never asked.
        if (app(Registry::class)->adopts('workspace-ui')) {
            self::workspaceTimezoneStep();
        }
        self::step('photo', 'Your photo', 20, fn (int $user): bool => (Avatars::urls($user)['url'] ?? null) !== null, 'Add a photo so teammates know who you are');
    }

    private static function workspaceTimezoneStep(): void
    {
        self::step('workspace-timezone', 'Workspace timezone', 15, fn (int $user, ?int $workspace): bool => self::workspaceTimezoneConfirmed($workspace), 'Confirm the timezone this workspace works in', optional: true, scope: 'workspace',
            applies: fn (int $user, ?int $workspace): bool => self::ownsWorkspace($user, $workspace) && ! self::workspaceTimezoneConfirmed($workspace),
            save: function (int $user, ?int $workspace, string $value): void {
                $registry = app(Registry::class)->workspaceTimezone();
                if ($registry !== null && $workspace !== null) {
                    Workspaces::run($workspace, function () use ($registry, $user, $value) {
                        return ($registry['set'])($user, $value);
                    });
                }
            },
            confirm: fn (int $user, ?int $workspace) => $workspace === null ? null : UserState::set($user, 'workspace.timezone.confirmed', '1', $workspace),
            value: function (int $user, ?int $workspace): ?string {
                $registry = app(Registry::class)->workspaceTimezone();
                $current = $registry !== null && $workspace !== null ? Workspaces::run($workspace, function () use ($registry) {
                    return ($registry['get'])();
                }) : null;

                // A workspace nobody has set yet starts from its owner's own zone, not UTC.
                return $current === null || $current === 'UTC' ? (UserState::get($user, 'timezone') ?? $current) : $current;
            });
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
        UserState::set($userId, 'onboarding.skipped.'.$id, '1', self::skipWorkspace($id));
    }

    /** Where a skip is kept: a workspace step's per workspace (skipping it in one workspace leaves it pending in another), a person's account-wide (0). */
    private static function skipWorkspace(string $id): int
    {
        self::defaults();

        return (self::$steps[$id]['scope'] ?? 'person') === 'workspace' ? (self::$workspace ?? 0) : 0;
    }

    public static function finished(int $userId): bool
    {
        return UserState::has($userId, 'onboarding.done');
    }

    /** @return 'done'|'skipped'|'pending' where one step stands for the person, from server state */
    public static function state(int $userId, string $id): string
    {
        self::defaults();

        return (self::$steps[$id]['done'])($userId, self::$workspace) ? 'done' : (UserState::has($userId, 'onboarding.skipped.'.$id, self::skipWorkspace($id)) ? 'skipped' : 'pending');
    }

    /**
     * Every step with where it stands, optionally of one scope only: the dashboard's checklists (You for `person`, the setup card for `workspace`).
     *
     * @param  'person'|'workspace'|null  $scope
     * @return list<array{id: string, label: string, hint: string, optional: bool, scope: string, state: string}>
     */
    public static function items(int $userId, ?string $scope = null): array
    {
        $steps = array_values(array_filter(self::steps(), fn (array $step): bool => $scope === null || self::$steps[$step['id']]['scope'] === $scope));

        return array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label'], 'hint' => self::$steps[$step['id']]['hint'], 'optional' => $step['optional'], 'scope' => self::$steps[$step['id']]['scope'], 'state' => self::state($userId, $step['id'])], $steps);
    }

    /**
     * One scope's steps for a person in a workspace, as kit Checklist rows linking to their welcome step: the dashboard's You and setup cards. Empty when the
     * `welcome` route group is off (the rows would link nowhere) or every step of the scope is done, so the card hides its checklist.
     *
     * @param  'person'|'workspace'  $scope
     * @return list<ChecklistItem>
     */
    public static function checklist(int $userId, int $workspaceId, string $scope): array
    {
        if (! SessionUiRoutes::on('welcome')) {
            return [];
        }
        self::context($userId, $workspaceId);
        $items = self::items($userId, $scope);
        if (array_filter($items, fn (array $item): bool => $item['state'] !== 'done') === []) {
            return [];
        }

        return array_map(fn (array $item): ChecklistItem => new ChecklistItem($item['id'], $item['label'], $item['state'], $item['hint'] === '' ? null : $item['hint'], required: ! $item['optional'],
            action: $item['state'] === 'done' ? null : new ChecklistAction('Set up', '/welcome/'.$item['id'], $item['id'])), $items);
    }
}
