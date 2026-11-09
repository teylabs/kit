<?php

namespace Tey\Kit\Features\Settings\Mail;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sending domains in the app's Resend account. Creating and verifying domains needs a key with domain access: `services.resend.domains_key`
 * (env RESEND_DOMAINS_KEY), falling back to `services.resend.key`. Without one, every call fails closed with `mail_domain_unavailable`.
 * A failed response throws MailDomainUnavailable carrying Resend's error `name` and `message` as its reason, logged at warning.
 */
final class ResendSendingDomains implements FindsSendingDomains, SendingDomains
{
    public function create(string $domain): array
    {
        $response = $this->client()->post('/domains', ['name' => $domain]);

        return self::domain(self::ok($response, 'create'));
    }

    public function check(string $id): array
    {
        $client = $this->client();
        self::ok($client->post('/domains/'.rawurlencode($id).'/verify'), 'verify');

        return self::domain(self::ok($client->get('/domains/'.rawurlencode($id)), 'find'));
    }

    public function find(string $id): array
    {
        return self::domain(self::ok($this->client()->get('/domains/'.rawurlencode($id)), 'find'));
    }

    /**
     * The response body, or MailDomainUnavailable with Resend's error name and message only: never the key, headers or request.
     *
     * @return array<string, mixed>
     */
    private static function ok(Response $response, string $call): array
    {
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
