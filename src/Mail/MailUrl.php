<?php

namespace Tey\Kit\Mail;

/**
 * An app path as an absolute URL on `app.url`, never the current request's scheme and host: mail is built behind a TLS-terminating proxy, in a queue worker or on the
 * command line, where `url()` can say http:// for an https app.
 */
final class MailUrl
{
    public static function to(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
