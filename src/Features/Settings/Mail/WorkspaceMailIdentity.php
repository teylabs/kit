<?php

namespace Tey\Kit\Features\Settings\Mail;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Settings\Data\MailIdentityView;
use Tey\Kit\Features\Workspaces\Workspaces;

/**
 * Workspace mail identity: who a workspace's mail says it is from, which address it goes from, and where replies go.
 *
 * - Sender name: the workspace's name unless set.
 * - From: the platform address, or `<local>@send.<domain>` once the workspace's own domain is verified at the provider. Until then mail keeps
 *   the platform address (with the workspace's sender name), so a pending or failed domain never stops mail.
 * - Reply-To: the member who sent the mail (`member`, falling back to the platform's reply-to when a mail has no sender), the platform's fixed
 *   reply-to address (`fixed`), or an address the workspace sets (`custom`).
 *
 * `resolve()` is the one place every FluentMailMessage reads it from (see SettingsProvider).
 */
final class WorkspaceMailIdentity
{
    private const TABLE = 'workspace_mail_identities';

    private static ?bool $migrated = null;

    public function read(Principal $principal): MailIdentityView
    {
        app(Access::class)->authorize($principal, 'settings.read');

        return self::view();
    }

    /** @param  array<string, mixed>  $input */
    public function update(Principal $principal, array $input): MailIdentityView
    {
        if (array_diff(array_keys($input), ['sender_name', 'from_mode', 'domain', 'from_local', 'reply_to_mode', 'reply_to_address', 'expected_version']) !== []) {
            throw new RuntimeException('unknown_arguments');
        }
        $data = Validator::make($input, [
            'sender_name' => ['nullable', 'string', 'max:120', 'not_regex:/[\r\n<>"]/'],
            'from_mode' => ['required', 'in:platform,domain'],
            'domain' => ['nullable', 'required_if:from_mode,domain', 'string', 'max:200', 'regex:/^(?!-)(?:[a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/i'],
            'from_local' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._+-]*$/i'],
            'reply_to_mode' => ['required', 'in:member,fixed,custom'],
            'reply_to_address' => ['nullable', 'required_if:reply_to_mode,custom', 'email:rfc', 'max:254'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();
        $workspace = app(Access::class)->authorize($principal, 'settings.write');
        // The domain the provider holds: the workspace's own domain under send., so its root domain's mail setup is untouched.
        $domain = $data['from_mode'] === 'domain' ? 'send.'.preg_replace('/^send\./i', '', strtolower((string) $data['domain'])) : null;
        $current = DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->first();
        if ((int) ($current->version ?? 1) !== (int) $data['expected_version']) {
            throw new RuntimeException('stale_mail_identity');
        }
        $provider = [];
        if ($domain !== null && $domain !== ($current->domain ?? null)) {
            // A new domain is created at the provider first, so the page can show its DNS records. The provider is outside the transaction:
            // if the save then loses a race, the domain exists unused at the provider, which is harmless and is reused on the next save.
            try {
                $created = app(SendingDomains::class)->create($domain);
            } catch (RuntimeException $error) {
                throw ValidationException::withMessages(['domain' => self::providerMessage($error, 'The mail provider could not add this domain.')]);
            }
            $provider = ['provider_domain_id' => $created['id'], 'domain_status' => $created['status'], 'dns_records' => json_encode($created['records'], JSON_THROW_ON_ERROR), 'checked_at' => now()];
        }

        DB::transaction(function () use ($workspace, $data, $domain, $provider, $principal): void {
            $locked = DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->lockForUpdate()->first();
            if ((int) ($locked->version ?? 1) !== (int) $data['expected_version']) {
                throw new RuntimeException('stale_mail_identity');
            }
            $values = [
                'sender_name' => filled($data['sender_name'] ?? null) ? trim((string) $data['sender_name']) : null,
                'from_mode' => $data['from_mode'],
                'from_local' => strtolower((string) ($data['from_local'] ?? '')) ?: 'hello',
                'reply_to_mode' => $data['reply_to_mode'],
                'reply_to_address' => $data['reply_to_mode'] === 'custom' ? $data['reply_to_address'] : null,
                'version' => (int) $data['expected_version'] + 1,
                'updated_at' => now(),
            ];
            // Switching back to the platform address keeps the domain and its status, so switching again does not create it twice.
            if ($domain !== null) {
                $values['domain'] = $domain;
            }
            $values += $provider;
            DB::table(self::TABLE)->updateOrInsert(['workspace_id' => $workspace->getKey()], $locked ? $values : $values + ['created_at' => now()]);
            app(Audit::class)->write($principal->actorId, (int) $workspace->getKey(), 'workspace.mail_identity.updated', ['fields' => array_keys($values)]);
        });

        return self::view();
    }

    /** "Check now": asks the provider to verify the workspace's domain and records what it says. */
    public function check(Principal $principal): MailIdentityView
    {
        $workspace = app(Access::class)->authorize($principal, 'settings.write');
        $row = DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->first();
        if ($row === null || $row->domain === null) {
            throw ValidationException::withMessages(['domain' => 'Add a domain before checking it.']);
        }
        try {
            $domain = $row->provider_domain_id ? app(SendingDomains::class)->check($row->provider_domain_id) : app(SendingDomains::class)->create($row->domain);
        } catch (RuntimeException $error) {
            throw ValidationException::withMessages(['domain' => self::providerMessage($error, 'The mail provider could not be reached.')]);
        }
        DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->update([
            'provider_domain_id' => $domain['id'] ?: $row->provider_domain_id,
            'domain_status' => $domain['status'],
            'dns_records' => json_encode($domain['records'], JSON_THROW_ON_ERROR),
            'checked_at' => now(),
            'updated_at' => now(),
        ]);

        return self::view();
    }

    /** The provider's reason when it gave one, else the generic "Try again later". */
    private static function providerMessage(RuntimeException $error, string $lead): string
    {
        $reason = $error instanceof MailDomainUnavailable ? $error->reason : null;

        return $reason !== null ? $lead.' The provider said: '.$reason : $lead.' Try again later.';
    }

    /**
     * The identity mail from the current workspace uses, or null outside a workspace (account mail keeps the platform's identity).
     *
     * @param  object|null  $sender  the member the mail is sent on behalf of, for a `member` reply-to
     * @return array{from: array{address: string, name: string}, reply_to: array{address: string, name: ?string}|null}|null
     */
    public static function resolve(?object $sender = null): ?array
    {
        $workspace = Workspaces::current();
        if ($workspace === null) {
            return null;
        }
        // Before the app runs the kit's migration, mail keeps working with the defaults rather than failing on a missing table.
        self::$migrated ??= Schema::hasTable(self::TABLE);
        $row = self::$migrated ? DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->first() : null;
        $name = (string) ($row->sender_name ?? '') ?: (string) $workspace->name;
        $replyTo = match ($row->reply_to_mode ?? 'member') {
            'custom' => filled($row->reply_to_address ?? null) ? ['address' => (string) $row->reply_to_address, 'name' => $name] : self::platformReplyTo(),
            'fixed' => self::platformReplyTo(),
            default => filled($sender->email ?? null) ? ['address' => (string) $sender->email, 'name' => filled($sender->name ?? null) ? (string) $sender->name : null] : self::platformReplyTo(),
        };

        return ['from' => ['address' => self::fromAddress($row), 'name' => $name], 'reply_to' => $replyTo];
    }

    private static function view(): MailIdentityView
    {
        $workspace = Workspaces::required();
        $row = DB::table(self::TABLE)->where('workspace_id', $workspace->getKey())->first();

        return new MailIdentityView(
            (int) $workspace->getKey(),
            $row->sender_name ?? null,
            (string) $workspace->name,
            (string) ($row->from_mode ?? 'platform'),
            $row->domain ?? null,
            (string) ($row->from_local ?? 'hello'),
            (string) ($row->domain_status ?? 'none'),
            $row && $row->dns_records ? json_decode((string) $row->dns_records, true, flags: JSON_THROW_ON_ERROR) : [],
            $row && $row->checked_at ? (string) $row->checked_at : null,
            (string) ($row->reply_to_mode ?? 'member'),
            $row->reply_to_address ?? null,
            (string) config('mail.from.address'),
            config('mail.reply_to.address') ? (string) config('mail.reply_to.address') : null,
            self::fromAddress($row),
            (int) ($row->version ?? 1),
        );
    }

    private static function fromAddress(?object $row): string
    {
        $verified = $row !== null && $row->from_mode === 'domain' && $row->domain_status === 'verified' && filled($row->domain);

        return $verified ? $row->from_local.'@'.$row->domain : (string) config('mail.from.address');
    }

    /** @return array{address: string, name: ?string}|null */
    private static function platformReplyTo(): ?array
    {
        $address = config('mail.reply_to.address');

        return filled($address) ? ['address' => (string) $address, 'name' => config('mail.reply_to.name')] : null;
    }
}
