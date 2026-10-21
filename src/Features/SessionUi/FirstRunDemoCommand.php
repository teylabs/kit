<?php

namespace Tey\Kit\Features\SessionUi;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tey\Concierge\Actions\AcceptInvitation;
use Tey\Concierge\Actions\CopyInvitationLink;
use Tey\Concierge\Actions\InviteMember;
use Tey\Concierge\Actions\LeaveWorkspace;
use Tey\Concierge\Actions\RevokeInvitation;
use Tey\Concierge\Concierge;
use Tey\Concierge\Support\ConciergeModels;
use Tey\Concierge\Support\GrantSet;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * Local only: invite a fresh demo person to a workspace and print the link, to click through the first-run path (accept, welcome steps, dashboard), or one of the
 * forks an invitation can take (`--fork=`; `--fork=all` prints a menu of every one). Every fork makes its own fresh people and invitation, so they never interfere.
 */
final class FirstRunDemoCommand extends Command
{
    private const FORKS = ['new', 'existing-signed-out', 'signed-in-as-invitee', 'signed-in-as-other', 'join-as-current', 'already-member', 'expired', 'revoked', 'rotated', 'left'];

    protected $signature = 'kit:first-run-demo {--workspace= : The workspace slug (default: the first one)} {--role=viewer : The role to invite as} {--fork=new : One of '.'new, existing-signed-out, signed-in-as-invitee, signed-in-as-other, join-as-current, already-member, expired, revoked, rotated, left, or all'.'}';

    protected $description = 'Print a fresh invitation link to try the first-run path, or any of its forks (local environment only)';

    private Workspace $workspace;

    private $owner;

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->error('kit:first-run-demo runs in the local environment only; nothing was created.');

            return self::FAILURE;
        }
        $workspace = $this->option('workspace') ? Workspace::query()->where('slug', $this->option('workspace'))->first() : Workspace::query()->orderBy('id')->first();
        $owner = $workspace === null ? null : app(Concierge::class)->ownerOf($workspace);
        if ($workspace === null || $owner === null) {
            $this->error('There is no workspace with an owner to invite to. Run starter:first-owner first.');

            return self::FAILURE;
        }
        $this->workspace = $workspace;
        $this->owner = $owner->user;
        $fork = (string) $this->option('fork');
        if ($fork !== 'all' && ! in_array($fork, self::FORKS, true)) {
            $this->error('Unknown fork "'.$fork.'". Use one of: '.implode(', ', self::FORKS).', all.');

            return self::FAILURE;
        }
        foreach ($fork === 'all' ? self::FORKS : [$fork] as $name) {
            [$link, $notes] = $this->{'fork'.Str::studly($name)}();
            $this->newLine();
            $this->info($name.' — '.$workspace->name);
            foreach ($notes as $note) {
                $this->line('  '.$note);
            }
            $this->line('  '.$link);
        }

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: list<string>} */
    private function forkNew(): array
    {
        $email = $this->email('demo');

        return [$this->link($this->invite($email)), ['Invited '.$email.', who has no account: sign-up, then the welcome steps. Open it signed out (a private window).']];
    }

    private function forkExistingSignedOut(): array
    {
        $user = $this->person('existing');

        return [$this->link($this->invite($user->email)), ['Has an account but is signed out: "Sign in to join", back to the invitation, then one click. Open it signed out.', 'email: '.$user->email, 'password: '.$user->demo_password]];
    }

    private function forkSignedInAsInvitee(): array
    {
        $user = $this->person('invitee');

        $issued = $this->invite($user->email);

        return [$this->signIn($user, '/invitations/'.$issued->token), ['One click: signs in as '.$user->email.' and opens the invitation, ready to accept.']];
    }

    private function forkSignedInAsOther(): array
    {
        $invitee = $this->email('invitee');
        $local = (string) config('concierge.local_login.account.email');
        $other = $local !== '' ? ConciergeModels::user()::query()->where('email', $local)->first() : null;
        $other ??= $this->person('other');
        $issued = $this->invite($invitee);

        return [$this->signIn($other, '/invitations/'.$issued->token), ['One click: signs in as '.$other->email.' and opens an invitation meant for '.$invitee.': "This invitation is for '.$invitee.'", with Switch account.']];
    }

    private function forkJoinAsCurrent(): array
    {
        $current = $this->person('current');
        $invitee = $this->email('invited');
        $issued = $this->invite($invitee);

        return [$this->signIn($current, '/invitations/'.$issued->token), ['One click: signs in as '.$current->email.' (verified) and opens an invitation sent to '.$invitee.': "Join as '.$current->email.'", with Switch account and Cancel. With KIT_INVITES_MATCH_EMAIL=true it is the strict page instead (Switch account only).']];
    }

    private function forkAlreadyMember(): array
    {
        $user = $this->person('member');
        $issued = $this->invite($user->email);
        AcceptInvitation::run($user, $issued->token);
        // Joined through the invitation, as a person would have: their first dashboard sends them through the welcome steps.
        UserState::set((int) $user->getKey(), 'onboarding.pending');

        return [$this->signIn($user, '/invitations/'.$issued->token), ['One click: signs in as '.$user->email.' (already joined) and opens the used invitation: "You\'re already in '.$this->workspace->name.'" with Go to it.']];
    }

    private function forkExpired(): array
    {
        $issued = $this->invite($this->email('demo'));
        DB::table((new (ConciergeModels::invitation()))->getTable())->where('id', $issued->invitation->getKey())->update(['expires_at' => now()->subDay()]);

        return [$this->link($issued), ['Expired a day ago. Open it signed out.']];
    }

    private function forkRevoked(): array
    {
        $issued = $this->invite($this->email('demo'));
        RevokeInvitation::run($this->owner, $issued->invitation);

        return [$this->link($issued), ['Withdrawn by the inviter. Open it signed out.']];
    }

    private function forkRotated(): array
    {
        $issued = $this->invite($this->email('demo'));
        $old = $this->link($issued);
        CopyInvitationLink::run($this->owner, $issued->invitation);

        return [$old, ['The inviter copied a fresh link, so this older one no longer works ("This link is not valid", with the hint that it may have been replaced). Open it signed out.']];
    }

    private function forkLeft(): array
    {
        $user = $this->person('left');
        AcceptInvitation::run($user, $this->invite($user->email)->token);
        LeaveWorkspace::run($user, $this->workspace);

        return [$this->signIn($user, '/'), ['One click: signs in as '.$user->email.', who joined and then left. They have no workspace, so they land on the workspaces page for now. The no-workspace page itself is pending (item 32 step 2).']];
    }

    /** A signed, expiring, local-only link that signs in as `$user` and goes on to `$to`. No password to type. */
    private function signIn(object $user, string $to): string
    {
        return URL::temporarySignedRoute('demo.login', now()->addHours(4), ['user' => $user->getKey(), 'to' => $to]);
    }

    private function invite(string $email): object
    {
        return InviteMember::run($this->owner, $this->workspace, $email, new GrantSet([(string) $this->option('role')]), 'Welcome aboard. This is a demo invitation.');
    }

    private function link(object $issued): string
    {
        return url('/invitations/'.$issued->token);
    }

    /** An address nobody has used, as an account or as a pending invitation to this workspace. */
    private function email(string $prefix): string
    {
        $n = 1;
        while (ConciergeModels::user()::query()->where('email', $prefix.'+'.$n.'@example.test')->exists() || ConciergeModels::invitation()::query()->where('workspace_id', $this->workspace->getKey())->where('pending_key', $prefix.'+'.$n.'@example.test')->exists()) {
            $n++;
        }

        return $prefix.'+'.$n.'@example.test';
    }

    /** A verified account with a throwaway password, for the forks that need someone who already exists. */
    private function person(string $prefix): object
    {
        $password = 'demo-'.Str::random(10);
        $user = ConciergeModels::user()::query()->forceCreate(['name' => ucfirst($prefix).' Demo', 'email' => $this->email($prefix), 'password' => Hash::make($password), 'email_verified_at' => now()]);
        $user->demo_password = $password;

        return $user;
    }
}
