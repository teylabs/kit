<?php

namespace Tey\Kit\Features\Settings\Mail;

use RuntimeException;

/**
 * The mail provider refused or could not be reached for a sending-domain call. The message stays `mail_domain_unavailable`, so a catch of
 * RuntimeException or a mapping on that message keeps working. `reason` is the provider's own error `name` and `message` (for example
 * "validation_error: The domain is invalid."), or null when there was none (no key, no response). It never holds the key, headers or request.
 */
final class MailDomainUnavailable extends RuntimeException
{
    public function __construct(public readonly ?string $reason = null)
    {
        parent::__construct('mail_domain_unavailable');
    }
}
