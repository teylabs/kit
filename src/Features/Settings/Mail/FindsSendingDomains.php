<?php

namespace Tey\Kit\Features\Settings\Mail;

/**
 * Reads a sending domain without asking the provider to verify it. Separate from SendingDomains, so an app's decorator of that interface keeps
 * working; a caller that only needs a domain's state and records uses `find()` when the bound implementation also implements this, and
 * `check()` otherwise (see `SendingDomainLookup`).
 */
interface FindsSendingDomains
{
    /** Returns the domain's current state and records, in the SendingDomains shape, with a read only: no verify request. */
    public function find(string $id): array;
}
