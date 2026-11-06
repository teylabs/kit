<?php

namespace Tey\Kit\Features\Settings\Mail;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sending domains in the app's Resend account. Creating and verifying domains needs a key with domain access: `services.resend.domains_key`
 * (env RESEND_DOMAINS_KEY), falling back to `services.resend.key`. Without one, every call fails closed with `mail_domain_unavailable`.
 */
final class ResendSendingDomains implements SendingDomains
{
    public function create(string $domain): array
    {
        $response = $this->client()->post('/domains', ['name' => $domain]);
        if ($response->failed()) {
            throw new RuntimeException('mail_domain_unavailable');
        }

        return self::domain((array) $response->json());
    }

    public function check(string $id): array
    {
        $client = $this->client();
        if ($client->post('/domains/'.rawurlencode($id).'/verify')->failed()) {
            throw new RuntimeException('mail_domain_unavailable');
        }
        $response = $client->get('/domains/'.rawurlencode($id));
        if ($response->failed()) {
            throw new RuntimeException('mail_domain_unavailable');
        }

        return self::domain((array) $response->json());
    }

    private function client(): PendingRequest
    {
        $key = (string) (config('services.resend.domains_key') ?: config('services.resend.key'));
        if ($key === '') {
            throw new RuntimeException('mail_domain_unavailable');
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
