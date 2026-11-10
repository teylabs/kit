<?php

namespace Tey\Kit\Features\Deliver;

/**
 * Opt-in: addresses that get a Bcc copy of every real send of a delivery (a send now, a resend, a scheduled send when it runs), never of a test send or a
 * preview. Register it with `Delivery::define(…)->copySends(new MyCopies)`. The kit records each one as a recipient with the role `copy`, so history shows
 * it, drops any already among the To, Cc or Bcc (case-insensitively) and skips an empty or invalid address. A `copyTo()` that throws copies no one; the
 * send goes ahead without it.
 */
interface CopiesSends
{
    /** @return list<string> */
    public function copyTo(mixed $subject): array;
}
