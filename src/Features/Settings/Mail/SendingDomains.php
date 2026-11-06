<?php

namespace Tey\Kit\Features\Settings\Mail;

/**
 * The mail provider's sending domains. The kit binds ResendSendingDomains; tests bind a fake, so no check calls a provider.
 *
 * A domain is `['id' => string, 'status' => 'pending'|'verified'|'failed', 'records' => list<array{type: string, name: string, value: string, priority: ?int, status: string}>]`.
 */
interface SendingDomains
{
    /** Creates the domain at the provider and returns it with the DNS records to publish. */
    public function create(string $domain): array;

    /** Asks the provider to verify the domain now, and returns its current state. */
    public function check(string $id): array;
}
