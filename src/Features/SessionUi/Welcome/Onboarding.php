<?php

namespace Tey\Kit\Features\SessionUi\Welcome;

use Closure;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\UserState;

/**
 * The first-run steps a new person walks through after joining: each has an id, a label and an order, and says whether it is done for a person. A step
 * is done when it was completed or skipped, so the flow never blocks and a skipped step comes back as an item of the "Finish setting up" checklist.
 * Apps add steps with `Onboarding::step()`; the kit registers the timezone and the photo.
 */
final class Onboarding
{
    /** @var array<string, array{id: string, label: string, hint: string, order: int, done: Closure(int): bool, skipped: Closure(int): bool}> */
    private static array $steps = [];

    /** @param  Closure(int): bool  $done  whether the person has completed the step */
    public static function step(string $id, string $label, int $order, Closure $done, string $hint = ''): void
    {
        self::$steps[$id] = ['id' => $id, 'label' => $label, 'hint' => $hint, 'order' => $order, 'done' => $done, 'skipped' => fn (int $userId): bool => UserState::has($userId, 'onboarding.skipped.'.$id)];
    }

    public static function defaults(): void
    {
        if (self::$steps === []) {
            self::step('timezone', 'Your timezone', 10, fn (int $userId): bool => UserState::has($userId, 'timezone'), 'Confirm the timezone your dates and times show in');
            self::step('photo', 'Your photo', 20, fn (int $userId): bool => (Avatars::urls($userId)['url'] ?? null) !== null, 'Add a photo so teammates know who you are');
        }
    }

    /** @return list<array{id: string, label: string, order: int}> every step, in order */
    public static function steps(): array
    {
        self::defaults();
        $steps = array_values(self::$steps);
        usort($steps, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label'], 'order' => $step['order']], $steps);
    }

    public static function find(string $id): ?array
    {
        self::defaults();

        return collect(self::steps())->firstWhere('id', $id);
    }

    /** Whether a step needs doing: neither completed nor skipped. */
    public static function pending(int $userId, string $id): bool
    {
        return isset(self::$steps[$id]) && ! (self::$steps[$id]['done'])($userId) && ! (self::$steps[$id]['skipped'])($userId);
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

    /** Steps that were skipped and are still not done: the "Finish setting up" checklist. @return list<array{id: string, label: string}> */
    public static function skipped(int $userId): array
    {
        return array_values(array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label']], array_filter(self::steps(), fn (array $step): bool => ! (self::$steps[$step['id']]['done'])($userId) && (self::$steps[$step['id']]['skipped'])($userId))));
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

        return (self::$steps[$id]['done'])($userId) ? 'done' : ((self::$steps[$id]['skipped'])($userId) ? 'skipped' : 'pending');
    }

    /** Every step with where it stands: the welcome card's checklist. @return list<array{id: string, label: string, hint: string, state: string}> */
    public static function items(int $userId): array
    {
        return array_map(fn (array $step): array => ['id' => $step['id'], 'label' => $step['label'], 'hint' => self::$steps[$step['id']]['hint'], 'state' => self::state($userId, $step['id'])], self::steps());
    }
}
