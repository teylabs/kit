<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\SessionUi\ClientTimezone;
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

    /** The workspace the person just joined, so the steps that are about it (its timezone, for an owner) can be asked. */
    public const SESSION_WORKSPACE = 'welcome.workspace';

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
            'timezone' => ['Confirm your timezone', 'Dates and times show in your timezone. We picked the one your browser reports; change it if it is wrong, and it is saved as you do.'],
            'workspace-timezone' => ['Confirm the workspace timezone', 'The workspace uses one timezone for what it schedules and reports. We started from yours; change it if the workspace works elsewhere.'],
            'photo' => ['Add a photo', 'Teammates see it next to your name. You can skip this and add one later in your account.'],
            default => [$found['label'], ''],
        };
        PageTitle::name($found['label'], $request);
        $id = (int) $user->getKey();
        $saves = in_array($step, ['timezone', 'workspace-timezone'], true);

        return Inertia::render('Welcome/'.Str::studly($step).'/Page', new WelcomeStepViewModel(
            $found['label'],
            $copy[0],
            $copy[1],
            new Action($step === 'photo' ? 'Upload and continue' : 'Continue', '/welcome/'.$step, 'post'),
            $found['optional'] ? new Action('Skip for now', '/welcome/'.$step.'/skip', 'post') : null,
            Onboarding::value($id, $step) ?? ClientTimezone::fromRequest($request),
            app(PageShell::class)->initials((string) $user->name),
            (int) config('kit.avatar.max_kb', 2048),
            $saves ? new Action('Save', '/welcome/'.$step, 'put') : null,
        ));
    }

    /** The answer as it changes: saved without a button, and not yet a confirmation. */
    public function update(Request $request, string $step): mixed
    {
        $user = $this->user($request);
        Onboarding::find($step) ?? abort(404);
        $zone = ClientTimezone::valid($request->input('timezone')) ?? throw ValidationException::withMessages(['timezone' => 'Choose a timezone from the list.']);
        Onboarding::save((int) $user->getKey(), $step, $zone);

        return back();
    }

    /** Continue: the answer is saved and confirmed (for a timezone, it is theirs from now on and the browser no longer overwrites it). */
    public function store(Request $request, string $step): mixed
    {
        $user = $this->user($request);
        Onboarding::find($step) ?? abort(404);
        $id = (int) $user->getKey();
        if (in_array($step, ['timezone', 'workspace-timezone'], true)) {
            $zone = ClientTimezone::valid($request->input('timezone')) ?? throw ValidationException::withMessages(['timezone' => 'Choose a timezone from the list.']);
            Onboarding::save($id, $step, $zone);
            Onboarding::confirm($id, $step);
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
        $request->session()->forget(self::SESSION_WORKSPACE);
        $to = $request->session()->pull(self::SESSION_NEXT) ?: '/me/workspaces';

        return $request->header('X-Inertia') ? Inertia::location(url($to)) : redirect($to);
    }

    private function user(Request $request): object
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        // Which workspace the steps are about: the one just joined, kept by the acceptance.
        Onboarding::context((int) $user->getKey(), ($workspace = $request->session()->get(self::SESSION_WORKSPACE)) === null ? null : (int) $workspace);

        return $user;
    }
}
