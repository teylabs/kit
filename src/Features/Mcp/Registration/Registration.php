<?php

namespace Tey\Kit\Features\Mcp\Registration;

/** A client registration request as the host's policy sees it: the validated metadata and where it came from. */
final readonly class Registration
{
    /**
     * @param  list<string>  $redirectUris
     * @param  array<string, mixed>  $metadata  the request body as sent, for fields the kit does not read
     */
    public function __construct(
        public string $clientName,
        public array $redirectUris,
        public string $applicationType,
        public ?string $ip,
        public array $metadata,
    ) {}

    public function loopbackOnly(): bool
    {
        foreach ($this->redirectUris as $uri) {
            if (! RedirectUri::isLoopback($uri)) {
                return false;
            }
        }

        return true;
    }
}
