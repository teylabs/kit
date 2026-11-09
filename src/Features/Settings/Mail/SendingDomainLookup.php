<?php

namespace Tey\Kit\Features\Settings\Mail;

/** Reads a domain from the bound SendingDomains: `find()` when it implements FindsSendingDomains, else `check()`, which also asks to verify. */
final class SendingDomainLookup
{
    public static function read(string $id): array
    {
        $domains = app(SendingDomains::class);

        return $domains instanceof FindsSendingDomains ? $domains->find($id) : $domains->check($id);
    }
}
