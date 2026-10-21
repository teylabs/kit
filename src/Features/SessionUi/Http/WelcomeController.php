<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\Avatars;
use Tey\Kit\Features\SessionUi\FocusStep;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;
use Tey\Kit\Features\SessionUi\Operations\StoreAvatar;
use Tey\Kit\Features\SessionUi\PageShell;
use Tey\Kit\Features\SessionUi\PageTitle;
use Tey\Kit\Features\SessionUi\UserState;
use Tey\Kit\Features\SessionUi\ViewModels\WelcomeStepViewModel;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;

/**
 * The welcome steps a person walks after joining a workspace: confirm the timezone, add a photo, then the dashboard. Each step can be skipped and none blocks;
 * a skipped step comes back on the dashboard's checklist. The FocusShell shows "Step n of m".
 */
class WelcomeController
{
    /** Where the flow ends: the workspace the person just joined, kept in the session by the acceptance, else their workspaces. */
    public const SESSION_NEXT = 'welcome.next';

    public function index(Request $request): mixed
    {
        $user = $this->user($request);
        $step = Onboarding::next((int) $user->getKey());

        return $step === null ? $this->finish($request, $user) : redirect('/welcome/'.$step['id']);
    }

    public function show(Request $request, string $step): mixed
    {
        $user = $this->user($request);
        $found = Onboarding::find($step) ?? abort(404);
        // Coming back to a skipped step from the dashboard: finish on the page that sent them.
        $back = (string) $request->headers->get('referer');
        if (! $request->session()->has(self::SESSION_NEXT) && str_starts_with($back, url('/')) && ! str_contains($back, '/welcome')) {
            $request->session()->put(self::SESSION_NEXT, $back);
        }
        $steps = Onboarding::steps();
        $position = array_search($step, array_column($steps, 'id'), true);
        FocusStep::set($request, (int) $position + 1, count($steps), $found['label']);
        $copy = match ($step) {
            'timezone' => ['Confirm your timezone', 'Dates and times show in your timezone. We have picked the one your browser reports; change it if it is wrong.'],
            'photo' => ['Add a photo', 'Teammates see it next to your name. You can skip this and add one later in your account.'],
            default => [$found['label'], ''],
        };
        PageTitle::name($found['label'], $request);

        return Inertia::render('Welcome/'.ucfirst($step).'/Page', new WelcomeStepViewModel(
            $found['label'],
            $copy[0],
            $copy[1],
            new Action($step === 'photo' ? 'Upload and continue' : 'Save and continue', '/welcome/'.$step, 'post'),
            new Action('Skip for now', '/welcome/'.$step.'/skip', 'post'),
            $step === 'timezone' ? UserState::get((int) $user->getKey(), 'timezone') : null,
            app(PageShell::class)->initials((string) $user->name),
            (int) config('kit.avatar.max_kb', 2048),
        ));
    }

    public function store(Request $request, string $step): mixed
    {
        $user = $this->user($request);
        Onboarding::find($step) ?? abort(404);
        $id = (int) $user->getKey();
        if ($step === 'timezone') {
            UserState::set($id, 'timezone', $request->validate(['timezone' => ['required', 'timezone']])['timezone']);
        } elseif ($step === 'photo') {
            $holder = UserAvatar::query()->findOrFail($id);
            Gate::forUser($user)->authorize('update', $holder);
            StoreAvatar::run($holder, $request->file('avatar') ?? abort(422, 'Choose a photo, or skip this step.'));
        }

        return redirect('/welcome');
    }

    /** One welcome, not two: the flow ends on the dashboard, whose card greets the person. */
    private function finish(Request $request, object $user): mixed
    {
        UserState::set((int) $user->getKey(), 'onboarding.done');
        $to = $request->session()->pull(self::SESSION_NEXT) ?: '/me/workspaces';

        return $request->header('X-Inertia') ? Inertia::location(url($to)) : redirect($to);
    }

    private function user(Request $request): object
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);

        return $user;
    }
}
