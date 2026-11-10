<?php

namespace Tey\Kit\Features\Settings\Mail;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sending domains in the app's Resend account. Creating and verifying domains needs a key with domain access: `services.resend.domains_key`
 * (env RESEND_DOMAINS_KEY), falling back to `services.resend.key`. Without one, every call fails closed with `mail_domain_unavailable`.
 * Verify is posted with a `{}` JSON body: Laravel's default empty data encodes as `[]`, which Resend refuses (422 "expected object, received array").
 * The body goes on a clone, so the follow-up GET stays bodiless.
 * A failed response throws MailDomainUnavailable carrying Resend's error `name` and `message` as its reason, logged at warning.
 */
final class ResendSendingDomains implements FindsSendingDomains, SendingDomains
{
    public function create(string $domain): array
    {
        $client = $this->client();

        return self::domain(self::ok(fn (): Response => $client->post('/domains', ['name' => $domain]), 'create'));
    }

    public function check(string $id): array
    {
        $client = $this->client();
        self::ok(fn (): Response => (clone $client)->withBody('{}', 'application/json')->post('/domains/'.rawurlencode($id).'/verify'), 'verify');

        return self::domain(self::ok(fn (): Response => $client->get('/domains/'.rawurlencode($id)), 'find'));
    }

    public function find(string $id): array
    {
        $client = $this->client();

        return self::domain(self::ok(fn (): Response => $client->get('/domains/'.rawurlencode($id)), 'find'));
    }

    /**
     * Sends the request and returns the response body, or throws MailDomainUnavailable with Resend's error name and message only: never the
     * key, headers or request. A connection failure (timeout, DNS, refused) has the fixed reason "connection failed", without its details.
     *
     * @param  Closure(): Response  $send
     * @return array<string, mixed>
     */
    private static function ok(Closure $send, string $call): array
    {
        try {
            $response = $send();
        } catch (ConnectionException) {
            Log::warning('Resend sending-domain call failed', ['call' => $call, 'status' => null, 'reason' => 'connection failed']);

            throw new MailDomainUnavailable('connection failed');
        }
        if (! $response->failed()) {
            return (array) $response->json();
        }
        $body = $response->json();
        $parts = is_array($body) ? array_filter([$body['name'] ?? null, $body['message'] ?? null], fn ($v): bool => is_string($v) && trim($v) !== '') : [];
        $reason = $parts === [] ? null : mb_substr(implode(': ', array_map('trim', $parts)), 0, 300);
        Log::warning('Resend sending-domain call failed', ['call' => $call, 'status' => $response->status(), 'reason' => $reason]);

        throw new MailDomainUnavailable($reason);
    }

    private function client(): PendingRequest
    {
        $key = (string) (config('services.resend.domains_key') ?: config('services.resend.key'));
        if ($key === '') {
            throw new MailDomainUnavailable;
        }

        return Http::baseUrl('https://api.resend.com')->withToken($key)->acceptJson()->timeout(10);
    }

    /** @param  array<string, mixed>  $body */
    private static function domain(array $body): array
    {
        return [
            'id' => (string) ($body['id'] ?? ''),
            'status' => self::status((string) ($body['status'] ?? '')),
            'records' => array_values(array_map(fn (array $r): array => [
                'type' => (string) ($r['type'] ?? ''),
                'name' => (string) ($r['name'] ?? ''),
                'value' => (string) ($r['value'] ?? ''),
                'priority' => isset($r['priority']) ? (int) $r['priority'] : null,
                'status' => self::status((string) ($r['status'] ?? '')),
            ], array_filter((array) ($body['records'] ?? []), 'is_array'))),
        ];
    }

    private static function status(string $status): string
    {
        return match ($status) {
            'verified' => 'verified',
            'failed', 'temporary_failure', 'partially_failed' => 'failed',
            default => 'pending',
        };
    }
}
