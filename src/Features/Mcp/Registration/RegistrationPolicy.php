<?php

namespace Tey\Kit\Features\Mcp\Registration;

/**
 * A host's say over who may register a client (`kit.mcp.registration_policy`, a class name resolved from the container). The kit has already validated the
 * request and its redirect URIs; the policy returns a reason to refuse, answered as 403 access_denied, or null to let the registration through.
 */
interface RegistrationPolicy
{
    public function refuse(Registration $registration): ?string;
}
